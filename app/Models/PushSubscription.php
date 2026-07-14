<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PushSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'provider',
        'endpoint',
        'device_token',
        'public_key',
        'auth_token',
        'content_encoding',
        'is_active',
        'last_used_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function getResolvedTokenAttribute(): ?string
    {
        if ($this->provider === 'fcm') {
            return $this->device_token ?: preg_replace('/^fcm:/', '', (string) $this->endpoint);
        }

        return null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deliveryLogs(): HasMany
    {
        return $this->hasMany(PushDeliveryLog::class);
    }
}
