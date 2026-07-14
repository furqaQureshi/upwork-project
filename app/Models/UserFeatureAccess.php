<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserFeatureAccess extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'feature',
        'used_count',
    ];

    protected function casts(): array
    {
        return [
            'used_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
