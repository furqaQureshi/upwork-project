@props([
    'location' => 'top',
    'containerClass' => '',
    'showPlaceholder' => false,
    'placeholderText' => 'Coming soon',
])

@php
    $adsEnabled = (bool) setting('adsense_enabled', false);
    $adsClientId = trim((string) setting('adsense_client_id', ''));
    $hasValidAdsClientId = preg_match('/^ca-pub-\d+$/', $adsClientId) === 1
        && $adsClientId !== 'ca-pub-2738051455706993';
    $fallbackSlotId = trim((string) setting('adsense_slot_id', ''));
    $slotByLocation = [
        'top' => trim((string) setting('adsense_banner_slot_top', '')),
        'bottom' => trim((string) setting('adsense_banner_slot_bottom', '')),
        'guest' => trim((string) setting('adsense_banner_slot_guest', '')),
        'native' => trim((string) setting('adsense_native_slot_id', '')),
        'feed' => trim((string) setting('adsense_native_slot_id', '')),
        'article' => trim((string) setting('adsense_article_slot_id', '')),
        'display' => trim((string) setting('adsense_display_slot_id', '')),
    ];
    $adsSlotId = $slotByLocation[$location] ?: $fallbackSlotId;
    
    $rawLocations = setting('adsense_locations', null);
    $defaultLocations = ['display', 'guest', 'feed', 'article', 'native', 'top', 'bottom'];
    
    // Handle both JSON string and array formats
    $parsedLocations = null;
    if (is_string($rawLocations)) {
        $decoded = json_decode($rawLocations, true);
        $parsedLocations = is_array($decoded) ? $decoded : null;
    } elseif (is_array($rawLocations)) {
        $parsedLocations = $rawLocations;
    }
    
    $adsLocations = (is_array($parsedLocations) && count($parsedLocations) > 0)
        ? $parsedLocations
        : $defaultLocations;
    
    // always map 'native' → 'feed' alias
    if (in_array('native', $adsLocations, true) && ! in_array('feed', $adsLocations, true)) {
        $adsLocations[] = 'feed';
    }

    $shouldRender = $adsEnabled
        && $hasValidAdsClientId
        && $adsSlotId !== ''
        && in_array($location, $adsLocations, true);
@endphp

@if ($shouldRender)
    <section class="rounded-3xl border border-slate-200/80 bg-white/75 p-3 shadow-sm {{ $containerClass }}" data-ad-banner="{{ $location }}">
        <ins
            class="adsbygoogle"
            style="display:block"
            data-ad-client="{{ $adsClientId }}"
            data-ad-slot="{{ $adsSlotId }}"
            data-ad-format="auto"
            data-full-width-responsive="true"
        ></ins>
        <script>
            (adsbygoogle = window.adsbygoogle || []).push({});
        </script>
    </section>
@elseif ($showPlaceholder)
    <section class="rounded-3xl border border-dashed border-slate-300 bg-slate-50/80 p-4 text-center shadow-sm {{ $containerClass }}" data-ad-placeholder="{{ $location }}">
        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Google Ad Grid Area</p>
        <p class="mt-1 text-xs font-semibold text-slate-500">{{ $placeholderText }}</p>
        <p class="mt-2 text-[9px] text-slate-400">
            [Debug: enabled={{ $adsEnabled ? 'yes' : 'no' }}, clientId={{ $adsClientId }}, slot={{ $adsSlotId }}, location={{ $location }}, inLocations={{ in_array($location, $adsLocations, true) ? 'yes' : 'no' }}, allowedLocations={{ implode(',', $adsLocations) }}]
        </p>
    </section>
@else
    <!-- Ad not rendered - Debug: enabled={{ $adsEnabled ? 'yes' : 'no' }}, clientId={{ $adsClientId }}, slot={{ $adsSlotId }}, location={{ $location }}, inLocations={{ in_array($location, $adsLocations, true) ? 'yes' : 'no' }}, allowedLocations={{ implode(',', $adsLocations) }} -->
@endif
