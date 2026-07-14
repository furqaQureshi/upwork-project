<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Conversation;
use App\Models\CustomField;
use App\Models\FreePostLimit;
use App\Models\Listing;
use App\Models\ListingCustomFieldValue;
use App\Models\ListingImage;
use App\Models\ListingReport;
use App\Models\SubscriptionPackage;
use App\Notifications\ListingCreatedNotification;
use App\Services\AI\PersonalizationService;
use App\Services\FeatureAccessService;
use App\Services\SellerPostingRuleService;
use App\Services\SubscriptionPackages\SubscriptionEntitlementService;
use App\Services\WebPush\WebPushService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ListingController extends Controller
{
    public function index(Request $request): View
    {
        $listings = $request->user()
            ->listings()
            ->with(['category', 'images', 'user'])
            ->latest()
            ->paginate(12);

        return view('listings.index', [
            'listings' => $listings,
        ]);
    }

    public function create(): View
    {
        $aiSuiteEnabled = (bool) setting('ai_enabled', false);
        $listingPackagesConfigured = $this->listingPackagesConfigured();
        $hasFreePostLimitsConfigured = $this->hasAnyFreePostLimitsConfigured();
        $listingPackageRequired = $listingPackagesConfigured && ! $hasFreePostLimitsConfigured;
        $listingPackageOptionalAfterFreeLimit = $listingPackagesConfigured && $hasFreePostLimitsConfigured;
        $user = auth()->user();
        $requiresSellerVerificationForPackage = $listingPackagesConfigured && $user && ! $user->is_seller_verified;

        return view('listings.create', [
            'categories' => $this->categoryOptions(),
            'categoryTree' => $this->buildCategoryTree(),
            'allCategories' => $this->allCategoriesFlat(),
            'customFields' => $this->customFieldDefinitionsForForm(),
            'hasListingPackageRequired' => $listingPackageRequired,
            'hasListingPackageOptionalAfterFreeLimit' => $listingPackageOptionalAfterFreeLimit,
            'requiresSellerVerificationForPackage' => $requiresSellerVerificationForPackage,
            'maxImages' => (int) setting('listing_max_images', 12),
            'aiListingAssistantEnabled' => $aiSuiteEnabled && (bool) setting('ai_listing_assistant_enabled', true),
            'aiPriceRecommendationEnabled' => $aiSuiteEnabled && (bool) setting('ai_price_recommendation_enabled', true),
        ]);
    }

    public function store(
        Request $request,
        SubscriptionEntitlementService $entitlementService,
        SellerPostingRuleService $sellerPostingRuleService,
        WebPushService $webPushService
    ): RedirectResponse
    {
        $maxImages = (int) setting('listing_max_images', 8);
        $maxPerUser = (int) setting('listing_max_per_user', 20);

        $selectedCategoryId = (int) $request->input('category_id', 0);
        $applicableCustomFields = $this->applicableCustomFieldsForCategory($selectedCategoryId);
        $categoryForRules = $selectedCategoryId > 0 ? Category::find($selectedCategoryId) : null;
        $conditionEnabled = $categoryForRules ? (bool) ($categoryForRules->condition_enabled ?? true) : true;

        $rules = [
            'title' => ['required', 'string', 'max:140'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
                'price_type' => ['nullable', Rule::in(['fixed', 'negotiable', 'free'])],
                'price' => $request->input('price_type', 'fixed') === 'free'
                    ? ['nullable', 'numeric', 'min:0']
                    : ['required', 'numeric', 'min:0'],
            'description' => ['required', 'string', 'min:20', 'max:5000'],
            'condition' => $conditionEnabled
                ? ['required', Rule::in(['new', 'used', 'refurbished'])]
                : ['nullable', Rule::in(['new', 'used', 'refurbished'])],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
                'youtube_url' => ['nullable', 'url', 'max:500'],
            'images' => ['required', 'array', 'min:1', "max:{$maxImages}"],
            'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];

        $rules = array_merge($rules, $this->customFieldRules($applicableCustomFields, $request));

        $validated = $request->validate($rules);
        $validated = $this->normalizeConditionForPersistence($validated);

        $activeCount = $request->user()->listings()
            ->whereIn('status', ['pending', 'approved'])
            ->count();

        if (trim((string) $request->user()->seller_type) === '' && $activeCount >= $maxPerUser) {
            return back()->withErrors(['images' => "You have reached the maximum of {$maxPerUser} active listings."])->withInput();
        }

        $selectedCategory = $selectedCategoryId > 0
            ? Category::with('parent:id,name')->find($selectedCategoryId)
            : null;
        $sellerRuleError = $sellerPostingRuleService->violationMessage($request->user(), $selectedCategory);

        if ($sellerRuleError !== null) {
            return back()->withInput()->withErrors(['category_id' => $sellerRuleError]);
        }

        $isVerifiedForCategory = $request->user()->applicableSellerVerificationForCategory($selectedCategory) !== null;
        if ($isVerifiedForCategory && ! $this->hasActiveStoryPackage($request->user())) {
            return back()->withInput()->withErrors([
                'category_id' => 'An active Stories package is required to post as a verified seller story. Please buy a Stories package first.',
            ]);
        }

        $packagePurchase = null;

        $listingPackagesConfigured = $this->listingPackagesConfigured();
        $hasApplicableFreeLimitRules = $this->hasApplicableFreePostLimitRules($selectedCategoryId);
        $limitError = $hasApplicableFreeLimitRules
            ? $this->checkFreePostLimit($request->user()->id, $selectedCategoryId)
            : null;

        if ($limitError === null && $hasApplicableFreeLimitRules) {
            // Allowed within configured free-post limits.
        } elseif ($listingPackagesConfigured) {
            $packagePurchase = $entitlementService->findUsablePurchase($request->user(), 'listing', $selectedCategory);

            if (! $packagePurchase) {
                $message = $limitError
                    ? $limitError . ' You can buy a listing package to continue posting now.'
                    : 'An active listing package is required before posting. Please buy a package first.';

                return back()
                    ->withInput()
                    ->withErrors(['category_id' => $message]);
            }

            if (! $request->user()->is_seller_verified) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'category_id' => 'Seller document verification is required for package-based posting. Please upload your document from profile and wait for admin approval.',
                    ]);
            }
        } elseif ($limitError !== null) {
            return back()->withInput()->withErrors(['category_id' => $limitError]);
        }

        $moderationEnabled = (bool) setting('listing_moderation_enabled', true);
        $requiresModeration = $moderationEnabled && ! $isVerifiedForCategory;

        $listing = Listing::create([
            ...collect($validated)->except(['images'])->toArray(),
            'user_id' => $request->user()->id,
            'slug' => $this->makeUniqueSlug($validated['title']),
          'price' => ($validated['price_type'] ?? 'fixed') === 'free' ? 0.00 : (float) ($validated['price'] ?? 0),
          'price_type' => $validated['price_type'] ?? 'fixed',
                    'status' => $requiresModeration ? 'pending' : 'approved',
                    'published_at' => $requiresModeration ? null : now(),
          'rejection_reason' => null,
        ]);

        $this->storeImages($listing, $validated['images']);
        $this->syncCustomFieldValues($listing, $applicableCustomFields, $request);

        if ($packagePurchase) {
            $listing->load('category.parent');
            $entitlementService->consumePurchase(
                $packagePurchase,
                'listing_create',
                $listing,
                ['source' => 'listing_create']
            );
        }

        $notification = new ListingCreatedNotification(
            listingId: $listing->id,
            listingSlug: $listing->slug,
            listingTitle: $listing->title,
            status: $listing->status,
        );

        $request->user()->notify($notification);
        $webPushService->sendToUser($request->user(), $notification->toWebPushPayload());

        $message = $requiresModeration
            ? 'Listing submitted. It will be visible after admin approval.'
            : 'Listing published successfully.';

        return redirect()
            ->route('listings.show', $listing)
            ->with('status', $message);
    }

    public function show(
        Request $request,
        Listing $listing,
        SubscriptionEntitlementService $entitlementService,
        FeatureAccessService $featureAccessService,
        PersonalizationService $personalizationService
    ): View
    {
        $user = $request->user();
        $publicStatuses = ['approved', 'sold'];

        if (! in_array($listing->status, $publicStatuses, true)
            && ! $listing->isOwnedBy($user)
            && ! $user?->is_admin) {
            abort(403, 'This listing is not publicly visible.');
        }

        $listing->load(['category.parent', 'images', 'user', 'customFieldValues.customField']);

        if (! $listing->isOwnedBy($user)) {
            $listing->increment('views');
        }

        $similarListings = $personalizationService->similarListings($listing, 6);

        if ($similarListings->isEmpty()) {
            $similarListings = Listing::query()
                ->with(['images', 'category', 'user'])
                ->approved()
                ->where('category_id', $listing->category_id)
                ->where('id', '!=', $listing->id)
                ->latest('published_at')
                ->take(6)
                ->get();
        }

        $isFavorited = $user
            ? $user->favoriteListings()->where('listings.id', $listing->id)->exists()
            : false;

        $conversation = null;

        if ($user && ! $listing->isOwnedBy($user)) {
            $conversation = Conversation::query()
                ->where('listing_id', $listing->id)
                ->where('buyer_id', $user->id)
                ->where('seller_id', $listing->user_id)
                ->first();
        }

        $customFieldValues = $listing->customFieldValues
            ->filter(function (ListingCustomFieldValue $value): bool {
                return (bool) $value->customField
                    && ($value->value_text !== null || $value->value_number !== null || ! empty($value->value_json));
            })
            ->sortBy(fn (ListingCustomFieldValue $value): int => (int) ($value->customField?->sort_order ?? 0))
            ->values();

        $hasCallAccess = false;
        $hasMapAccess = false;

        if ($user && ! $listing->isOwnedBy($user)) {
            $hasPaidCallAccess = $entitlementService->hasCallAccess($user);
            $hasCallAccess = $hasPaidCallAccess || $featureAccessService->hasFreeAccess($user, 'call');
            $hasMapAccess = $hasPaidCallAccess || $featureAccessService->hasFreeAccess($user, 'map');
        }

        return view('listings.show', [
            'listing' => $listing,
            'similarListings' => $similarListings,
            'isFavorited' => $isFavorited,
            'hasCallAccess' => $hasCallAccess,
            'hasMapAccess' => $hasMapAccess,
            'conversation' => $conversation,
            'customFieldValues' => $customFieldValues,
        ]);
    }

    public function startCall(
        Request $request,
        Listing $listing,
        SubscriptionEntitlementService $entitlementService,
        FeatureAccessService $featureAccessService
    ): RedirectResponse {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($listing->isOwnedBy($user)) {
            return redirect()->route('listings.show', $listing);
        }

        $sellerPhone = preg_replace('/[^0-9\+]/', '', trim((string) ($listing->user?->phone ?? '')));

        if ($sellerPhone === '') {
            return redirect()
                ->route('listings.show', $listing)
                ->with('status', 'Seller phone is not available for this listing.');
        }

        $hasPaidCallAccess = $entitlementService->hasCallAccess($user);

        if (! $hasPaidCallAccess && ! $featureAccessService->consumeFreeAccess($user, 'call')) {
            return redirect()->route('subscriptions.index', ['feature' => 'call']);
        }

        return redirect()->away('tel:'.$sellerPhone);
    }

    public function openMap(
        Request $request,
        Listing $listing,
        SubscriptionEntitlementService $entitlementService,
        FeatureAccessService $featureAccessService
    ): RedirectResponse {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        $hasAccess = $listing->isOwnedBy($user) || $entitlementService->hasCallAccess($user);

        if (! $hasAccess && ! $featureAccessService->consumeFreeAccess($user, 'map')) {
            return redirect()->route('subscriptions.index', ['feature' => 'call']);
        }

        $mapQuery = collect([$listing->address, $listing->city, $listing->state])
            ->filter(fn ($value): bool => trim((string) $value) !== '')
            ->implode(', ');

        if ($mapQuery === '') {
            $mapQuery = $listing->title;
        }

        $mapUrl = ($listing->latitude !== null && $listing->longitude !== null)
            ? 'https://www.google.com/maps?q='.urlencode($listing->latitude.','.$listing->longitude)
            : 'https://www.google.com/maps?q='.urlencode($mapQuery);

        return redirect()->away($mapUrl);
    }

    public function edit(Request $request, Listing $listing): View
    {
        $this->ensureOwner($request, $listing);

        $customFields = $this->customFieldDefinitionsForForm();
        $customFieldValues = $listing->customFieldValues()
            ->get()
            ->keyBy('custom_field_id');

        return view('listings.edit', [
            'listing' => $listing->load(['images', 'category']),
            'categories' => $this->categoryOptions(),
            'customFields' => $customFields,
            'customFieldValues' => $customFieldValues,
        ]);
    }

    public function update(Request $request, Listing $listing, SellerPostingRuleService $sellerPostingRuleService): RedirectResponse
    {
        $this->ensureOwner($request, $listing);

        $selectedCategoryId = (int) $request->input('category_id', $listing->category_id);
        $applicableCustomFields = $this->applicableCustomFieldsForCategory($selectedCategoryId);
        $categoryForRules = $selectedCategoryId > 0 ? Category::find($selectedCategoryId) : null;
        $conditionEnabled = $categoryForRules ? (bool) ($categoryForRules->condition_enabled ?? true) : true;

        $rules = [
            'title' => ['required', 'string', 'max:140'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
                'price_type' => ['nullable', Rule::in(['fixed', 'negotiable', 'free'])],
                'price' => $request->input('price_type', 'fixed') === 'free'
                    ? ['nullable', 'numeric', 'min:0']
                    : ['required', 'numeric', 'min:0'],
            'description' => ['required', 'string', 'min:20', 'max:5000'],
            'condition' => $conditionEnabled
                ? ['required', Rule::in(['new', 'used', 'refurbished'])]
                : ['nullable', Rule::in(['new', 'used', 'refurbished'])],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'remove_images' => ['nullable', 'array'],
            'remove_images.*' => ['integer', 'exists:listing_images,id'],
            'images' => ['nullable', 'array', 'max:8'],
            'images.*' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
                'youtube_url' => ['nullable', 'url', 'max:500'],
        ];

        $rules = array_merge($rules, $this->customFieldRules($applicableCustomFields, $request, $listing));

        $validated = $request->validate($rules);
        $validated = $this->normalizeConditionForPersistence($validated, $listing);
        $selectedCategory = $selectedCategoryId > 0
            ? Category::with('parent:id,name')->find($selectedCategoryId)
            : null;
        $sellerRuleError = $sellerPostingRuleService->violationMessage($request->user(), $selectedCategory, $listing->id);

        if ($sellerRuleError !== null) {
            return back()->withInput()->withErrors(['category_id' => $sellerRuleError]);
        }

        $isVerifiedForCategory = $request->user()->applicableSellerVerificationForCategory($selectedCategory) !== null;
        if ($isVerifiedForCategory && ! $this->hasActiveStoryPackage($request->user())) {
            return back()->withInput()->withErrors([
                'category_id' => 'An active Stories package is required to post as a verified seller story. Please buy a Stories package first.',
            ]);
        }

          $requiresModeration = (bool) setting('listing_moderation_enabled', true) && ! $isVerifiedForCategory;

          $listing->update([
            ...collect($validated)->except(['images', 'remove_images'])->toArray(),
            'slug' => $this->makeUniqueSlug($validated['title'], $listing->id),
              'price' => ($validated['price_type'] ?? 'fixed') === 'free' ? 0.00 : (float) ($validated['price'] ?? 0),
              'price_type' => $validated['price_type'] ?? 'fixed',
              'status' => $requiresModeration ? 'pending' : 'approved',
              'rejection_reason' => null,
              'published_at' => $requiresModeration ? null : now(),
        ]);

        if (! empty($validated['remove_images'])) {
            $this->removeImages($listing, $validated['remove_images']);
        }

        if (! empty($validated['images'])) {
            $this->storeImages($listing, $validated['images']);
        }

        $this->syncCustomFieldValues($listing, $applicableCustomFields, $request);

        $primaryImageExists = $listing->images()->where('is_primary', true)->exists();

        if (! $primaryImageExists) {
            $listing->images()->orderBy('sort_order')->limit(1)->update(['is_primary' => true]);
        }

        return redirect()
            ->route('listings.show', $listing)
            ->with('status', $requiresModeration ? 'Listing updated and sent for review.' : 'Listing updated and published.');
    }

    public function destroy(Request $request, Listing $listing): RedirectResponse
    {
        $this->ensureOwner($request, $listing);

        $paths = $listing->images()->pluck('path')->all();

        $customFieldFilePaths = $listing->customFieldValues()
            ->whereHas('customField', function ($builder): void {
                $builder->where('field_type', 'file');
            })
            ->whereNotNull('value_text')
            ->pluck('value_text')
            ->all();

        if (! empty($paths)) {
            Storage::disk('public')->delete($paths);
        }

        if (! empty($customFieldFilePaths)) {
            Storage::disk('public')->delete($customFieldFilePaths);
        }

        $listing->delete();

        return redirect()
            ->route('listings.index')
            ->with('status', 'Listing deleted successfully.');
    }

    public function markSold(Request $request, Listing $listing): RedirectResponse
    {
        $this->ensureOwner($request, $listing);

        $listing->update([
            'status' => 'sold',
        ]);

        return back()->with('status', 'Listing marked as sold.');
    }

    public function report(Request $request, Listing $listing): RedirectResponse
    {
        if ($listing->isOwnedBy($request->user())) {
            return back()->with('status', 'You cannot report your own listing.');
        }

        $validated = $request->validate([
            'reason' => ['required', Rule::in(['spam', 'fake', 'abusive', 'wrong-category', 'sold-elsewhere', 'other'])],
            'details' => ['nullable', 'string', 'max:1000'],
        ]);

        ListingReport::updateOrCreate(
            [
                'listing_id' => $listing->id,
                'user_id' => $request->user()->id,
            ],
            [
                'reason' => $validated['reason'],
                'details' => $validated['details'] ?? null,
                'status' => 'open',
            ]
        );

        return back()->with('status', 'Thanks. The report has been sent to admins.');
    }

    private function ensureOwner(Request $request, Listing $listing): void
    {
        if (! $listing->isOwnedBy($request->user()) && ! $request->user()->is_admin) {
            abort(403, 'You do not have permission to modify this listing.');
        }
    }

    private function makeUniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($title);
        $baseSlug = $baseSlug !== '' ? $baseSlug : 'listing';
        $slug = $baseSlug;
        $suffix = 2;

        while (
            Listing::query()
                ->where('slug', $slug)
                ->when($ignoreId, function ($builder) use ($ignoreId): void {
                    $builder->where('id', '!=', $ignoreId);
                })
                ->exists()
        ) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function categoryOptions()
    {
        return Category::query()
            ->with('parent:id,name')
            ->where('is_active', true)
            ->orderByRaw('COALESCE(parent_id, id)')
            ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        }

        private function buildCategoryTree(): array
        {
            return Category::query()
                ->whereNull('parent_id')
                ->with(['children' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')->orderBy('name')])
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (Category $cat) => [
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'icon_url' => $cat->icon_url,
                    'condition_enabled' => (bool) ($cat->condition_enabled ?? true),
                    'children' => $cat->children->map(fn (Category $child) => [
                        'id' => $child->id,
                        'name' => $child->name,
                        'icon_url' => $child->icon_url,
                        'condition_enabled' => (bool) ($child->condition_enabled ?? true),
                    ])->values()->all(),
                ])
                ->values()
                ->all();
        }

        private function allCategoriesFlat(): array
        {
            return Category::query()
                ->where('is_active', true)
                ->orderByRaw('COALESCE(parent_id, id)')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'parent_id', 'condition_enabled'])
                ->map(fn (Category $cat) => [
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'parent_id' => $cat->parent_id,
                    'condition_enabled' => (bool) ($cat->condition_enabled ?? true),
                ])
                ->values()
                ->all();
        }

    private function listingPackagesConfigured(): bool
    {
        return SubscriptionPackage::query()
            ->where('package_type', 'listing')
            ->where('is_active', true)
            ->exists();
    }

    private function hasAnyFreePostLimitsConfigured(): bool
    {
        return FreePostLimit::query()->exists();
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

    private function hasActiveStoryPackage($user): bool
    {
        return $user->subscriptionPackagePurchases()
            ->active()
            ->whereHas('subscriptionPackage', function ($query): void {
                $query->where('is_active', true)->where('package_type', 'story');
            })
            ->exists();
    }

    private function normalizeConditionForPersistence(array $validated, ?Listing $listing = null): array
    {
        $condition = $validated['condition'] ?? null;

        if ($condition === null || $condition === '') {
            $validated['condition'] = $listing?->condition ?: 'used';
        }

        return $validated;
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

    private function customFieldDefinitionsForForm(): Collection
    {
        $fields = CustomField::query()
            ->with(['category:id,parent_id', 'parentField:id,name'])
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
            // Flatten options: nested options (associative) → all child values
            $rawOpts = (array) ($field->options ?? []);
            $options = [];
            foreach ($rawOpts as $optKey => $optVal) {
                if (is_array($optVal)) {
                    foreach ($optVal as $child) {
                        $options[] = (string) $child;
                    }
                } else {
                    $options[] = (string) $optVal;
                }
            }
            $options = array_values(array_unique($options));

            // Sub-field: force nullable when parent has no submitted value
            if ($field->parent_field_id) {
                $parentValue = $request->input('custom_fields.' . $field->parent_field_id);
                if ($parentValue === null || $parentValue === '') {
                    $required = false;
                }
            }

            if ($field->field_type === 'file') {
                $removeKey = 'custom_fields_remove.'.$field->id;
                $hasExisting = $existingValues->has($field->id)
                    && ! $request->boolean($removeKey);

                $fileRequired = $required && ! $hasExisting;

                $rules[$key] = [
                    $fileRequired ? 'required' : 'nullable',
                    'file',
                    'mimes:jpg,jpeg,png,webp,pdf,doc,docx',
                    'max:8192',
                ];
                $rules[$removeKey] = ['nullable', 'boolean'];

                continue;
            }

            if ($field->field_type === 'checkbox') {
                $rules[$key] = [$required ? 'required' : 'nullable', 'array'];
                $rules[$key.'.*'] = $options !== []
                    ? ['string', Rule::in($options)]
                    : ['string'];

                continue;
            }

            if ($field->field_type === 'radio' || $field->field_type === 'dropdown') {
                $rules[$key] = [
                    $required ? 'required' : 'nullable',
                    'string',
                    $options !== [] ? Rule::in($options) : 'string',
                ];

                continue;
            }

            if ($field->field_type === 'number') {
                $numberRules = [$required ? 'required' : 'nullable', 'numeric'];

                if ($field->min_length !== null || $field->max_length !== null) {
                    $minLength = $field->min_length;
                    $maxLength = $field->max_length;

                    $numberRules[] = function (string $attribute, mixed $value, \Closure $fail) use ($minLength, $maxLength): void {
                        if ($value === null || $value === '') {
                            return;
                        }

                        $digits = strlen(preg_replace('/[^0-9]/', '', (string) $value));

                        if ($minLength !== null && $digits < $minLength) {
                            $fail("The {$attribute} must be at least {$minLength} digits.");
                        }

                        if ($maxLength !== null && $digits > $maxLength) {
                            $fail("The {$attribute} must not exceed {$maxLength} digits.");
                        }
                    };
                }

                $rules[$key] = $numberRules;

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

        $obsoleteValues = $listing->customFieldValues()
            ->with('customField:id,field_type')
            ->whereNotIn('custom_field_id', $fieldIds)
            ->get();

        foreach ($obsoleteValues as $obsoleteValue) {
            if ($obsoleteValue->customField?->field_type === 'file') {
                $this->deleteUploadedFile($obsoleteValue->value_text);
            }

            $obsoleteValue->delete();
        }

        $existingValues = $listing->customFieldValues()
            ->get()
            ->keyBy('custom_field_id');

        foreach ($fields as $field) {
            $fieldId = (int) $field->id;
            $key = 'custom_fields.'.$fieldId;
            $existing = $existingValues->get($fieldId);

            if ($field->field_type === 'file') {
                $removeFile = $request->boolean('custom_fields_remove.'.$fieldId);

                if ($removeFile && $existing) {
                    $this->deleteUploadedFile($existing->value_text);
                    $existing->delete();
                    continue;
                }

                if ($request->hasFile($key)) {
                    $this->deleteUploadedFile($existing?->value_text);
                    $storedPath = $request->file($key)->store('custom-fields/values/'.$listing->id, 'public');

                    $listing->customFieldValues()->updateOrCreate(
                        ['custom_field_id' => $fieldId],
                        [
                            'value_text' => $storedPath,
                            'value_number' => null,
                            'value_json' => null,
                        ]
                    );
                }

                continue;
            }

            if ($field->field_type === 'checkbox') {
                $values = array_values(array_filter(
                    (array) $request->input($key, []),
                    fn ($item) => $item !== null && $item !== ''
                ));

                if ($values === []) {
                    $existing?->delete();
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
                $existing?->delete();
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

    private function deleteUploadedFile(?string $path): void
    {
        if (! $path) {
            return;
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    private function storeImages(Listing $listing, array $images): void
    {
        $currentCount = $listing->images()->count();

        foreach ($images as $index => $image) {
            $path = $image->store('listings/'.$listing->id, 'public');

            $listing->images()->create([
                'path' => $path,
                'sort_order' => $currentCount + $index,
                'is_primary' => ($currentCount === 0 && $index === 0),
            ]);
        }
    }

    private function removeImages(Listing $listing, array $imageIds): void
    {
        $images = ListingImage::query()
            ->where('listing_id', $listing->id)
            ->whereIn('id', $imageIds)
            ->get();

        foreach ($images as $image) {
            Storage::disk('public')->delete($image->path);
            $image->delete();
        }
    }
}
