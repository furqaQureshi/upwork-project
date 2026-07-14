<?php

namespace App\Services;

use App\Models\Category;
use App\Models\SubscriptionPackage;
use App\Models\User;

class SellerPostingRuleService
{
    public function violationMessage(User $user, ?Category $category, ?int $ignoreListingId = null): ?string
    {
        if (! $category) {
            return null;
        }

        $sellerType = trim((string) ($user->seller_type ?? ''));

        if ($sellerType === '') {
            return null;
        }

        $verification = $user->applicableSellerVerificationForCategory($category);
        $package = $verification?->subscriptionPackage;

        if (! $package instanceof SubscriptionPackage) {
            return match ($sellerType) {
                'car_verified' => 'Your Car Seller account can post only in the Cars category or its subcategories.',
                'premium_verified' => 'Your Premium Seller account is approved only for the categories assigned by admin.',
                default => 'Your Verified Seller approval is not valid for this category.',
            };
        }

        if ($package->item_limit_type !== 'limited') {
            return null;
        }

        $limit = (int) ($package->item_limit_count ?? 0);

        if ($limit <= 0) {
            return null;
        }

        $activeCount = $user->listings()
            ->with('category.parent')
            ->whereIn('status', ['pending', 'approved'])
            ->when($ignoreListingId !== null, fn ($query) => $query->where('id', '!=', $ignoreListingId))
            ->get()
            ->filter(fn ($listing) => $package->category_scope === 'global' || $package->appliesToCategory($listing->category))
            ->count();

        if ($activeCount >= $limit) {
            $scopeText = $package->category_scope === 'global'
                ? 'for this seller plan'
                : 'for this category';

            return sprintf(
                '%s allows only %d active listing(s) %s.',
                $package->resolved_seller_badge_label,
                $limit,
                $scopeText,
            );
        }

        return null;
    }
}