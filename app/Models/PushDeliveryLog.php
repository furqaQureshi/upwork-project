<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushDeliveryLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'push_subscription_id',
        'provider',
        'status',
        'target',
        'response_status',
        'error_code',
        'error_message',
        'payload',
        'response_body',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'response_body' => 'array',
            'delivered_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pushSubscription(): BelongsTo
    {
        return $this->belongsTo(PushSubscription::class);
    }
}
