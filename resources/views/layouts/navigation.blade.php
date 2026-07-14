@php
    $initialUnreadNotifications = auth()->check() ? auth()->user()->unreadNotifications()->count() : 0;
    $siteName = (string) setting('site_name', 'Unsell');
    $siteLogoSizePx = (int) setting('site_logo_size_px', 40);
    if ($siteLogoSizePx < 24) {
        $siteLogoSizePx = 24;
    }
    if ($siteLogoSizePx > 128) {
        $siteLogoSizePx = 128;
    }
    $siteTagline = setting('site_tagline', 'PWA Market');
    $brandTextPartOne = trim((string) setting('site_brand_text_part_1', ''));
    $brandTextPartTwo = trim((string) setting('site_brand_text_part_2', ''));
    $brandTextColorOne = strtoupper(trim((string) setting('site_brand_text_color_1', '#0F172A')));
    $brandTextColorTwo = strtoupper(trim((string) setting('site_brand_text_color_2', '#F97316')));
    $brandTextSpacing = strtolower(trim((string) setting('site_brand_text_spacing', 'none')));
    if (! in_array($brandTextSpacing, ['none', 'space'], true)) {
        $brandTextSpacing = 'none';
    }
    $brandTextSpacer = $brandTextSpacing === 'space' ? ' ' : '';
    if (preg_match('/^#[0-9A-F]{6}$/', $brandTextColorOne) !== 1) {
        $brandTextColorOne = '#0F172A';
    }
    if (preg_match('/^#[0-9A-F]{6}$/', $brandTextColorTwo) !== 1) {
        $brandTextColorTwo = '#F97316';
    }
    $hasTwoPartBrandText = $brandTextPartOne !== '' && $brandTextPartTwo !== '';
    $googleMapsApiKey = trim((string) setting('google_maps_api_key', ''));
    $mobileLocationParts = array_values(array_filter([
        request('area'),
        request('city', auth()->user()->city ?? ''),
        request('state', auth()->user()->state ?? ''),
    ], fn ($value): bool => trim((string) $value) !== ''));
    $initialMobileLocationLabel = $mobileLocationParts !== [] ? implode(', ', $mobileLocationParts) : 'Select location';
@endphp

<nav
    x-data="navigationData({
        initialMobileLocationLabel: @js($initialMobileLocationLabel),
        homeUrl: @js(route('home')),
        mapsApiKey: @js($googleMapsApiKey),
        notificationPromptAvailable: @js(auth()->check()),
    })"
    x-init="init()"
    class="sticky top-0 z-40 border-b border-white/60 bg-white/70 backdrop-blur-xl"
