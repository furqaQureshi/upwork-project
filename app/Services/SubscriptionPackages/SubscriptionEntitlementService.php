<?php

namespace App\Services\SubscriptionPackages;

use App\Models\Category;
use App\Models\Listing;
use App\Models\SubscriptionPackagePurchase;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SubscriptionEntitlementService
{
    public function activeAiPurchasesForUser(User $user): Collection
    {
        return SubscriptionPackagePurchase::query()
            ->with(['subscriptionPackage.category'])
            ->where('user_id', $user->id)
            ->active()
            ->whereHas('subscriptionPackage', function (Builder $builder): void {
                $builder
                    ->where('is_active', true)
                    ->where('allows_ai', true);
            })
            ->orderByRaw('CASE WHEN package_expires_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('package_expires_at')
            ->orderBy('id')
            ->get();
    }

    public function activePackagePurchasesForUser(User $user): Collection
    {
        return SubscriptionPackagePurchase::query()
            ->with(['subscriptionPackage.category.parent'])
            ->where('user_id', $user->id)
            ->active()
            ->whereHas('subscriptionPackage', function (Builder $builder): void {
                $builder->where('is_active', true);
            })
            ->orderByRaw('CASE WHEN package_expires_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('package_expires_at')
            ->orderBy('id')
            ->get();
    }

    public function activeCallPurchasesForUser(User $user): Collection
    {
        return SubscriptionPackagePurchase::query()
            ->with(['subscriptionPackage.category'])
            ->where('user_id', $user->id)
            ->active()
            ->whereHas('subscriptionPackage', function (Builder $builder): void {
                $builder
                    ->where('is_active', true)
                    ->where('allows_call', true);
            })
            ->orderByRaw('CASE WHEN package_expires_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('package_expires_at')
            ->orderBy('id')
            ->get();
    }

    public function activePurchasesForUser(User $user, string $packageType, ?Category $category = null): Collection
    {
        return SubscriptionPackagePurchase::query()
            ->with(['subscriptionPackage.category'])
            ->where('user_id', $user->id)
            ->active()
            ->whereHas('subscriptionPackage', function (Builder $builder) use ($packageType, $category): void {
                $builder
                    ->where('package_type', $packageType)
                    ->where('is_active', true)
                    ->when($packageType === 'listing', function (Builder $listingBuilder) use ($category): void {
                        if (! $category) {
                            return;
                        }

                        $categoryIds = array_filter([
                            (int) $category->id,
                            $category->parent_id ? (int) $category->parent_id : null,
                        ]);

                        $listingBuilder->where(function (Builder $scopeBuilder) use ($categoryIds): void {
                            $scopeBuilder
                                ->where('category_scope', 'global')
                                ->orWhere(function (Builder $specificBuilder) use ($categoryIds): void {
                                    $specificBuilder
                                        ->where('category_scope', 'specific')
                                        ->whereIn('category_id', $categoryIds);
                                });
                        });
                    });
            })
            ->orderByRaw('CASE WHEN package_expires_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('package_expires_at')
            ->orderBy('id')
            ->get();
    }

    public function hasCallAccess(User $user): bool
    {
        return $this->activeCallPurchasesForUser($user)->isNotEmpty();
    }

    public function hasAiAccess(User $user): bool
    {
        return $this->activeAiPurchasesForUser($user)
            ->contains(fn (SubscriptionPackagePurchase $purchase): bool => $purchase->hasRemainingAiItems());
    }

    public function findUsableAiPurchase(User $user): ?SubscriptionPackagePurchase
    {
        return $this->activeAiPurchasesForUser($user)
            ->first(fn (SubscriptionPackagePurchase $purchase): bool => $purchase->hasRemainingAiItems());
    }

    public function findUsablePurchase(User $user, string $packageType, ?Category $category = null): ?SubscriptionPackagePurchase
    {
        return $this->activePurchasesForUser($user, $packageType, $category)
            ->first(fn (SubscriptionPackagePurchase $purchase): bool => $purchase->hasRemainingItems());
    }

    public function consumePurchase(
        SubscriptionPackagePurchase $purchase,
        string $usageType,
        ?Listing $listing = null,
        array $meta = []
    ): ?SubscriptionPackagePurchase {
        return DB::transaction(function () use ($purchase, $usageType, $listing, $meta): ?SubscriptionPackagePurchase {
            $lockedPurchase = SubscriptionPackagePurchase::query()
                ->with('subscriptionPackage')
                ->lockForUpdate()
                ->find($purchase->id);

            if (! $lockedPurchase || ! $lockedPurchase->isActive() || ! $lockedPurchase->hasRemainingItems()) {
                return null;
            }

            if ($listing && $lockedPurchase->subscriptionPackage?->package_type === 'listing') {
                $listing->loadMissing('category');

                if (! $lockedPurchase->subscriptionPackage->appliesToCategory($listing->category)) {
                    return null;
                }
            }

            $alreadyConsumed = $lockedPurchase->usages()
                ->where('usage_type', $usageType)
                ->when($listing, function (Builder $builder) use ($listing): void {
                    $builder->where('listing_id', $listing->id);
                })
                ->exists();

            if ($alreadyConsumed) {
                return $lockedPurchase;
            }

            $package = $lockedPurchase->subscriptionPackage;
            $updatedUsedItems = (int) $lockedPurchase->used_items + 1;
            $updatedRemainingItems = $lockedPurchase->remaining_items;

            if ($package?->item_limit_type === 'limited') {
                $currentRemainingItems = (int) ($lockedPurchase->remaining_items ?? 0);

                if ($currentRemainingItems <= 0) {
                    return null;
                }

                $updatedRemainingItems = $currentRemainingItems - 1;
            }

            $lockedPurchase->update([
                'used_items' => $updatedUsedItems,
                'remaining_items' => $updatedRemainingItems,
            ]);

            $lockedPurchase->usages()->create([
                'listing_id' => $listing?->id,
                'usage_type' => $usageType,
                'consumed_at' => now(),
                'meta' => $meta,
            ]);

            return $lockedPurchase->fresh(['subscriptionPackage']);
        });
    }

    public function consumeAiPurchase(
        SubscriptionPackagePurchase $purchase,
        string $usageType,
        ?Listing $listing = null,
        array $meta = []
    ): ?SubscriptionPackagePurchase
    {
        return DB::transaction(function () use ($purchase, $usageType, $listing, $meta): ?SubscriptionPackagePurchase {
            $lockedPurchase = SubscriptionPackagePurchase::query()
                ->with('subscriptionPackage')
                ->lockForUpdate()
                ->find($purchase->id);

            if (! $lockedPurchase || ! $lockedPurchase->isActive() || ! $lockedPurchase->hasRemainingAiItems()) {
                return null;
            }

            $package = $lockedPurchase->subscriptionPackage;

            if (! $package || ! $package->is_active || ! $package->allows_ai) {
                return null;
            }

            $updatedUsedAiItems = (int) $lockedPurchase->used_ai_items + 1;
            $updatedRemainingAiItems = $lockedPurchase->remaining_ai_items;

            if ($package->ai_usage_limit_type === 'limited') {
                $currentRemainingAiItems = (int) ($lockedPurchase->remaining_ai_items ?? 0);

                if ($currentRemainingAiItems <= 0) {
                    return null;
                }

                $updatedRemainingAiItems = $currentRemainingAiItems - 1;
            }

            $lockedPurchase->update([
                'used_ai_items' => $updatedUsedAiItems,
                'remaining_ai_items' => $updatedRemainingAiItems,
            ]);

            $lockedPurchase->usages()->create([
                'listing_id' => $listing?->id,
                'usage_type' => $usageType,
                'consumed_at' => now(),
                'meta' => $meta,
            ]);

            return $lockedPurchase->fresh(['subscriptionPackage']);
        });
    }

    public function aiPackageStats(User $user): array
    {
        $purchases = $this->activeAiPurchasesForUser($user);

        $hasUnlimited = $purchases->contains(function (SubscriptionPackagePurchase $purchase): bool {
            return $purchase->subscriptionPackage?->ai_usage_limit_type === 'unlimited';
        });

        $totalRemaining = $hasUnlimited
            ? null
            : (int) $purchases->sum(fn (SubscriptionPackagePurchase $purchase): int => (int) ($purchase->remaining_ai_items ?? 0));

        return [
            'has_active_package' => $purchases->isNotEmpty(),
            'has_usable_package' => $purchases->contains(fn (SubscriptionPackagePurchase $purchase): bool => $purchase->hasRemainingAiItems()),
            'is_unlimited' => $hasUnlimited,
            'total_remaining' => $totalRemaining,
            'active_purchases_count' => $purchases->count(),
        ];
    }

    public function packageStats(User $user, string $packageType, ?Category $category = null): array
    {
        $purchases = $this->activePurchasesForUser($user, $packageType, $category);

        $hasUnlimited = $purchases->contains(function (SubscriptionPackagePurchase $purchase): bool {
            return $purchase->subscriptionPackage?->item_limit_type === 'unlimited';
        });

        $totalRemaining = $hasUnlimited
            ? null
            : (int) $purchases->sum(fn (SubscriptionPackagePurchase $purchase): int => (int) ($purchase->remaining_items ?? 0));

        return [
            'has_active_package' => $purchases->isNotEmpty(),
            'has_usable_package' => $purchases->contains(fn (SubscriptionPackagePurchase $purchase): bool => $purchase->hasRemainingItems()),
            'is_unlimited' => $hasUnlimited,
            'total_remaining' => $totalRemaining,
            'active_purchases_count' => $purchases->count(),
        ];
    }
}
