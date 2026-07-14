<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FreePostLimit extends Model
{
    protected $fillable = [
        'category_id',
        'window_days',
        'limit_count',
    ];

    protected function casts(): array
    {
        return [
            'category_id' => 'integer',
            'window_days' => 'integer',
            'limit_count' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Human-readable scope label, e.g. "Cars & Vehicles" or "All Categories".
     */
    public function getScopeLabelAttribute(): string
    {
        return $this->category ? $this->category->display_name : 'All Categories';
    }
}
