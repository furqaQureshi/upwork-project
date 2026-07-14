@php
    $siteName = (string) setting('site_name', config('app.name', 'Unsell'));
    $siteLogoUrl = branding_asset_url((string) setting('site_logo', ''));
@endphp

@if ($siteLogoUrl)
    <img src="{{ $siteLogoUrl }}" alt="{{ $siteName }}" {{ $attributes->merge(['class' => 'object-contain']) }}>
@else
    <svg viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" {{ $attributes }}>
        <defs>
            <linearGradient id="unisellGradient" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0%" stop-color="#fb923c" />
                <stop offset="100%" stop-color="#ea580c" />
            </linearGradient>
        </defs>
        <rect x="4" y="4" width="56" height="56" rx="18" fill="url(#unisellGradient)" />
        <circle cx="24" cy="24" r="8" fill="#fff4e6" />
        <circle cx="40" cy="40" r="8" fill="#fde68a" />
        <path d="M21 43l22-22" stroke="#fff" stroke-width="4" stroke-linecap="round" />
    </svg>
@endif
