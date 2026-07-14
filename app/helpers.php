<?php

if (! function_exists('setting')) {
    /**
     * Retrieve a typed app setting from the database (cached).
     * Falls back to $default when the key doesn't exist or the
     * app_settings table hasn't been migrated yet.
     */
    function setting(string $key, mixed $default = null): mixed
    {
        try {
            return \App\Models\AppSetting::get($key, $default);
        } catch (\Throwable) {
            return $default;
        }
    }
}

if (! function_exists('branding_asset_url')) {
    /**
     * Resolve a branding asset value to a browser-safe URL.
     */
    function branding_asset_url(?string $value, ?string $fallback = null): ?string
    {
        $normalized = trim((string) $value);

        if ($normalized === '') {
            return $fallback;
        }

        if (
            preg_match('/^https?:\/\//i', $normalized) === 1
            || str_starts_with($normalized, 'data:')
            || str_starts_with($normalized, '/')
        ) {
            return $normalized;
        }

        if (str_starts_with($normalized, 'storage/')) {
            return '/'.ltrim($normalized, '/');
        }

        if (\Illuminate\Support\Facades\Storage::disk('public')->exists($normalized)) {
            return \Illuminate\Support\Facades\Storage::url($normalized);
        }

        return asset($normalized);
    }
}

if (! function_exists('adsense_config')) {
    /**
     * Retrieve AdSense configuration as an array.
     */
    function adsense_config(): array
    {
        return [
            'enabled' => (bool) setting('adsense_enabled', false),
            'client_id' => trim((string) setting('adsense_client_id', '')),
            'interstitial_enabled' => (bool) setting('adsense_interstitial_enabled', false),
            'interstitial_slot' => trim((string) setting('adsense_interstitial_slot_id', '')),
            'interstitial_clicks' => (int) setting('adsense_interstitial_clicks', 6),
            'reward_enabled' => (bool) setting('adsense_reward_enabled', false),
            'reward_slot' => trim((string) setting('adsense_reward_slot_id', '')),
            'reward_slot_secondary' => trim((string) setting('adsense_reward_slot_id_secondary', '')),
            'reward_clicks' => (int) setting('adsense_reward_clicks', 10),
            'app_open_slot' => trim((string) setting('adsense_app_open_slot_id', '')),
        ];
    }
}
