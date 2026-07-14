@php
    $siteName = (string) setting('site_name', config('app.name', 'Unsell'));
    $seoDescription = (string) setting('seo_meta_description', 'Buy and sell everything nearby with a complete mobile-first marketplace experience.');
    $seoKeywords = trim((string) setting('seo_meta_keywords', ''));
    $seoRobots = (string) setting('seo_robots', 'index,follow');
    $googleVerification = trim((string) setting('seo_google_site_verification', ''));
    $bingVerification = trim((string) setting('seo_bing_site_verification', ''));
    $googleAnalyticsId = trim((string) setting('seo_google_analytics_id', ''));
    $siteTagline = (string) setting('site_tagline', 'Buy & Sell Anything');
    $siteFaviconUrl = branding_asset_url((string) setting('site_favicon', ''), asset('icons/icon.svg'));
    $appleTouchIconUrl = branding_asset_url((string) setting('site_favicon', ''), branding_asset_url((string) setting('site_logo', ''), asset('icons/icon.svg')));
    $supportEmail = trim((string) setting('contact_email', ''));
    $supportPhone = trim((string) setting('support_phone', ''));
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
    $notificationSoundRaw = trim((string) setting('notification_sound_url', ''));
    $notificationSoundUrl = $notificationSoundRaw !== ''
        ? (preg_match('/^https?:\/\//i', $notificationSoundRaw) === 1
            ? $notificationSoundRaw
            : \Illuminate\Support\Facades\Storage::url($notificationSoundRaw))
        : '';
    $fcmApiKey = (string) setting('fcm_api_key', config('services.fcm.api_key', ''));
    $fcmProjectId = (string) setting('fcm_project_id', config('services.fcm.project_id', ''));
    $fcmMessagingSenderId = (string) setting('fcm_messaging_sender_id', config('services.fcm.messaging_sender_id', ''));
    $fcmAppId = (string) setting('fcm_app_id', config('services.fcm.app_id', ''));
    $fcmVapidKey = (string) setting('fcm_vapid_key', config('services.fcm.vapid_key', ''));
    $isChatPage = request()->routeIs('chat.show');
    $isImmersivePage = request()->routeIs('listings.create') || request()->routeIs('chat.show');
    $showAiAssistantFab = auth()->check()
        && (bool) setting('ai_enabled', false)
        && (bool) setting('ai_compass_enabled', true)
        && ! request()->routeIs('ai.compass');
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
    @auth
        <meta name="notifications-index-url" content="{{ route('notifications.index') }}">
        <meta name="notifications-read-url-template" content="{{ url('/notifications/__id__/read') }}">
        <meta name="notifications-read-all-url" content="{{ route('notifications.read-all') }}">
        <meta name="notifications-poll-seconds" content="{{ setting('notification_poll_seconds', config('featured_ads.notification_poll_seconds', 20)) }}">
        <meta name="push-subscribe-url" content="{{ route('push-subscriptions.store') }}">
        <meta name="push-unsubscribe-url" content="{{ route('push-subscriptions.destroy') }}">
        <meta name="notification-sound-url" content="{{ $notificationSoundUrl }}">
        <meta name="fcm-api-key" content="{{ $fcmApiKey }}">
        <meta name="fcm-project-id" content="{{ $fcmProjectId }}">
        <meta name="fcm-messaging-sender-id" content="{{ $fcmMessagingSenderId }}">
        <meta name="fcm-app-id" content="{{ $fcmAppId }}">
        <meta name="fcm-vapid-key" content="{{ $fcmVapidKey }}">
    @endauth
    <meta name="theme-color" content="#ea580c">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="{{ $siteName }}">
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

    @if ($googleVerification !== '')
        <meta name="google-site-verification" content="{{ $googleVerification }}">
    @endif
    @if ($bingVerification !== '')
        <meta name="msvalidate.01" content="{{ $bingVerification }}">
    @endif

    <title>{{ $siteName }}</title>

    <link rel="manifest" href="{{ route('pwa.manifest') }}">
    <link rel="icon" href="{{ $siteFaviconUrl }}">
    <link rel="apple-touch-icon" href="{{ $appleTouchIconUrl }}">

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
    <div class="relative min-h-screen {{ $isImmersivePage ? '' : 'safe-pb' }}">
        @include('layouts.navigation')

        @if (session('status'))
            <div class="mx-auto mt-4 w-full max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="app-card border-emerald-200 bg-emerald-50 text-sm font-medium text-emerald-700">
                    {{ session('status') }}
                </div>
            </div>
        @endif

        @isset($header)
            <header class="mx-auto w-full max-w-7xl px-4 pt-5 sm:px-6 lg:px-8">
                {{ $header }}
            </header>
        @endisset

        <main class="{{ $isChatPage ? 'w-full p-0' : 'mx-auto w-full max-w-7xl px-4 py-5 sm:px-6 lg:px-8' }}">
            {{ $slot }}
        </main>

        @unless ($isImmersivePage)
            <div class="mx-auto mb-4 w-full max-w-7xl px-4 sm:px-6 lg:px-8">
                <x-google-ad location="display" />
            </div>
        @endunless

        @unless ($isChatPage)
            <footer class="mx-auto w-full max-w-7xl px-4 pb-6 sm:px-6 lg:px-8">
                <div class="rounded-2xl border border-slate-200 bg-white/80 px-4 py-3 text-xs text-slate-600">
                    <div class="sm:flex sm:items-center sm:justify-between">
                        <p>
                            <span class="font-semibold text-slate-800">{{ $siteName }}</span>
                            <span class="ml-2">{{ $siteTagline }}</span>
                        </p>
                        @if ($supportEmail !== '' || $supportPhone !== '')
                            <p class="mt-2 sm:mt-0">
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
                            </p>
                        @endif
                    </div>

                    <div class="mt-3 flex flex-wrap gap-x-3 gap-y-1 text-[11px] font-semibold text-slate-500">
                        <a href="{{ route('legal.terms') }}" class="transition hover:text-orange-600">Terms</a>
                        <a href="{{ route('legal.privacy') }}" class="transition hover:text-orange-600">Privacy</a>
                        <a href="{{ route('legal.refund') }}" class="transition hover:text-orange-600">Refunds</a>
                        <a href="{{ route('legal.content-policy') }}" class="transition hover:text-orange-600">Content Policy</a>
                        <a href="{{ route('legal.data-deletion') }}" class="transition hover:text-orange-600">Data Deletion</a>
                    </div>
                </div>
            </footer>
        @endunless

        @include('layouts.startup-popup')

        @if ($showAiAssistantFab)
            <a href="{{ route('ai.compass') }}"
               class="fixed bottom-24 right-4 z-40 inline-flex items-center gap-2 rounded-full bg-teal-700 px-4 py-3 text-xs font-bold uppercase tracking-wide text-white shadow-lg shadow-teal-900/25 transition hover:bg-teal-800 sm:bottom-6 sm:right-6"
               aria-label="Open AI Assistant">
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
