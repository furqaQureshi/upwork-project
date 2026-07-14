<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\CustomField;
use App\Models\Listing;
use App\Services\AI\PersonalizationService;
use App\Services\FeatureAccessService;
use App\Services\SubscriptionPackages\SubscriptionEntitlementService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(
        Request $request,
        SubscriptionEntitlementService $entitlementService,
        FeatureAccessService $featureAccessService,
        PersonalizationService $personalizationService
    ): View
    {
        $selectedCountry = $this->normalizeCountryCode($request->input('country'));
        $selectedState = $this->normalizeLocationText($request->input('state'));
        $selectedCity = $this->normalizeLocationText($request->input('city'));
        $selectedArea = $this->normalizeLocationText($request->input('area'));
        [$selectedLatitude, $selectedLongitude] = $this->extractCoordinates($request);
        $nearbyRadiusKm = $this->locationNearbyRadiusKm();

        $selectedCategoryId = (int) $request->input('category', 0);
        $customFilters = (array) $request->input('custom_filters', []);
        $customFields = $this->customFieldsForCategory($selectedCategoryId)
            ->reject(fn (CustomField $field): bool => $field->field_type === 'file')
            ->values();

        $query = Listing::query()
            ->with(['category', 'user', 'images'])
            ->approved()
            ->search($request->string('q')->toString())
            ->when($selectedCategoryId > 0, function ($builder) use ($selectedCategoryId): void {
                $builder->where('category_id', $selectedCategoryId);
            })
            ->when($selectedCity !== '', function ($builder) use ($selectedCity): void {
                $builder->where('city', 'like', '%'.$selectedCity.'%');
            })
            ->when($selectedState !== '', function ($builder) use ($selectedState): void {
                $builder->where('state', 'like', '%'.$selectedState.'%');
            })
            ->when($selectedArea !== '', function ($builder) use ($selectedArea): void {
                $builder->where('address', 'like', '%'.$selectedArea.'%');
            })
            ->when($request->filled('condition'), function ($builder) use ($request): void {
                $builder->where('condition', $request->string('condition')->toString());
            })
            ->when($request->filled('min_price'), function ($builder) use ($request): void {
                $builder->where('price', '>=', (float) $request->input('min_price'));
            })
            ->when($request->filled('max_price'), function ($builder) use ($request): void {
                $builder->where('price', '<=', (float) $request->input('max_price'));
            });

        $personalizationService->applyFeedOrdering($query, $request->user());

        $query
            ->orderByRaw(
                'CASE WHEN is_featured = 1 AND (featured_until IS NULL OR featured_until > ?) THEN 1 ELSE 0 END DESC',
                [now()]
            )
            ->latest('published_at')
            ->latest();

        $this->applyNearbyBounds($query, $selectedLatitude, $selectedLongitude, $nearbyRadiusKm);

        $this->applyCustomFieldFilters($query, $customFields, $customFilters);

        $listings = $query->paginate(12)->withQueryString();

        $nearbyListings = collect();

        if ($selectedLatitude !== null && $selectedLongitude !== null) {
            $nearbyQuery = Listing::query()
                ->with(['category', 'user', 'images'])
                ->approved();

            $this->applyNearbyBounds($nearbyQuery, $selectedLatitude, $selectedLongitude, $nearbyRadiusKm);

            $nearbyListings = $nearbyQuery
                ->latest('published_at')
                ->take(8)
                ->get();
        } elseif ($selectedCity !== '') {
            $nearbyListings = Listing::query()
                ->with(['category', 'user', 'images'])
                ->approved()
                ->where('city', 'like', '%'.$selectedCity.'%')
                ->latest('published_at')
                ->take(8)
                ->get();
        }

        $featuredListings = Listing::query()
            ->with(['category', 'images', 'user'])
            ->approved()
            ->featuredActive()
            ->latest('published_at')
            ->take(6)
            ->get();

        $categories = Category::query()
            ->with('parent:id,name')
            ->where('is_active', true)
            ->orderByRaw('COALESCE(parent_id, id)')
            ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $quickCategories = $categories
            ->filter(fn ($category) => $category->parent_id === null)
            ->values();

        if ($quickCategories->count() < 8) {
            $quickCategories = $categories->values();
        }

        $popularCategories = $quickCategories->take(7)->values();
        $inFeedRowsInterval = max(1, (int) setting('adsense_feed_rows_interval', 2));
        $inFeedInsertEvery = $inFeedRowsInterval * 2;

        $favoriteListingIds = $request->user()
            ? $request->user()->favoriteListings()->pluck('listings.id')->all()
            : [];

        $hasCallAccess = false;

        if ($request->user()) {
            $hasPaidCallAccess = $entitlementService->hasCallAccess($request->user());
            $hasCallAccess = $hasPaidCallAccess || $featureAccessService->hasFreeAccess($request->user(), 'call');
        }

        $homeBannerData = $this->buildHomeBannerData();
        $locationState = $this->buildLocationState($selectedCountry, $selectedState, $selectedCity, $selectedArea, $selectedLatitude, $selectedLongitude);
        $locationLabel = $this->buildLocationLabel($locationState);
        $homeMyListingsUrl = $request->user() ? route('listings.index') : route('login');
        $homeChatUrl = $request->user() ? route('chat.index') : route('login');

        return view('home', [
            'listings' => $listings,
            'nearbyListings' => $nearbyListings,
            'featuredListings' => $featuredListings,
            'categories' => $categories,
            'customFields' => $customFields,
            'customFilters' => $customFilters,
            'favoriteListingIds' => $favoriteListingIds,
            'locationFilter' => [
                'country' => $selectedCountry,
                'state' => $selectedState,
                'city' => $selectedCity,
                'area' => $selectedArea,
                'latitude' => $selectedLatitude,
                'longitude' => $selectedLongitude,
            ],
            'locationState' => $locationState,
            'locationLabel' => $locationLabel,
            'nearbyRadiusKm' => $nearbyRadiusKm,
            'locationApiEndpoints' => [
                'countries' => route('api.location.countries'),
                'states' => route('api.location.states'),
                'cities' => route('api.location.cities'),
                'areas' => route('api.location.areas'),
            ],
            'locationDefaultCountry' => strtoupper((string) setting('location_default_country', 'IN')),
            'googleMapsApiKey' => trim((string) setting('google_maps_api_key', '')),
            'hasCallAccess' => $hasCallAccess,
            'homeMyListingsUrl' => $homeMyListingsUrl,
            'homeChatUrl' => $homeChatUrl,
            'popularCategories' => $popularCategories,
            'inFeedInsertEvery' => $inFeedInsertEvery,
        ] + $homeBannerData);
    }

    private function buildLocationState(string $country, string $state, string $city, string $area, ?float $latitude, ?float $longitude): array
    {
        return [
            'country' => strtoupper($country !== '' ? $country : 'IN'),
            'state' => $state,
            'city' => trim($city),
            'area' => trim($area),
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];
    }

    private function buildLocationLabel(array $locationState): string
    {
        $labelParts = array_values(array_filter([
            $locationState['area'] ?? '',
            $locationState['city'] ?? '',
            $locationState['state'] ?? '',
        ], fn ($part) => trim((string) $part) !== ''));

        return $labelParts !== [] ? implode(', ', $labelParts) : 'Select location';
    }

    private function buildHomeBannerData(): array
    {
        $homeBannerMode = strtolower(trim((string) setting('home_banner_mode', 'text')));
        if (! in_array($homeBannerMode, ['text', 'image'], true)) {
            $homeBannerMode = 'text';
        }

        $homeBannerImages = array_values(array_filter(array_map(
            static fn ($value): string => trim((string) $value),
            (array) setting('home_banner_images', [])
        ), static fn (string $value): bool => $value !== ''));

        $legacyHomeBannerImage = trim((string) setting('home_banner_image_url', ''));
        if ($homeBannerImages === [] && $legacyHomeBannerImage !== '') {
            $homeBannerImages[] = $legacyHomeBannerImage;
        }

        $homeBannerImageUrls = array_values(array_map(static function (string $imagePath): string {
            if (
                str_starts_with($imagePath, 'http://')
                || str_starts_with($imagePath, 'https://')
                || str_starts_with($imagePath, '/')
            ) {
                return $imagePath;
            }

            return Storage::url($imagePath);
        }, $homeBannerImages));

        $showImageBanner = $homeBannerMode === 'image' && $homeBannerImageUrls !== [];
        $resolvedHomeBannerMode = $showImageBanner ? 'image' : 'text';

        $allowedBannerPositionValues = ['center', 'top', 'bottom', 'left', 'right'];
        $homeBannerPositions = array_values(array_map(
            static fn ($v): string => in_array((string) $v, $allowedBannerPositionValues, true) ? (string) $v : 'center',
            (array) setting('home_banner_image_positions', [])
        ));

        while (count($homeBannerPositions) < count($homeBannerImageUrls)) {
            $homeBannerPositions[] = 'center';
        }

        $homeBannerFits = array_values(array_map(
            static fn ($v): string => (string) $v === 'contain' ? 'contain' : 'cover',
            (array) setting('home_banner_image_fits', [])
        ));

        while (count($homeBannerFits) < count($homeBannerImageUrls)) {
            $homeBannerFits[] = 'cover';
        }

        $homeBannerSlides = [
            [
                'badge' => trim((string) setting('home_banner_slide_1_badge', 'Trending Deals')),
                'title' => trim((string) setting('home_banner_slide_1_title', 'Cars and bikes this week')),
                'desc' => trim((string) setting('home_banner_slide_1_desc', 'Browse top listings from nearby sellers and compare prices fast.')),
            ],
            [
                'badge' => trim((string) setting('home_banner_slide_2_badge', 'Smart Buying')),
                'title' => trim((string) setting('home_banner_slide_2_title', 'Verified listings, faster chats')),
                'desc' => trim((string) setting('home_banner_slide_2_desc', 'Message sellers instantly and close deals with confidence.')),
            ],
            [
                'badge' => trim((string) setting('home_banner_slide_3_badge', 'Post Instantly')),
                'title' => trim((string) setting('home_banner_slide_3_title', 'Sell in your city today')),
                'desc' => trim((string) setting('home_banner_slide_3_desc', 'Upload photos, set price, and publish your ad in under two minutes.')),
            ],
        ];

        foreach ($homeBannerSlides as $index => $slide) {
            if ($slide['badge'] === '') {
                $homeBannerSlides[$index]['badge'] = ['Trending Deals', 'Smart Buying', 'Post Instantly'][$index];
            }
            if ($slide['title'] === '') {
                $homeBannerSlides[$index]['title'] = ['Cars and bikes this week', 'Verified listings, faster chats', 'Sell in your city today'][$index];
            }
            if ($slide['desc'] === '') {
                $homeBannerSlides[$index]['desc'] = [
                    'Browse top listings from nearby sellers and compare prices fast.',
                    'Message sellers instantly and close deals with confidence.',
                    'Upload photos, set price, and publish your ad in under two minutes.',
                ][$index];
            }
        }

        $homeBannerSlideCount = $showImageBanner
            ? max(1, count($homeBannerImageUrls))
            : max(1, count($homeBannerSlides));

        $homeBannerDisplaySeconds = (int) setting('home_banner_display_seconds', 5);
        if ($homeBannerDisplaySeconds < 1 || $homeBannerDisplaySeconds > 60) {
            $homeBannerDisplaySeconds = 5;
        }

        return [
            'showImageBanner' => $showImageBanner,
            'homeBannerImageUrls' => $homeBannerImageUrls,
            'homeBannerPositions' => $homeBannerPositions,
            'homeBannerFits' => $homeBannerFits,
            'resolvedHomeBannerMode' => $resolvedHomeBannerMode,
            'homeBannerSlideCount' => $homeBannerSlideCount,
            'homeBannerDisplaySeconds' => $homeBannerDisplaySeconds,
            'homeBannerSlides' => $homeBannerSlides,
        ];
    }

    public function categories(): View
    {
        $parentCategories = Category::query()
            ->with(['children' => function ($builder): void {
                $builder
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name');
            }])
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $allCategories = Category::query()
            ->with('parent:id,name')
            ->where('is_active', true)
            ->orderByRaw('COALESCE(parent_id, id)')
            ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('categories.index', [
            'parentCategories' => $parentCategories,
            'allCategories' => $allCategories,
        ]);
    }

    public function showCategory(
        Request $request,
        Category $category,
        SubscriptionEntitlementService $entitlementService,
        FeatureAccessService $featureAccessService,
        PersonalizationService $personalizationService
    ): View
    {
        abort_unless($category->is_active, 404);

        $category->load([
            'parent:id,name,slug',
            'children' => function ($builder): void {
                $builder
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name');
            },
        ]);

        $selectedCountry = $this->normalizeCountryCode($request->input('country'));
        $selectedState = $this->normalizeLocationText($request->input('state'));
        $selectedCity = $this->normalizeLocationText($request->input('city'));
        $selectedArea = $this->normalizeLocationText($request->input('area'));
        [$selectedLatitude, $selectedLongitude] = $this->extractCoordinates($request);
        $nearbyRadiusKm = $this->locationNearbyRadiusKm();

        $selectedSubcategoryId = (int) $request->input('subcategory', 0);
        $selectedSubcategory = $category->children->firstWhere('id', $selectedSubcategoryId);
        $filterCategory = $selectedSubcategory ?: $category;
        $listingCategoryIds = $selectedSubcategory
            ? [$selectedSubcategory->id]
            : array_values(array_unique(array_map('intval', array_merge([$category->id], $category->children->pluck('id')->all()))));

        $customFilters = (array) $request->input('custom_filters', []);
        $customFields = $this->customFieldsForCategory($filterCategory->id)
            ->reject(fn (CustomField $field): bool => $field->field_type === 'file')
            ->values();

        $query = Listing::query()
            ->with(['category.parent', 'user', 'images'])
            ->approved()
            ->search($request->string('q')->toString())
            ->when($listingCategoryIds !== [], function (Builder $builder) use ($listingCategoryIds): void {
                if (count($listingCategoryIds) === 1) {
                    $builder->where('category_id', $listingCategoryIds[0]);
                    return;
                }

                $builder->whereIn('category_id', $listingCategoryIds);
            })
            ->when($selectedCity !== '', function ($builder) use ($selectedCity): void {
                $builder->where('city', 'like', '%'.$selectedCity.'%');
            })
            ->when($selectedState !== '', function ($builder) use ($selectedState): void {
                $builder->where('state', 'like', '%'.$selectedState.'%');
            })
            ->when($selectedArea !== '', function ($builder) use ($selectedArea): void {
                $builder->where('address', 'like', '%'.$selectedArea.'%');
            })
            ->when($request->filled('condition'), function ($builder) use ($request): void {
                $builder->where('condition', $request->string('condition')->toString());
            })
            ->when($request->filled('min_price'), function ($builder) use ($request): void {
                $builder->where('price', '>=', (float) $request->input('min_price'));
            })
            ->when($request->filled('max_price'), function ($builder) use ($request): void {
                $builder->where('price', '<=', (float) $request->input('max_price'));
            });

        $personalizationService->applyFeedOrdering($query, $request->user());

        $query
            ->orderByRaw(
                'CASE WHEN is_featured = 1 AND (featured_until IS NULL OR featured_until > ?) THEN 1 ELSE 0 END DESC',
                [now()]
            )
            ->latest('published_at')
            ->latest();

        $this->applyNearbyBounds($query, $selectedLatitude, $selectedLongitude, $nearbyRadiusKm);
        $this->applyCustomFieldFilters($query, $customFields, $customFilters);

        $listings = $query->paginate(12)->withQueryString();

        $favoriteListingIds = $request->user()
            ? $request->user()->favoriteListings()->pluck('listings.id')->all()
            : [];

        $hasCallAccess = false;

        if ($request->user()) {
            $hasPaidCallAccess = $entitlementService->hasCallAccess($request->user());
            $hasCallAccess = $hasPaidCallAccess || $featureAccessService->hasFreeAccess($request->user(), 'call');
        }

        return view('categories.show', [
            'category' => $category,
            'selectedSubcategory' => $selectedSubcategory,
            'filterCategory' => $filterCategory,
            'listings' => $listings,
            'customFields' => $customFields,
            'customFilters' => $customFilters,
            'favoriteListingIds' => $favoriteListingIds,
            'locationFilter' => [
                'country' => $selectedCountry,
                'state' => $selectedState,
                'city' => $selectedCity,
                'area' => $selectedArea,
                'latitude' => $selectedLatitude,
                'longitude' => $selectedLongitude,
            ],
            'nearbyRadiusKm' => $nearbyRadiusKm,
            'hasCallAccess' => $hasCallAccess,
        ]);
    }

    public function menu(Request $request): View
    {
        return view('menu.index', [
            'unreadNotifications' => $request->user()?->unreadNotifications()->count() ?? 0,
        ]);
    }

    private function normalizeLocationText(mixed $value): string
    {
        return mb_substr(trim((string) $value), 0, 120);
    }

    private function normalizeCountryCode(mixed $value): string
    {
        $normalized = preg_replace('/[^A-Za-z]/', '', trim((string) $value)) ?? '';

        return strtoupper(substr($normalized, 0, 2));
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

    private function applyNearbyBounds(Builder $query, ?float $latitude, ?float $longitude, int $radiusKm): bool
    {
        if ($latitude === null || $longitude === null) {
            return false;
        }

        $latDelta = $radiusKm / 111;
        $cosFactor = max(0.1, abs(cos(deg2rad($latitude))));
        $lngDelta = $radiusKm / (111 * $cosFactor);

        $query
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereBetween('latitude', [$latitude - $latDelta, $latitude + $latDelta])
            ->whereBetween('longitude', [$longitude - $lngDelta, $longitude + $lngDelta]);

        return true;
    }

    private function customFieldsForCategory(int $categoryId): Collection
    {
        if ($categoryId <= 0) {
            return collect();
        }

        $category = Category::query()->select(['id', 'parent_id'])->find($categoryId);

        if (! $category) {
            return collect();
        }

        $categoryIds = [$category->id];

        if ($category->parent_id) {
            $categoryIds[] = (int) $category->parent_id;
        }

        return CustomField::query()
            ->with('parentField:id,name')
            ->where('is_active', true)
            ->whereIn('category_id', array_values(array_unique(array_map('intval', $categoryIds))))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    private function applyCustomFieldFilters(Builder $query, Collection $customFields, array $customFilters): void
    {
        foreach ($customFields as $field) {
            $fieldId = (int) $field->id;
            $rawFilter = $customFilters[$fieldId] ?? null;

            if ($field->field_type === 'checkbox') {
                $values = array_values(array_filter(
                    array_map('trim', array_map('strval', (array) $rawFilter)),
                    fn (string $value): bool => $value !== ''
                ));

                if ($values === []) {
                    continue;
                }

                $query->whereHas('customFieldValues', function (Builder $builder) use ($fieldId, $values): void {
                    $builder->where('custom_field_id', $fieldId);

                    foreach ($values as $value) {
                        $builder->where('value_text', 'like', '%'.$value.'%');
                    }
                });

                continue;
            }

            $value = is_array($rawFilter)
                ? trim((string) ($rawFilter[0] ?? ''))
                : trim((string) ($rawFilter ?? ''));

            if ($value === '') {
                continue;
            }

            if ($field->field_type === 'number') {
                if (! is_numeric($value)) {
                    continue;
                }

                $query->whereHas('customFieldValues', function (Builder $builder) use ($fieldId, $value): void {
                    $builder
                        ->where('custom_field_id', $fieldId)
                        ->where('value_number', (float) $value);
                });

                continue;
            }

            if (in_array($field->field_type, ['radio', 'dropdown'], true)) {
                $query->whereHas('customFieldValues', function (Builder $builder) use ($fieldId, $value): void {
                    $builder
                        ->where('custom_field_id', $fieldId)
                        ->where('value_text', $value);
                });

                continue;
            }

            $query->whereHas('customFieldValues', function (Builder $builder) use ($fieldId, $value): void {
                $builder
                    ->where('custom_field_id', $fieldId)
                    ->where('value_text', 'like', '%'.$value.'%');
            });
        }
    }
}
