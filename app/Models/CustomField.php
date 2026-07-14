<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class CustomField extends Model
{
    use HasFactory;

    public const FIELD_TYPES = [
        'number',
        'text',
        'file',
        'radio',
        'dropdown',
        'checkbox',
    ];

    protected $fillable = [
        'category_id',
        'parent_field_id',
        'name',
        'slug',
        'field_type',
        'min_length',
        'max_length',
        'options',
        'icon',
        'sort_order',
        'is_required',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'parent_field_id' => 'integer',
            'options' => 'array',
            'min_length' => 'integer',
            'max_length' => 'integer',
            'sort_order' => 'integer',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function parentField(): BelongsTo
    {
        return $this->belongsTo(CustomField::class, 'parent_field_id');
    }

    public function subFields(): HasMany
    {
        return $this->hasMany(CustomField::class, 'parent_field_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(ListingCustomFieldValue::class);
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
}
