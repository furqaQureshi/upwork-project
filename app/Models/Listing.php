<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Listing extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'category_id',
        'title',
        'slug',
        'description',
        'price',
        'currency',
        'condition',
        'city',
        'state',
        'address',
        'latitude',
        'longitude',
        'status',
        'is_featured',
        'featured_until',
        'last_featured_payment_id',
        'views',
        'rejection_reason',
        'published_at',
        'expires_at',
          'price_type',
          'youtube_url',
     ];

    protected $appends = [
        'main_image_url',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_featured' => 'boolean',
            'featured_until' => 'datetime',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ListingImage::class)->orderByDesc('is_primary')->orderBy('sort_order');
    }

    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorites')->withTimestamps();
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ListingReport::class);
    }

    public function customFieldValues(): HasMany
    {
        return $this->hasMany(ListingCustomFieldValue::class);
    }

    public function subscriptionPackageUsages(): HasMany
    {
        return $this->hasMany(SubscriptionPackageUsage::class);
    }

    public function featuredPayments(): HasMany
    {
        return $this->hasMany(FeaturedAdPayment::class);
    }

    public function lastFeaturedPayment(): HasOne
    {
        return $this->hasOne(FeaturedAdPayment::class, 'id', 'last_featured_payment_id');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function scopeFeaturedActive(Builder $query): Builder
    {
        return $query
            ->where('is_featured', true)
            ->where(function (Builder $builder): void {
                $builder
                    ->whereNull('featured_until')
                    ->orWhere('featured_until', '>', now());
            });
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        [$priceMin, $priceMax] = self::searchPriceBounds($term);
        $condition = self::searchCondition($term);
        $tokens = self::searchTokens($term);

        return $query
            ->when($priceMin !== null, fn (Builder $builder) => $builder->where('price', '>=', $priceMin))
            ->when($priceMax !== null, fn (Builder $builder) => $builder->where('price', '<=', $priceMax))
            ->when($condition !== null, fn (Builder $builder) => $builder->where('condition', $condition))
            ->when($tokens !== [], function (Builder $builder) use ($tokens): void {
                $builder->where(function (Builder $outer) use ($tokens): void {
                    foreach ($tokens as $token) {
                        $like = '%'.$token.'%';
                        $outer->orWhere(function (Builder $inner) use ($like): void {
                            $inner
                                ->where('title', 'like', $like)
                                ->orWhere('description', 'like', $like)
                                ->orWhere('city', 'like', $like)
                                ->orWhere('state', 'like', $like)
                                ->orWhere('address', 'like', $like)
                                ->orWhereHas('category', function (Builder $categoryQuery) use ($like): void {
                                    $categoryQuery
                                        ->where('name', 'like', $like)
                                        ->orWhere('slug', 'like', $like);
                                })
                                ->orWhereHas('customFieldValues', function (Builder $fieldQuery) use ($like): void {
                                    $fieldQuery->where('value', 'like', $like);
                                });
                        });
                    }
                });
            });
    }

    /**
     * @return array{0: float|null, 1: float|null}
     */
    private static function searchPriceBounds(string $term): array
    {
        $min = null;
        $max = null;

        if (preg_match('/\b(?:under|below|max|less\s+than)\s+([0-9,.]+)\s*(lakh|lac|k|thousand|crore|cr)?\b/i', $term, $match) === 1) {
            $max = self::searchAmountToNumber($match[1] ?? '', $match[2] ?? '');
        }

        if (preg_match('/\b(?:above|over|min|from|more\s+than)\s+([0-9,.]+)\s*(lakh|lac|k|thousand|crore|cr)?\b/i', $term, $match) === 1) {
            $min = self::searchAmountToNumber($match[1] ?? '', $match[2] ?? '');
        }

        return [$min, $max];
    }

    private static function searchCondition(string $term): ?string
    {
        if (preg_match('/\b(brand\s+new|new)\b/i', $term) === 1) {
            return 'new';
        }

        if (preg_match('/\b(used|old|second\s*hand|secondhand|preowned|pre-owned)\b/i', $term) === 1) {
            return 'used';
        }

        if (preg_match('/\b(refurbished|renewed)\b/i', $term) === 1) {
            return 'refurbished';
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private static function searchTokens(string $term): array
    {
        $normalized = strtolower($term);
        $parts = preg_split('/[^a-z0-9]+/i', $normalized) ?: [];
        $stopWords = [
            'find', 'show', 'search', 'looking', 'need', 'want', 'buy', 'sell',
            'in', 'near', 'under', 'below', 'above', 'over', 'from', 'for', 'with',
            'and', 'or', 'a', 'an', 'the', 'me', 'my', 'please', 'best', 'good',
            'cheap', 'deals', 'deal', 'verified', 'seller', 'sellers', 'price',
            'budget', 'max', 'min', 'rs', 'rupees', 'lakh', 'lac', 'crore', 'cr',
            'k', 'thousand', 'new', 'used', 'old', 'second', 'hand',
        ];
        $aliases = [
            'cars' => 'car',
            'vehicle' => 'car',
            'vehicles' => 'car',
            'bikes' => 'bike',
            'scooters' => 'scooter',
            'phones' => 'phone',
            'mobiles' => 'mobile',
            'smartphones' => 'phone',
            'laptops' => 'laptop',
            'flats' => 'flat',
            'apartments' => 'apartment',
            'houses' => 'house',
            'plots' => 'plot',
        ];

        $tokens = [];
        foreach ($parts as $part) {
            $token = trim($part);
            if ($token === '' || strlen($token) < 2 || is_numeric($token) || in_array($token, $stopWords, true)) {
                continue;
            }

            $tokens[] = $aliases[$token] ?? $token;
        }

        return array_values(array_unique($tokens));
    }

    private static function searchAmountToNumber(string $amount, string $unit): ?float
    {
        $raw = str_replace(',', '', trim($amount));
        if ($raw === '' || ! is_numeric($raw)) {
            return null;
        }

        $value = (float) $raw;
        $normalizedUnit = strtolower(trim($unit));

        return match ($normalizedUnit) {
            'lakh', 'lac' => $value * 100000,
            'crore', 'cr' => $value * 10000000,
            'k', 'thousand' => $value * 1000,
            default => $value,
        };
    }

    public function getMainImageUrlAttribute(): string
    {
        $image = $this->relationLoaded('images')
            ? ($this->images->firstWhere('is_primary', true) ?? $this->images->sortBy('sort_order')->first())
            : $this->images()->first();

        return $image ? Storage::url($image->path) : asset('images/placeholder-listing.svg');
    }

    public function isOwnedBy(?User $user): bool
    {
        return (bool) $user && $this->user_id === $user->id;
    }

    public function getHasActiveFeaturedAttribute(): bool
    {
        if (! $this->is_featured) {
            return false;
        }

        return $this->featured_until === null || $this->featured_until->isFuture();
    }
}
