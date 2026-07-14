<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ListingImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'listing_id',
        'path',
        'is_primary',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function getUrlAttribute(): string
    {
        return Storage::url($this->path);
    }

    public function getThumbnailPathAttribute(): string
    {
        $normalized = trim((string) $this->path, '/');
        if ($normalized === '') {
            return '';
        }

        $directory = pathinfo($normalized, PATHINFO_DIRNAME);
        $filename = pathinfo($normalized, PATHINFO_FILENAME);

        if ($filename === '') {
            return '';
        }

        $baseDirectory = $directory === '.' ? '' : $directory.'/';

        return $baseDirectory.'thumbs/'.$filename.'.jpg';
    }

    public function getThumbnailUrlAttribute(): string
    {
        $thumbnailPath = $this->thumbnail_path;
        if ($thumbnailPath !== '' && Storage::disk('public')->exists($thumbnailPath)) {
            return Storage::url($thumbnailPath);
        }

        return $this->url;
    }
}