>
    <div class="mx-auto flex w-full max-w-7xl items-center gap-3 px-4 py-3 sm:px-6 lg:px-8">
        <a href="{{ route('home') }}" class="inline-flex items-center gap-2">
            <x-application-logo class="h-auto w-auto" style="width: {{ $siteLogoSizePx }}px; height: {{ $siteLogoSizePx }}px;" />
            <div>
                @if ($hasTwoPartBrandText)
                    <p class="font-display text-lg font-bold leading-none" data-brand-title="split">
                        <span style="color: {{ $brandTextColorOne }};">{{ $brandTextPartOne }}</span><span style="color: {{ $brandTextColorTwo }};">{{ $brandTextSpacer }}{{ $brandTextPartTwo }}</span>
                    </p>
                @else
                    <p class="font-display text-lg font-bold leading-none text-slate-900">{{ $siteName }}</p>
                @endif
                <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-orange-600">{{ $siteTagline }}</p>
            </div>
        </a>

        <form method="GET" action="{{ route('home') }}" class="hidden flex-1 md:block">
            <label for="global-search" class="sr-only">Search listings</label>
            <input
                id="global-search"
                type="text"
                name="q"
                value="{{ request('q') }}"
                placeholder="Search mobiles, bikes, properties..."
                class="app-input h-11"
            >
        </form>

        <button
            type="button"
            data-pwa-install
            class="hidden rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700"
        >
            Install App
        </button>

        @auth
            <a href="{{ route('listings.create') }}" class="hidden items-center gap-2 rounded-full bg-orange-500 px-5 py-2.5 text-xs font-black uppercase tracking-[0.14em] text-white shadow-lg shadow-orange-200 transition hover:bg-orange-600 md:inline-flex">
                <x-heroicon name="plus" class="h-4 w-4" />
                Sell
            </a>

            <button
                type="button"
                @click="chooseMobileLocation()"
                class="ml-auto inline-flex min-w-[9.5rem] max-w-[13rem] items-center gap-2 rounded-2xl border border-orange-200 bg-gradient-to-br from-orange-50 to-amber-50 px-2.5 py-1.5 text-slate-700 shadow-sm shadow-orange-100/70 ring-1 ring-white/80 sm:hidden"
                :class="locationRequired ? 'border-rose-200 bg-rose-50/90 shadow-rose-100/80' : ''"
                aria-label="Select location"
            >
                <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-xl bg-white/90 ring-1 ring-orange-200">
                    <x-heroicon name="map-pin" class="h-4 w-4 text-orange-600" />
                </span>
                <span class="min-w-0 text-left leading-tight">
                    <span class="block text-[9px] font-black uppercase tracking-[0.14em] text-slate-500">Location</span>
                    <span class="block truncate text-[11px] font-semibold text-slate-800" x-text="mobileLocationLabel"></span>
                </span>
                <x-heroicon name="chevron-down" class="h-3.5 w-3.5 shrink-0 text-slate-400" />
            </button>

            <div class="relative ml-auto hidden sm:block">
                <button
                    type="button"
                    @click="notificationsOpen = !notificationsOpen"
                    data-notification-trigger
                    class="relative inline-flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-700"
                    aria-label="Notifications"
                >
                    <x-heroicon name="bell" class="h-6 w-6" />
                    <span data-notification-badge class="absolute -right-1 -top-1 rounded-full bg-orange-500 px-1.5 py-0.5 text-[10px] font-bold text-white {{ $initialUnreadNotifications > 0 ? '' : 'hidden' }}">
                        {{ $initialUnreadNotifications > 99 ? '99+' : $initialUnreadNotifications }}
                    </span>
                </button>

                <div
                    x-show="notificationsOpen"
                    x-cloak
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 translate-y-1 scale-95"
                    x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                    x-transition:leave="transition ease-in duration-140"
                    x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                    x-transition:leave-end="opacity-0 translate-y-1 scale-95"
                    @click.outside="notificationsOpen = false"
                    class="notification-drawer absolute right-0 top-12 z-50 w-[18rem] rounded-3xl border border-slate-200 bg-white p-3 shadow-xl sm:w-[20rem]"
                >
                    <div class="flex items-center justify-between gap-2 border-b border-slate-100 pb-2">
                        <p class="font-display text-lg font-bold text-slate-900">Notifications</p>
                        <button type="button" data-notification-mark-all class="text-xs font-semibold uppercase tracking-wide text-orange-600">Mark all read</button>
                    </div>

                    <button
                        type="button"
                        data-notification-enable
                        class="mt-3 hidden w-full rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700"
                    >
                        Enable browser alerts
                    </button>

                    <div data-notification-list class="mt-3 max-h-80 space-y-2 overflow-y-auto pr-1"></div>
                    <p data-notification-empty class="mt-3 text-sm text-slate-600">No notifications yet.</p>
                </div>
            </div>

            <button
                @click="open = !open"
                type="button"
                class="hidden h-10 w-10 items-center justify-center rounded-full bg-slate-900 text-white sm:inline-flex"
                aria-label="Open profile menu"
            >
                <x-heroicon name="user-circle" class="h-6 w-6" />
            </button>
        @else
            <div class="hidden items-center gap-2 sm:flex">
                <a href="{{ route('login') }}" class="app-btn-muted">Log in</a>
                <a href="{{ route('register') }}" class="app-btn-primary">Register</a>
            </div>
            <button
                type="button"
                @click="chooseMobileLocation()"
                class="ml-auto inline-flex min-w-[9.5rem] max-w-[13rem] items-center gap-2 rounded-2xl border border-orange-200 bg-gradient-to-br from-orange-50 to-amber-50 px-2.5 py-1.5 text-slate-700 shadow-sm shadow-orange-100/70 ring-1 ring-white/80 sm:hidden"
                :class="locationRequired ? 'border-rose-200 bg-rose-50/90 shadow-rose-100/80' : ''"
                aria-label="Select location"
            >
                <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-xl bg-white/90 ring-1 ring-orange-200">
                    <x-heroicon name="map-pin" class="h-4 w-4 text-orange-600" />
                </span>
                <span class="min-w-0 text-left leading-tight">
                    <span class="block text-[9px] font-black uppercase tracking-[0.14em] text-slate-500">Location</span>
                    <span class="block truncate text-[11px] font-semibold text-slate-800" x-text="mobileLocationLabel"></span>
                </span>
                <x-heroicon name="chevron-down" class="h-3.5 w-3.5 shrink-0 text-slate-400" />
            </button>
        @endauth
    </div>

    @auth
        <div
            x-show="showNotificationDeniedBanner()"
            x-cloak
            class="mx-auto mt-1 w-full max-w-7xl px-4 pb-3 sm:px-6 lg:px-8"
        >
            <div class="flex flex-col gap-3 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-rose-900 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex min-w-0 items-start gap-2">
                    <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-white ring-1 ring-rose-200">
                        <x-heroicon name="bell" class="h-4 w-4 text-rose-600" />
                    </span>
                    <div>
                        <p class="text-sm font-semibold">Notifications are blocked in browser settings.</p>
                        <p class="mt-0.5 text-xs text-rose-700">Enable notifications for this site to receive chat alerts, listing updates, and admin announcements.</p>
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <button
                        type="button"
                        @click="showNotificationHelpFromBanner()"
                        class="rounded-xl bg-rose-600 px-3 py-2 text-xs font-semibold text-white"
                    >
                        How to enable
                    </button>
                    <button
                        type="button"
                        @click="dismissNotificationDeniedBanner()"
                        class="rounded-xl border border-rose-300 bg-white px-3 py-2 text-xs font-semibold text-rose-700"
                    >
                        Dismiss
                    </button>
                </div>
            </div>
        </div>
    @endauth

    <template x-teleport="body">
        <section
            data-startup-location-selector
            x-show="locationSelectorOpen"
            x-cloak
            x-transition.opacity
            @click.self="closeLocationSelector()"
            @keydown.escape.window="closeLocationSelector()"
            class="fixed inset-0 z-[120] overflow-y-auto bg-slate-950/70 px-4 py-4 sm:px-6 sm:py-8"
        >
            <div class="flex min-h-full items-center justify-center">
                <div class="relative w-full max-w-md rounded-3xl border border-slate-200/70 bg-white/95 p-4 shadow-2xl sm:p-6" @click.stop>
                    <button
                        type="button"
                        x-show="!locationRequired"
                        @click="closeLocationSelector()"
                        class="absolute right-4 top-4 rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600"
                    >
                        Close
                    </button>

                    <div class="flex items-start gap-3">
                        <div class="pr-12">
                            <p class="text-[11px] font-black uppercase tracking-[0.14em] text-orange-500">Startup Permissions</p>
                            <h2 class="mt-1 font-display text-xl font-bold text-slate-900" x-text="permissionPromptTitle()"></h2>
                            <p class="mt-1 text-sm text-slate-600" x-text="permissionPromptSubtitle()"></p>
                        </div>
                    </div>

                    <div x-show="notificationPromptAvailable && notificationRequired" x-cloak class="mt-4 rounded-2xl border border-indigo-200 bg-indigo-50 p-4">
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-white text-indigo-600 ring-1 ring-indigo-200">
                                <x-heroicon name="bell" class="h-5 w-5" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-slate-900">Allow notifications</p>
                                <p class="mt-1 text-xs text-slate-600">Required for instant chat alerts, listing approvals, and admin updates.</p>
                                <button
                                    type="button"
                                    @click="enableNotificationsFromStartup()"
                                    :disabled="notificationBusy"
                                    class="mt-3 inline-flex items-center rounded-xl bg-indigo-600 px-4 py-2 text-xs font-semibold text-white disabled:cursor-not-allowed disabled:opacity-70"
                                >
                                    <span x-show="!notificationBusy">Enable notifications</span>
                                    <span x-show="notificationBusy" x-cloak>Requesting permission...</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div x-show="locationSelectorOpen" x-cloak class="mt-3 inline-flex w-full items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                        <x-heroicon name="map-pin" class="h-4 w-4 text-orange-600" />
                        <span class="truncate text-xs font-semibold text-slate-700" x-text="mobileLocationLabel"></span>
                    </div>

                    <div x-show="locationSelectorOpen" x-cloak class="mt-4 grid gap-3">
                        <button
                            type="button"
                            @click="detectMobileLocation()"
                            :disabled="locationDetecting"
                            class="inline-flex w-full items-center gap-2 rounded-2xl bg-emerald-600 px-4 py-3 text-left text-white disabled:cursor-not-allowed disabled:opacity-70"
                        >
                            <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-white/20">
                                <x-heroicon name="map-pin" class="h-4 w-4" />
                            </span>
                            <span>
                                <span class="block text-sm font-semibold" x-show="!locationDetecting">Detect my location</span>
                                <span class="block text-sm font-semibold" x-show="locationDetecting" x-cloak>Detecting...</span>
                                <span class="mt-0.5 block text-[11px] text-emerald-100">Use GPS for nearby ads</span>
                            </span>
                        </button>

                        <button
                            type="button"
                            @click="askManualLocation()"
                            class="inline-flex w-full items-center gap-2 rounded-2xl border border-orange-200 bg-orange-50 px-4 py-3 text-left text-orange-700"
                        >
                            <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-white/90 ring-1 ring-orange-200">
                                <x-heroicon name="magnifying-glass" class="h-4 w-4" />
                            </span>
                            <span>
                                <span class="block text-sm font-semibold">Search manually</span>
                                <span class="mt-0.5 block text-[11px] text-orange-600">Type city, area, or state</span>
                            </span>
                        </button>
                    </div>

                    <div x-show="showManualLocationInput" x-cloak class="mt-4 space-y-2 rounded-2xl border border-slate-200 bg-slate-50 p-3">
                        <label for="manual_location_input" class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">City / Area / State</label>
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                            <div class="relative flex-1">
                                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
                                    <x-heroicon name="magnifying-glass" class="h-4 w-4" />
                                </span>
                                <input
                                    id="manual_location_input"
                                    x-ref="manualLocationInput"
                                    type="text"
                                    x-model="manualLocationInput"
                                    placeholder="Enter city, area, or state"
                                    class="app-input h-11 pl-9"
                                    @input="scheduleManualLocationLookup()"
                                    @focus="scheduleManualLocationLookup()"
                                    @keydown.enter.prevent="applyManualLocation()"
                                    @keydown.escape="locationSuggestionsOpen = false"
                                >

                                <div
                                    x-show="locationSuggestionsOpen && (locationSearchLoading || locationSuggestions.length > 0 || manualLocationInput.trim().length >= 2)"
                                    x-cloak
                                    class="absolute left-0 right-0 top-[calc(100%+0.4rem)] z-20 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl"
                                >
                                    <p x-show="locationSearchLoading" x-cloak class="px-3 py-2 text-xs font-semibold text-slate-500">Searching locations...</p>

                                    <ul x-show="!locationSearchLoading && locationSuggestions.length > 0" x-cloak class="max-h-56 overflow-y-auto divide-y divide-slate-100">
                                        <template x-for="item in locationSuggestions" :key="item.placeId || item.label">
                                            <li>
                                                <button type="button" @click="chooseLocationSuggestion(item)" class="w-full px-3 py-2 text-left transition hover:bg-orange-50">
                                                    <span class="block text-sm font-semibold text-slate-800" x-text="item.primary"></span>
                                                    <span class="block text-xs text-slate-500" x-text="item.secondary || item.label"></span>
                                                </button>
                                            </li>
                                        </template>
                                    </ul>

                                    <p x-show="!locationSearchLoading && locationSuggestions.length === 0 && manualLocationInput.trim().length >= 2" x-cloak class="px-3 py-2 text-xs font-semibold text-slate-500">
                                        No matching locations found. You can still tap Apply.
                                    </p>
                                </div>
                            </div>
                            <button type="button" @click="applyManualLocation()" :disabled="locationSearchLoading" class="inline-flex h-11 w-full items-center justify-center rounded-xl bg-orange-500 px-4 text-xs font-black uppercase tracking-[0.12em] text-white shadow-md shadow-orange-200 disabled:cursor-not-allowed disabled:opacity-70 sm:w-auto">
                                <span x-show="!locationSearchLoading">Apply</span>
                                <span x-show="locationSearchLoading" x-cloak>Loading...</span>
                            </button>
                        </div>
                    </div>

                    <p x-show="notificationMessage" x-cloak x-text="notificationMessage" class="mt-3 text-xs font-semibold text-indigo-700"></p>
                    <p x-show="locationSelectorMessage" x-cloak x-text="locationSelectorMessage" class="mt-3 text-xs font-semibold text-slate-600"></p>
                </div>
            </div>
        </section>
    </template>

    @auth
        <div class="hidden sm:block">
            <div x-show="open" x-cloak class="border-t border-white/70 bg-white/85 px-4 py-4 sm:px-6 lg:px-8">
                <div class="mx-auto flex w-full max-w-7xl flex-wrap items-center gap-2">
                    <a href="{{ route('profile.edit') }}" class="app-btn-muted">Profile</a>
                    <a href="{{ route('listings.index') }}" class="app-btn-muted">My Listings</a>
                    <a href="{{ route('subscriptions.index') }}" class="app-btn-muted">Packages</a>
                    <a href="{{ route('chat.index') }}" class="app-btn-muted">
                        Messages
                        <span data-notification-badge-inline class="ml-1 rounded-full bg-orange-500 px-1.5 py-0.5 text-[10px] font-bold text-white {{ $initialUnreadNotifications > 0 ? '' : 'hidden' }}">
                            {{ $initialUnreadNotifications > 99 ? '99+' : $initialUnreadNotifications }}
                        </span>
                    </a>
                    @if (auth()->user()->is_admin)
                        <a href="{{ route('admin.dashboard') }}" class="app-btn-muted">Admin Panel</a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-rose-600 px-5 py-3 text-sm font-semibold text-white">
                            Logout
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @endauth
</nav>

