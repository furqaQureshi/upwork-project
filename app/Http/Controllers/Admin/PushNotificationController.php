<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\AdminCustomPushNotification;
use App\Services\WebPush\WebPushService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class PushNotificationController extends Controller
{
    public function create(Request $request): View
    {
        $selectedUserId = $request->integer('user');

        $recentUsers = User::query()
            ->withCount([
                'pushSubscriptions as active_push_devices_count' => fn ($builder) => $builder->where('is_active', true),
                'listings',
            ])
            ->latest('id')
            ->limit(100)
            ->get();

        if ($selectedUserId > 0 && ! $recentUsers->contains('id', $selectedUserId)) {
            $selectedUser = User::query()
                ->withCount([
                    'pushSubscriptions as active_push_devices_count' => fn ($builder) => $builder->where('is_active', true),
                    'listings',
                ])
                ->find($selectedUserId);

            if ($selectedUser) {
                $recentUsers->prepend($selectedUser);
            }
        }

        return view('admin.push-notifications.create', [
            'users' => $recentUsers->unique('id')->values(),
        ]);
    }

    public function store(Request $request, WebPushService $webPushService): RedirectResponse
    {
        $validated = $request->validate([
            'audience' => ['required', 'string', 'in:all_users,sellers,admins,specific_user'],
            'user_id' => ['nullable', 'integer', 'exists:users,id', 'required_if:audience,specific_user'],
            'title' => ['required', 'string', 'max:80'],
            'body' => ['required', 'string', 'max:240'],
            'url' => ['nullable', 'string', 'max:500'],
        ]);

        $audience = $validated['audience'];
        $url = $this->normalizeUrl((string) ($validated['url'] ?? ''));
        $notification = new AdminCustomPushNotification(
            title: $validated['title'],
            body: $validated['body'],
            url: $url,
            senderName: (string) $request->user()->name,
        );

        $targetQuery = $this->buildAudienceQuery($audience, $validated['user_id'] ?? null);

        if (! $targetQuery->exists()) {
            return back()->withInput()->with('status', 'No matching users found for the selected audience.');
        }

        $recipientCount = 0;
        $activeDeviceCount = 0;

        $targetQuery
            ->withCount([
                'pushSubscriptions as active_push_devices_count' => fn ($builder) => $builder->where('is_active', true),
            ])
            ->orderBy('id')
            ->chunkById(100, function (Collection $users) use ($notification, $webPushService, &$recipientCount, &$activeDeviceCount): void {
                foreach ($users as $user) {
                    $user->notify($notification);
                    $recipientCount++;

                    $deviceCount = (int) ($user->active_push_devices_count ?? 0);
                    $activeDeviceCount += $deviceCount;

                    if ($deviceCount > 0) {
                        $webPushService->sendToUser($user, $notification->toWebPushPayload());
                    }
                }
            });

        return redirect()
            ->route('admin.push-notifications.create')
            ->with('status', "Custom notification sent to {$recipientCount} user(s) across {$activeDeviceCount} active device(s).");
    }

    private function buildAudienceQuery(string $audience, mixed $userId = null): Builder
    {
        return User::query()
            ->when($audience === 'all_users', function (Builder $builder): void {
                $builder->where('is_admin', false);
            })
            ->when($audience === 'sellers', function (Builder $builder): void {
                $builder->where('is_admin', false)->whereHas('listings');
            })
            ->when($audience === 'admins', function (Builder $builder): void {
                $builder->where('is_admin', true);
            })
            ->when($audience === 'specific_user' && $userId, function (Builder $builder) use ($userId): void {
                $builder->whereKey((int) $userId);
            });
    }

    private function normalizeUrl(string $url): string
    {
        $trimmed = trim($url);

        if ($trimmed === '') {
            return '/notifications';
        }

        if (preg_match('/^https?:\/\//i', $trimmed) === 1) {
            return $trimmed;
        }

        return '/'.ltrim($trimmed, '/');
    }
}
