@php
    /** @var \App\Models\SubscriptionPackage|null $package */
    $editing = isset($package) && $package;
    $packageTypeValue = old('package_type', $package?->package_type ?? 'listing');
    $durationTypeValue = old('package_duration_type', $package?->package_duration_type ?? 'limited');
    $itemLimitTypeValue = old('item_limit_type', $package?->item_limit_type ?? 'limited');
    $listingDurationTypeValue = old('listing_duration_type', $package?->listing_duration_type ?? 'standard');
    $categoryScopeValue = old('category_scope', $package?->category_scope ?? 'global');
    $allowsCallValue = (bool) old('allows_call', $package?->allows_call ?? false);
    $allowsAiValue = (bool) old('allows_ai', $package?->allows_ai ?? false);
    $aiUsageLimitTypeValue = old('ai_usage_limit_type', $package?->ai_usage_limit_type ?? 'limited');
    $sellerVerificationValue = (bool) old('is_seller_verification', $package?->is_seller_verification ?? false);
    $sellerTierValue = old('seller_tier', $package?->seller_tier ?? 'verified');
    $requiredDocumentsValue = old('required_documents', is_array($package?->required_documents ?? null)
        ? implode(PHP_EOL, $package->required_documents)
        : '');

    $priceValue = (float) old('price', (float) ($package?->price ?? 0));
    $discountValue = (float) old('discount_percent', (float) ($package?->discount_percent ?? 0));
    $computedFinalPrice = max(0, round($priceValue - (($priceValue * $discountValue) / 100), 2));

    $keyPointsValue = old('key_points', is_array($package?->key_points ?? null)
        ? implode(PHP_EOL, $package->key_points)
        : '');
