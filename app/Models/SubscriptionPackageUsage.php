<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPackageUsage extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_package_purchase_id',
        'listing_id',
        'usage_type',
        'consumed_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'consumed_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPackagePurchase::class, 'subscription_package_purchase_id');
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}
