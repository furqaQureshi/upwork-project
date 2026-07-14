<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <!-- Google Tag Manager -->
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','GTM-5GJGR53R');</script>
    <!-- End Google Tag Manager -->

    @php
        $siteName = (string) setting('site_name', config('app.name', 'Unsell'));
        $seoDescription = (string) setting('seo_meta_description', 'Buy and sell everything nearby with a complete mobile-first marketplace experience.');
        $seoRobots = (string) setting('seo_robots', 'index,follow');
        $adsEnabled = (bool) setting('adsense_enabled', false);
        $adsClientId = trim((string) setting('adsense_client_id', ''));
        $adsRuntimeEnabled = $adsEnabled
            && preg_match('/^ca-pub-\d+$/', $adsClientId) === 1
            && $adsClientId !== 'ca-pub-2738051455706993';
        $interstitialEnabled = (bool) setting('adsense_interstitial_enabled', false);
        $interstitialSlot = trim((string) setting('adsense_interstitial_slot_id', ''));
        $interstitialClicks = (int) setting('adsense_interstitial_clicks', 6);
        $rewardEnabled = (bool) setting('adsense_reward_enabled', false);
        $rewardSlot = trim((string) setting('adsense_reward_slot_id', ''));
        $rewardSecondarySlot = trim((string) setting('adsense_reward_slot_id_secondary', ''));
        $rewardClicks = (int) setting('adsense_reward_clicks', 10);
        $appOpenSlot = trim((string) setting('adsense_app_open_slot_id', ''));
        $googleAnalyticsId = trim((string) setting('seo_google_analytics_id', ''));
        $siteLogoSizePx = (int) setting('site_logo_size_px', 40);
        if ($siteLogoSizePx < 24) {
            $siteLogoSizePx = 24;
        }
        if ($siteLogoSizePx > 128) {
            $siteLogoSizePx = 128;
        }
        $siteFaviconUrl = branding_asset_url((string) setting('site_favicon', ''), asset('icons/icon.svg'));
    @endphp
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#10242f">
    <meta name="description" content="{{ $seoDescription }}">
    <meta name="robots" content="{{ $seoRobots }}">
    <link rel="canonical" href="{{ url()->current() }}">
    <meta name="ads-runtime-enabled" content="{{ $adsRuntimeEnabled ? '1' : '0' }}">
    <meta name="adsense-client-id" content="{{ $adsClientId }}">
    <meta name="ads-interstitial-enabled" content="{{ $interstitialEnabled ? '1' : '0' }}">
    <meta name="ads-interstitial-slot" content="{{ $interstitialSlot }}">
    <meta name="ads-interstitial-clicks" content="{{ max(1, $interstitialClicks) }}">
    <meta name="ads-reward-enabled" content="{{ $rewardEnabled ? '1' : '0' }}">
    <meta name="ads-reward-slot" content="{{ $rewardSlot }}">
    <meta name="ads-reward-slot-secondary" content="{{ $rewardSecondarySlot }}">
    <meta name="ads-reward-clicks" content="{{ max(1, $rewardClicks) }}">
    <meta name="ads-app-open-slot" content="{{ $appOpenSlot }}">

    <title>Admin | {{ $siteName }}</title>
    <link rel="icon" href="{{ $siteFaviconUrl }}">

    @if ($adsRuntimeEnabled)
        <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client={{ $adsClientId }}" crossorigin="anonymous"></script>
    @endif

    @if ($googleAnalyticsId !== '')
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $googleAnalyticsId }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag() { dataLayer.push(arguments); }
            gtag('js', new Date());
            gtag('config', '{{ $googleAnalyticsId }}');
        </script>
    @endif

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#ecf1f5] text-slate-900 antialiased">
    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-5GJGR53R"
    height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->

    <div x-data="{ sidebarOpen: false }" class="min-h-screen lg:grid lg:grid-cols-[280px_1fr]">
        <aside class="fixed inset-y-0 left-0 z-50 w-72 -translate-x-full border-r border-slate-200 bg-slate-900 p-5 text-slate-200 transition lg:static lg:translate-x-0" :class="sidebarOpen ? 'translate-x-0' : ''">
            <div class="flex items-center justify-between">
                <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center gap-2">
                    <x-application-logo class="h-auto w-auto" style="width: {{ $siteLogoSizePx }}px; height: {{ $siteLogoSizePx }}px;" />
                    <div>
                        <p class="font-display text-lg font-bold text-white">Admin Studio</p>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-orange-300">{{ setting('site_tagline', setting('site_name', 'Unsell')) }}</p>
                    </div>
                </a>
                <button @click="sidebarOpen = false" class="rounded-lg p-2 text-slate-400 lg:hidden">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <nav class="mt-8 space-y-1">
                <p class="mb-1 px-3 text-[10px] font-bold uppercase tracking-widest text-slate-500">Main</p>
                <a href="{{ route('admin.dashboard') }}" class="block rounded-xl px-3 py-2 text-sm font-semibold {{ request()->routeIs('admin.dashboard') ? 'bg-orange-500 text-white' : 'text-slate-300 hover:bg-slate-800' }}">Dashboard</a>
                <a href="{{ route('admin.listings.index') }}" class="block rounded-xl px-3 py-2 text-sm font-semibold {{ request()->routeIs('admin.listings.*') ? 'bg-orange-500 text-white' : 'text-slate-300 hover:bg-slate-800' }}">Listings</a>
                <a href="{{ route('admin.categories.index') }}" class="block rounded-xl px-3 py-2 text-sm font-semibold {{ request()->routeIs('admin.categories.*') ? 'bg-orange-500 text-white' : 'text-slate-300 hover:bg-slate-800' }}">Categories</a>
                <a href="{{ route('admin.custom-fields.index') }}" class="block rounded-xl px-3 py-2 text-sm font-semibold {{ request()->routeIs('admin.custom-fields.*') ? 'bg-orange-500 text-white' : 'text-slate-300 hover:bg-slate-800' }}">Custom Fields</a>

                <p class="mb-1 mt-4 px-3 text-[10px] font-bold uppercase tracking-widest text-slate-500">People</p>
                <a href="{{ route('admin.users.index') }}" class="block rounded-xl px-3 py-2 text-sm font-semibold {{ request()->routeIs('admin.users.*') ? 'bg-orange-500 text-white' : 'text-slate-300 hover:bg-slate-800' }}">Users &amp; Push QA</a>
                <a href="{{ route('admin.push-notifications.create') }}" class="block rounded-xl px-3 py-2 text-sm font-semibold {{ request()->routeIs('admin.push-notifications.*') ? 'bg-orange-500 text-white' : 'text-slate-300 hover:bg-slate-800' }}">Custom Push</a>
                <a href="{{ route('admin.push-delivery-logs.index') }}" class="block rounded-xl px-3 py-2 text-sm font-semibold {{ request()->routeIs('admin.push-delivery-logs.*') ? 'bg-orange-500 text-white' : 'text-slate-300 hover:bg-slate-800' }}">FCM Delivery Logs</a>
                <a href="{{ route('admin.sellers.index') }}" class="block rounded-xl px-3 py-2 text-sm font-semibold {{ request()->routeIs('admin.sellers.*') ? 'bg-orange-500 text-white' : 'text-slate-300 hover:bg-slate-800' }}">Seller Management</a>
                <a href="{{ route('admin.seller-verification.index') }}" class="block rounded-xl px-3 py-2 text-sm font-semibold {{ request()->routeIs('admin.seller-verification.*') ? 'bg-orange-500 text-white' : 'text-slate-300 hover:bg-slate-800' }}">Seller Verification</a>

                <p class="mb-1 mt-4 px-3 text-[10px] font-bold uppercase tracking-widest text-slate-500">Config</p>
                <a href="{{ route('admin.settings.index') }}" class="block rounded-xl px-3 py-2 text-sm font-semibold {{ request()->routeIs('admin.settings.*') ? 'bg-orange-500 text-white' : 'text-slate-300 hover:bg-slate-800' }}">Settings</a>
                <a href="{{ route('admin.legal-content.index') }}" class="block rounded-xl px-3 py-2 text-sm font-semibold {{ request()->routeIs('admin.legal-content.*') ? 'bg-orange-500 text-white' : 'text-slate-300 hover:bg-slate-800' }}">Legal Content</a>
                <a href="{{ route('admin.free-post-limits.index') }}" class="block rounded-xl px-3 py-2 text-sm font-semibold {{ request()->routeIs('admin.free-post-limits.*') ? 'bg-orange-500 text-white' : 'text-slate-300 hover:bg-slate-800' }}">Free Post Limits</a>
                <a href="{{ route('admin.subscription-packages.index') }}" class="block rounded-xl px-3 py-2 text-sm font-semibold {{ request()->routeIs('admin.subscription-packages.*') ? 'bg-orange-500 text-white' : 'text-slate-300 hover:bg-slate-800' }}">Subscription Packages</a>
                <a href="{{ route('admin.seller-verification.statistics') }}" class="block rounded-xl px-3 py-2 text-sm font-semibold {{ request()->routeIs('admin.seller-verification.statistics') ? 'bg-orange-500 text-white' : 'text-slate-300 hover:bg-slate-800' }}">Verification Statistics</a>

                <p class="mb-1 mt-4 px-3 text-[10px] font-bold uppercase tracking-widest text-slate-500">Site</p>
                <a href="{{ route('home') }}" class="block rounded-xl px-3 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800">Open Marketplace</a>
            </nav>

            <div class="mt-8 rounded-2xl bg-slate-800 p-3">
                <p class="text-xs uppercase tracking-wide text-slate-400">Signed in as</p>
                <p class="mt-1 text-sm font-semibold text-white">{{ auth()->user()->name }}</p>
                <p class="text-xs text-slate-300">{{ auth()->user()->email }}</p>
            </div>

            <form method="POST" action="{{ route('logout') }}" class="mt-4">
                @csrf
                <button type="submit" class="w-full rounded-xl bg-rose-600 px-3 py-2 text-sm font-semibold text-white">Logout</button>
            </form>
        </aside>

        <div class="min-w-0">
            <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/80 px-4 py-3 backdrop-blur sm:px-6">
                <div class="flex items-center justify-between">
                    <button @click="sidebarOpen = true" class="rounded-xl border border-slate-200 bg-white p-2 lg:hidden">
                        <svg class="h-6 w-6 text-slate-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Admin Control Room</p>
                        <h1 class="font-display text-2xl font-bold text-slate-900">@yield('title', 'Dashboard')</h1>
                    </div>
                    <a href="{{ route('home') }}" class="app-btn-muted hidden sm:inline-flex">Marketplace</a>
                </div>
            </header>

            <main class="space-y-5 p-4 sm:p-6">
                @if (session('status'))
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                        {{ session('status') }}
                    </div>
                @endif

                <x-google-ad location="top" />

                @yield('content')

                <x-google-ad location="bottom" />
            </main>
        </div>
    </div>
</body>
</html>