@php
    $mobileMyAdsUrl = auth()->check() ? route('listings.index') : route('login');
    $mobileSellUrl = auth()->check() ? route('listings.create') : route('login');
    $mobileChatUrl = auth()->check() ? route('chat.index') : route('login');
    $mobileProfileUrl = route('menu.index');
        $hideMobileBottomNav = request()->routeIs('listings.create')
            || request()->routeIs('chat.show')
            || request()->routeIs('payments.*')
            || request()->routeIs('subscriptions.initialize')
            || request()->routeIs('subscriptions.callback.*');
@endphp

@unless ($hideMobileBottomNav)
    <div class="fixed inset-x-0 bottom-0 z-50 bg-gradient-to-t from-slate-950/10 via-white/95 to-white/85 pb-[calc(env(safe-area-inset-bottom)+0.5rem)] backdrop-blur-xl md:hidden">
        <div class="mx-auto max-w-md px-3 pt-1.5">
            <div class="rounded-[1.7rem] border border-slate-200/80 bg-white/95 p-2 ring-1 ring-white/90 shadow-[0_20px_48px_-24px_rgba(15,23,42,0.75)]">
                <div class="grid grid-cols-5 items-end gap-1">
                    <a href="{{ route('home') }}" class="group relative flex min-h-[54px] flex-col items-center justify-center gap-1 rounded-2xl px-1 text-[10px] font-black uppercase tracking-wide transition {{ request()->routeIs('home') ? 'bg-orange-50 text-orange-600 shadow-sm shadow-orange-100/80' : 'text-slate-500 hover:bg-slate-100/80' }}">
                        @if (request()->routeIs('home'))
                            <span class="absolute -top-1 h-1 w-7 rounded-full bg-gradient-to-r from-orange-500 to-amber-400"></span>
                        @endif
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl {{ request()->routeIs('home') ? 'bg-white ring-1 ring-orange-200' : 'bg-slate-100/90 group-hover:bg-white' }}">
                            <x-heroicon name="home" class="h-6 w-6" />
                        </span>
                        Home
                    </a>

                    <a href="{{ $mobileMyAdsUrl }}" class="group relative flex min-h-[54px] flex-col items-center justify-center gap-1 rounded-2xl px-1 text-[10px] font-black uppercase tracking-wide transition {{ request()->routeIs('listings.*') ? 'bg-orange-50 text-orange-600 shadow-sm shadow-orange-100/80' : 'text-slate-500 hover:bg-slate-100/80' }}">
                        @if (request()->routeIs('listings.*'))
                            <span class="absolute -top-1 h-1 w-7 rounded-full bg-gradient-to-r from-orange-500 to-amber-400"></span>
                        @endif
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl {{ request()->routeIs('listings.*') ? 'bg-white ring-1 ring-orange-200' : 'bg-slate-100/90 group-hover:bg-white' }}">
                            <x-heroicon name="clipboard-document-list" class="h-6 w-6" />
                        </span>
                        My Ads
                    </a>

                    <a href="{{ $mobileSellUrl }}" class="relative -mt-5 flex min-h-[66px] flex-col items-center justify-center gap-1 rounded-2xl bg-gradient-to-b from-orange-500 to-orange-600 px-1 text-[10px] font-black uppercase tracking-wide text-white ring-[5px] ring-white shadow-[0_18px_34px_-16px_rgba(249,115,22,0.95)]">
                        <x-heroicon name="plus" class="h-7 w-7" />
                        Sell
                    </a>

                    <a href="{{ $mobileChatUrl }}" class="group relative flex min-h-[54px] flex-col items-center justify-center gap-1 rounded-2xl px-1 text-[10px] font-black uppercase tracking-wide transition {{ request()->routeIs('chat.*') ? 'bg-orange-50 text-orange-600 shadow-sm shadow-orange-100/80' : 'text-slate-500 hover:bg-slate-100/80' }}">
                        @if (request()->routeIs('chat.*'))
                            <span class="absolute -top-1 h-1 w-7 rounded-full bg-gradient-to-r from-orange-500 to-amber-400"></span>
                        @endif
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl {{ request()->routeIs('chat.*') ? 'bg-white ring-1 ring-orange-200' : 'bg-slate-100/90 group-hover:bg-white' }}">
                            <x-heroicon name="chat-bubble-left-right" class="h-6 w-6" />
                        </span>
                        @if (auth()->check())
                            <span data-notification-badge-mobile class="absolute right-1 top-1 rounded-full bg-orange-500 px-1.5 py-0.5 text-[10px] font-bold text-white {{ $initialUnreadNotifications > 0 ? '' : 'hidden' }}">
                                {{ $initialUnreadNotifications > 99 ? '99+' : $initialUnreadNotifications }}
                            </span>
                        @endif
                        Chat
                    </a>

                    <a href="{{ $mobileProfileUrl }}" class="group relative flex min-h-[54px] flex-col items-center justify-center gap-1 rounded-2xl px-1 text-[10px] font-black uppercase tracking-wide transition {{ request()->routeIs('menu.*') || request()->routeIs('profile.*') ? 'bg-orange-50 text-orange-600 shadow-sm shadow-orange-100/80' : 'text-slate-500 hover:bg-slate-100/80' }}">
                        @if (request()->routeIs('menu.*') || request()->routeIs('profile.*'))
                            <span class="absolute -top-1 h-1 w-7 rounded-full bg-gradient-to-r from-orange-500 to-amber-400"></span>
                        @endif
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl {{ request()->routeIs('menu.*') || request()->routeIs('profile.*') ? 'bg-white ring-1 ring-orange-200' : 'bg-slate-100/90 group-hover:bg-white' }}">
                            <x-heroicon name="user-circle" class="h-6 w-6" />
                        </span>
                        Profile
                    </a>
                </div>
            </div>
        </div>
    </div>
@endunless
