@extends('admin.layout')

@section('title', 'Edit Custom Field')

@section('content')
    <section class="mx-auto max-w-3xl rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="font-display text-2xl font-bold text-slate-900">Edit Custom Field</h2>

        <form method="POST" action="{{ route('admin.custom-fields.update', $customField) }}" enctype="multipart/form-data" class="mt-5 space-y-4">
            @csrf
            @method('PUT')

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="category_id" value="Category or Subcategory" />
                    <select id="category_id" name="category_id" class="app-select mt-1" required>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) old('category_id', $customField->category_id) === (string) $category->id)>
                                {{ $category->display_name }}
                            </option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('category_id')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="field_type" value="Field Type" />
                    <select id="field_type" name="field_type" class="app-select mt-1" required>
                        @foreach ($types as $type)
                            <option value="{{ $type }}" @selected(old('field_type', $customField->field_type) === $type)>{{ strtoupper($type) }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('field_type')" class="mt-2" />
                </div>
            </div>

            <div>
                <x-input-label for="parent_field_id" value="Parent Field (optional — for dependent dropdown)" />
                <select id="parent_field_id" name="parent_field_id" class="app-select mt-1">
                    <option value="">None (independent field)</option>
                    @foreach ($parentFieldOptions as $pf)
                        <option value="{{ $pf->id }}"
                                data-category="{{ $pf->category_id }}"
                                @selected((string) old('parent_field_id', $customField->parent_field_id) === (string) $pf->id)>
                            {{ $pf->category?->name ?? '?' }} — {{ $pf->name }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-500">When set, options must be nested — one parent per line: <code class="bg-slate-100 px-1 rounded">ParentValue: Child1 | Child2 | Child3</code></p>
                <x-input-error :messages="$errors->get('parent_field_id')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="name" value="Field Name" />
                <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $customField->name)" required maxlength="120" />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="min_length" value="Field Length (Min)" />
                    <x-text-input id="min_length" name="min_length" type="number" min="0" class="mt-1 block w-full" :value="old('min_length', $customField->min_length)" />
                    <x-input-error :messages="$errors->get('min_length')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="max_length" value="Field Length (Max)" />
                    <x-text-input id="max_length" name="max_length" type="number" min="1" class="mt-1 block w-full" :value="old('max_length', $customField->max_length)" />
                    <x-input-error :messages="$errors->get('max_length')" class="mt-2" />
                </div>
            </div>

            <div>
                <x-input-label for="options" value="Options (for radio/dropdown/checkbox)" />
                @php
                    $optionsText = '';
                    if (old('options') !== null) {
                        $optionsText = old('options');
                    } elseif (! empty($customField->options)) {
                        $opts     = $customField->options;
                        $isNested = false;
                        foreach ($opts as $v) {
                            if (is_array($v)) { $isNested = true; break; }
                        }
                        if ($isNested) {
                            $lines = [];
                            foreach ($opts as $parent => $children) {
                                $lines[] = $parent . ': ' . implode(' | ', (array) $children);
                            }
                            $optionsText = implode("\n", $lines);
                        } else {
                            $optionsText = implode("\n", $opts);
                        }
                    }
                @endphp
                <textarea id="options" name="options" class="app-textarea mt-1" rows="6"
                    placeholder="One option per line. Sub-fields: ParentValue: Child1 | Child2">{{ $optionsText }}</textarea>
                <p class="mt-1 text-xs text-slate-500">For sub-fields use nested format: <code class="bg-slate-100 px-1 rounded">ParentValue: Child1 | Child2</code></p>
                <x-input-error :messages="$errors->get('options')" class="mt-2" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="icon_file" value="Icon Upload (optional)" />
                    <input id="icon_file" name="icon_file" type="file" accept="image/*" class="app-input mt-1">
                    <x-input-error :messages="$errors->get('icon_file')" class="mt-2" />

                    @if ($customField->icon_url)
                        <div class="mt-3 flex items-center gap-3 rounded-xl border border-slate-200 p-2">
                            <img src="{{ $customField->icon_url }}" alt="Custom field icon" class="h-12 w-12 rounded object-cover">
                            <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                                <input type="hidden" name="remove_icon" value="0">
                                <input type="checkbox" name="remove_icon" value="1" class="h-4 w-4 rounded border-slate-300 text-rose-600" @checked(old('remove_icon'))>
                                Remove current icon
                            </label>
                        </div>
                    @endif
                </div>

                <div>
                    <x-input-label for="sort_order" value="Sort Order" />
                    <x-text-input id="sort_order" name="sort_order" type="number" min="0" class="mt-1 block w-full" :value="old('sort_order', $customField->sort_order)" />
                    <x-input-error :messages="$errors->get('sort_order')" class="mt-2" />
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                    <input type="hidden" name="is_required" value="0">
                    <input type="checkbox" name="is_required" value="1" class="h-4 w-4 rounded border-slate-300 text-orange-500" @checked(old('is_required', $customField->is_required))>
                    Required field
                </label>

                <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" class="h-4 w-4 rounded border-slate-300 text-orange-500" @checked(old('is_active', $customField->is_active))>
                    Active field
                </label>
            </div>

            <div class="flex flex-wrap gap-2">
                <x-primary-button>Update Custom Field</x-primary-button>
                <a href="{{ route('admin.custom-fields.index') }}" class="app-btn-muted">Cancel</a>
            </div>
        </form>
    </section>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const catSel    = document.getElementById('category_id');
            const parentSel = document.getElementById('parent_field_id');
            if (!catSel || !parentSel) return;

            function filterParentFields() {
                const catId = String(catSel.value);
                Array.from(parentSel.options).forEach(function (opt) {
                    if (opt.value === '') return;
                    opt.hidden = (catId !== '' && opt.dataset.category !== catId);
                });
                const current = parentSel.querySelector('option:checked');
                if (current && current.hidden) parentSel.value = '';
            }

            catSel.addEventListener('change', filterParentFields);
            filterParentFields();
        });
    </script>
@endsection
