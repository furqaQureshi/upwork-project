@php
    $customFieldValues = $customFieldValues ?? collect();
@endphp

@if ($customFields->isNotEmpty())
    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 space-y-4" data-custom-fields-root>
        <div>
            <h3 class="font-display text-lg font-bold text-slate-900">Additional Details</h3>
            <p class="text-xs text-slate-500">Fields are loaded automatically based on selected category or subcategory.</p>
        </div>

        @foreach ($customFields as $field)
            @php
                $fieldId = (int) $field->id;
                $inputName = 'custom_fields['.$fieldId.']';
                $inputErrorKey = 'custom_fields.'.$fieldId;
                $inputValue = old($inputErrorKey);
                $existingValue = $customFieldValues->get($fieldId);

                if ($inputValue === null) {
                    $inputValue = $field->field_type === 'checkbox'
                        ? ($existingValue?->value_json ?? [])
                        : ($field->field_type === 'number'
                            ? $existingValue?->value_number
                            : $existingValue?->value_text);
                }

                $appliesTo = (array) ($field->applies_to_category_ids ?? [$field->category_id]);
                $appliesTo = array_values(array_unique(array_map('intval', $appliesTo)));
                $categoryCsv = implode(',', $appliesTo);
            @endphp

            @php
                $isSubField    = (bool) $field->parent_field_id;
                $rawOptions    = (array) ($field->options ?? []);

                $nestedOptions = [];
                if ($isSubField) {
                    foreach ($rawOptions as $parent => $children) {
                        if (! is_array($children) || $children === []) {
                            continue;
                        }

                        $parentKey = trim((string) $parent);
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

                // Flat options used by non-dependent inputs, also guards against malformed nested data.
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
            @endphp
            <div class="space-y-2 hidden"
                 data-custom-field-wrapper
                 data-categories="{{ $categoryCsv }}"
                 @if ($isSubField)
                     data-parent-field="{{ $field->parent_field_id }}"
                     data-nested-options='@json($nestedOptions)'
                 @endif>
                <label class="settings-label flex items-center gap-2" for="custom_field_{{ $fieldId }}">
                    @if ($field->icon_url)
                        <img src="{{ $field->icon_url }}" alt="{{ $field->name }}" class="h-5 w-5 rounded object-cover">
                    @endif
                    <span>{{ $field->name }}</span>
                    @if ($field->is_required)
                        <span class="text-rose-600">*</span>
                    @endif
                </label>

                @if ($field->field_type === 'text')
                    <input
                        id="custom_field_{{ $fieldId }}"
                        name="{{ $inputName }}"
                        type="text"
                        class="app-input"
                        value="{{ is_scalar($inputValue) ? (string) $inputValue : '' }}"
                        @if ($field->min_length !== null) minlength="{{ $field->min_length }}" @endif
                        @if ($field->max_length !== null) maxlength="{{ $field->max_length }}" @endif
                        data-custom-required="{{ $field->is_required ? '1' : '0' }}"
                    >
                @elseif ($field->field_type === 'number')
                    <input
                        id="custom_field_{{ $fieldId }}"
                        name="{{ $inputName }}"
                        type="number"
                        class="app-input"
                        value="{{ is_scalar($inputValue) ? (string) $inputValue : '' }}"
                        data-custom-required="{{ $field->is_required ? '1' : '0' }}"
                    >
                @elseif ($field->field_type === 'file')
                    <input
                        id="custom_field_{{ $fieldId }}"
                        name="{{ $inputName }}"
                        type="file"
                        class="app-input"
                        data-custom-required="{{ $field->is_required && ! $existingValue?->value_text ? '1' : '0' }}"
                    >

                    @if ($existingValue?->value_text)
                        <div class="flex flex-wrap items-center gap-3 text-xs text-slate-600">
                            <a href="{{ \Illuminate\Support\Facades\Storage::url($existingValue->value_text) }}" target="_blank" rel="noopener" class="font-semibold text-orange-600 underline">View uploaded file</a>
                            <label class="inline-flex items-center gap-2 font-semibold">
                                <input type="hidden" name="custom_fields_remove[{{ $fieldId }}]" value="0">
                                <input type="checkbox" name="custom_fields_remove[{{ $fieldId }}]" value="1" class="h-4 w-4 rounded border-slate-300 text-rose-600" @checked(old('custom_fields_remove.'.$fieldId))>
                                Remove file
                            </label>
                        </div>
                    @endif
                @elseif ($field->field_type === 'radio')
                    <div id="custom_field_{{ $fieldId }}" class="grid gap-2 sm:grid-cols-2">
                        @foreach ($flatOptions as $option)
                            <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
                                <input
                                    type="radio"
                                    name="{{ $inputName }}"
                                    value="{{ $option }}"
                                    class="rounded border-slate-300 text-orange-500"
                                    @checked((string) $inputValue === (string) $option)
                                >
                                {{ $option }}
                            </label>
                        @endforeach
                    </div>
                @elseif ($field->field_type === 'dropdown')
                    @if ($isSubField)
                        {{-- Sub-field: options rebuilt dynamically by JS based on parent value --}}
                        <select
                            id="custom_field_{{ $fieldId }}"
                            name="{{ $inputName }}"
                            class="app-select"
                            data-custom-required="{{ $field->is_required ? '1' : '0' }}"
                            data-subfield-select
                            data-initial-value="{{ is_scalar($inputValue) ? (string) $inputValue : '' }}"
                        >
                            <option value="">Select {{ $field->parentField?->name ?? 'parent option' }} first</option>
                        </select>
                    @else
                        <select
                            id="custom_field_{{ $fieldId }}"
                            name="{{ $inputName }}"
                            class="app-select"
                            data-custom-required="{{ $field->is_required ? '1' : '0' }}"
                        >
                            <option value="">Select option</option>
                            @foreach ($flatOptions as $option)
                                <option value="{{ $option }}" @selected((string) $inputValue === (string) $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    @endif
                @elseif ($field->field_type === 'checkbox')
                    @php
                        $checkedValues = is_array($inputValue) ? $inputValue : [];
                    @endphp
                    <div id="custom_field_{{ $fieldId }}" class="grid gap-2 sm:grid-cols-2">
                        @foreach ($flatOptions as $option)
                            <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
                                <input
                                    type="checkbox"
                                    name="{{ $inputName }}[]"
                                    value="{{ $option }}"
                                    class="h-4 w-4 rounded border-slate-300 text-orange-500"
                                    @checked(in_array((string) $option, array_map('strval', $checkedValues), true))
                                >
                                {{ $option }}
                            </label>
                        @endforeach
                    </div>
                @endif

                <x-input-error :messages="$errors->get($inputErrorKey)" class="mt-2" />
                <x-input-error :messages="$errors->get($inputErrorKey.'.*')" class="mt-2" />
            </div>
        @endforeach

        <p class="hidden text-xs font-semibold text-slate-500" data-custom-fields-empty>
            No custom fields configured for the selected category.
        </p>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const root = document.querySelector('[data-custom-fields-root]');
            const categorySelect = document.getElementById('category_id');

            if (!root || !categorySelect) {
                return;
            }

            const wrappers = Array.from(root.querySelectorAll('[data-custom-field-wrapper]'));
            const emptyState = root.querySelector('[data-custom-fields-empty]');

            // ---------- category-based visibility ----------
            const syncVisibility = function () {
                const selectedCategory = Number(categorySelect.value || 0);
                let visibleCount = 0;

                wrappers.forEach(function (wrapper) {
                    const categories = (wrapper.dataset.categories || '')
                        .split(',')
                        .map(function (value) {
                            return Number(value.trim());
                        })
                        .filter(function (value) {
                            return Number.isFinite(value) && value > 0;
                        });

                    const shouldShow = selectedCategory > 0 && categories.includes(selectedCategory);
                    wrapper._catVisible = shouldShow;
                    updateWrapperVisibility(wrapper);

                    const requiredInputs = wrapper.querySelectorAll('[data-custom-required]');
                    requiredInputs.forEach(function (input) {
                        input.required = shouldShow && input.dataset.customRequired === '1';
                    });

                    if (shouldShow) {
                        visibleCount += 1;
                    }
                });

                if (emptyState) {
                    emptyState.classList.toggle('hidden', !(selectedCategory > 0 && visibleCount === 0));
                }

                // After category sync, re-evaluate sub-field visibility
                syncSubFieldVisibility();
            };

            function updateWrapperVisibility(wrapper) {
                const catOk    = wrapper._catVisible    !== false;
                const parentOk = wrapper._parentVisible !== false;
                wrapper.classList.toggle('hidden', !(catOk && parentOk));
            }

            // ---------- sub-field (dependent dropdown) logic ----------
            function syncSubFieldVisibility() {
                wrappers.forEach(function (wrapper) {
                    if (! wrapper.dataset.parentField) return;

                    const pfId         = wrapper.dataset.parentField;
                    const parentSelect = document.getElementById('custom_field_' + pfId);
                    if (! parentSelect) return;

                    const parentWrapper = parentSelect.closest('[data-custom-field-wrapper]');
                    const parentCatOk   = parentWrapper ? (parentWrapper._catVisible !== false) : true;
                    const hasValue      = parentSelect.value !== '';

                    wrapper._parentVisible = parentCatOk && hasValue;
                    updateWrapperVisibility(wrapper);

                    if (! wrapper._parentVisible) {
                        // Remove required when hidden
                        wrapper.querySelectorAll('[data-custom-required]').forEach(function (inp) {
                            inp.required = false;
                        });
                    }
                });
            }

            function parseNestedOptions(raw) {
                if (!raw || typeof raw !== 'string') {
                    return {};
                }

                const tryParse = function (input) {
                    try {
                        const parsed = JSON.parse(input);
                        return parsed && typeof parsed === 'object' ? parsed : {};
                    } catch (_) {
                        return null;
                    }
                };

                const direct = tryParse(raw);
                if (direct !== null) {
                    return direct;
                }

                const decoded = raw
                    .replace(/&quot;|&#34;/g, '"')
                    .replace(/&apos;|&#39;|&#039;/g, "'")
                    .replace(/&amp;/g, '&')
                    .replace(/&lt;/g, '<')
                    .replace(/&gt;/g, '>');

                const entityDecoded = tryParse(decoded);
                if (entityDecoded !== null) {
                    return entityDecoded;
                }

                console.warn('Invalid nested options JSON for custom field:', raw);
                return {};
            }

            // Rebuild options for a sub-field dropdown given the parent's chosen value
            function rebuildSubOptions(wrapper, parentValue) {
                const childSelect = wrapper.querySelector('[data-subfield-select]');
                if (! childSelect) return;

                const nestedOptions = parseNestedOptions(wrapper.dataset.nestedOptions || '{}');
                const savedValue    = childSelect.dataset.initialValue || '';
                const rawOpts       = (parentValue && nestedOptions[parentValue]) ? nestedOptions[parentValue] : [];
                const opts          = Array.isArray(rawOpts) ? rawOpts : [];

                childSelect.innerHTML = '';
                const placeholder = document.createElement('option');
                placeholder.value   = '';
                placeholder.textContent = 'Select option';
                childSelect.appendChild(placeholder);

                opts.forEach(function (opt) {
                    const el         = document.createElement('option');
                    el.value         = String(opt);
                    el.textContent   = String(opt);
                    if (String(opt) === String(savedValue)) el.selected = true;
                    childSelect.appendChild(el);
                });

                // Consume the saved initial value once
                childSelect.dataset.initialValue = '';
            }

            // Wire up parent field change listeners for each sub-field wrapper
            wrappers.forEach(function (wrapper) {
                if (! wrapper.dataset.parentField) {
                    wrapper._parentVisible = true;
                    return;
                }

                const pfId         = wrapper.dataset.parentField;
                const parentSelect = document.getElementById('custom_field_' + pfId);
                if (! parentSelect) { wrapper._parentVisible = true; return; }

                // Initial rebuild on page load
                rebuildSubOptions(wrapper, parentSelect.value);

                parentSelect.addEventListener('change', function () {
                    rebuildSubOptions(wrapper, this.value);
                    syncSubFieldVisibility();
                    // Update required state for this wrapper's inputs
                    const isVisible = wrapper._parentVisible !== false && wrapper._catVisible !== false;
                    wrapper.querySelectorAll('[data-custom-required]').forEach(function (inp) {
                        inp.required = isVisible && inp.dataset.customRequired === '1';
                    });
                });
            });

            categorySelect.addEventListener('input', syncVisibility);
            categorySelect.addEventListener('change', syncVisibility);
            syncVisibility();
        });
    </script>
@endif
