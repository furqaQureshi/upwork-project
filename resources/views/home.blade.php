<x-app-layout>
    <div class="-mt-4 space-y-4 sm:mt-0 sm:space-y-5" x-data="homeMarketplace({
        locationApi: @js($locationApiEndpoints ?? []),
        defaultCountry: @js(strtoupper((string) ($locationDefaultCountry ?? 'IN'))),
        initialLocation: @js($locationState),
        initialLocationLabel: @js($locationLabel),
        initialSearchQuery: @js((string) request('q', '')),
        mapsApiKey: @js($googleMapsApiKey ?? ''),
        nearbyRadiusKm: @js((int) ($nearbyRadiusKm ?? 30)),
        bannerMode: @js($resolvedHomeBannerMode),
        bannerSlideCount: @js($homeBannerSlideCount),
        bannerDisplayMs: @js($homeBannerDisplaySeconds * 1000),
        nextPageUrl: @js($listings->nextPageUrl())
    })" x-init="init()">
        <section
            x-cloak
            x-show="showLocationPrompt"
            x-transition.opacity
            class="fixed inset-0 z-[70] flex items-end bg-slate-950/60 p-0 sm:items-center sm:justify-center sm:p-6"
        >
            <div class="w-full max-h-[88vh] overflow-y-auto rounded-t-3xl bg-white p-4 shadow-2xl sm:max-w-2xl sm:rounded-3xl sm:p-6">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-display text-xl font-bold text-slate-900">Allow location to discover nearby ads</h2>
                        <p class="mt-1 text-sm text-slate-600">We show listings within <span class="font-semibold" x-text="nearbyRadiusKm"></span> km based on your current or selected area.</p>
                    </div>
                    <button type="button" @click="closeLocationPrompt()" class="rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600">Skip</button>
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <button type="button" @click="requestGeoLocation()" class="inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white">
                        <x-heroicon name="map-pin" class="h-4 w-4" />
                        Detect my location
                    </button>
                    <button type="button" @click="openLocationEditor()" class="inline-flex w-full items-center justify-center gap-2 rounded-2xl border border-orange-200 bg-orange-50 px-4 py-3 text-sm font-semibold text-orange-700">
                        <x-heroicon name="pencil-square" class="h-4 w-4" />
                        Select manually
                    </button>
                </div>

                <p x-show="locationMessage" x-text="locationMessage" class="mt-3 text-xs font-semibold text-slate-600"></p>

                <div x-show="isLocationEditorOpen" x-cloak class="mt-4 space-y-4 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="settings-label" for="modal_country">Country</label>
                            <select id="modal_country" class="app-select mt-1" x-model="selectedCountry" @change="onCountryChanged()">
                                <template x-for="country in countryOptions" :key="country.code">
                                    <option :value="country.code" x-text="country.name"></option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label class="settings-label" for="modal_state">State</label>
                            <select id="modal_state" class="app-select mt-1" x-model="selectedState" @change="onStateChanged()">
                                <option value="">Select state</option>
                                <template x-for="state in stateOptions" :key="state.name">
                                    <option :value="state.name" x-text="state.name"></option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label class="settings-label" for="modal_city">City</label>
                            <select id="modal_city" class="app-select mt-1" x-model="selectedCity" @change="onCityChanged()">
                                <option value="">Select city</option>
                                <template x-for="city in cityOptions" :key="city.name">
                                    <option :value="city.name" x-text="city.name"></option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label class="settings-label" for="modal_area">Area</label>
                            <select id="modal_area" class="app-select mt-1" x-model="selectedArea" @change="onAreaChanged()">
                                <option value="">Select area</option>
                                <template x-for="area in areaOptions" :key="area.name">
                                    <option :value="area.name" x-text="area.name"></option>
                                </template>
                            </select>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-2">
                        <div id="location-selector-map" class="h-56 w-full rounded-xl"></div>
                        <p class="mt-2 text-xs text-slate-500">Tap the map to pin exact location for nearby ads filtering.</p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="settings-label" for="modal_lat">Latitude</label>
                            <input id="modal_lat" class="app-input mt-1" x-model="selectedLat" readonly>
                        </div>
                        <div>
                            <label class="settings-label" for="modal_lng">Longitude</label>
                            <input id="modal_lng" class="app-input mt-1" x-model="selectedLng" readonly>
                        </div>
                    </div>

                    <div class="flex flex-wrap justify-end gap-2">
                        <button type="button" @click="isLocationEditorOpen = false" class="app-btn-muted">Close</button>
                        <button type="button" @click="applySelectedLocation()" class="app-btn-primary">Use this location</button>
                    </div>
                </div>
            </div>
        </section>

        <section class="relative overflow-hidden rounded-[1.6rem] bg-slate-900 p-3 text-white shadow-xl shadow-slate-300/30 sm:rounded-[1.8rem] sm:p-5">
            <span class="pointer-events-none absolute -right-10 -top-10 h-36 w-36 rounded-full bg-orange-400/30 blur-2xl"></span>
            <span class="pointer-events-none absolute bottom-0 left-20 h-24 w-24 rounded-full bg-cyan-300/30 blur-2xl"></span>

            <div class="relative">
                <form method="GET" action="{{ route('home') }}" class="space-y-3">
                    <input type="hidden" name="country" x-model="selectedCountry">
                    <input type="hidden" name="state" x-model="selectedState">
                    <input type="hidden" name="city" x-model="selectedCity">
                    <input type="hidden" name="area" x-model="selectedArea">
                    <input type="hidden" name="lat" x-model="selectedLat">
                    <input type="hidden" name="lng" x-model="selectedLng">
                    <div class="flex items-center gap-2 sm:gap-2.5">
                        <div class="relative min-w-0 flex-1">
                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-500">
                                <x-heroicon name="magnifying-glass" class="h-5 w-5" />
                            </span>
                            <input
                                id="q"
                                type="text"
                                name="q"
                                x-model="searchQuery"
                                class="h-11 w-full rounded-2xl border-0 bg-white px-11 pr-24 text-sm font-medium text-slate-800 shadow-lg shadow-slate-900/20 placeholder:text-slate-400 focus:ring-2 focus:ring-orange-300 sm:h-12"
                                placeholder="What are you looking for?"
                            >
                            <button
                                type="button"
                                @click="toggleSpeechInput()"
                                :disabled="!speechSupported"
                                class="absolute inset-y-0 right-11 inline-flex w-10 items-center justify-center rounded-xl text-slate-500 transition hover:text-orange-600 disabled:cursor-not-allowed disabled:opacity-40"
                                :class="speechListening ? 'text-emerald-600' : ''"
                                aria-label="Voice search"
                            >
                                <x-heroicon name="microphone" class="h-5 w-5" />
                            </button>
                            <button type="submit" class="absolute inset-y-1 right-1 inline-flex items-center justify-center rounded-xl bg-orange-500 px-3 text-white shadow-md shadow-orange-700/30" aria-label="Search">
                                <x-heroicon name="magnifying-glass" class="h-4 w-4" />
                            </button>
                        </div>

                        <a href="{{ $homeMyListingsUrl }}" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-white/15 text-white ring-1 ring-white/30 transition hover:bg-white/20 sm:h-12 sm:w-12" aria-label="My listings">
                            <x-heroicon name="clipboard-document-list" class="h-5 w-5" />
                        </a>
                        <a href="{{ $homeChatUrl }}" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-white/15 text-white ring-1 ring-white/30 transition hover:bg-white/20 sm:h-12 sm:w-12" aria-label="Chat">
                            <x-heroicon name="chat-bubble-left-right" class="h-5 w-5" />
                        </a>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-200 sm:text-xs">Search cars, mobiles, jobs, properties and more</p>
                        <p x-show="speechListening" x-cloak class="text-[10px] font-bold uppercase tracking-wide text-emerald-200">Listening...</p>
                        <p x-show="speechError" x-cloak x-text="speechError" class="text-[10px] font-semibold text-rose-200"></p>
                    </div>
                </form>
            </div>
        </section>

        <section class="relative overflow-hidden rounded-[1.5rem] border border-slate-200 bg-white p-2.5 shadow-sm shadow-slate-200 sm:p-3">
            @if ($showImageBanner)
                <div class="overflow-hidden rounded-2xl border border-slate-100 bg-slate-50">
                    @foreach ($homeBannerImageUrls as $bannerImageUrl)
                        @php
                            $bannerPosition = $homeBannerPositions[$loop->index] ?? 'center';
                        @endphp
                        <div
                            x-cloak
                            x-show="sliderIndex === {{ $loop->index }}"
                            x-transition.opacity.duration.300ms
                            class="relative flex items-center justify-center bg-slate-50 p-1 sm:p-2"
                        >
                            <img
                                src="{{ $bannerImageUrl }}"
                                alt="Homepage banner {{ $loop->iteration }}"
                                class="mx-auto block h-auto max-h-[70vh] w-full object-contain"
                                style="object-position: {{ $bannerPosition }}"
                                loading="lazy"
                            >
                        </div>
                    @endforeach
                </div>

                @if (count($homeBannerImageUrls) > 1)
                    <div class="mt-2.5 flex items-center sm:mt-3">
                        <div class="flex items-center gap-1.5">
                            @foreach ($homeBannerImageUrls as $bannerImageUrl)
                                <button
                                    type="button"
                                    @click="sliderIndex = {{ $loop->index }}"
                                    class="h-2.5 rounded-full transition-all"
                                    :class="sliderIndex === {{ $loop->index }} ? 'w-7 bg-orange-500' : 'w-2.5 bg-slate-300'"
                                    aria-label="Go to banner {{ $loop->iteration }}"
                                ></button>
                            @endforeach
                        </div>
                    </div>
                @endif
            @else
                <div class="relative h-32 overflow-hidden rounded-2xl sm:h-40 lg:h-44">
                    <div x-show="sliderIndex === 0" x-transition.opacity.duration.300ms class="absolute inset-0 bg-gradient-to-r from-amber-400 via-orange-400 to-orange-500 p-4 text-white sm:p-5">
                        <p class="text-xs font-black uppercase tracking-[0.14em] text-orange-50">{{ $homeBannerSlides[0]['badge'] }}</p>
                        <h2 class="mt-1 font-display text-xl font-bold leading-tight sm:text-3xl">{{ $homeBannerSlides[0]['title'] }}</h2>
                        <p class="mt-1 max-w-sm text-xs text-orange-50 sm:text-sm">{{ $homeBannerSlides[0]['desc'] }}</p>
                    </div>
                    <div x-show="sliderIndex === 1" x-transition.opacity.duration.300ms class="absolute inset-0 bg-gradient-to-r from-sky-500 via-cyan-500 to-teal-500 p-4 text-white sm:p-5">
                        <p class="text-xs font-black uppercase tracking-[0.14em] text-cyan-50">{{ $homeBannerSlides[1]['badge'] }}</p>
                        <h2 class="mt-1 font-display text-xl font-bold leading-tight sm:text-3xl">{{ $homeBannerSlides[1]['title'] }}</h2>
                        <p class="mt-1 max-w-sm text-xs text-cyan-50 sm:text-sm">{{ $homeBannerSlides[1]['desc'] }}</p>
                    </div>
                    <div x-show="sliderIndex === 2" x-transition.opacity.duration.300ms class="absolute inset-0 bg-gradient-to-r from-violet-500 via-fuchsia-500 to-rose-500 p-4 text-white sm:p-5">
                        <p class="text-xs font-black uppercase tracking-[0.14em] text-rose-50">{{ $homeBannerSlides[2]['badge'] }}</p>
                        <h2 class="mt-1 font-display text-xl font-bold leading-tight sm:text-3xl">{{ $homeBannerSlides[2]['title'] }}</h2>
                        <p class="mt-1 max-w-sm text-xs text-rose-50 sm:text-sm">{{ $homeBannerSlides[2]['desc'] }}</p>
                    </div>
                </div>

                <div class="mt-2.5 flex items-center sm:mt-3">
                    <div class="flex items-center gap-1.5">
                        <button type="button" @click="sliderIndex = 0" class="h-2.5 rounded-full transition-all" :class="sliderIndex === 0 ? 'w-7 bg-orange-500' : 'w-2.5 bg-slate-300'"></button>
                        <button type="button" @click="sliderIndex = 1" class="h-2.5 rounded-full transition-all" :class="sliderIndex === 1 ? 'w-7 bg-orange-500' : 'w-2.5 bg-slate-300'"></button>
                        <button type="button" @click="sliderIndex = 2" class="h-2.5 rounded-full transition-all" :class="sliderIndex === 2 ? 'w-7 bg-orange-500' : 'w-2.5 bg-slate-300'"></button>
                    </div>
                </div>
            @endif
        </section>

        @if (auth()->check() && (bool) setting('ai_enabled', false))
            <section class="rounded-[1.5rem] border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="font-display text-lg font-bold text-slate-900">AI Hub</h2>
                    <span class="rounded-full bg-sky-50 px-3 py-1 text-[11px] font-bold uppercase tracking-wide text-sky-700">Agentic Tools</span>
                </div>

                <div class="mt-3 grid gap-2 sm:grid-cols-3">
                    @if ((bool) setting('ai_compass_enabled', true))
                        <a href="{{ route('ai.compass') }}" class="rounded-2xl border border-slate-200 bg-sky-50 px-3 py-3 text-sm font-semibold text-sky-800 hover:border-sky-300">
                            CompassGPT
                            <p class="mt-1 text-xs font-medium text-sky-700">Conversational property matchmaking</p>
                        </a>
                    @endif

                    @if ((bool) setting('ai_autoiq_enabled', true))
                        <a href="{{ route('ai.autoiq') }}" class="rounded-2xl border border-slate-200 bg-emerald-50 px-3 py-3 text-sm font-semibold text-emerald-800 hover:border-emerald-300">
                            AutoIQ
                            <p class="mt-1 text-xs font-medium text-emerald-700">Dealer pricing, leads, and inventory intelligence</p>
                        </a>
                    @endif

                    @if ((bool) setting('ai_job_matching_enabled', true))
                        <a href="{{ route('ai.navigator') }}" class="rounded-2xl border border-slate-200 bg-amber-50 px-3 py-3 text-sm font-semibold text-amber-800 hover:border-amber-300">
                            AI Navigator
                            <p class="mt-1 text-xs font-medium text-amber-700">CV parsing and job matching</p>
                        </a>
                    @endif
                </div>
            </section>
        @endif

        <section class="space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="font-display text-xl font-bold text-slate-900">Popular Categories</h2>
                <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Quick Access</p>
            </div>

            <div class="grid grid-cols-4 gap-2">
                @foreach ($popularCategories->take(4) as $category)
                    <a href="{{ route('categories.show', array_merge(['category' => $category->slug], request()->except('page', 'category', 'subcategory', 'custom_filters'))) }}" class="inline-flex min-h-[84px] flex-col items-center justify-center gap-1 rounded-2xl border border-slate-200 bg-white px-2.5 py-2 text-center text-[11px] font-semibold text-slate-700 transition hover:border-orange-300 hover:bg-orange-50 hover:text-orange-700 sm:px-3 sm:text-xs">
                        <span class="flex h-8 w-8 items-center justify-center overflow-hidden rounded-xl bg-slate-100">
                            @if ($category->icon_url)
                                <img src="{{ $category->icon_url }}" alt="{{ $category->name }}" class="h-full w-full object-cover">
                            @else
                                <x-heroicon name="squares-2x2" class="h-4 w-4 text-slate-600" />
                            @endif
                        </span>
                        <span class="line-clamp-2">{{ $category->display_name }}</span>
                    </a>
                @endforeach

                @foreach ($popularCategories->slice(4, 3) as $category)
                    <a href="{{ route('categories.show', array_merge(['category' => $category->slug], request()->except('page', 'category', 'subcategory', 'custom_filters'))) }}" class="inline-flex min-h-[84px] flex-col items-center justify-center gap-1 rounded-2xl border border-slate-200 bg-white px-2.5 py-2 text-center text-[11px] font-semibold text-slate-700 transition hover:border-orange-300 hover:bg-orange-50 hover:text-orange-700 sm:px-3 sm:text-xs">
                        <span class="flex h-8 w-8 items-center justify-center overflow-hidden rounded-xl bg-slate-100">
                            @if ($category->icon_url)
                                <img src="{{ $category->icon_url }}" alt="{{ $category->name }}" class="h-full w-full object-cover">
                            @else
                                <x-heroicon name="squares-2x2" class="h-4 w-4 text-slate-600" />
                            @endif
                        </span>
                        <span class="line-clamp-2">{{ $category->display_name }}</span>
                    </a>
                @endforeach

                <a href="{{ route('categories.index') }}" class="inline-flex min-h-[84px] flex-col items-center justify-center gap-1 rounded-2xl border px-2.5 py-2 text-center text-[11px] font-semibold sm:px-3 sm:text-xs border-slate-200 bg-white text-slate-700 hover:border-orange-300 hover:text-orange-600">
                    <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-slate-100">
                        <x-heroicon name="bars-3" class="h-4 w-4 text-slate-700" />
                    </span>
                    <span class="line-clamp-2">See all</span>
                </a>
            </div>
        </section>

        @if ($featuredListings->isNotEmpty())
            <section class="space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="font-display text-xl font-bold text-slate-900">Featured picks</h2>
                </div>

                <div class="flex gap-3 overflow-x-auto pb-1">
                    @foreach ($featuredListings as $featured)
                        <div class="min-w-[220px] max-w-[220px] sm:min-w-[280px] sm:max-w-[280px] lg:min-w-[300px] lg:max-w-[300px]">
                            <x-listing-card :listing="$featured" :show-favorite-overlay="true" :is-favorited="in_array($featured->id, $favoriteListingIds, true)" />
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        @if (($nearbyListings ?? collect())->isNotEmpty())
            <section class="space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="font-display text-xl font-bold text-slate-900">Ads Nearby</h2>
                    <span class="rounded-full bg-emerald-50 px-3 py-1 text-[11px] font-bold uppercase tracking-wide text-emerald-700">
                        Within {{ (int) ($nearbyRadiusKm ?? 30) }} km
                    </span>
                </div>

                <div class="flex gap-3 overflow-x-auto pb-1">
                    @foreach ($nearbyListings as $nearby)
                        <div class="min-w-[220px] max-w-[220px] sm:min-w-[280px] sm:max-w-[280px] lg:min-w-[300px] lg:max-w-[300px]">
                            <x-listing-card :listing="$nearby" :show-favorite-overlay="true" :is-favorited="in_array($nearby->id, $favoriteListingIds, true)" />
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="space-y-4">
            <div class="flex items-center justify-between gap-3">
                <h2 class="font-display text-2xl font-bold text-slate-900">Fresh listings</h2>
                <p class="text-sm font-semibold text-slate-500">{{ $listings->total() }} ads</p>
            </div>

            @if ($listings->isEmpty())
                <div class="app-card text-center">
                    <p class="text-slate-600">No listings match your filters yet. Try broadening your search.</p>
                </div>
            @else
                <div data-feed-grid x-ref="feedGrid" class="home-feed-grid">
                    @foreach ($listings as $listing)
                        <div>
                            <x-listing-card :listing="$listing" :show-favorite-overlay="true" :is-favorited="in_array($listing->id, $favoriteListingIds, true)">
                                <x-slot:actions>
                                    @php
                                        $sellerPhone = trim((string) ($listing->user?->phone ?? ''));
                                        $sellerPhoneHref = preg_replace('/[^0-9\+]/', '', $sellerPhone);
                                    @endphp

                                    <div class="grid grid-cols-2 gap-2">
                                        @auth
                                            @if (! $listing->isOwnedBy(auth()->user()))
                                                <form method="POST" action="{{ route('chat.from-listing', $listing) }}">
                                                    @csrf
                                                    <input type="hidden" name="message" value="Hi, is this still available?">
                                                    <button type="submit" class="app-btn-muted inline-flex w-full items-center justify-center gap-2 text-xs">
                                                        <x-heroicon name="chat-bubble-left-right" class="h-4 w-4" />
                                                        Chat
                                                    </button>
                                                </form>

                                                @if ($sellerPhoneHref !== '' && $hasCallAccess)
                                                    <a href="{{ route('listings.start-call', $listing) }}" class="inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-emerald-600 px-4 py-2.5 text-xs font-semibold text-white shadow-sm shadow-emerald-600/20">
                                                        <x-heroicon name="phone" class="h-4 w-4" />
                                                        Call
                                                    </a>
                                                @elseif ($sellerPhoneHref !== '')
                                                    <a href="{{ route('subscriptions.index', ['feature' => 'call']) }}" class="inline-flex w-full items-center justify-center gap-2 rounded-2xl border border-orange-200 bg-orange-50 px-4 py-2.5 text-xs font-semibold text-orange-700">
                                                        <x-heroicon name="lock-closed" class="h-4 w-4" />
                                                        Unlock Call
                                                    </a>
                                                @else
                                                    <span class="inline-flex w-full cursor-not-allowed items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-slate-100 px-4 py-2.5 text-xs font-semibold text-slate-400">
                                                        <x-heroicon name="phone" class="h-4 w-4" />
                                                        No Phone
                                                    </span>
                                                @endif
                                            @else
                                                <a href="{{ route('listings.show', $listing) }}" class="app-btn-muted inline-flex w-full items-center justify-center gap-2 text-center text-xs">
                                                    <x-heroicon name="eye" class="h-4 w-4" />
                                                    View
                                                </a>
                                                <a href="{{ route('listings.edit', $listing) }}" class="app-btn-primary inline-flex w-full items-center justify-center gap-2 text-center text-xs">
                                                    <x-heroicon name="pencil-square" class="h-4 w-4" />
                                                    Edit
                                                </a>
                                            @endif
                                        @else
                                            <a href="{{ route('login') }}" class="app-btn-muted inline-flex w-full items-center justify-center gap-2 text-center text-xs">
                                                <x-heroicon name="chat-bubble-left-right" class="h-4 w-4" />
                                                Chat
                                            </a>
                                            <a href="{{ route('login') }}" class="app-btn-primary inline-flex w-full items-center justify-center gap-2 text-center text-xs">
                                                <x-heroicon name="phone" class="h-4 w-4" />
                                                Call
                                            </a>
                                        @endauth
                                    </div>
                                </x-slot:actions>
                            </x-listing-card>
                        </div>

                        @if ($loop->iteration % $inFeedInsertEvery === 0 && ! $loop->last)
                            <div class="col-span-2 md:col-span-3 2xl:col-span-4">
                                <x-google-ad
                                    location="feed"
                                    :show-placeholder="true"
                                    placeholder-text="Coming soon"
                                    container-class="h-full min-h-[180px]"
                                />
                            </div>
                        @endif
                    @endforeach
                </div>

                <div data-next-page-url="{{ $listings->nextPageUrl() ?? '' }}" class="hidden"></div>

                <div x-ref="feedSentinel" class="h-3"></div>

                <div x-show="loadingMore" x-cloak class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-center text-sm font-semibold text-slate-600">
                    Loading more ads...
                </div>

                <div x-show="isFinished" x-cloak class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-center text-xs font-bold uppercase tracking-wide text-slate-500">
                    You have reached the end of results
                </div>

                <noscript>
                    {{ $listings->links() }}
                </noscript>
            @endif
        </section>
    </div>

    <script>
        function homeMarketplace(config) {
            return {
                mapsScriptPromise: null,
                mapInstance: null,
                mapMarker: null,
                isLocationEditorOpen: false,
                showLocationPrompt: false,
                locationMessage: '',
                locationApi: (config && config.locationApi) ? config.locationApi : {},
                defaultCountry: (config && config.defaultCountry) ? config.defaultCountry : 'IN',
                mapsApiKey: (config && config.mapsApiKey) ? config.mapsApiKey : '',
                nearbyRadiusKm: (config && config.nearbyRadiusKm) ? Number(config.nearbyRadiusKm) : 30,
                selectedCountry: '',
                selectedState: '',
                selectedCity: '',
                selectedArea: '',
                selectedLat: '',
                selectedLng: '',
                countryOptions: [],
                stateOptions: [],
                cityOptions: [],
                areaOptions: [],
                locationLabel: (config && config.initialLocationLabel) ? config.initialLocationLabel : 'Select location',
                searchQuery: (config && config.initialSearchQuery) ? config.initialSearchQuery : '',
                speechSupported: false,
                speechListening: false,
                speechError: '',
                speechRecognition: null,
                bannerMode: (config && config.bannerMode) ? String(config.bannerMode) : 'text',
                bannerSlideCount: (config && config.bannerSlideCount) ? Number(config.bannerSlideCount) : 3,
                bannerDisplayMs: (config && config.bannerDisplayMs) ? Number(config.bannerDisplayMs) : 5000,
                sliderIndex: 0,
                sliderTimer: null,
                nextPageUrl: (config && config.nextPageUrl) ? config.nextPageUrl : '',
                loadingMore: false,
                isFinished: !((config && config.nextPageUrl) ? config.nextPageUrl : ''),
                observer: null,
                locationStorageKey: 'unsell_location_state',

                readStoredLocationState() {
                    try {
                        const rawState = window.localStorage.getItem(this.locationStorageKey);
                        if (rawState) {
                            const parsedState = JSON.parse(rawState);
                            if (parsedState && typeof parsedState === 'object') {
                                return {
                                    label: typeof parsedState.label === 'string' ? parsedState.label : '',
                                    promptHandled: parsedState.promptHandled === true,
                                };
                            }
                        }
                    } catch (_) {
                    }

                    try {
                        const legacyLabel = window.localStorage.getItem('unsell_selected_location_label');
                        const legacyPromptHandled = window.localStorage.getItem('unsell_location_prompt_seen') === '1';
                        if ((legacyLabel && legacyLabel.trim() !== '') || legacyPromptHandled) {
                            const migratedState = {
                                label: legacyLabel && legacyLabel.trim() !== '' ? legacyLabel.trim() : '',
                                promptHandled: legacyPromptHandled || !!(legacyLabel && legacyLabel.trim() !== ''),
                            };
                            this.writeStoredLocationState(migratedState);
                            return migratedState;
                        }
                    } catch (_) {
                    }

                    return {
                        label: '',
                        promptHandled: false,
                    };
                },

                writeStoredLocationState(state) {
                    try {
                        const normalizedState = {
                            label: state && typeof state.label === 'string' ? state.label.trim() : '',
                            promptHandled: !!(state && state.promptHandled),
                        };

                        window.localStorage.setItem(this.locationStorageKey, JSON.stringify(normalizedState));
                        window.localStorage.removeItem('unsell_selected_location_label');
                        window.localStorage.removeItem('unsell_location_prompt_seen');
                    } catch (_) {
                    }
                },

                init() {
                    this.bootstrapLocation(config && config.initialLocation ? config.initialLocation : {});
                    this.initSpeechRecognition();
                    this.startSlider();
                    this.initInfiniteFeed();
                },

                async bootstrapLocation(initialLocation) {
                    this.selectedCountry = (initialLocation.country || this.defaultCountry || 'IN').toUpperCase();
                    this.selectedState = initialLocation.state || '';
                    this.selectedCity = initialLocation.city || '';
                    this.selectedArea = initialLocation.area || '';
                    this.selectedLat = initialLocation.latitude !== null && initialLocation.latitude !== undefined ? String(initialLocation.latitude) : '';
                    this.selectedLng = initialLocation.longitude !== null && initialLocation.longitude !== undefined ? String(initialLocation.longitude) : '';

                    const storedLocationState = this.readStoredLocationState();
                    const startupLocationSelectorPresent = !!document.querySelector('[data-startup-location-selector]');
                    this.showLocationPrompt = !storedLocationState.promptHandled && !startupLocationSelectorPresent;

                    await this.loadCountries();
                    if (this.selectedState) {
                        await this.loadStates();
                    }
                    if (this.selectedCity) {
                        await this.loadCities();
                    }
                    if (this.selectedArea) {
                        await this.loadAreas();
                    }

                    this.updateLocationLabel();
                },

                closeLocationPrompt() {
                    this.showLocationPrompt = false;
                    const storedLocationState = this.readStoredLocationState();
                    this.writeStoredLocationState({
                        label: storedLocationState.label,
                        promptHandled: true,
                    });
                },

                openLocationEditor() {
                    this.isLocationEditorOpen = true;
                    this.initializeMapPicker();
                },

                async requestGeoLocation() {
                    if (!('geolocation' in navigator)) {
                        this.locationMessage = 'Location detection is not supported in this browser.';
                        this.openLocationEditor();
                        return;
                    }

                    this.locationMessage = 'Detecting your location...';

                    navigator.geolocation.getCurrentPosition(
                        (position) => {
                            this.selectedLat = position.coords.latitude.toFixed(6);
                            this.selectedLng = position.coords.longitude.toFixed(6);
                            this.locationMessage = 'Location detected. You can continue or adjust on map.';
                            this.openLocationEditor();
                            this.initializeMapPicker();
                        },
                        () => {
                            this.locationMessage = 'Location access was denied. Please select manually.';
                            this.openLocationEditor();
                        },
                        {
                            enableHighAccuracy: true,
                            timeout: 12000,
                            maximumAge: 0,
                        }
                    );
                },

                async loadCountries() {
                    const endpoint = this.locationApi.countries;
                    if (!endpoint) {
                        return;
                    }

                    try {
                        const response = await fetch(endpoint, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                        if (!response.ok) {
                            return;
                        }

                        const payload = await response.json();
                        this.countryOptions = Array.isArray(payload.items) ? payload.items : [];
                    } catch (_) {
                        this.countryOptions = [];
                    }
                },

                async onCountryChanged() {
                    this.selectedState = '';
                    this.selectedCity = '';
                    this.selectedArea = '';
                    this.stateOptions = [];
                    this.cityOptions = [];
                    this.areaOptions = [];
                    this.selectedLat = '';
                    this.selectedLng = '';
                    await this.loadStates();
                },

                async onStateChanged() {
                    this.selectedCity = '';
                    this.selectedArea = '';
                    this.cityOptions = [];
                    this.areaOptions = [];
                    this.selectedLat = '';
                    this.selectedLng = '';
                    await this.loadCities();
                },

                async onCityChanged() {
                    this.selectedArea = '';
                    this.areaOptions = [];
                    this.selectedLat = '';
                    this.selectedLng = '';
                    await this.loadAreas();
                },

                onAreaChanged() {
                    const selected = this.areaOptions.find((item) => item.name === this.selectedArea);
                    if (selected && selected.latitude && selected.longitude) {
                        this.selectedLat = String(selected.latitude);
                        this.selectedLng = String(selected.longitude);
                        this.placeMarker(parseFloat(this.selectedLat), parseFloat(this.selectedLng));
                    }
                },

                async loadStates() {
                    const endpoint = this.locationApi.states;
                    if (!endpoint || !this.selectedCountry) {
                        return;
                    }

                    try {
                        const response = await fetch(`${endpoint}?country=${encodeURIComponent(this.selectedCountry)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                        if (!response.ok) {
                            return;
                        }

                        const payload = await response.json();
                        this.stateOptions = Array.isArray(payload.items) ? payload.items : [];
                    } catch (_) {
                        this.stateOptions = [];
                    }
                },

                async loadCities() {
                    const endpoint = this.locationApi.cities;
                    if (!endpoint || !this.selectedCountry || !this.selectedState) {
                        return;
                    }

                    const params = new URLSearchParams({
                        country: this.selectedCountry,
                        state: this.selectedState,
                    });

                    try {
                        const response = await fetch(`${endpoint}?${params.toString()}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                        if (!response.ok) {
                            return;
                        }

                        const payload = await response.json();
                        this.cityOptions = Array.isArray(payload.items) ? payload.items : [];
                    } catch (_) {
                        this.cityOptions = [];
                    }
                },

                async loadAreas() {
                    const endpoint = this.locationApi.areas;
                    if (!endpoint || !this.selectedCountry || !this.selectedState || !this.selectedCity) {
                        return;
                    }

                    const params = new URLSearchParams({
                        country: this.selectedCountry,
                        state: this.selectedState,
                        city: this.selectedCity,
                    });

                    try {
                        const response = await fetch(`${endpoint}?${params.toString()}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                        if (!response.ok) {
                            return;
                        }

                        const payload = await response.json();
                        this.areaOptions = Array.isArray(payload.items) ? payload.items : [];
                    } catch (_) {
                        this.areaOptions = [];
                    }
                },

                updateLocationLabel() {
                    const parts = [this.selectedArea, this.selectedCity, this.selectedState].filter((part) => part && part.trim() !== '');
                    this.locationLabel = parts.length > 0 ? parts.join(', ') : 'Select location';
                },

                applySelectedLocation() {
                    this.updateLocationLabel();
                    this.locationMessage = '';
                    this.showLocationPrompt = false;
                    this.isLocationEditorOpen = false;
                    this.writeStoredLocationState({
                        label: this.locationLabel,
                        promptHandled: true,
                    });

                    window.dispatchEvent(new CustomEvent('unsell-location-updated', {
                        detail: {
                            label: this.locationLabel,
                        },
                    }));

                    this.submitLocation();
                },

                submitLocation() {
                    const url = new URL(window.location.href);

                    this.writeParam(url, 'country', this.selectedCountry);
                    this.writeParam(url, 'state', this.selectedState);
                    this.writeParam(url, 'city', this.selectedCity);
                    this.writeParam(url, 'area', this.selectedArea);
                    this.writeParam(url, 'lat', this.selectedLat);
                    this.writeParam(url, 'lng', this.selectedLng);

                    url.searchParams.delete('page');

                    window.location.assign(url.toString());
                },

                writeParam(url, key, value) {
                    if (value && String(value).trim() !== '') {
                        url.searchParams.set(key, String(value).trim());
                        return;
                    }

                    url.searchParams.delete(key);
                },

                startSlider() {
                    if (!Number.isFinite(this.bannerSlideCount) || this.bannerSlideCount <= 1) {
                        return;
                    }

                    if (this.sliderTimer) {
                        window.clearInterval(this.sliderTimer);
                    }

                    const intervalMs = Number.isFinite(this.bannerDisplayMs)
                        ? Math.min(60000, Math.max(1000, Math.round(this.bannerDisplayMs)))
                        : 5000;

                    this.sliderTimer = window.setInterval(() => {
                        this.sliderIndex = (this.sliderIndex + 1) % this.bannerSlideCount;
                    }, intervalMs);
                },

                changeCity() {
                    this.openLocationEditor();
                },

                initSpeechRecognition() {
                    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;

                    if (!SpeechRecognition) {
                        this.speechSupported = false;
                        return;
                    }

                    this.speechSupported = true;
                    this.speechRecognition = new SpeechRecognition();
                    this.speechRecognition.continuous = false;
                    this.speechRecognition.interimResults = false;
                    this.speechRecognition.maxAlternatives = 1;

                    this.speechRecognition.onresult = (event) => {
                        const first = event.results && event.results[0] && event.results[0][0] ? event.results[0][0] : null;
                        const transcript = first && first.transcript ? String(first.transcript).trim() : '';

                        if (transcript !== '') {
                            this.searchQuery = transcript;
                        }
                    };

                    this.speechRecognition.onerror = () => {
                        this.speechListening = false;
                        this.speechError = 'Voice input failed. Please try again.';
                    };

                    this.speechRecognition.onend = () => {
                        this.speechListening = false;
                    };
                },

                toggleSpeechInput() {
                    if (!this.speechSupported || !this.speechRecognition) {
                        this.speechError = 'Voice search is not supported on this browser.';
                        return;
                    }

                    if (this.speechListening) {
                        this.speechRecognition.stop();
                        return;
                    }

                    this.speechError = '';
                    this.speechRecognition.lang = (this.selectedCountry || this.defaultCountry || 'IN').toUpperCase() === 'IN' ? 'en-IN' : 'en-US';

                    try {
                        this.speechListening = true;
                        this.speechRecognition.start();
                    } catch (_) {
                        this.speechListening = false;
                        this.speechError = 'Unable to start voice input right now.';
                    }
                },

                async initializeMapPicker() {
                    if (!this.mapsApiKey) {
                        return;
                    }

                    await this.ensureGoogleMapsScript();

                    if (!window.google || !window.google.maps) {
                        return;
                    }

                    const mapContainer = document.getElementById('location-selector-map');
                    if (!mapContainer) {
                        return;
                    }

                    const defaultLat = parseFloat(this.selectedLat || '28.6139');
                    const defaultLng = parseFloat(this.selectedLng || '77.2090');

                    if (!this.mapInstance) {
                        this.mapInstance = new window.google.maps.Map(mapContainer, {
                            center: { lat: defaultLat, lng: defaultLng },
                            zoom: 11,
                            mapTypeControl: false,
                            streetViewControl: false,
                        });

                        this.mapInstance.addListener('click', (event) => {
                            const lat = event.latLng.lat();
                            const lng = event.latLng.lng();
                            this.selectedLat = lat.toFixed(6);
                            this.selectedLng = lng.toFixed(6);
                            this.placeMarker(lat, lng);
                        });
                    } else {
                        this.mapInstance.setCenter({ lat: defaultLat, lng: defaultLng });
                    }

                    this.placeMarker(defaultLat, defaultLng);
                },

                placeMarker(lat, lng) {
                    if (!window.google || !window.google.maps || !this.mapInstance) {
                        return;
                    }

                    if (!this.mapMarker) {
                        this.mapMarker = new window.google.maps.Marker({
                            map: this.mapInstance,
                            draggable: true,
                            position: { lat, lng },
                        });

                        this.mapMarker.addListener('dragend', (event) => {
                            const markerLat = event.latLng.lat();
                            const markerLng = event.latLng.lng();
                            this.selectedLat = markerLat.toFixed(6);
                            this.selectedLng = markerLng.toFixed(6);
                        });

                        return;
                    }

                    this.mapMarker.setPosition({ lat, lng });
                },

                async ensureGoogleMapsScript() {
                    if (window.google && window.google.maps) {
                        return;
                    }

                    if (this.mapsScriptPromise) {
                        return this.mapsScriptPromise;
                    }

                    this.mapsScriptPromise = new Promise((resolve, reject) => {
                        const existing = document.querySelector('script[data-google-maps="1"]');
                        if (existing) {
                            existing.addEventListener('load', () => resolve(), { once: true });
                            existing.addEventListener('error', () => reject(new Error('Google Maps failed')), { once: true });
                            return;
                        }

                        const script = document.createElement('script');
                        script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(this.mapsApiKey)}`;
                        script.async = true;
                        script.defer = true;
                        script.dataset.googleMaps = '1';
                        script.onload = () => resolve();
                        script.onerror = () => reject(new Error('Google Maps failed'));
                        document.head.appendChild(script);
                    });

                    return this.mapsScriptPromise;
                },

                initInfiniteFeed() {
                    if (!this.nextPageUrl || !('IntersectionObserver' in window) || !this.$refs.feedSentinel) {
                        this.isFinished = !this.nextPageUrl;
                        return;
                    }

                    this.observer = new IntersectionObserver((entries) => {
                        const seen = entries.some((entry) => entry.isIntersecting);
                        if (seen) {
                            this.loadMore();
                        }
                    }, {
                        rootMargin: '500px 0px',
                    });

                    this.observer.observe(this.$refs.feedSentinel);
                },

                async loadMore() {
                    if (this.loadingMore || !this.nextPageUrl) {
                        return;
                    }

                    this.loadingMore = true;

                    try {
                        const response = await fetch(this.nextPageUrl, {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        });

                        if (!response.ok) {
                            throw new Error('Unable to load next page');
                        }

                        const html = await response.text();
                        const doc = new DOMParser().parseFromString(html, 'text/html');

                        const incomingGrid = doc.querySelector('[data-feed-grid]');
                        if (incomingGrid && this.$refs.feedGrid) {
                            Array.from(incomingGrid.children).forEach((node) => {
                                this.$refs.feedGrid.appendChild(node);
                            });
                        }

                        const marker = doc.querySelector('[data-next-page-url]');
                        this.nextPageUrl = marker ? (marker.getAttribute('data-next-page-url') || '') : '';
                        this.isFinished = !this.nextPageUrl;

                        if (!this.nextPageUrl && this.observer) {
                            this.observer.disconnect();
                        }
                    } catch (error) {
                        this.isFinished = true;
                        if (this.observer) {
                            this.observer.disconnect();
                        }
                    } finally {
                        this.loadingMore = false;
                    }
                },
            };
        }
    </script>
</x-app-layout>
