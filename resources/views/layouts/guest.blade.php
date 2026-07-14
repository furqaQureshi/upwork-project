@php
    $siteName = (string) setting('site_name', config('app.name', 'Unsell'));
    $seoDescription = (string) setting('seo_meta_description', 'Buy and sell everything nearby with a complete mobile-first marketplace experience.');
    $seoKeywords = trim((string) setting('seo_meta_keywords', ''));
    $seoRobots = (string) setting('seo_robots', 'index,follow');
    $googleVerification = trim((string) setting('seo_google_site_verification', ''));
    $bingVerification = trim((string) setting('seo_bing_site_verification', ''));
    $googleAnalyticsId = trim((string) setting('seo_google_analytics_id', ''));
    $siteTagline = (string) setting('site_tagline', 'Marketplace PWA');
    $siteLogoSizePx = (int) setting('site_logo_size_px', 40);
    if ($siteLogoSizePx < 24) {
        $siteLogoSizePx = 24;
    }
    if ($siteLogoSizePx > 128) {
        $siteLogoSizePx = 128;
    }
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
    $siteFaviconUrl = branding_asset_url((string) setting('site_favicon', ''), asset('icons/icon.svg'));
    $supportEmail = trim((string) setting('contact_email', ''));
    $supportPhone = trim((string) setting('support_phone', ''));
    $adsEnabled = (bool) setting('adsense_enabled', false);
    $adsClientId = trim((string) setting('adsense_client_id', ''));
    $showAdsOnGuestPage = ! request()->routeIs('login', 'register', 'password.request');
    $adsRuntimeEnabled = $adsEnabled
        && $showAdsOnGuestPage
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
    $fcmApiKey = (string) setting('fcm_api_key', config('services.fcm.api_key', ''));
    $fcmProjectId = (string) setting('fcm_project_id', config('services.fcm.project_id', ''));
    $fcmMessagingSenderId = (string) setting('fcm_messaging_sender_id', config('services.fcm.messaging_sender_id', ''));
    $fcmAppId = (string) setting('fcm_app_id', config('services.fcm.app_id', ''));
    $firebaseAuthDomain = trim((string) setting('firebase_auth_domain', ''));
    $showAiAssistantFab = (bool) setting('ai_enabled', false)
        && (bool) setting('ai_compass_enabled', true);
@endphp

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

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#ea580c">
    <meta name="description" content="{{ $seoDescription }}">
    @if ($seoKeywords !== '')
        <meta name="keywords" content="{{ $seoKeywords }}">
    @endif
    <meta name="robots" content="{{ $seoRobots }}">
    <link rel="canonical" href="{{ url()->current() }}">

    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $siteName }}">
    <meta property="og:description" content="{{ $seoDescription }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:site_name" content="{{ $siteName }}">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $siteName }}">
    <meta name="twitter:description" content="{{ $seoDescription }}">

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
    <meta name="fcm-api-key" content="{{ $fcmApiKey }}">
    <meta name="fcm-project-id" content="{{ $fcmProjectId }}">
    <meta name="fcm-messaging-sender-id" content="{{ $fcmMessagingSenderId }}">
    <meta name="fcm-app-id" content="{{ $fcmAppId }}">
    <meta name="firebase-auth-domain" content="{{ $firebaseAuthDomain }}">

    @if ($googleVerification !== '')
        <meta name="google-site-verification" content="{{ $googleVerification }}">
    @endif
    @if ($bingVerification !== '')
        <meta name="msvalidate.01" content="{{ $bingVerification }}">
    @endif

    <title>{{ $siteName }}</title>

    <link rel="manifest" href="{{ route('pwa.manifest') }}">
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
<body class="antialiased">
    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-5GJGR53R"
    height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->

    @include('layouts.partials.pull-to-refresh')
    <div class="relative flex min-h-screen items-center justify-center px-4 py-8">
        <span class="floating-dot -left-10 top-24 h-32 w-32 bg-orange-300/45"></span>
        <span class="floating-dot right-10 top-10 h-20 w-20 bg-teal-300/45" style="animation-delay: 1.2s;"></span>

        <div class="w-full max-w-md">
            <a href="{{ route('home') }}" class="mb-6 inline-flex items-center gap-3">
                <x-application-logo class="h-auto w-auto" style="width: {{ $siteLogoSizePx }}px; height: {{ $siteLogoSizePx }}px;" />
                <div>
                    @if ($hasTwoPartBrandText)
                        <p class="font-display text-2xl font-bold" data-brand-title="split">
                            <span style="color: {{ $brandTextColorOne }};">{{ $brandTextPartOne }}</span><span style="color: {{ $brandTextColorTwo }};">{{ $brandTextSpacer }}{{ $brandTextPartTwo }}</span>
                        </p>
                    @else
                        <p class="font-display text-2xl font-bold text-slate-900">{{ $siteName }}</p>
                    @endif
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-orange-600">{{ $siteTagline }}</p>
                </div>
            </a>

            @if ($showAdsOnGuestPage)
                <x-google-ad location="guest" containerClass="mb-4" />
            @endif

            <div class="glass-panel p-6 sm:p-8">
                {{ $slot }}
            </div>

            @if ($supportEmail !== '' || $supportPhone !== '')
                <div class="mt-3 rounded-2xl border border-slate-200 bg-white/80 px-4 py-3 text-xs text-slate-600">
                    Support:
                    @if ($supportEmail !== '')
                        <a href="mailto:{{ $supportEmail }}" class="font-semibold text-orange-600">{{ $supportEmail }}</a>
                    @endif
                    @if ($supportEmail !== '' && $supportPhone !== '')
                        <span class="mx-1">|</span>
                    @endif
                    @if ($supportPhone !== '')
                        <a href="tel:{{ preg_replace('/[^0-9\+]/', '', $supportPhone) }}" class="font-semibold text-orange-600">{{ $supportPhone }}</a>
                    @endif
                </div>
            @endif
        </div>

        @include('layouts.startup-popup')

        @if ($showAiAssistantFab)
            <a href="{{ route('login') }}"
               class="fixed bottom-24 right-4 z-40 inline-flex items-center gap-2 rounded-full bg-teal-700 px-4 py-3 text-xs font-bold uppercase tracking-wide text-white shadow-lg shadow-teal-900/25 transition hover:bg-teal-800 sm:bottom-6 sm:right-6"
               aria-label="Open AI Assistant (login required)">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 8V4H8" />
                    <rect x="4" y="8" width="16" height="12" rx="3" />
                    <path d="M9 14h6" />
                    <path d="M12 11v6" />
                </svg>
                AI Assistant
            </a>
        @endif
    </div>
</body>
</html>
