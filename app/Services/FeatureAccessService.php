<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserFeatureAccess;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FeatureAccessService
{
    public function hasFreeAccess(User $user, string $feature): bool
    {
        return $this->remainingFreeAccess($user, $feature) > 0;
    }

    public function remainingFreeAccess(User $user, string $feature): int
    {
        $limit = $this->limitFor($feature);

        if ($limit <= 0) {
            return 0;
        }

        $usedCount = (int) UserFeatureAccess::query()
            ->where('user_id', $user->id)
            ->where('feature', $feature)
            ->value('used_count');

        return max(0, $limit - $usedCount);
    }

    public function consumeFreeAccess(User $user, string $feature): bool
    {
        $limit = $this->limitFor($feature);

        if ($limit <= 0) {
            return false;
        }

        return DB::transaction(function () use ($user, $feature, $limit): bool {
            $access = UserFeatureAccess::query()
                ->where('user_id', $user->id)
                ->where('feature', $feature)
                ->lockForUpdate()
                ->first();

            $usedCount = (int) ($access?->used_count ?? 0);

            if ($usedCount >= $limit) {
                return false;
            }

            if ($access) {
                $access->update([
                    'used_count' => $usedCount + 1,
                ]);
            } else {
                UserFeatureAccess::query()->create([
                    'user_id' => $user->id,
                    'feature' => $feature,
                    'used_count' => 1,
                ]);
            }

            return true;
        });
    }

    private function limitFor(string $feature): int
    {
        $settingKey = match ($feature) {
            'call' => 'free_call_access_limit',
            'map' => 'free_map_access_limit',
            default => throw new InvalidArgumentException("Unsupported feature access type: {$feature}"),
        };

        $limit = (int) setting($settingKey, 0);

        return max(0, min(100000, $limit));
    }
}
