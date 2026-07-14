<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class SubscriptionPackagePurchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_package_id',
        'user_id',
        'gateway',
        'merchant_order_id',
        'provider_order_id',
        'provider_payment_id',
        'provider_reference',
        'amount',
        'currency',
        'status',
        'used_items',
        'remaining_items',
        'used_ai_items',
        'remaining_ai_items',
        'package_started_at',
        'package_expires_at',
        'paid_at',
        'activated_at',
        'meta',
        'callback_payload',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'used_items' => 'integer',
            'remaining_items' => 'integer',
            'used_ai_items' => 'integer',
            'remaining_ai_items' => 'integer',
            'package_started_at' => 'datetime',
            'package_expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'activated_at' => 'datetime',
            'meta' => 'array',
            'callback_payload' => 'array',
        ];
    }

    public function subscriptionPackage(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPackage::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(SubscriptionPackageUsage::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('status', 'paid')
            ->where(function (Builder $builder): void {
                $builder
                    ->whereNull('package_expires_at')
                    ->orWhere('package_expires_at', '>', now());
            });
    }

    public function isActive(): bool
    {
        if ($this->status !== 'paid') {
            return false;
        }

        return $this->package_expires_at === null || $this->package_expires_at->isFuture();
    }

    public function hasRemainingItems(): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        $package = $this->subscriptionPackage;

        if (! $package) {
            return false;
        }

        if ($package->item_limit_type === 'unlimited') {
            return true;
        }

        return (int) ($this->remaining_items ?? 0) > 0;
    }

    public function hasRemainingAiItems(): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        $package = $this->subscriptionPackage;

        if (! $package || ! $package->allows_ai) {
            return false;
        }

        if ($package->ai_usage_limit_type === 'unlimited') {
            return true;
        }

        return (int) ($this->remaining_ai_items ?? 0) > 0;
    }
}
