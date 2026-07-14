<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Category;
use App\Models\CustomField;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('q', ''));
        $categoryId = (int) $request->input('category_id', 0);
        $categoryFilterIds = $this->resolveCategoryFilterIds($categoryId);
        $locationLabel = $this->normalizeLocationKeyword((string) $request->input('location_label', ''));
        [$latitude, $longitude] = $this->extractCoordinates($request);
        $nearbyRadiusKm = $this->locationNearbyRadiusKm();

        $categories = Category::query()
            ->where('is_active', true)
            ->orderByRaw('COALESCE(parent_id, id)')
            ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $baseQuery = Listing::query()
            ->with(['category.parent', 'user', 'images'])
            ->approved()
            ->search($search)
            ->when($categoryFilterIds !== [], fn ($builder) => $builder->whereIn('category_id', $categoryFilterIds));

        $nearbyFilterApplied = $this->applyNearbyBounds($baseQuery, $latitude, $longitude, $nearbyRadiusKm, $locationLabel);

        $featuredListings = (clone $baseQuery)
            ->featuredActive();
        $this->applyLocationPriorityOrdering($featuredListings, $locationLabel, $latitude, $longitude);
        $featuredListings = $featuredListings
            ->latest('published_at')
            ->take(8)
            ->get();

        $latestListings = (clone $baseQuery);
        $this->applyLocationPriorityOrdering($latestListings, $locationLabel, $latitude, $longitude);
        $latestListings = $latestListings
            ->latest('published_at')
            ->take(20)
            ->get();

        $storiesAccess = $this->resolveStoriesAccess();
        $verifiedSellerStories = $this->verifiedSellerStories($nearbyRadiusKm, $latitude, $longitude, $locationLabel);

        return response()->json([
            'data' => [
                'categories' => $categories->map(fn (Category $category): array => $this->serializeCategory($category))->values(),
                'featured_listings' => $featuredListings->map(fn (Listing $listing): array => $this->serializeListing($listing, true))->values(),
                'latest_listings' => $latestListings->map(fn (Listing $listing): array => $this->serializeListing($listing, true))->values(),
                'verified_seller_stories' => $verifiedSellerStories->values(),
                'stories_access' => $storiesAccess,
                'nearby_radius_km' => $nearbyRadiusKm,
                'nearby_filter_applied' => $nearbyFilterApplied,
            ],
            'settings' => [
                'site_name'     => (string) AppSetting::get('site_name', 'UniSell'),
                'logo_url'      => (string) AppSetting::get('logo_url', ''),
                'banner_mode'   => (string) AppSetting::get('home_banner_mode', 'text'),
                'banner_images' => $this->resolveBannerImages(),
                'banner_display_seconds' => (int) AppSetting::get('app_banner_display_seconds', AppSetting::get('home_banner_display_seconds', 5)),
            ],
        ]);
    }

    public function categories(): JsonResponse
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->orderByRaw('COALESCE(parent_id, id)')
            ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $categories->map(fn (Category $category): array => $this->serializeCategory($category))->values(),
        ]);
    }

    public function categoryCustomFields(Category $category): JsonResponse
    {
        if (! $category->is_active) {
            abort(404);
        }

        $appliesTo = [$category->id];
        if ($category->parent_id) {
            $appliesTo[] = (int) $category->parent_id;
        }

        $fields = CustomField::query()
            ->with(['parentField:id,name'])
            ->where('is_active', true)
            ->whereIn('category_id', array_values(array_unique(array_map('intval', $appliesTo))))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $fields->map(fn (CustomField $field): array => [
                'id' => $field->id,
                'name' => $field->name,
                'slug' => $field->slug,
                'field_type' => $field->field_type,
                'is_required' => (bool) $field->is_required,
                'category_id' => $field->category_id,
                'parent_field_id' => $field->parent_field_id,
                'parent_field_name' => $field->parentField?->name,
                'min_length' => $field->min_length,
                'max_length' => $field->max_length,
                'options' => $this->flattenFieldOptions((array) ($field->options ?? [])),
                'nested_options' => $this->nestedFieldOptions((array) ($field->options ?? [])),
                'icon_url' => $this->absoluteUrl($field->icon_url),
            ])->values(),
        ]);
    }

    /**
     * Returns app version info for force-update checks.
     *
     * Admin configures these three settings in the panel:
     *   app_latest_version   e.g. "1.2.0"
     *   app_min_version      e.g. "1.1.0"  (anything below this is force-updated)
     *   app_play_store_url   e.g. "https://play.google.com/store/apps/details?id=com.example.app"
     */
    public function appVersion(): JsonResponse
    {
        return response()->json([
            'latest_version'   => trim((string) setting('app_latest_version', '1.0.0')),
            'min_version'      => trim((string) setting('app_min_version', '1.0.0')),
            'play_store_url'   => trim((string) setting('app_play_store_url', 'https://play.google.com/store')),
            'force_update_msg' => trim((string) setting('app_force_update_msg', 'A required update is available. Please update the app to continue.')),
        ]);
    }

    private function nestedFieldOptions(array $rawOptions): array
    {
        $options = [];

        foreach ($rawOptions as $parentOption => $childOptions) {
            if (! is_array($childOptions)) {
                continue;
            }

            $parentOption = trim((string) $parentOption);
            if ($parentOption === '') {
                continue;
            }

            $children = array_values(array_filter(array_map(
                static fn ($item): string => is_scalar($item) ? trim((string) $item) : '',
                $childOptions
            )));

            if ($children !== []) {
                $options[$parentOption] = array_values(array_unique($children));
            }
        }

        return $options;
    }

    private function flattenFieldOptions(array $rawOptions): array
    {
        $options = [];

        foreach ($rawOptions as $option) {
            if (is_array($option)) {
                foreach ($option as $childOption) {
                    if (is_scalar($childOption) && trim((string) $childOption) !== '') {
                        $options[] = (string) $childOption;
                    }
                }
            } elseif (is_scalar($option) && trim((string) $option) !== '') {
                $options[] = (string) $option;
            }
        }

        return array_values(array_unique($options));
    }

    private function serializeCategory(Category $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'parent_id' => $category->parent_id,
            'icon_url' => $this->absoluteUrl($category->icon_url),
            'condition_enabled' => (bool) $category->condition_enabled,
        ];
    }

    private function resolveCategoryFilterIds(int $categoryId): array
    {
        if ($categoryId <= 0) {
            return [];
        }

        $selected = Category::query()
            ->select(['id'])
            ->find($categoryId);

        if (! $selected) {
            return [];
        }

        $children = Category::query()
            ->where('parent_id', $selected->id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        return array_values(array_unique([(int) $selected->id, ...$children]));
    }

    private function absoluteUrl(?string $url): string
    {
        if (! $url) {
            return '';
        }
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return url($url);
    }

    private function extractCoordinates(Request $request): array
    {
        $latitudeRaw = $request->input('lat');
        $longitudeRaw = $request->input('lng');

        if (! is_numeric($latitudeRaw) || ! is_numeric($longitudeRaw)) {
            return [null, null];
        }

        $latitude = (float) $latitudeRaw;
        $longitude = (float) $longitudeRaw;

        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            return [null, null];
        }

        return [
            round($latitude, 6),
            round($longitude, 6),
        ];
    }

    private function locationNearbyRadiusKm(): int
    {
        $radius = (int) setting('location_nearby_radius_km', 30);

        return max(1, min(500, $radius));
    }

    private function applyNearbyBounds(Builder $query, ?float $latitude, ?float $longitude, int $radiusKm, ?string $locationLabel = null): bool
    {
        $hasCoordinates = $latitude !== null && $longitude !== null;
        $locationKeyword = $this->normalizeLocationKeyword((string) ($locationLabel ?? ''));

        if (! $hasCoordinates && $locationKeyword === null) {
            return false;
        }

        $query->where(function (Builder $locationQuery) use ($hasCoordinates, $latitude, $longitude, $radiusKm, $locationKeyword): void {
            if ($hasCoordinates) {
                $latDelta = $radiusKm / 111;
                $cosFactor = max(0.1, abs(cos(deg2rad((float) $latitude))));
                $lngDelta = $radiusKm / (111 * $cosFactor);

                $locationQuery->where(function (Builder $geoQuery) use ($latitude, $longitude, $latDelta, $lngDelta): void {
                    $geoQuery
                        ->whereNotNull('latitude')
                        ->whereNotNull('longitude')
                        ->whereBetween('latitude', [(float) $latitude - $latDelta, (float) $latitude + $latDelta])
                        ->whereBetween('longitude', [(float) $longitude - $lngDelta, (float) $longitude + $lngDelta]);
                });
            }

            if ($locationKeyword !== null) {
                $locationQuery->orWhere(function (Builder $cityQuery) use ($locationKeyword): void {
                    $cityQuery
                        ->where('city', 'like', '%'.$locationKeyword.'%')
                        ->orWhere('state', 'like', '%'.$locationKeyword.'%')
                        ->orWhere('address', 'like', '%'.$locationKeyword.'%');
                });
            }
        });

        return true;
    }

    private function normalizeLocationKeyword(string $locationLabel): ?string
    {
        $trimmed = trim($locationLabel);
        if ($trimmed === '') {
            return null;
        }

        $parts = explode(',', $trimmed);
        $firstPart = trim((string) ($parts[0] ?? $trimmed));
        $candidate = trim($firstPart !== '' ? $firstPart : $trimmed);
        $normalized = strtolower($candidate);

        if ($normalized === '' || in_array($normalized, ['near you', 'set location', 'current location'], true)) {
            return null;
        }

        return strlen($candidate) < 2 ? null : $candidate;
    }

    private function applyLocationPriorityOrdering(Builder $query, ?string $locationLabel, ?float $latitude = null, ?float $longitude = null): void
    {
        $locationKeyword = $this->normalizeLocationKeyword((string) ($locationLabel ?? ''));
        if ($locationKeyword === null) {
            return;
        }

        $containsPattern = '%'.$locationKeyword.'%';

        $query->orderByRaw(
            "CASE
                WHEN LOWER(COALESCE(city, '')) = LOWER(?) THEN 60
                WHEN LOWER(COALESCE(city, '')) LIKE LOWER(?) THEN 50
                WHEN LOWER(COALESCE(state, '')) = LOWER(?) THEN 40
                WHEN LOWER(COALESCE(state, '')) LIKE LOWER(?) THEN 30
                WHEN LOWER(COALESCE(address, '')) LIKE LOWER(?) THEN 20
                ELSE 0
            END DESC",
            [$locationKeyword, $containsPattern, $locationKeyword, $containsPattern, $containsPattern]
        );

        if ($latitude !== null && $longitude !== null) {
            // Prefer listings with coordinates and shorter geo distance within the same relevance bucket.
            $query->orderByRaw(
                "CASE
                    WHEN latitude IS NOT NULL AND longitude IS NOT NULL
                        THEN 6371 * ACOS(
                            MIN(
                                1,
                                MAX(
                                    -1,
                                    COS(RADIANS(?)) * COS(RADIANS(latitude)) * COS(RADIANS(longitude) - RADIANS(?))
                                        + SIN(RADIANS(?)) * SIN(RADIANS(latitude))
                                )
                            )
                        )
                    ELSE 999999
                END ASC",
                [$latitude, $longitude, $latitude]
            );
        }
    }

    private function serializeListing(Listing $listing, bool $lite = false): array
    {
        $sellerVerification = $listing->user?->applicableSellerVerificationForCategory($listing->category);
        $sellerPackage = $sellerVerification?->subscriptionPackage;
        $primaryImage = $listing->images
            ->sortByDesc('is_primary')
            ->sortBy('sort_order')
            ->first();

        $mainImageUrl = $primaryImage
            ? $this->absoluteUrl($lite ? $primaryImage->thumbnail_url : $primaryImage->url)
            : $this->absoluteUrl($listing->main_image_url);

        $base = [
            'id' => $listing->id,
            'title' => $listing->title,
            'slug' => $listing->slug,
            'price' => (float) $listing->price,
            'price_type' => $listing->price_type,
            'currency' => $listing->currency,
            'condition' => $listing->condition,
            'city' => $listing->city,
            'state' => $listing->state,
            'status' => $listing->status,
            'is_featured' => (bool) $listing->is_featured,
            'main_image_url' => $mainImageUrl,
            'published_at' => optional($listing->published_at)?->toIso8601String(),
            'category' => $listing->category ? [
                'id' => $listing->category->id,
                'name' => $listing->category->name,
                'slug' => $listing->category->slug,
            ] : null,
            'seller' => $listing->user ? [
                'id' => $listing->user->id,
                'name' => $listing->user->name,
                'phone' => $listing->user->phone,
                'seller_verification_status' => $sellerVerification?->status ?? $listing->user->seller_verification_status,
                'is_seller_verified' => $sellerVerification !== null,
                'is_car_seller_verified' => ($sellerPackage?->seller_tier ?? '') === 'car_verified',
                'is_premium_seller_verified' => ($sellerPackage?->seller_tier ?? '') === 'premium_verified',
                'seller_type' => (string) ($sellerPackage?->seller_tier ?? $listing->user->seller_type ?? ''),
                'seller_badge_label' => $sellerPackage?->resolved_seller_badge_label ?? $listing->user->sellerBadgeLabel(),
            ] : null,
        ];

        if ($lite) {
            return $base;
        }

        return array_merge($base, [
            'description' => $listing->description,
            'main_thumbnail_url' => $primaryImage ? $this->absoluteUrl($primaryImage->thumbnail_url) : $mainImageUrl,
            'image_urls' => $listing->images
                ->sortBy('sort_order')
                ->values()
                ->map(fn (ListingImage $image): string => $this->absoluteUrl($image->url))
                ->all(),
            'address'   => $listing->address,
            'latitude'  => $listing->latitude !== null ? (float) $listing->latitude : null,
            'longitude' => $listing->longitude !== null ? (float) $listing->longitude : null,
            'seller' => $listing->user ? [
                'id' => $listing->user->id,
                'name' => $listing->user->name,
                'seller_verification_status' => $sellerVerification?->status ?? $listing->user->seller_verification_status,
                'is_seller_verified' => $sellerVerification !== null,
                'is_car_seller_verified' => ($sellerPackage?->seller_tier ?? '') === 'car_verified',
                'is_premium_seller_verified' => ($sellerPackage?->seller_tier ?? '') === 'premium_verified',
                'seller_type' => (string) ($sellerPackage?->seller_tier ?? $listing->user->seller_type ?? ''),
                'seller_badge_label' => $sellerPackage?->resolved_seller_badge_label ?? $listing->user->sellerBadgeLabel(),
                'phone' => $listing->user->phone,
            ] : null,
        ]);
    }

    private function resolveBannerImages(): array
    {
        $appImages = $this->normalizeBannerImageList(AppSetting::get('app_banner_images', []));
        if ($appImages !== []) {
            return $appImages;
        }

        $mode = (string) AppSetting::get('home_banner_mode', 'text');
        if ($mode !== 'image') {
            return [];
        }

        $images = $this->normalizeBannerImageList(AppSetting::get('home_banner_images', []));
        if ($images !== []) {
            return $images;
        }

        $single = (string) AppSetting::get('home_banner_image_url', '');
        if ($single !== '') {
            return [str_starts_with($single, 'http') ? $single : url('storage/' . ltrim($single, '/'))];
        }

        return [];
    }

    private function normalizeBannerImageList(mixed $images): array
    {
        if (! is_array($images) || count($images) === 0) {
            return [];
        }

        return array_values(array_filter(array_map(function ($item) {
            if (is_string($item) && $item !== '') {
                return str_starts_with($item, 'http') ? $item : url('storage/' . ltrim($item, '/'));
            }
            if (is_array($item) && isset($item['url']) && (string) $item['url'] !== '') {
                $u = (string) $item['url'];
                return str_starts_with($u, 'http') ? $u : url('storage/' . ltrim($u, '/'));
            }
            return null;
        }, $images)));
    }

    /**
     * @return array{has_access:bool,message:string,package_type:string,locked_count:int}
     */
    private function resolveStoriesAccess(): array
    {
        return [
            'has_access' => true,
            'message' => 'Story viewing is free. Subscribe to a Story package to publish your stories.',
            'package_type' => 'story',
            'locked_count' => 0,
        ];
    }

    private function verifiedSellerStories(int $radiusKm, ?float $latitude, ?float $longitude, ?string $locationLabel = null): Collection
    {
        $query = Listing::query()
            ->with(['category.parent', 'user', 'images'])
            ->approved()
            ->whereHas('user.sellerVerifications', function (Builder $builder): void {
                $builder->where('status', 'approved');
            })
            ->whereHas('user.subscriptionPackagePurchases', function (Builder $builder): void {
                $builder
                    ->active()
                    ->whereHas('subscriptionPackage', function (Builder $packageBuilder): void {
                        $packageBuilder->where('is_active', true)->where('package_type', 'story');
                    });
            });

        $this->applyNearbyBounds($query, $latitude, $longitude, $radiusKm, $locationLabel);
        $this->applyLocationPriorityOrdering($query, $locationLabel, $latitude, $longitude);

        $query
            ->latest('published_at')
            ->take(120);

        $listings = $query->get();
        $grouped = $listings->groupBy('user_id');

        return $grouped->map(function (Collection $sellerListings, int|string $userId): array {
            /** @var Listing $first */
            $first = $sellerListings->first();

            return [
                'seller_id' => (int) $userId,
                'seller_name' => (string) ($first->user?->name ?? 'Verified Seller'),
                'seller_badge_label' => (string) (($first->user?->sellerBadgeLabel()) ?: 'VERIFIED SELLER'),
                'seller_type' => (string) ($first->user?->seller_type ?? ''),
                'items' => $sellerListings
                    ->take(8)
                    ->map(fn (Listing $listing): array => $this->serializeListing($listing, false))
                    ->values(),
            ];
        })->take(24)->values();
    }
}
