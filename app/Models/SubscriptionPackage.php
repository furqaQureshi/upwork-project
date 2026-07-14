<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class SubscriptionPackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'package_type',
        'price',
        'discount_percent',
        'final_price',
        'package_duration_type',
        'package_duration_days',
        'item_limit_type',
        'item_limit_count',
        'listing_duration_type',
        'listing_duration_days',
        'category_scope',
        'category_id',
        'key_points',
        'icon',
        'allows_call',
        'allows_ai',
        'ai_usage_limit_type',
        'ai_usage_limit_count',
        'is_active',
        'required_documents',
        'is_seller_verification',
        'seller_tier',
        'seller_badge_label',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'final_price' => 'decimal:2',
            'package_duration_days' => 'integer',
            'item_limit_count' => 'integer',
            'listing_duration_days' => 'integer',
            'category_id' => 'integer',
            'key_points' => 'array',
            'allows_call' => 'boolean',
            'allows_ai' => 'boolean',
            'ai_usage_limit_count' => 'integer',
            'is_active' => 'boolean',
            'required_documents' => 'array',
            'is_seller_verification' => 'boolean',
            'seller_tier' => 'string',
            'seller_badge_label' => 'string',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(SubscriptionPackagePurchase::class);
    }

    public function sellerVerifications(): HasMany
    {
        return $this->hasMany(SellerVerification::class);
    }

    public function appliesToCategory(?Category $category): bool
    {
        if ($this->category_scope === 'global') {
            return true;
        }

        if (! $category || ! $this->category_id) {
            return false;
        }

        if ($category->id === (int) $this->category_id) {
            return true;
        }

        if ((int) $category->parent_id === (int) $this->category_id) {
            return true;
        }

        return false;
    }

    public function getIconUrlAttribute(): ?string
    {
        if (! $this->icon) {
            return null;
        }

        return Storage::disk('public')->exists($this->icon)
            ? Storage::url($this->icon)
            : null;
    }

    public function getPackageTypeLabelAttribute(): string
    {
        return match ((string) $this->package_type) {
            'featured' => 'Featured Ads Package',
            'story' => 'Verified Stories Package',
            default => 'Ad Listing Package',
        };
    }

    public function getPackageDurationLabelAttribute(): string
    {
        if ($this->package_duration_type === 'unlimited') {
            return 'Unlimited';
        }

        return ($this->package_duration_days ?: 0).' day(s)';
    }

    public function getItemLimitLabelAttribute(): string
    {
        if ($this->item_limit_type === 'unlimited') {
            return 'Unlimited';
        }

        return ($this->item_limit_count ?: 0).' item(s)';
    }

    public function getListingDurationLabelAttribute(): string
    {
        if ($this->listing_duration_type === 'standard') {
            return 'Standard 30 days';
        }

        return ($this->listing_duration_days ?: 0).' day(s)';
    }

    public function getAiUsageLimitLabelAttribute(): string
    {
        if (! $this->allows_ai) {
            return 'Not included';
        }

        if ($this->ai_usage_limit_type === 'unlimited') {
            return 'Unlimited';
        }

        return ($this->ai_usage_limit_count ?: 0).' usage(s)';
    }

    public function getSellerTierLabelAttribute(): string
    {
        return match ((string) $this->seller_tier) {
            'car_verified' => 'Car Seller',
            'premium_verified' => 'Premium Seller',
            'verified' => 'Verified Seller',
            default => 'Seller Package',
        };
    }

    public function getResolvedSellerBadgeLabelAttribute(): string
    {
        $label = trim((string) ($this->seller_badge_label ?? ''));

        if ($label !== '') {
            return $label;
        }

        return match ((string) $this->seller_tier) {
            'car_verified' => 'CAR VERIFIED SELLER',
            'premium_verified' => 'PREMIUM SELLER',
            'verified' => 'VERIFIED SELLER',
            default => 'VERIFIED SELLER',
        };
    }
}
