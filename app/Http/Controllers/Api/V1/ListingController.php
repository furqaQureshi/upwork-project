<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Notifications\ListingCreatedNotification;
use App\Models\Category;
use App\Models\CustomField;
use App\Models\Favorite;
use App\Models\FreePostLimit;
use App\Models\Listing;
use App\Models\ListingCustomFieldValue;
use App\Models\ListingImage;
use App\Services\AI\PersonalizationService;
use App\Services\ListingImageThumbnailService;
use App\Services\SellerPostingRuleService;
use App\Services\WebPush\WebPushService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ListingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('q', ''));
        $categoryId = (int) $request->input('category_id', 0);
        $categoryFilterIds = $this->resolveCategoryFilterIds($categoryId);
        $featuredOnly = $request->boolean('featured');
        $locationLabel = $this->normalizeLocationKeyword((string) $request->input('location_label', ''));
        $perPage = max(1, min((int) $request->input('per_page', 20), 50));
        $lite = $request->boolean('lite', true);
        [$latitude, $longitude] = $this->extractCoordinates($request);
        $nearbyRadiusKm = $this->locationNearbyRadiusKm();

        $query = Listing::query()
            ->with(['category.parent', 'user', 'images'])
            ->approved()
            ->search($search)
            ->when($categoryFilterIds !== [], fn ($builder) => $builder->whereIn('category_id', $categoryFilterIds))
            ->when($featuredOnly, fn ($builder) => $builder->featuredActive());

        $nearbyFilterApplied = $this->applyNearbyBounds($query, $latitude, $longitude, $nearbyRadiusKm, $locationLabel);
        $this->applyLocationPriorityOrdering($query, $locationLabel, $latitude, $longitude);

        $query->latest('published_at');

        $listings = $query->paginate($perPage);

        return response()->json([
            'data' => $listings->getCollection()->map(fn (Listing $listing): array => $this->serializeListing($listing, $lite))->values(),
            'meta' => [
                'current_page' => $listings->currentPage(),
                'last_page' => $listings->lastPage(),
                'total' => $listings->total(),
                'per_page' => $listings->perPage(),
                'has_more' => $listings->hasMorePages(),
                'nearby_radius_km' => $nearbyRadiusKm,
                'nearby_filter_applied' => $nearbyFilterApplied,
            ],
        ]);
    }

    public function myListings(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->input('per_page', 20), 50));
        $lite = $request->boolean('lite', true);
        $status = strtolower(trim((string) $request->input('status', 'all')));
        $allowedStatuses = ['pending', 'approved', 'rejected', 'sold', 'expired'];

        $listings = $request->user()
            ->listings()
            ->with(['category.parent', 'user', 'images'])
            ->when(
                in_array($status, $allowedStatuses, true),
                fn ($builder) => $builder->where('status', $status)
            )
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'data' => $listings->getCollection()->map(fn (Listing $listing): array => $this->serializeListing($listing, $lite))->values(),
            'meta' => [
                'current_page' => $listings->currentPage(),
                'last_page' => $listings->lastPage(),
                'total' => $listings->total(),
                'per_page' => $listings->perPage(),
                'has_more' => $listings->hasMorePages(),
            ],
        ]);
    }

    public function show(Request $request, Listing $listing, PersonalizationService $personalizationService): JsonResponse
    {
        if ($listing->status !== 'approved') {
            abort(404);
        }

        $listing->load(['category.parent', 'user', 'images', 'customFieldValues.customField']);
        $sellerVerification = $listing->user?->applicableSellerVerificationForCategory($listing->category);
        $sellerPackage = $sellerVerification?->subscriptionPackage;
        $similarListings = $this->resolveSimilarListings($listing, $personalizationService, 6);

        return response()->json([
            'data' => array_merge($this->serializeListing($listing, false), [
                'is_favorited' => false,
                'address' => $listing->address,
                'latitude' => $listing->latitude ? (float) $listing->latitude : null,
                'longitude' => $listing->longitude ? (float) $listing->longitude : null,
                'youtube_url' => $listing->youtube_url,
                'category_specifications' => $this->serializeCategorySpecifications($listing),
                'seller' => $listing->user ? [
                    'id' => $listing->user->id,
                    'name' => $listing->user->name,
                    'phone' => $listing->user->phone,
                    'city' => $listing->user->city,
                    'seller_verification_status' => $sellerVerification?->status ?? $listing->user->seller_verification_status,
                    'is_seller_verified' => $sellerVerification !== null,
                    'is_car_seller_verified' => ($sellerPackage?->seller_tier ?? '') === 'car_verified',
                    'is_premium_seller_verified' => ($sellerPackage?->seller_tier ?? '') === 'premium_verified',
                    'seller_type' => (string) ($sellerPackage?->seller_tier ?? $listing->user->seller_type ?? ''),
                    'seller_badge_label' => $sellerPackage?->resolved_seller_badge_label ?? $listing->user->sellerBadgeLabel(),
                    'member_since' => $listing->user->created_at?->toDateString(),
                ] : null,
                'similar_listings' => $similarListings
                    ->map(fn (Listing $similarListing): array => $this->serializeListing($similarListing, true))
                    ->values(),
            ]),
        ]);
    }

    public function similar(Request $request, Listing $listing, PersonalizationService $personalizationService): JsonResponse
    {
        if ($listing->status !== 'approved') {
            abort(404);
        }

        $limit = max(1, min((int) $request->input('limit', 10), 20));
        $lite = $request->boolean('lite', true);
        $similarListings = $this->resolveSimilarListings($listing, $personalizationService, $limit);

        return response()->json([
            'data' => $similarListings
                ->map(fn (Listing $similarListing): array => $this->serializeListing($similarListing, $lite))
                ->values(),
        ]);
    }

    public function toggleFavorite(Request $request, Listing $listing): JsonResponse
    {
        if ($listing->status !== 'approved') {
            abort(404);
        }

        $user = $request->user();
        $existing = Favorite::query()
            ->where('user_id', $user->id)
            ->where('listing_id', $listing->id)
            ->first();

        if ($existing) {
            $existing->delete();
            $isFavorited = false;
        } else {
            Favorite::query()->create([
                'user_id' => $user->id,
                'listing_id' => $listing->id,
            ]);
            $isFavorited = true;
        }

        return response()->json([
            'is_favorited' => $isFavorited,
            'listing_id' => $listing->id,
        ]);
    }

    public function myFavorites(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->input('per_page', 20), 50));
        $lite = $request->boolean('lite', true);

        $listings = Listing::query()
            ->with(['category.parent', 'user', 'images'])
            ->whereHas('favoritedBy', fn ($q) => $q->where('users.id', $request->user()->id))
            ->approved()
            ->latest('published_at')
            ->paginate($perPage);

        return response()->json([
            'data' => $listings->getCollection()->map(fn (Listing $listing): array => array_merge(
                $this->serializeListing($listing, $lite),
                ['is_favorited' => true],
            ))->values(),
            'meta' => [
                'current_page' => $listings->currentPage(),
                'last_page' => $listings->lastPage(),
                'total' => $listings->total(),
                'per_page' => $listings->perPage(),
                'has_more' => $listings->hasMorePages(),
            ],
        ]);
    }

    public function store(Request $request, ListingImageThumbnailService $thumbnailService, SellerPostingRuleService $sellerPostingRuleService): JsonResponse
    {
        $selectedCategoryId = (int) $request->input('category_id', 0);
        $applicableCustomFields = $this->applicableCustomFieldsForCategory($selectedCategoryId);
        $priceTypeInput = (string) $request->input('price_type', 'fixed');
        $priceRules = ['numeric', 'min:0'];
        if ($priceTypeInput === 'free') {
            array_unshift($priceRules, 'nullable');
        } else {
            array_unshift($priceRules, 'required');
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:140'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'price_type' => ['nullable', Rule::in(['fixed', 'negotiable', 'free'])],
            'price' => $priceRules,
            'description' => ['required', 'string', 'min:20', 'max:5000'],
            'condition' => ['nullable', Rule::in(['new', 'used', 'refurbished'])],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'youtube_url' => ['nullable', 'url', 'max:500'],
            'images' => ['required', 'array', 'min:1', 'max:12'],
            'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ] + $this->customFieldRules($applicableCustomFields, $request));

        $priceType = (string) ($validated['price_type'] ?? 'fixed');
        $selectedCategory = Category::query()->with('parent')->find((int) $validated['category_id']);

        if ($this->hasApplicableFreePostLimitRules((int) $validated['category_id'])) {
            $freeLimitError = $this->checkFreePostLimit($request->user()->id, (int) $validated['category_id']);

            if ($freeLimitError !== null) {
                return response()->json([
                    'message' => $freeLimitError,
                    'errors' => [
                        'category_id' => [$freeLimitError],
                    ],
                ], 422);
            }
        }

        $sellerRuleError = $sellerPostingRuleService->violationMessage($request->user(), $selectedCategory);

        if ($sellerRuleError !== null) {
            return response()->json([
                'message' => $sellerRuleError,
                'errors' => [
                    'category_id' => [$sellerRuleError],
                ],
            ], 422);
        }

        $isVerifiedForCategory = $request->user()->applicableSellerVerificationForCategory($selectedCategory) !== null;
        if ($isVerifiedForCategory && ! $this->hasActiveStoryPackage($request->user())) {
            $message = 'An active Stories package is required to post as a verified seller story. Please buy a Stories package first.';

            return response()->json([
                'message' => $message,
                'errors' => [
                    'category_id' => [$message],
                ],
            ], 422);
        }

        $requiresModeration = (bool) setting('listing_moderation_enabled', true) && ! $isVerifiedForCategory;

        $listing = Listing::query()->create([
            'user_id' => $request->user()->id,
            'category_id' => (int) $validated['category_id'],
            'title' => (string) $validated['title'],
            'slug' => $this->makeUniqueSlug((string) $validated['title']),
            'description' => (string) $validated['description'],
            'price_type' => $priceType,
            'price' => $priceType === 'free' ? 0 : (float) ($validated['price'] ?? 0),
            'currency' => (string) setting('featured_ad_currency', 'INR'),
            'condition' => $validated['condition'] ?? 'used',
            'city' => (string) $validated['city'],
            'state' => $validated['state'] ?? null,
            'address' => $validated['address'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'youtube_url' => $validated['youtube_url'] ?? null,
            'status' => $requiresModeration ? 'pending' : 'approved',
            'published_at' => $requiresModeration ? null : now(),
        ]);

        /** @var array<int, UploadedFile> $images */
        $images = (array) ($validated['images'] ?? []);
        foreach ($images as $index => $image) {
            $path = $image->store('listings/'.$listing->id, 'public');

            $thumbnailService->createFromUploadedFile($image, $path);

            ListingImage::query()->create([
                'listing_id' => $listing->id,
                'path' => $path,
                'is_primary' => $index === 0,
                'sort_order' => $index,
            ]);
        }

        $this->syncCustomFieldValues($listing, $applicableCustomFields, $request);

        $this->dispatchListingNotifications($listing, $request);

        $listing->load(['category.parent', 'user', 'images']);

        return response()->json([
            'data' => $this->serializeListing($listing, false),
            'message' => $requiresModeration
                ? 'Listing submitted successfully.'
                : 'Listing published successfully.',
        ], 201);
    }

    public function update(Request $request, Listing $listing, ListingImageThumbnailService $thumbnailService, SellerPostingRuleService $sellerPostingRuleService): JsonResponse
    {
        abort_unless($listing->isOwnedBy($request->user()), 403, 'You can edit only your own listing.');

        $selectedCategoryId = (int) $request->input('category_id', 0);
        $applicableCustomFields = $this->applicableCustomFieldsForCategory($selectedCategoryId);
        $priceTypeInput = (string) $request->input('price_type', 'fixed');
        $priceRules = ['numeric', 'min:0'];
        if ($priceTypeInput === 'free') {
            array_unshift($priceRules, 'nullable');
        } else {
            array_unshift($priceRules, 'required');
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:140'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'price_type' => ['nullable', Rule::in(['fixed', 'negotiable', 'free'])],
            'price' => $priceRules,
            'description' => ['required', 'string', 'min:20', 'max:5000'],
            'condition' => ['nullable', Rule::in(['new', 'used', 'refurbished'])],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'youtube_url' => ['nullable', 'url', 'max:500'],
            'images' => ['nullable', 'array', 'min:1', 'max:12'],
            'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ] + $this->customFieldRules($applicableCustomFields, $request, $listing));

        $priceType = (string) ($validated['price_type'] ?? 'fixed');
        $newTitle = (string) $validated['title'];
        $selectedCategory = Category::query()->with('parent')->find((int) $validated['category_id']);
        $sellerRuleError = $sellerPostingRuleService->violationMessage($request->user(), $selectedCategory, $listing->id);

        if ($sellerRuleError !== null) {
            return response()->json([
                'message' => $sellerRuleError,
                'errors' => [
                    'category_id' => [$sellerRuleError],
                ],
            ], 422);
        }

        $isVerifiedForCategory = $request->user()->applicableSellerVerificationForCategory($selectedCategory) !== null;
        if ($isVerifiedForCategory && ! $this->hasActiveStoryPackage($request->user())) {
            $message = 'An active Stories package is required to post as a verified seller story. Please buy a Stories package first.';

            return response()->json([
                'message' => $message,
                'errors' => [
                    'category_id' => [$message],
                ],
            ], 422);
        }

        $requiresModeration = (bool) setting('listing_moderation_enabled', true) && ! $isVerifiedForCategory;

        $listing->fill([
            'category_id' => (int) $validated['category_id'],
            'title' => $newTitle,
            'slug' => $newTitle !== $listing->title ? $this->makeUniqueSlug($newTitle) : $listing->slug,
            'description' => (string) $validated['description'],
            'price_type' => $priceType,
            'price' => $priceType === 'free' ? 0 : (float) ($validated['price'] ?? 0),
            'condition' => $validated['condition'] ?? 'used',
            'city' => (string) $validated['city'],
            'state' => $validated['state'] ?? null,
            'address' => $validated['address'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'youtube_url' => $validated['youtube_url'] ?? null,
        ]);

        if ($requiresModeration) {
            $listing->status = 'pending';
            $listing->published_at = null;
        } elseif ($listing->status !== 'approved') {
            $listing->status = 'approved';
            $listing->published_at = now();
        }

        $listing->save();

        /** @var array<int, UploadedFile> $images */
        $images = (array) ($validated['images'] ?? []);
        if ($images !== []) {
            $existingImages = $listing->images()->get();
            foreach ($existingImages as $existingImage) {
                if ($existingImage->path && ! str_starts_with($existingImage->path, 'http')) {
                    Storage::disk('public')->delete($existingImage->path);
                    if ($existingImage->thumbnail_path !== '') {
                        Storage::disk('public')->delete($existingImage->thumbnail_path);
                    }
                }
            }
            $listing->images()->delete();

            foreach ($images as $index => $image) {
                $path = $image->store('listings/'.$listing->id, 'public');

                $thumbnailService->createFromUploadedFile($image, $path);

                ListingImage::query()->create([
                    'listing_id' => $listing->id,
                    'path' => $path,
                    'is_primary' => $index === 0,
                    'sort_order' => $index,
                ]);
            }
        }

        $this->syncCustomFieldValues($listing, $applicableCustomFields, $request);

        $this->dispatchListingNotifications($listing, $request);

        $listing->load(['category.parent', 'user', 'images']);

        return response()->json([
            'data' => $this->serializeListing($listing, false),
            'message' => 'Listing updated successfully.',
        ]);
    }

    public function destroy(Request $request, Listing $listing): JsonResponse
    {
        abort_unless($listing->isOwnedBy($request->user()), 403, 'You can delete only your own listing.');

        $listing->load('images');
        foreach ($listing->images as $image) {
            if ($image->path && ! str_starts_with($image->path, 'http')) {
                Storage::disk('public')->delete($image->path);
            }
        }

        $listing->delete();

        return response()->json([
            'message' => 'Listing deleted successfully.',
        ]);
    }

    public function markSold(Request $request, Listing $listing): JsonResponse
    {
        abort_unless($listing->isOwnedBy($request->user()), 403, 'You can mark sold only for your own listing.');

        $listing->status = 'sold';
        $listing->save();

        $listing->load(['category.parent', 'user', 'images']);

        return response()->json([
            'data' => $this->serializeListing($listing),
            'message' => 'Listing marked as sold.',
        ]);
    }

    public function boostFeatured(Request $request, Listing $listing): JsonResponse
    {
        abort_unless($listing->isOwnedBy($request->user()), 403, 'You can boost only your own listing.');

        $validated = $request->validate([
            'days' => ['nullable', 'integer', 'min:1', 'max:30'],
        ]);

        $days = (int) ($validated['days'] ?? 7);
        $featureStart = $listing->featured_until && $listing->featured_until->isFuture()
            ? $listing->featured_until->copy()
            : now();

        $listing->is_featured = true;
        $listing->featured_until = $featureStart->addDays($days);
        $listing->save();

        $listing->load(['category.parent', 'user', 'images']);

        return response()->json([
            'data' => $this->serializeListing($listing),
            'message' => 'Listing boosted as featured.',
        ]);
    }

    private function makeUniqueSlug(string $title): string
    {
        $slug = Str::slug($title);

        if ($slug === '') {
            $slug = 'listing';
        }

        $baseSlug = $slug;
        $counter = 2;

        while (Listing::query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    private function hasActiveStoryPackage($user): bool
    {
        return $user->subscriptionPackagePurchases()
            ->active()
            ->whereHas('subscriptionPackage', function ($query): void {
                $query->where('is_active', true)->where('package_type', 'story');
            })
            ->exists();
    }

    private function hasApplicableFreePostLimitRules(int $categoryId): bool
    {
        $category = $categoryId > 0
            ? Category::query()->select(['id', 'parent_id'])->find($categoryId)
            : null;

        $selectedCategoryId = $category?->id;
        $parentCategoryId = $category?->parent_id;

        if ($selectedCategoryId
            && FreePostLimit::query()->where('category_id', $selectedCategoryId)->exists()) {
            return true;
        }

        if ($parentCategoryId
            && FreePostLimit::query()->where('category_id', $parentCategoryId)->exists()) {
            return true;
        }

        return FreePostLimit::query()->whereNull('category_id')->exists();
    }

    /**
     * Check whether a user has exceeded the applicable free post limit for the given category.
     *
     * Priority: category-specific rule > parent-category rule > global rule.
     * Returns an error string if blocked, or null if posting is allowed.
     */
    private function checkFreePostLimit(int $userId, int $categoryId): ?string
    {
        $category = $categoryId > 0
            ? Category::query()->select(['id', 'parent_id'])->find($categoryId)
            : null;

        $selectedCategoryId = $category?->id;
        $parentCategoryId = $category?->parent_id;

        $hasCategorySpecificRules = $selectedCategoryId
            ? FreePostLimit::query()->where('category_id', $selectedCategoryId)->exists()
            : false;

        $hasParentSpecificRules = $parentCategoryId
            ? FreePostLimit::query()->where('category_id', $parentCategoryId)->exists()
            : false;

        $scopeCategoryId = null;

        if ($hasCategorySpecificRules) {
            $scopeCategoryId = $selectedCategoryId;
        } elseif ($hasParentSpecificRules) {
            $scopeCategoryId = $parentCategoryId;
        }

        $rules = FreePostLimit::query()
            ->when($scopeCategoryId !== null, function ($q) use ($scopeCategoryId): void {
                $q->where('category_id', $scopeCategoryId);
            }, function ($q): void {
                $q->whereNull('category_id');
            })
            ->orderBy('window_days')
            ->get();

        if ($rules->isEmpty()) {
            return null;
        }

        $scopeCategoryIds = $this->resolveFreePostLimitScopeCategoryIds($scopeCategoryId);
        $scopeLabel = 'any category';

        if ($scopeCategoryId !== null) {
            $scopeCategory = Category::query()
                ->with('parent:id,name')
                ->find($scopeCategoryId);

            $scopeLabel = $scopeCategory?->display_name ?? 'selected category';
        }

        foreach ($rules as $rule) {
            $since = now()->subDays((int) $rule->window_days);

            $recentCount = Listing::query()
                ->where('user_id', $userId)
                ->when($scopeCategoryIds !== null, function ($q) use ($scopeCategoryIds): void {
                    $q->whereIn('category_id', $scopeCategoryIds);
                })
                ->where('created_at', '>=', $since)
                ->whereIn('status', ['pending', 'approved'])
                ->count();

            if ($recentCount >= (int) $rule->limit_count) {
                return "You can only post {$rule->limit_count} ad(s) in {$scopeLabel} every {$rule->window_days} days. Please wait before posting again.";
            }
        }

        return null;
    }

    /**
     * Resolve category scope IDs for free-limit counting.
     *
     * For parent-category rules, include all direct child categories so users
     * cannot bypass limits by switching subcategories under the same parent.
     *
     * @return array<int>|null
     */
    private function resolveFreePostLimitScopeCategoryIds(?int $scopeCategoryId): ?array
    {
        if ($scopeCategoryId === null) {
            return null;
        }

        $childCategoryIds = Category::query()
            ->where('parent_id', $scopeCategoryId)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        return array_values(array_unique([
            $scopeCategoryId,
            ...$childCategoryIds,
        ]));
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

    /**
     * @return Collection<int, Listing>
     */
    private function resolveSimilarListings(Listing $listing, PersonalizationService $personalizationService, int $limit): Collection
    {
        $similarListings = $personalizationService->similarListings($listing, $limit);

        if ($similarListings->isNotEmpty()) {
            return $similarListings->take($limit)->values();
        }

        return Listing::query()
            ->with(['images', 'category.parent', 'user'])
            ->approved()
            ->where('category_id', $listing->category_id)
            ->where('id', '!=', $listing->id)
            ->latest('published_at')
            ->take($limit)
            ->get();
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
            'is_favorited' => false,
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
                'phone' => $listing->user->phone,
                'seller_verification_status' => $sellerVerification?->status ?? $listing->user->seller_verification_status,
                'is_seller_verified' => $sellerVerification !== null,
                'is_car_seller_verified' => ($sellerPackage?->seller_tier ?? '') === 'car_verified',
                'is_premium_seller_verified' => ($sellerPackage?->seller_tier ?? '') === 'premium_verified',
                'seller_type' => (string) ($sellerPackage?->seller_tier ?? $listing->user->seller_type ?? ''),
                'seller_badge_label' => $sellerPackage?->resolved_seller_badge_label ?? $listing->user->sellerBadgeLabel(),
            ] : null,
        ]);
    }


    private function serializeCategorySpecifications(Listing $listing): array
    {
        return $listing->customFieldValues
            ->sortBy(fn ($value) => [
                (int) ($value->customField?->sort_order ?? 9999),
                strtolower((string) ($value->customField?->name ?? '')),
            ])
            ->map(function ($value): ?array {
                $field = $value->customField;
                if (! $field || ! $field->is_active) {
                    return null;
                }

                $displayValue = '';
                if (is_array($value->value_json) && $value->value_json !== []) {
                    $displayValue = implode(', ', array_map('strval', $value->value_json));
                } elseif ($value->value_text !== null && trim((string) $value->value_text) !== '') {
                    $displayValue = trim((string) $value->value_text);
                } elseif ($value->value_number !== null) {
                    $displayValue = (string) $value->value_number;
                }

                if ($displayValue === '') {
                    return null;
                }

                return [
                    'id' => (int) $field->id,
                    'name' => (string) $field->name,
                    'value' => $displayValue,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function customFieldDefinitionsForForm(): Collection
    {
        $fields = CustomField::query()
            ->with(['category:id,parent_id'])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $childrenByParent = Category::query()
            ->select(['id', 'parent_id'])
            ->whereNotNull('parent_id')
            ->get()
            ->groupBy('parent_id')
            ->map(function (Collection $items): array {
                return $items->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
            })
            ->all();

        return $fields->map(function (CustomField $field) use ($childrenByParent): CustomField {
            $appliesTo = [(int) $field->category_id];

            if (isset($childrenByParent[$field->category_id])) {
                $appliesTo = array_merge($appliesTo, $childrenByParent[$field->category_id]);
            }

            $field->setAttribute('applies_to_category_ids', array_values(array_unique(array_map('intval', $appliesTo))));

            return $field;
        })->values();
    }

    private function applicableCustomFieldsForCategory(int $categoryId): Collection
    {
        if ($categoryId <= 0) {
            return collect();
        }

        return $this->customFieldDefinitionsForForm()
            ->filter(function (CustomField $field) use ($categoryId): bool {
                $appliesTo = (array) $field->getAttribute('applies_to_category_ids');

                return in_array($categoryId, $appliesTo, true);
            })
            ->values();
    }

    private function customFieldRules(Collection $fields, Request $request, ?Listing $listing = null): array
    {
        $rules = [];
        $existingValues = $listing
            ? $listing->customFieldValues()->get()->keyBy('custom_field_id')
            : collect();

        foreach ($fields as $field) {
            $key = 'custom_fields.'.$field->id;
            $required = (bool) $field->is_required;
            if ($field->parent_field_id) {
                $parentValue = $request->input('custom_fields.'.$field->parent_field_id);
                if ($parentValue === null || $parentValue === '') {
                    $required = false;
                }
            }

            $rawOptions = (array) ($field->options ?? []);
            $options = [];
            foreach ($rawOptions as $option) {
                if (is_array($option)) {
                    foreach ($option as $childOption) {
                        $options[] = (string) $childOption;
                    }
                } else {
                    $options[] = (string) $option;
                }
            }
            $options = array_values(array_unique(array_filter(array_map('trim', $options), fn ($v) => $v !== '')));

            if ($field->field_type === 'checkbox') {
                $rules[$key] = [$required ? 'required' : 'nullable'];
                continue;
            }

            if (in_array($field->field_type, ['radio', 'dropdown'], true)) {
                $rules[$key] = [
                    $required ? 'required' : 'nullable',
                    'string',
                    $options !== [] ? Rule::in($options) : 'string',
                ];
                continue;
            }

            if ($field->field_type === 'number') {
                $rules[$key] = [$required ? 'required' : 'nullable', 'numeric'];
                continue;
            }

            if ($field->field_type === 'file') {
                $hasExisting = $existingValues->has((int) $field->id)
                    && trim((string) ($existingValues->get((int) $field->id)?->value_text ?? '')) !== '';
                $fileRequired = $required && ! $hasExisting;

                $rules[$key] = [$fileRequired ? 'required' : 'nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx', 'max:8192'];
                continue;
            }

            $textRules = [$required ? 'required' : 'nullable', 'string'];
            if ($field->min_length !== null) {
                $textRules[] = 'min:'.$field->min_length;
            }
            if ($field->max_length !== null) {
                $textRules[] = 'max:'.$field->max_length;
            }
            $rules[$key] = $textRules;
        }

        return $rules;
    }

    private function syncCustomFieldValues(Listing $listing, Collection $fields, Request $request): void
    {
        $fieldIds = $fields->pluck('id')->map(fn ($id) => (int) $id)->all();

        $listing->customFieldValues()
            ->whereNotIn('custom_field_id', $fieldIds)
            ->delete();

        foreach ($fields as $field) {
            $fieldId = (int) $field->id;
            $key = 'custom_fields.'.$fieldId;

            if ($field->field_type === 'file') {
                if (! $request->hasFile($key)) {
                    continue;
                }

                $storedPath = $request->file($key)?->store('custom-fields/values/'.$listing->id, 'public');
                if (! $storedPath) {
                    continue;
                }

                $listing->customFieldValues()->updateOrCreate(
                    ['custom_field_id' => $fieldId],
                    [
                        'value_text' => $storedPath,
                        'value_number' => null,
                        'value_json' => null,
                    ]
                );
                continue;
            }

            if ($field->field_type === 'checkbox') {
                $rawValue = $request->input($key, []);
                if (is_string($rawValue)) {
                    $rawValue = array_map('trim', explode(',', $rawValue));
                }

                $values = array_values(array_filter((array) $rawValue, fn ($item) => $item !== null && trim((string) $item) !== ''));

                if ($values === []) {
                    $listing->customFieldValues()->where('custom_field_id', $fieldId)->delete();
                    continue;
                }

                $listing->customFieldValues()->updateOrCreate(
                    ['custom_field_id' => $fieldId],
                    [
                        'value_text' => implode(', ', $values),
                        'value_number' => null,
                        'value_json' => $values,
                    ]
                );
                continue;
            }

            $rawValue = $request->input($key);
            $stringValue = is_string($rawValue) ? trim($rawValue) : $rawValue;

            if ($stringValue === null || $stringValue === '') {
                $listing->customFieldValues()->where('custom_field_id', $fieldId)->delete();
                continue;
            }

            if ($field->field_type === 'number') {
                $listing->customFieldValues()->updateOrCreate(
                    ['custom_field_id' => $fieldId],
                    [
                        'value_text' => (string) $stringValue,
                        'value_number' => (float) $stringValue,
                        'value_json' => null,
                    ]
                );
                continue;
            }

            $listing->customFieldValues()->updateOrCreate(
                ['custom_field_id' => $fieldId],
                [
                    'value_text' => (string) $stringValue,
                    'value_number' => null,
                    'value_json' => null,
                ]
            );
        }
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

    private function dispatchListingNotifications(Listing $listing, Request $request): void
    {
        $notification = new ListingCreatedNotification(
            listingId: (int) $listing->id,
            listingSlug: (string) $listing->slug,
            listingTitle: (string) $listing->title,
            status: (string) $listing->status,
        );

        try {
            $request->user()->notify($notification);
            app(WebPushService::class)->sendToUser($request->user(), $notification->toWebPushPayload());
        } catch (\Throwable $exception) {
            // Notification failures should not block listing create/update responses.
        }
    }
}
