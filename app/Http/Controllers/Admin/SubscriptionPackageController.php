<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\SubscriptionPackage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SubscriptionPackageController extends Controller
{
    public function index(Request $request): View
    {
        $typeFilter = strtolower(trim((string) $request->query('type', 'all')));

        $packages = SubscriptionPackage::query()
            ->with(['category.parent'])
            ->when(in_array($typeFilter, ['listing', 'featured', 'story'], true), function ($query) use ($typeFilter): void {
                $query->where('package_type', $typeFilter);
            })
            ->latest('id')
            ->paginate(20);

        return view('admin.subscription-packages.index', [
            'packages' => $packages,
            'typeFilter' => $typeFilter,
        ]);
    }

    public function create(): View
    {
        return view('admin.subscription-packages.create', [
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateRequest($request);

        $payload = $this->buildPayload($request, $validated);

        if ($request->hasFile('icon_file')) {
            $payload['icon'] = $request->file('icon_file')->store('subscription-packages/icons', 'public');
        }

        SubscriptionPackage::create($payload);

        return redirect()
            ->route('admin.subscription-packages.index')
            ->with('status', 'Subscription package created successfully.');
    }

    public function show(SubscriptionPackage $subscriptionPackage): View
    {
        $subscriptionPackage->load('category.parent');

        return view('admin.subscription-packages.show', [
            'package' => $subscriptionPackage,
        ]);
    }

    public function edit(SubscriptionPackage $subscriptionPackage): View
    {
        return view('admin.subscription-packages.edit', [
            'package' => $subscriptionPackage,
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function update(Request $request, SubscriptionPackage $subscriptionPackage): RedirectResponse
    {
        $validated = $this->validateRequest($request);

        $payload = $this->buildPayload($request, $validated, $subscriptionPackage);

        if ($request->boolean('remove_icon')) {
            $this->deleteFileIfExists($subscriptionPackage->icon);
            $payload['icon'] = null;
        }

        if ($request->hasFile('icon_file')) {
            $this->deleteFileIfExists($subscriptionPackage->icon);
            $payload['icon'] = $request->file('icon_file')->store('subscription-packages/icons', 'public');
        }

        $subscriptionPackage->update($payload);

        return redirect()
            ->route('admin.subscription-packages.index')
            ->with('status', 'Subscription package updated successfully.');
    }

    private function validateRequest(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'package_type' => ['required', 'string', 'in:listing,featured,story'],
            'price' => ['required', 'numeric', 'min:0'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'package_duration_type' => ['required', 'string', 'in:limited,unlimited'],
            'package_duration_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'item_limit_type' => ['required', 'string', 'in:limited,unlimited'],
            'item_limit_count' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'listing_duration_type' => ['required', 'string', 'in:standard,custom'],
            'listing_duration_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'category_scope' => ['nullable', 'string', 'in:global,specific'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'key_points' => ['nullable', 'string', 'max:5000'],
            'icon_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'remove_icon' => ['nullable', 'boolean'],
            'allows_call' => ['nullable', 'boolean'],
            'allows_ai' => ['nullable', 'boolean'],
            'ai_usage_limit_type' => ['nullable', 'string', 'in:limited,unlimited'],
            'ai_usage_limit_count' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'is_active' => ['nullable', 'boolean'],
            'is_seller_verification' => ['nullable', 'boolean'],
            'seller_tier' => ['nullable', 'string', 'in:verified,car_verified,premium_verified'],
            'seller_badge_label' => ['nullable', 'string', 'max:120'],
            'required_documents' => ['nullable', 'string', 'max:5000'],
        ]);

        if (($validated['package_duration_type'] ?? 'limited') === 'limited') {
            $request->validate([
                'package_duration_days' => ['required', 'integer', 'min:1', 'max:3650'],
            ]);
        }

        if (($validated['item_limit_type'] ?? 'limited') === 'limited') {
            $request->validate([
                'item_limit_count' => ['required', 'integer', 'min:1', 'max:1000000'],
            ]);
        }

        if (($validated['listing_duration_type'] ?? 'standard') === 'custom') {
            $request->validate([
                'listing_duration_days' => ['required', 'integer', 'min:1', 'max:3650'],
            ]);
        }

        if ($request->boolean('allows_ai')) {
            $request->validate([
                'ai_usage_limit_type' => ['required', 'string', 'in:limited,unlimited'],
            ]);

            if (($validated['ai_usage_limit_type'] ?? 'limited') === 'limited') {
                $request->validate([
                    'ai_usage_limit_count' => ['required', 'integer', 'min:1', 'max:1000000'],
                ]);
            }
        }

        if (($validated['package_type'] ?? 'listing') === 'listing' && ($validated['category_scope'] ?? 'global') === 'specific') {
            $request->validate([
                'category_id' => ['required', 'integer', 'exists:categories,id'],
            ]);
        }

        if ($request->boolean('is_seller_verification')) {
            $request->validate([
                'seller_tier' => ['required', 'string', 'in:verified,car_verified,premium_verified'],
            ]);

            if (($validated['seller_tier'] ?? null) === 'car_verified') {
                $carsCategoryId = (int) Category::query()->where('slug', 'cars')->value('id');

                $request->validate([
                    'category_scope' => ['required', 'in:specific'],
                    'category_id' => ['required', 'integer', 'in:'.$carsCategoryId],
                ]);
            }
        }

        return $validated;
    }

    private function buildPayload(
        Request $request,
        array $validated,
        ?SubscriptionPackage $existing = null
    ): array {
        $price = (float) ($validated['price'] ?? 0);
        $discountPercent = (float) ($validated['discount_percent'] ?? 0);
        $finalPrice = $this->calculateFinalPrice($price, $discountPercent);
        $isSellerVerification = $request->boolean('is_seller_verification');
        $sellerTier = $isSellerVerification ? (string) ($validated['seller_tier'] ?? 'verified') : null;

        $packageType = (string) ($validated['package_type'] ?? 'listing');
        $categoryScope = (in_array($packageType, ['featured', 'story'], true) && ! $isSellerVerification)
            ? 'global'
            : (string) ($validated['category_scope'] ?? 'global');

        $categoryId = $categoryScope === 'specific'
            ? (int) ($validated['category_id'] ?? 0)
            : null;

        if (in_array($packageType, ['featured', 'story'], true) && ! $isSellerVerification) {
            $categoryId = null;
        }

        if ($isSellerVerification && $sellerTier === 'car_verified') {
            $categoryScope = 'specific';
            $categoryId = (int) Category::query()->where('slug', 'cars')->value('id');
        }

        $packageDurationType = (string) ($validated['package_duration_type'] ?? 'limited');
        $packageDurationDays = $packageDurationType === 'limited'
            ? (int) ($validated['package_duration_days'] ?? 0)
            : null;

        $itemLimitType = (string) ($validated['item_limit_type'] ?? 'limited');
        $itemLimitCount = $itemLimitType === 'limited'
            ? (int) ($validated['item_limit_count'] ?? 0)
            : null;

        $listingDurationType = (string) ($validated['listing_duration_type'] ?? 'standard');
        $listingDurationDays = $listingDurationType === 'custom'
            ? (int) ($validated['listing_duration_days'] ?? 0)
            : 30;

        $allowsAi = $request->boolean('allows_ai');
        $aiUsageLimitType = $allowsAi
            ? (string) ($validated['ai_usage_limit_type'] ?? 'limited')
            : 'limited';

        $aiUsageLimitCount = ($allowsAi && $aiUsageLimitType === 'limited')
            ? (int) ($validated['ai_usage_limit_count'] ?? 0)
            : null;

        return [
            'name' => $validated['name'],
            'package_type' => $packageType,
            'price' => number_format($price, 2, '.', ''),
            'discount_percent' => number_format($discountPercent, 2, '.', ''),
            'final_price' => number_format($finalPrice, 2, '.', ''),
            'package_duration_type' => $packageDurationType,
            'package_duration_days' => $packageDurationDays,
            'item_limit_type' => $itemLimitType,
            'item_limit_count' => $itemLimitCount,
            'listing_duration_type' => $listingDurationType,
            'listing_duration_days' => $listingDurationDays,
            'category_scope' => $categoryScope,
            'category_id' => $categoryId,
            'key_points' => $this->parseKeyPoints((string) ($validated['key_points'] ?? '')),
            'icon' => $existing?->icon,
            'allows_call' => $request->boolean('allows_call'),
            'allows_ai' => $allowsAi,
            'ai_usage_limit_type' => $aiUsageLimitType,
            'ai_usage_limit_count' => $aiUsageLimitCount,
            'is_active' => $request->boolean('is_active', true),
            'required_documents' => $isSellerVerification
                ? $this->parseKeyPoints((string) ($validated['required_documents'] ?? ''))
                : [],
            'is_seller_verification' => $isSellerVerification,
            'seller_tier' => $sellerTier,
            'seller_badge_label' => $isSellerVerification
                ? (trim((string) ($validated['seller_badge_label'] ?? '')) ?: null)
                : null,
        ];
    }

    private function calculateFinalPrice(float $price, float $discountPercent): float
    {
        $final = $price - (($price * $discountPercent) / 100);

        return max(0, round($final, 2));
    }

    private function parseKeyPoints(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }

        $parts = preg_split('/[\r\n,]+/', $raw) ?: [];
        $points = [];

        foreach ($parts as $part) {
            $point = trim((string) $part);

            if ($point === '') {
                continue;
            }

            $points[] = $point;
        }

        return array_values(array_unique($points));
    }

    private function deleteFileIfExists(?string $path): void
    {
        if (! $path) {
            return;
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
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
}
