<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="font-display text-3xl font-bold text-slate-900">Edit Listing</h1>
            <p class="text-sm text-slate-600">Changes will be re-submitted for moderation.</p>
        </div>
    </x-slot>

    <div class="grid gap-5 lg:grid-cols-3">
        <section class="space-y-4 lg:col-span-2">
            <form method="POST" action="{{ route('listings.update', $listing) }}" enctype="multipart/form-data" class="app-card space-y-4" x-data="listingEditForm()" x-init="init()">
                @csrf
                @method('PUT')

                <div>
                    <x-input-label for="title" value="Listing Title" />
                    <x-text-input id="title" name="title" type="text" class="mt-1 block w-full" :value="old('title', $listing->title)" required maxlength="140" />
                    <x-input-error :messages="$errors->get('title')" class="mt-2" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="category_id" value="Category" />
                        <select id="category_id" name="category_id" class="app-select mt-1" required>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected((string) old('category_id', $listing->category_id) === (string) $category->id)>{{ $category->display_name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('category_id')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="condition" value="Condition" />
                        <select id="condition" name="condition" class="app-select mt-1" required>
                            @foreach (['new' => 'New', 'used' => 'Used', 'refurbished' => 'Refurbished'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('condition', $listing->condition) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('condition')" class="mt-2" />
                    </div>
                </div>

                @include('listings.partials.custom-fields', [
                    'customFields' => $customFields,
                    'customFieldValues' => $customFieldValues,
                ])

                <div>
                    <x-input-label for="price" value="Price (INR)" />
                    <x-text-input id="price" name="price" type="number" min="1" step="0.01" class="mt-1 block w-full" :value="old('price', $listing->price)" required />
                    <x-input-error :messages="$errors->get('price')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="description" value="Description" />
                    <textarea id="description" name="description" class="app-textarea mt-1" required>{{ old('description', $listing->description) }}</textarea>
                    <x-input-error :messages="$errors->get('description')" class="mt-2" />
                </div>

                <div class="space-y-3 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div>
                        <h2 class="font-display text-lg font-bold text-slate-900">Location</h2>
                        <p class="mt-1 text-sm text-slate-500">Update the listing location using GPS detection or manual search.</p>
                    </div>

                    @include('listings.partials.location-selector')
                </div>

                @if ($listing->images->isNotEmpty())
                    <div class="space-y-2">
                        <p class="text-sm font-semibold text-slate-700">Existing Images</p>
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                            @foreach ($listing->images as $image)
                                <label class="relative overflow-hidden rounded-2xl border border-slate-200">
                                    <img src="{{ $image->url }}" alt="Listing image" class="h-28 w-full object-cover">
                                    <span class="absolute left-2 top-2 rounded-full bg-white/90 px-2 py-1 text-[11px] font-semibold text-slate-700">Remove</span>
                                    <input type="checkbox" name="remove_images[]" value="{{ $image->id }}" class="absolute right-2 top-2 h-5 w-5 rounded border-slate-300 text-rose-600 focus:ring-rose-300">
                                </label>
                            @endforeach
                        </div>
                        <x-input-error :messages="$errors->get('remove_images')" class="mt-2" />
                        <x-input-error :messages="$errors->get('remove_images.*')" class="mt-2" />
                    </div>
                @endif

                <div>
                    <x-input-label for="images" value="Add New Images" />
                    <input id="images" name="images[]" type="file" accept="image/*" multiple class="app-input mt-1">
                    <x-input-error :messages="$errors->get('images')" class="mt-2" />
                    <x-input-error :messages="$errors->get('images.*')" class="mt-2" />
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <x-primary-button>Save Changes</x-primary-button>
                    <a href="{{ route('listings.show', $listing) }}" class="app-btn-muted">Cancel</a>
                </div>
            </form>
        </section>

        <aside class="space-y-4">
            <div class="app-card bg-orange-50">
                <h3 class="font-display text-lg font-bold text-orange-700">Current Status</h3>
                <p class="mt-2 text-sm text-orange-700">{{ ucfirst($listing->status) }}</p>
                @if ($listing->rejection_reason)
                    <p class="mt-3 rounded-xl bg-white/80 p-3 text-sm text-rose-600">
                        {{ $listing->rejection_reason }}
                    </p>
                @endif
            </div>
            <div class="app-card">
                <h3 class="font-display text-lg font-bold text-slate-900">Performance</h3>
                <p class="mt-2 text-sm text-slate-600">Views: <span class="font-semibold text-slate-900">{{ $listing->views }}</span></p>
                <p class="mt-1 text-sm text-slate-600">Favorites: <span class="font-semibold text-slate-900">{{ $listing->favoritedBy()->count() }}</span></p>
            </div>
        </aside>
    </div>

    <script>
    @include('listings.partials.location-selector-script')

    function listingEditForm() {
        return {
            ...listingLocationSelector({
                initialCity: @js((string) old('city', $listing->city)),
                initialState: @js((string) old('state', $listing->state)),
                initialAddress: @js((string) old('address', $listing->address)),
                initialLatitude: @js((string) old('latitude', $listing->latitude)),
                initialLongitude: @js((string) old('longitude', $listing->longitude)),
                locationApi: @js([
                    'countries' => route('api.location.countries'),
                    'states' => route('api.location.states'),
                    'cities' => route('api.location.cities'),
                    'areas' => route('api.location.areas'),
                ]),
                defaultCountry: @js(strtoupper((string) setting('location_default_country', 'IN'))),
                mapsApiKey: @js(trim((string) setting('google_maps_api_key', ''))),
            }),

            init() {
                this.initLocationSelector();
            },
        };
    }
    </script>
</x-app-layout>
