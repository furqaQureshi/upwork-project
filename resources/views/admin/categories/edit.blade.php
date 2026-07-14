@extends('admin.layout')

@section('title', 'Edit Category')

@section('content')
    <section class="mx-auto max-w-2xl rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="font-display text-2xl font-bold text-slate-900">Edit {{ $category->name }}</h2>

        <form method="POST" action="{{ route('admin.categories.update', $category) }}" enctype="multipart/form-data" class="mt-5 space-y-4">
            @csrf
            @method('PUT')

            <div>
                <x-input-label for="parent_id" value="Parent Category (optional)" />
                <select id="parent_id" name="parent_id" class="app-select mt-1">
                    <option value="">None (Top-level category)</option>
                    @foreach ($parentCategories as $parentCategory)
                        <option value="{{ $parentCategory->id }}" @selected((string) old('parent_id', $category->parent_id) === (string) $parentCategory->id)>
                            {{ $parentCategory->name }}
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('parent_id')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="name" value="Name" />
                <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $category->name)" required />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="icon_file" value="Icon Upload (optional)" />
                <input id="icon_file" name="icon_file" type="file" accept="image/*" class="app-input mt-1">
                <x-input-error :messages="$errors->get('icon_file')" class="mt-2" />

                @if ($category->icon_url)
                    <div class="mt-3 flex items-center gap-3 rounded-xl border border-slate-200 p-2">
                        <img src="{{ $category->icon_url }}" alt="{{ $category->name }}" class="h-12 w-12 rounded object-cover">
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
                <x-text-input id="sort_order" name="sort_order" type="number" min="0" class="mt-1 block w-full" :value="old('sort_order', $category->sort_order)" />
                <x-input-error :messages="$errors->get('sort_order')" class="mt-2" />
            </div>

            <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" class="h-4 w-4 rounded border-slate-300 text-orange-500" @checked(old('is_active', $category->is_active))>
                Active category
            </label>

            <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                <input type="hidden" name="condition_enabled" value="0">
                <input type="checkbox" name="condition_enabled" value="1" class="h-4 w-4 rounded border-slate-300 text-orange-500" @checked(old('condition_enabled', $category->condition_enabled ?? true))>
                Enable Condition field (New / Used / Refurbished)
            </label>

            <div class="flex flex-wrap gap-2">
                <x-primary-button>Update Category</x-primary-button>
                <a href="{{ route('admin.categories.index') }}" class="app-btn-muted">Cancel</a>
            </div>
        </form>
    </section>
@endsection
