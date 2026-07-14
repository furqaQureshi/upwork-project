<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class FeaturedAdPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'listing_id',
        'user_id',
        'gateway',
        'merchant_order_id',
        'provider_order_id',
        'provider_payment_id',
        'provider_reference',
        'amount',
        'currency',
        'feature_days',
        'status',
        'meta',
        'callback_payload',
        'paid_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'meta' => 'array',
            'callback_payload' => 'array',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
