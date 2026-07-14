<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingCustomFieldValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'listing_id',
        'custom_field_id',
        'value_text',
        'value_number',
        'value_json',
    ];

    protected function casts(): array
    {
        return [
            'value_number' => 'decimal:2',
            'value_json' => 'array',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function customField(): BelongsTo
    {
        return $this->belongsTo(CustomField::class);
    }
}
