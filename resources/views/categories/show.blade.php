@php
    $activeCategory = $selectedSubcategory ?? $category;

    $locationState = array_merge([
        'country' => '',
        'state' => '',
        'city' => '',
        'area' => '',
        'latitude' => null,
        'longitude' => null,
    ], (array) ($locationFilter ?? []));

    $locationLabelParts = array_values(array_filter([
        $locationState['area'] ?? '',
        $locationState['city'] ?? '',
        $locationState['state'] ?? '',
    ], static fn ($part): bool => trim((string) $part) !== ''));

    $locationLabel = $locationLabelParts !== [] ? implode(', ', $locationLabelParts) : 'All locations';

    $hiddenLocationFields = array_filter([
        'country' => strtoupper((string) ($locationState['country'] ?? '')),
        'state' => trim((string) ($locationState['state'] ?? '')),
        'city' => trim((string) ($locationState['city'] ?? '')),
        'area' => trim((string) ($locationState['area'] ?? '')),
        'lat' => $locationState['latitude'],
        'lng' => $locationState['longitude'],
    ], static function ($value): bool {
        return $value !== null && trim((string) $value) !== '';
    });

    $allCategoryQuery = array_merge(
        ['category' => $category->slug],
        request()->except('page', 'category', 'subcategory', 'custom_filters')
    );

    $resetFilterQuery = array_merge(
        ['category' => $category->slug],
        $hiddenLocationFields,
        $selectedSubcategory ? ['subcategory' => $selectedSubcategory->id] : []
    );

    $hasAnySmartFilter = request()->filled('q')
        || request()->filled('condition')
        || request()->filled('min_price')
        || request()->filled('max_price')
        || (array) request()->input('custom_filters', []) !== [];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <span class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-orange-100 text-orange-600">
                @if ($activeCategory->icon_url)
                    <img src="{{ $activeCategory->icon_url }}" alt="{{ $activeCategory->name }}" class="h-full w-full object-cover">
                @else
                    <x-heroicon name="squares-2x2" class="h-6 w-6" />
                @endif
            </span>
            <div>
                <p class="text-xs font-black uppercase tracking-[0.14em] text-orange-600">Smart Category Browse</p>
                <h1 class="font-display text-2xl font-bold text-slate-900">{{ $activeCategory->name }}</h1>
                <p class="text-sm text-slate-600">Use category-aware filters to narrow listings in {{ $locationLabel }}.</p>
            </div>
        </div>
    </x-slot>

    <div class="space-y-5">
        @if ($category->children->isNotEmpty())
            <section class="flex flex-wrap gap-2">
                <a href="{{ route('categories.show', $allCategoryQuery) }}" class="inline-flex items-center rounded-full border px-4 py-2 text-sm font-semibold transition {{ $selectedSubcategory ? 'border-slate-200 bg-white text-slate-700 hover:border-orange-300 hover:text-orange-700' : 'border-orange-500 bg-orange-50 text-orange-700' }}">
                    All {{ $category->name }}
                </a>
                @foreach ($category->children as $child)
                    <a href="{{ route('categories.show', array_merge(['category' => $category->slug], request()->except('page', 'category', 'subcategory', 'custom_filters'), ['subcategory' => $child->id])) }}" class="inline-flex items-center rounded-full border px-4 py-2 text-sm font-semibold transition {{ (int) ($selectedSubcategory?->id ?? 0) === (int) $child->id ? 'border-orange-500 bg-orange-50 text-orange-700' : 'border-slate-200 bg-white text-slate-700 hover:border-orange-300 hover:text-orange-700' }}">
                        {{ $child->name }}
                    </a>
                @endforeach
            </section>
        @endif

        <div class="grid gap-5 lg:grid-cols-[20rem,minmax(0,1fr)]">
            <aside>
                <form method="GET" action="{{ route('categories.show', ['category' => $category->slug]) }}" class="space-y-4 rounded-[1.6rem] border border-slate-200 bg-white p-4 shadow-sm lg:sticky lg:top-24">
                    @foreach ($hiddenLocationFields as $hiddenKey => $hiddenValue)
                        <input type="hidden" name="{{ $hiddenKey }}" value="{{ $hiddenValue }}">
                    @endforeach

                    @if ($selectedSubcategory)
                        <input type="hidden" name="subcategory" value="{{ $selectedSubcategory->id }}">
                    @endif

                    <div class="space-y-1">
                        <p class="text-xs font-black uppercase tracking-[0.14em] text-orange-600">Smart Filters</p>
                        <h3 class="font-display text-xl font-bold text-slate-900">Refine {{ $activeCategory->name }}</h3>
                        <p class="text-sm text-slate-500">Search with pricing, condition, and relevant specifications.</p>
                    </div>

                    <div>
                        <label class="settings-label" for="browse_q">Search in this category</label>
                        <input id="browse_q" type="text" name="q" value="{{ request('q') }}" class="app-input mt-1" placeholder="Search by title or keyword">
                    </div>

                    @if ((bool) ($filterCategory->condition_enabled ?? true))
                        <div>
                            <label class="settings-label" for="browse_condition">Condition</label>
                            <select id="browse_condition" name="condition" class="app-select mt-1">
                                <option value="">Any condition</option>
                                @foreach (['new' => 'New', 'used' => 'Used', 'refurbished' => 'Refurbished'] as $conditionValue => $conditionLabel)
                                    <option value="{{ $conditionValue }}" @selected(request('condition') === $conditionValue)>{{ $conditionLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="settings-label" for="browse_min_price">Min Price</label>
                            <input id="browse_min_price" type="number" min="0" name="min_price" value="{{ request('min_price') }}" class="app-input mt-1" placeholder="0">
                        </div>
                        <div>
                            <label class="settings-label" for="browse_max_price">Max Price</label>
                            <input id="browse_max_price" type="number" min="0" name="max_price" value="{{ request('max_price') }}" class="app-input mt-1" placeholder="Any">
                        </div>
                    </div>

                    <div class="space-y-4 rounded-2xl border border-slate-200 bg-slate-50 p-3" data-smart-filters-root>
                        @if ($customFields->isNotEmpty())
                            @foreach ($customFields as $field)
                                @php
                                    $fieldId = (int) $field->id;
                                    $inputName = 'custom_filters['.$fieldId.']';
                                    $inputValue = $customFilters[$fieldId] ?? null;
                                    $isSubField = (bool) $field->parent_field_id;
                                    $rawOptions = (array) ($field->options ?? []);

                                    $nestedOptions = [];
                                    if ($isSubField) {
                                        foreach ($rawOptions as $parentValue => $children) {
                                            if (! is_array($children) || $children === []) {
                                                continue;
                                            }

                                            $parentKey = trim((string) $parentValue);
                                            if ($parentKey === '') {
                                                continue;
                                            }

                                            $childValues = array_values(array_filter(array_map(
                                                fn ($item) => is_scalar($item) ? trim((string) $item) : '',
                                                $children
                                            )));

                                            if ($childValues !== []) {
                                                $nestedOptions[$parentKey] = $childValues;
                                            }
                                        }
                                    }

                                    $flatOptions = [];
                                    foreach ($rawOptions as $optionKey => $optionValue) {
                                        if (is_array($optionValue)) {
                                            $fallback = trim((string) $optionKey);
                                            if ($fallback !== '') {
                                                $flatOptions[] = $fallback;
                                            }
                                            continue;
                                        }

                                        $optionText = is_scalar($optionValue) ? trim((string) $optionValue) : '';
                                        if ($optionText !== '') {
                                            $flatOptions[] = $optionText;
                                        }
                                    }
                                    $flatOptions = array_values(array_unique($flatOptions));
                                    $checkedValues = is_array($inputValue) ? array_map('strval', $inputValue) : [];
                                @endphp

                                <div class="space-y-2 {{ $isSubField ? 'hidden' : '' }}" data-smart-filter-wrapper @if ($isSubField) data-parent-field="{{ $field->parent_field_id }}" data-nested-options='@json($nestedOptions)' @endif>
                                    <label class="settings-label" for="smart_filter_field_{{ $fieldId }}">{{ $field->name }}</label>

                                    @if (in_array($field->field_type, ['dropdown', 'radio'], true))
                                        @if ($isSubField)
                                            <select id="smart_filter_field_{{ $fieldId }}" name="{{ $inputName }}" class="app-select" data-smart-subfield-select data-initial-value="{{ is_scalar($inputValue) ? (string) $inputValue : '' }}">
                                                <option value="">Select {{ $field->parentField?->name ?? 'parent option' }} first</option>
                                            </select>
                                        @else
                                            <select id="smart_filter_field_{{ $fieldId }}" name="{{ $inputName }}" class="app-select">
                                                <option value="">Any {{ strtolower($field->name) }}</option>
                                                @foreach ($flatOptions as $option)
                                                    <option value="{{ $option }}" @selected((string) $inputValue === (string) $option)>{{ $option }}</option>
                                                @endforeach
                                            </select>
                                        @endif
                                    @elseif ($field->field_type === 'checkbox')
                                        <div class="grid gap-2">
                                            @foreach ($flatOptions as $option)
                                                <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
                                                    <input type="checkbox" name="{{ $inputName }}[]" value="{{ $option }}" class="h-4 w-4 rounded border-slate-300 text-orange-500" @checked(in_array((string) $option, $checkedValues, true))>
                                                    {{ $option }}
                                                </label>
                                            @endforeach
                                        </div>
                                    @elseif ($field->field_type === 'number')
                                        <input id="smart_filter_field_{{ $fieldId }}" type="number" name="{{ $inputName }}" value="{{ is_scalar($inputValue) ? (string) $inputValue : '' }}" class="app-input" placeholder="Enter {{ strtolower($field->name) }}">
                                    @else
                                        <input id="smart_filter_field_{{ $fieldId }}" type="text" name="{{ $inputName }}" value="{{ is_scalar($inputValue) ? (string) $inputValue : '' }}" class="app-input" placeholder="Enter {{ strtolower($field->name) }}">
                                    @endif
                                </div>
                            @endforeach
                        @else
                            <p class="text-sm text-slate-500">No smart filters available for this category yet.</p>
                        @endif
                    </div>

                    <div class="flex flex-col gap-2 sm:flex-row lg:flex-col xl:flex-row">
                        <button type="submit" class="app-btn-primary flex-1 justify-center">Apply Filters</button>
                        <a href="{{ route('categories.show', $resetFilterQuery) }}" class="app-btn-muted flex-1 justify-center text-center">Reset</a>
                    </div>
                </form>
            </aside>

            <section class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-display text-2xl font-bold text-slate-900">Filtered Results</h2>
                        <p class="text-sm text-slate-500">Browsing {{ $activeCategory->display_name ?? $activeCategory->name }} in {{ $locationLabel }}.</p>
                    </div>

                    @if ($hasAnySmartFilter)
                        <a href="{{ route('categories.show', $resetFilterQuery) }}" class="app-btn-muted">Clear Filters</a>
                    @endif
                </div>

                @if ($listings->isEmpty())
                    <div class="app-card text-center">
                        <p class="text-slate-600">No listings match these smart filters yet. Try broadening the range or switching subcategory.</p>
                    </div>
                @else
                    <div class="home-feed-grid">
                        @foreach ($listings as $listing)
                            <div>
                                <x-listing-card :listing="$listing" :show-favorite-overlay="true" :is-favorited="in_array($listing->id, $favoriteListingIds, true)" />
                            </div>
                        @endforeach
                    </div>

                    {{ $listings->links() }}
                @endif
            </section>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const roots = document.querySelectorAll('[data-smart-filters-root]');

            const parseNestedOptions = function (raw) {
                if (!raw || typeof raw !== 'string') {
                    return {};
                }

                try {
                    const parsed = JSON.parse(raw);
                    return parsed && typeof parsed === 'object' ? parsed : {};
                } catch (_) {
                    return {};
                }
            };

            roots.forEach(function (root) {
                const wrappers = Array.from(root.querySelectorAll('[data-smart-filter-wrapper]'));

                const updateVisibility = function (wrapper) {
                    wrapper.classList.toggle('hidden', wrapper._parentVisible === false);
                };

                const rebuildSubOptions = function (wrapper, parentValue) {
                    const childSelect = wrapper.querySelector('[data-smart-subfield-select]');
                    if (!childSelect) {
                        return;
                    }

                    const nestedOptions = parseNestedOptions(wrapper.dataset.nestedOptions || '{}');
                    const savedValue = childSelect.dataset.initialValue || '';
                    const options = parentValue && Array.isArray(nestedOptions[parentValue]) ? nestedOptions[parentValue] : [];

                    childSelect.innerHTML = '';

                    const placeholder = document.createElement('option');
                    placeholder.value = '';
                    placeholder.textContent = 'Any option';
                    childSelect.appendChild(placeholder);

                    options.forEach(function (option) {
                        const optionElement = document.createElement('option');
                        optionElement.value = String(option);
                        optionElement.textContent = String(option);

                        if (String(option) === String(savedValue)) {
                            optionElement.selected = true;
                        }

                        childSelect.appendChild(optionElement);
                    });

                    childSelect.dataset.initialValue = '';
                };

                wrappers.forEach(function (wrapper) {
                    if (!wrapper.dataset.parentField) {
                        wrapper._parentVisible = true;
                        updateVisibility(wrapper);
                        return;
                    }

                    const parentSelect = document.getElementById('smart_filter_field_' + wrapper.dataset.parentField);
                    if (!parentSelect) {
                        wrapper._parentVisible = true;
                        updateVisibility(wrapper);
                        return;
                    }

                    const sync = function () {
                        rebuildSubOptions(wrapper, parentSelect.value);
                        wrapper._parentVisible = String(parentSelect.value || '').trim() !== '';
                        updateVisibility(wrapper);
                    };

                    sync();
                    parentSelect.addEventListener('change', sync);
                });
            });
        });
    </script>
</x-app-layout>