@endphp

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="space-y-5">
    @csrf
    @if ($editing)
        @method('PUT')
    @endif

    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
        <h3 class="font-display text-lg font-bold text-slate-900">Package Basics</h3>

        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div class="sm:col-span-2">
                <label class="settings-label" for="name">Package Name</label>
                <input id="name" name="name" type="text" class="app-input mt-1" value="{{ old('name', $package?->name) }}" maxlength="120" required>
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div>
                <label class="settings-label" for="package_type">Package Type</label>
                <select id="package_type" name="package_type" class="app-select mt-1" required>
                    <option value="listing" @selected($packageTypeValue === 'listing')>Ad Listing Package</option>
                    <option value="featured" @selected($packageTypeValue === 'featured')>Featured Ads Package</option>
                    <option value="story" @selected($packageTypeValue === 'story')>Verified Stories Package</option>
                </select>
                <x-input-error :messages="$errors->get('package_type')" class="mt-2" />
            </div>

            <div>
                <label class="settings-label" for="price">Price</label>
                <input id="price" name="price" type="number" min="0" step="0.01" class="app-input mt-1" value="{{ old('price', $package?->price ?? 0) }}" required>
                <x-input-error :messages="$errors->get('price')" class="mt-2" />
            </div>

            <div>
                <label class="settings-label" for="discount_percent">Discount (%)</label>
                <input id="discount_percent" name="discount_percent" type="number" min="0" max="100" step="0.01" class="app-input mt-1" value="{{ old('discount_percent', $package?->discount_percent ?? 0) }}">
                <x-input-error :messages="$errors->get('discount_percent')" class="mt-2" />
            </div>

            <div>
                <label class="settings-label" for="final_price_preview">Final Price (Rs)</label>
                <input id="final_price_preview" type="text" class="app-input mt-1 bg-slate-50" value="{{ number_format($computedFinalPrice, 2) }}" readonly>
            </div>

            <div>
                <label class="settings-label" for="package_duration_type">Package Duration Type</label>
                <select id="package_duration_type" name="package_duration_type" class="app-select mt-1" required>
                    <option value="limited" @selected($durationTypeValue === 'limited')>Limited</option>
                    <option value="unlimited" @selected($durationTypeValue === 'unlimited')>Unlimited</option>
                </select>
                <x-input-error :messages="$errors->get('package_duration_type')" class="mt-2" />
            </div>

            <div>
                <label class="settings-label" for="package_duration_days">Package Duration (days)</label>
                <input id="package_duration_days" name="package_duration_days" type="number" min="1" max="3650" class="app-input mt-1" value="{{ old('package_duration_days', $package?->package_duration_days) }}">
                <x-input-error :messages="$errors->get('package_duration_days')" class="mt-2" />
            </div>
        </div>
    </section>

    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
        <h3 class="font-display text-lg font-bold text-slate-900">Package Settings</h3>

        <div class="mt-2 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
            <p data-package-type-note-listing class="{{ $packageTypeValue === 'listing' ? '' : 'hidden' }}">Ad Listing Package Settings: item limit, listing duration, category scope, key points, call access, AI usage access, and icon.</p>
            <p data-package-type-note-featured class="{{ $packageTypeValue === 'featured' ? '' : 'hidden' }}">Ad Featured Package Settings: item limit, listing duration, key points, call access, AI usage access, and icon. Category is global for all categories.</p>
            <p data-package-type-note-story class="{{ $packageTypeValue === 'story' ? '' : 'hidden' }}">Verified Stories Package Settings: use this package type to unlock verified seller stories in app. Category is global and optimized for quick conversion.</p>
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <label class="settings-label" for="item_limit_type">Item Limit</label>
                <select id="item_limit_type" name="item_limit_type" class="app-select mt-1" required>
                    <option value="limited" @selected($itemLimitTypeValue === 'limited')>Limited</option>
                    <option value="unlimited" @selected($itemLimitTypeValue === 'unlimited')>Unlimited</option>
                </select>
                <x-input-error :messages="$errors->get('item_limit_type')" class="mt-2" />
            </div>

            <div>
                <label class="settings-label" for="item_limit_count">Item Limit Count</label>
                <input id="item_limit_count" name="item_limit_count" type="number" min="1" max="1000000" class="app-input mt-1" value="{{ old('item_limit_count', $package?->item_limit_count) }}">
                <x-input-error :messages="$errors->get('item_limit_count')" class="mt-2" />
            </div>

            <div>
                <label class="settings-label" for="listing_duration_type">Listing Duration Type</label>
                <select id="listing_duration_type" name="listing_duration_type" class="app-select mt-1" required>
                    <option value="standard" @selected($listingDurationTypeValue === 'standard')>Standard 30 days</option>
                    <option value="custom" @selected($listingDurationTypeValue === 'custom')>Custom</option>
                </select>
                <x-input-error :messages="$errors->get('listing_duration_type')" class="mt-2" />
            </div>

            <div>
                <label class="settings-label" for="listing_duration_days">Custom Listing Duration (days)</label>
                <input id="listing_duration_days" name="listing_duration_days" type="number" min="1" max="3650" class="app-input mt-1" value="{{ old('listing_duration_days', $package?->listing_duration_days) }}">
                <x-input-error :messages="$errors->get('listing_duration_days')" class="mt-2" />
            </div>

            <div data-listing-only class="{{ $packageTypeValue === 'listing' ? '' : 'hidden' }}">
                <label class="settings-label" for="category_scope">Category Scope (Listing)</label>
                <select id="category_scope" name="category_scope" class="app-select mt-1">
                    <option value="global" @selected($categoryScopeValue === 'global')>Global (All Categories)</option>
                    <option value="specific" @selected($categoryScopeValue === 'specific')>Specific Category</option>
                </select>
                <x-input-error :messages="$errors->get('category_scope')" class="mt-2" />
            </div>

            <div data-listing-category class="{{ $packageTypeValue === 'listing' && $categoryScopeValue === 'specific' ? '' : 'hidden' }}">
                <label class="settings-label" for="category_id">Category</label>
                <select id="category_id" name="category_id" class="app-select mt-1">
                    <option value="">Select category</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) old('category_id', $package?->category_id) === (string) $category->id)>{{ $category->display_name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('category_id')" class="mt-2" />
            </div>

            <div class="sm:col-span-2 lg:col-span-3 rounded-2xl border border-slate-200 p-4">
                <label class="settings-label" for="is_seller_verification">Seller Verification Workflow</label>
                <div class="mt-2 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <input type="hidden" name="is_seller_verification" value="0">
                        <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                            <input id="is_seller_verification" type="checkbox" name="is_seller_verification" value="1" class="rounded accent-orange-500" @checked($sellerVerificationValue)>
                            Seller verification package
                        </label>
                    </div>
                    <div>
                        <label class="settings-label" for="seller_tier">Seller Tier</label>
                        <select id="seller_tier" name="seller_tier" class="app-select mt-1">
                            <option value="verified" @selected($sellerTierValue === 'verified')>Verified Seller</option>
                            <option value="car_verified" @selected($sellerTierValue === 'car_verified')>Car Seller</option>
                            <option value="premium_verified" @selected($sellerTierValue === 'premium_verified')>Premium Seller</option>
                        </select>
                        <x-input-error :messages="$errors->get('seller_tier')" class="mt-2" />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="settings-label" for="seller_badge_label">Badge Label</label>
                        <input id="seller_badge_label" name="seller_badge_label" type="text" class="app-input mt-1" value="{{ old('seller_badge_label', $package?->seller_badge_label) }}" maxlength="120" placeholder="PREMIUM SELLER">
                        <x-input-error :messages="$errors->get('seller_badge_label')" class="mt-2" />
                    </div>
                </div>
                <div class="mt-4">
                    <label class="settings-label" for="required_documents">Required Documents</label>
                    <textarea id="required_documents" name="required_documents" class="app-textarea mt-1" rows="3" placeholder="One document per line">{{ $requiredDocumentsValue }}</textarea>
                    <p class="mt-2 text-xs text-slate-500">Example: company_certificate, aadhar, pan, gst_certificate, rc_book.</p>
                    <x-input-error :messages="$errors->get('required_documents')" class="mt-2" />
                </div>
            </div>
        </div>

        <div class="mt-4">
            <label class="settings-label" for="key_points">Key Points (multiple)</label>
            <textarea id="key_points" name="key_points" class="app-textarea mt-1" rows="4" placeholder="One point per line or comma separated">{{ $keyPointsValue }}</textarea>
            <x-input-error :messages="$errors->get('key_points')" class="mt-2" />
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <label class="settings-label" for="allows_call">Call Access</label>
                <div class="mt-1 rounded-xl border border-slate-200 px-3 py-3">
                    <input type="hidden" name="allows_call" value="0">
                    <label class="inline-flex items-start gap-3 text-sm font-semibold text-slate-700">
                        <input id="allows_call" type="checkbox" name="allows_call" value="1" class="mt-0.5 rounded accent-orange-500" @checked($allowsCallValue)>
                        <span>
                            <span class="block">Enable direct seller calls</span>
                            <span class="mt-1 block text-xs font-medium text-slate-500">Buyers with an active purchase of this package can use the Call action on listings.</span>
                        </span>
                    </label>
                </div>
                <x-input-error :messages="$errors->get('allows_call')" class="mt-2" />
            </div>

            <div>
                <label class="settings-label" for="allows_ai">AI Access</label>
                <div class="mt-1 rounded-xl border border-slate-200 px-3 py-3">
                    <input type="hidden" name="allows_ai" value="0">
                    <label class="inline-flex items-start gap-3 text-sm font-semibold text-slate-700">
                        <input id="allows_ai" type="checkbox" name="allows_ai" value="1" class="mt-0.5 rounded accent-orange-500" @checked($allowsAiValue)>
                        <span>
                            <span class="block">Enable AI tools in this package</span>
                            <span class="mt-1 block text-xs font-medium text-slate-500">Users with an active purchase can consume AI credits for AI assistant, pricing, CompassGPT, and CV matching.</span>
                        </span>
                    </label>
                </div>
                <x-input-error :messages="$errors->get('allows_ai')" class="mt-2" />
            </div>

            <div data-ai-settings class="{{ $allowsAiValue ? '' : 'hidden' }}">
                <label class="settings-label" for="ai_usage_limit_type">AI Usage Limit</label>
                <select id="ai_usage_limit_type" name="ai_usage_limit_type" class="app-select mt-1">
                    <option value="limited" @selected($aiUsageLimitTypeValue === 'limited')>Limited</option>
                    <option value="unlimited" @selected($aiUsageLimitTypeValue === 'unlimited')>Unlimited</option>
                </select>
                <x-input-error :messages="$errors->get('ai_usage_limit_type')" class="mt-2" />
            </div>

            <div data-ai-limit-count class="{{ $allowsAiValue && $aiUsageLimitTypeValue === 'limited' ? '' : 'hidden' }}">
                <label class="settings-label" for="ai_usage_limit_count">AI Usage Count</label>
                <input id="ai_usage_limit_count" name="ai_usage_limit_count" type="number" min="1" max="1000000" class="app-input mt-1" value="{{ old('ai_usage_limit_count', $package?->ai_usage_limit_count) }}">
                <x-input-error :messages="$errors->get('ai_usage_limit_count')" class="mt-2" />
            </div>

            <div>
                <label class="settings-label" for="icon_file">Icon</label>
                <input id="icon_file" name="icon_file" type="file" accept="image/*" class="app-input mt-1">
                <x-input-error :messages="$errors->get('icon_file')" class="mt-2" />

                @if ($editing && $package->icon_url)
                    <div class="mt-3 flex items-center gap-3 rounded-xl border border-slate-200 p-2">
                        <img src="{{ $package->icon_url }}" alt="{{ $package->name }}" class="h-12 w-12 rounded object-cover">
                        <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                            <input type="hidden" name="remove_icon" value="0">
                            <input type="checkbox" name="remove_icon" value="1" class="h-4 w-4 rounded border-slate-300 text-rose-600" @checked(old('remove_icon'))>
                            Remove icon
                        </label>
                    </div>
                @endif
            </div>

            <div>
                <label class="settings-label" for="is_active">Package Status</label>
                <div class="mt-1 rounded-xl border border-slate-200 px-3 py-2">
                    <input type="hidden" name="is_active" value="0">
                    <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                        <input id="is_active" type="checkbox" name="is_active" value="1" class="rounded accent-orange-500" @checked(old('is_active', $package?->is_active ?? true))>
                        Active package
                    </label>
                </div>
            </div>
        </div>
    </section>

    <div class="flex flex-wrap gap-2">
        <button type="submit" class="app-btn-primary">{{ $submitLabel }}</button>
        <a href="{{ route('admin.subscription-packages.index') }}" class="app-btn-muted">Cancel</a>
    </div>
</form>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const packageType = document.getElementById('package_type');
        const categoryScope = document.getElementById('category_scope');
        const listingOnlySections = document.querySelectorAll('[data-listing-only]');
        const listingCategorySections = document.querySelectorAll('[data-listing-category]');
        const listingNote = document.querySelector('[data-package-type-note-listing]');
        const featuredNote = document.querySelector('[data-package-type-note-featured]');
        const storyNote = document.querySelector('[data-package-type-note-story]');

        const durationType = document.getElementById('package_duration_type');
        const durationDays = document.getElementById('package_duration_days');
        const itemLimitType = document.getElementById('item_limit_type');
        const itemLimitCount = document.getElementById('item_limit_count');
        const listingDurationType = document.getElementById('listing_duration_type');
        const listingDurationDays = document.getElementById('listing_duration_days');
        const allowsAi = document.getElementById('allows_ai');
        const aiUsageLimitType = document.getElementById('ai_usage_limit_type');
        const aiUsageLimitCount = document.getElementById('ai_usage_limit_count');
        const aiSettingsSections = document.querySelectorAll('[data-ai-settings]');
        const aiLimitCountSections = document.querySelectorAll('[data-ai-limit-count]');

        const price = document.getElementById('price');
        const discount = document.getElementById('discount_percent');
        const finalPricePreview = document.getElementById('final_price_preview');

        const setVisibility = function (element, visible) {
            if (!element) {
                return;
            }

            element.classList.toggle('hidden', !visible);
        };

        const syncTypeState = function () {
            const isListing = packageType && packageType.value === 'listing';
            const isFeatured = packageType && packageType.value === 'featured';
            const isStory = packageType && packageType.value === 'story';

            listingOnlySections.forEach(function (section) {
                setVisibility(section, isListing);
            });

            if (!isListing && categoryScope) {
                categoryScope.value = 'global';
            }

            const showCategorySelect = isListing && categoryScope && categoryScope.value === 'specific';
            listingCategorySections.forEach(function (section) {
                setVisibility(section, showCategorySelect);
            });

            setVisibility(listingNote, isListing);
            setVisibility(featuredNote, isFeatured);
            setVisibility(storyNote, isStory);
        };

        const syncDurationState = function () {
            if (!durationType || !durationDays) {
                return;
            }

            const limited = durationType.value === 'limited';
            durationDays.disabled = !limited;
            durationDays.required = limited;

            if (!limited) {
                durationDays.value = '';
            }
        };

        const syncItemLimitState = function () {
            if (!itemLimitType || !itemLimitCount) {
                return;
            }

            const limited = itemLimitType.value === 'limited';
            itemLimitCount.disabled = !limited;
            itemLimitCount.required = limited;

            if (!limited) {
                itemLimitCount.value = '';
            }
        };

        const syncListingDurationState = function () {
            if (!listingDurationType || !listingDurationDays) {
                return;
            }

            const custom = listingDurationType.value === 'custom';
            listingDurationDays.disabled = !custom;
            listingDurationDays.required = custom;

            if (!custom) {
                listingDurationDays.value = '30';
            }
        };

        const syncAiUsageState = function () {
            if (!allowsAi) {
                return;
            }

            const enabled = allowsAi.checked;

            aiSettingsSections.forEach(function (section) {
                setVisibility(section, enabled);
            });

            if (!aiUsageLimitType || !aiUsageLimitCount) {
                return;
            }

            aiUsageLimitType.disabled = !enabled;
            aiUsageLimitType.required = enabled;

            const limited = enabled && aiUsageLimitType.value === 'limited';

            aiLimitCountSections.forEach(function (section) {
                setVisibility(section, limited);
            });

            aiUsageLimitCount.disabled = !limited;
            aiUsageLimitCount.required = limited;

            if (!enabled) {
                aiUsageLimitCount.value = '';
            }
        };

        const syncFinalPrice = function () {
            if (!price || !discount || !finalPricePreview) {
                return;
            }

            const priceValue = parseFloat(price.value || '0');
            const discountValue = parseFloat(discount.value || '0');

            const safePrice = Number.isFinite(priceValue) ? priceValue : 0;
            const safeDiscount = Number.isFinite(discountValue) ? discountValue : 0;

            const computed = Math.max(0, safePrice - ((safePrice * safeDiscount) / 100));
            finalPricePreview.value = computed.toFixed(2);
        };

        if (packageType) {
            packageType.addEventListener('change', syncTypeState);
        }

        if (categoryScope) {
            categoryScope.addEventListener('change', syncTypeState);
        }

        if (durationType) {
            durationType.addEventListener('change', syncDurationState);
        }

        if (itemLimitType) {
            itemLimitType.addEventListener('change', syncItemLimitState);
        }

        if (listingDurationType) {
            listingDurationType.addEventListener('change', syncListingDurationState);
        }

        if (allowsAi) {
            allowsAi.addEventListener('change', syncAiUsageState);
        }

        if (aiUsageLimitType) {
            aiUsageLimitType.addEventListener('change', syncAiUsageState);
        }

        if (price) {
            price.addEventListener('input', syncFinalPrice);
        }

        if (discount) {
            discount.addEventListener('input', syncFinalPrice);
        }

        syncTypeState();
        syncDurationState();
        syncItemLimitState();
        syncListingDurationState();
        syncAiUsageState();
        syncFinalPrice();
    });
</script>
