<?php

namespace App\Services\Admin;

use App\Models\AppSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class SettingsIndexViewData
{
    public function toArray(?Collection $settings = null): array
    {
        $settings ??= AppSetting::all()->keyBy('key');

        $val = fn(string $key, mixed $fallback = null) => $settings->get($key)?->value ?? $fallback;
        $field = fn(string $key, mixed $fallback = null) => old($key, $val($key, $fallback));
        $bool = fn(string $key) => filter_var($settings->get($key)?->value ?? '0', FILTER_VALIDATE_BOOLEAN);
        $oldBool = fn(string $key) => filter_var((string) old($key, $bool($key) ? '1' : '0'), FILTER_VALIDATE_BOOLEAN);

        $allowedDays = $this->prepareAllowedDays($val);
        $adLocations = $this->prepareAdLocations($val);
        $homeBannerImages = $this->prepareHomeBannerImages($val);
        $homeBannerDisplaySeconds = $this->normalizeDisplaySeconds($field('home_banner_display_seconds', 5));
        $appBannerImages = $this->prepareAppBannerImages($val);
        $appBannerDisplaySeconds = $this->normalizeDisplaySeconds($field('app_banner_display_seconds', $homeBannerDisplaySeconds));
        $brandTextSpacingValue = $this->normalizeBrandSpacing($field);
        $savedAppUrl = $this->getSavedAppUrl($val);
        $startupPopupStyleValue = $this->normalizeStartupPopupStyle($field);

        return [
            'settings' => $settings,
            'field' => $field,
            'bool' => $bool,
            'oldBool' => $oldBool,
            'tabs' => $this->getTabs(),
            'activeTab' => old('settings_section', session('settings_section', 'general')),
            'allowedDays' => $allowedDays,
            'adLocations' => $adLocations,
            'supportFaqsText' => $this->prepareSupportFaqs($val),
            'aiSeoLastKeywords' => $this->prepareJsonArray($val, 'ai_seo_last_keywords'),
            'aiSeoLastActions' => $this->prepareJsonArray($val, 'ai_seo_last_actions'),
            'twaFingerprintsText' => $this->prepareTwaFingerprints($val),
            'keystorePasswordSet' => trim((string) $val('twa_keystore_password', '')) !== '',
            'keyPasswordSet' => trim((string) $val('twa_key_password', '')) !== '',
            'homeBannerImages' => $homeBannerImages,
            'homeBannerPositions' => $this->prepareHomeBannerPositions($val, $homeBannerImages),
            'homeBannerFits' => $this->prepareHomeBannerFits($val, $homeBannerImages),
            'homeBannerDisplaySeconds' => $homeBannerDisplaySeconds,
            'appBannerImages' => $appBannerImages,
            'appBannerDisplaySeconds' => $appBannerDisplaySeconds,
            'bannerPreviewStorageBaseUrl' => rtrim(Storage::url(''), '/'),
            'siteLogoValue' => (string) $field('site_logo'),
            'siteFaviconValue' => (string) $field('site_favicon'),
            'siteLogoPreviewUrl' => $this->getMediaUrl($field('site_logo')),
            'siteFaviconPreviewUrl' => $this->getMediaUrl($field('site_favicon')),
            'homeBannerSlides' => $this->prepareHomeBannerSlides($field),
            'brandTextPartOneValue' => (string) $field('site_brand_text_part_1'),
            'brandTextPartTwoValue' => (string) $field('site_brand_text_part_2'),
            'brandTextColorOneValue' => $this->normalizeBrandColor($field('site_brand_text_color_1', '#0F172A'), '#0F172A'),
            'brandTextColorTwoValue' => $this->normalizeBrandColor($field('site_brand_text_color_2', '#F97316'), '#F97316'),
            'brandTextSpacingValue' => $brandTextSpacingValue,
            'brandPreviewPartOne' => $this->getBrandPreviewPartOne($field),
            'brandPreviewPartTwo' => trim((string) $field('site_brand_text_part_2')),
            'brandPreviewSpacing' => $brandTextSpacingValue === 'space' ? ' ' : '',
            'logoSizePxValue' => $this->normalizeLogoSize($field),
            'savedAppUrl' => $savedAppUrl,
            'appUrlFieldValue' => $this->getAppUrlFieldValue($field, $val),
            'manifestRuntimeUrl' => $savedAppUrl . '/manifest.webmanifest',
            'assetlinksRuntimeUrl' => $savedAppUrl . '/.well-known/assetlinks.json',
            'activeGoogleAnalyticsId' => strtoupper(trim((string) $val('seo_google_analytics_id', ''))),
            'notificationSoundValue' => trim((string) $field('notification_sound_url')),
            'notificationSoundPreviewUrl' => $this->getMediaUrl($field('notification_sound_url')),
            'startupPopupImageValue' => trim((string) $field('startup_popup_image_url')),
            'startupPopupPreviewUrl' => $this->getMediaUrl($field('startup_popup_image_url')),
            'startupPopupLinkValue' => trim((string) $field('startup_popup_link_url')),
            'startupPopupStyleValue' => $startupPopupStyleValue,
            'startupPopupPreviewCardClasses' => $this->getStartupPopupPreviewCardClasses($startupPopupStyleValue),
            'startupPopupPreviewButtonClasses' => $this->getStartupPopupPreviewButtonClasses($startupPopupStyleValue),
        ];
    }

    private function prepareAllowedDays(callable $val): array
    {
        $allowedDaysInput = old('featured_allowed_days');
        if (is_array($allowedDaysInput)) {
            return array_values(array_map('intval', $allowedDaysInput));
        }

        $default = [3, 7, 15, 30];
        $stored = json_decode($val('featured_allowed_days', json_encode($default)), true);

        return is_array($stored) ? $stored : $default;
    }

    private function prepareAdLocations(callable $val): array
    {
        $adLocationsInput = old('adsense_locations');
        if (is_array($adLocationsInput)) {
            $locations = array_values(array_map('strval', $adLocationsInput));
        } else {
            $default = ['display', 'guest', 'feed', 'article'];
            $locations = json_decode($val('adsense_locations', json_encode($default)), true) ?: $default;
        }

        if (in_array('native', $locations, true) && ! in_array('feed', $locations, true)) {
            $locations[] = 'feed';
        }

        return $locations;
    }

    private function prepareSupportFaqs(callable $val): string
    {
        $rawSupportFaqs = json_decode((string) $val('support_faqs', '[]'), true);
        if (! is_array($rawSupportFaqs)) {
            $rawSupportFaqs = [];
        }

        $supportFaqLines = collect($rawSupportFaqs)
            ->map(static function ($entry): string {
                $question = trim((string) data_get($entry, 'question', ''));
                $answer = trim((string) data_get($entry, 'answer', ''));

                return $question !== '' && $answer !== '' ? $question . ' || ' . $answer : '';
            })
            ->filter(static fn(string $line): bool => $line !== '')
            ->values()
            ->all();

        return old('support_faqs_text', implode(PHP_EOL, $supportFaqLines));
    }

    private function prepareJsonArray(callable $val, string $key): array
    {
        $input = old($key);
        if (is_array($input)) {
            return array_values(array_map('strval', $input));
        }

        $stored = json_decode((string) $val($key, '[]'), true);

        return is_array($stored) ? $stored : [];
    }

    private function prepareTwaFingerprints(callable $val): string
    {
        $storedFingerprints = json_decode((string) $val('twa_sha256_fingerprints', '[]'), true);
        if (! is_array($storedFingerprints)) {
            $storedFingerprints = [];
        }

        $fingerprintInput = old('twa_sha256_fingerprints_text');

        return is_string($fingerprintInput)
            ? $fingerprintInput
            : implode(PHP_EOL, array_values(array_map('strval', $storedFingerprints)));
    }

    private function prepareHomeBannerImages(callable $val): array
    {
        $rawImages = old('home_banner_images');
        if (! is_array($rawImages)) {
            $rawImages = json_decode((string) $val('home_banner_images', '[]'), true);
        }
        if (! is_array($rawImages)) {
            $rawImages = [];
        }

        $images = array_values(array_filter(
            array_map(fn($value): string => trim((string) $value), $rawImages),
            fn(string $value): bool => $value !== ''
        ));
        $legacyImage = trim((string) old('home_banner_image_url', $val('home_banner_image_url', '')));

        if ($images === [] && $legacyImage !== '') {
            $images[] = $legacyImage;
        }
        if ($images === []) {
            $images[] = '';
        }

        return $images;
    }

    private function prepareHomeBannerPositions(callable $val, array $images): array
    {
        $allowedPositions = ['center', 'top', 'bottom', 'left', 'right'];
        $rawPositions = old('home_banner_image_positions');
        if (! is_array($rawPositions)) {
            $rawPositions = json_decode((string) $val('home_banner_image_positions', '[]'), true);
        }
        if (! is_array($rawPositions)) {
            $rawPositions = [];
        }

        $positions = array_values(array_map(
            fn($value): string => in_array((string) $value, $allowedPositions, true) ? (string) $value : 'center',
            $rawPositions
        ));

        while (count($positions) < count($images)) {
            $positions[] = 'center';
        }

        return $positions;
    }

    private function prepareHomeBannerFits(callable $val, array $images): array
    {
        $rawFits = old('home_banner_image_fits');
        if (! is_array($rawFits)) {
            $rawFits = json_decode((string) $val('home_banner_image_fits', '[]'), true);
        }
        if (! is_array($rawFits)) {
            $rawFits = [];
        }

        $fits = array_values(array_map(
            fn($value): string => in_array((string) $value, ['cover', 'contain'], true) ? (string) $value : 'cover',
            $rawFits
        ));

        while (count($fits) < count($images)) {
            $fits[] = 'cover';
        }

        return $fits;
    }

    private function prepareAppBannerImages(callable $val): array
    {
        $rawImages = old('app_banner_images');
        if (! is_array($rawImages)) {
            $rawImages = json_decode((string) $val('app_banner_images', '[]'), true);
        }
        if (! is_array($rawImages)) {
            $rawImages = [];
        }

        return array_values(array_filter(
            array_map(fn($value): string => trim((string) $value), $rawImages),
            fn(string $value): bool => $value !== ''
        ));
    }

    private function normalizeBrandColor(mixed $value, string $fallback): string
    {
        $normalized = strtoupper(trim((string) $value));

        return preg_match('/^#[0-9A-F]{6}$/', $normalized) === 1 ? $normalized : $fallback;
    }

    private function normalizeBrandSpacing(callable $field): string
    {
        $spacing = strtolower(trim((string) $field('site_brand_text_spacing', 'none')));

        return in_array($spacing, ['none', 'space'], true) ? $spacing : 'none';
    }

    private function getBrandPreviewPartOne(callable $field): string
    {
        $partOne = trim((string) $field('site_brand_text_part_1'));

        return $partOne !== '' ? $partOne : (string) $field('site_name', 'Unsell');
    }

    private function normalizeLogoSize(callable $field): int
    {
        $size = (int) $field('site_logo_size_px', 40);

        return min(128, max(24, $size));
    }

    private function normalizeDisplaySeconds(mixed $value): int
    {
        $seconds = (int) $value;

        return $seconds < 1 || $seconds > 60 ? 5 : $seconds;
    }

    private function normalizeStartupPopupStyle(callable $field): string
    {
        $style = strtolower(trim((string) $field('startup_popup_style', 'premium')));

        return in_array($style, ['minimal', 'premium', 'festive'], true) ? $style : 'premium';
    }

    private function getSavedAppUrl(callable $val): string
    {
        $url = rtrim(trim((string) $val('app_url', config('app.url', url('/')))), '/');

        return $url !== '' ? $url : rtrim(url('/'), '/');
    }

    private function getAppUrlFieldValue(callable $field, callable $val): string
    {
        $savedUrl = $this->getSavedAppUrl($val);
        $fieldValue = trim((string) $field('app_url', $savedUrl));

        return $fieldValue !== '' ? $fieldValue : $savedUrl;
    }

    private function getMediaUrl(mixed $path): string
    {
        $path = (string) $path;
        if (trim($path) === '') {
            return '';
        }
        if (preg_match('/^https?:\/\//i', $path)) {
            return $path;
        }
        if (Storage::disk('public')->exists($path)) {
            return Storage::url($path);
        }

        return '';
    }

    private function getStartupPopupPreviewCardClasses(string $style): string
    {
        return match ($style) {
            'minimal' => 'mt-4 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm',
            'festive' => 'mt-4 overflow-hidden rounded-3xl border border-rose-200 bg-gradient-to-br from-rose-50 via-amber-50 to-orange-50 shadow-sm',
            default => 'mt-4 overflow-hidden rounded-3xl border border-orange-100 bg-white shadow-sm',
        };
    }

    private function getStartupPopupPreviewButtonClasses(string $style): string
    {
        return match ($style) {
            'minimal' => 'inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white',
            'festive' => 'inline-flex items-center justify-center rounded-2xl bg-gradient-to-r from-rose-500 via-orange-500 to-amber-500 px-4 py-2 text-sm font-semibold text-white',
            default => 'inline-flex items-center justify-center rounded-2xl bg-orange-500 px-4 py-2 text-sm font-semibold text-white',
        };
    }

    private function prepareHomeBannerSlides(callable $field): array
    {
        return [
            [
                'badge' => (string) $field('home_banner_slide_1_badge', 'Trending Deals'),
                'title' => (string) $field('home_banner_slide_1_title', 'Cars and bikes this week'),
                'desc' => (string) $field('home_banner_slide_1_desc', 'Browse top listings from nearby sellers and compare prices fast.'),
            ],
            [
                'badge' => (string) $field('home_banner_slide_2_badge', 'Smart Buying'),
                'title' => (string) $field('home_banner_slide_2_title', 'Verified listings, faster chats'),
                'desc' => (string) $field('home_banner_slide_2_desc', 'Message sellers instantly and close deals with confidence.'),
            ],
            [
                'badge' => (string) $field('home_banner_slide_3_badge', 'Post Instantly'),
                'title' => (string) $field('home_banner_slide_3_title', 'Sell in your city today'),
                'desc' => (string) $field('home_banner_slide_3_desc', 'Upload photos, set price, and publish your ad in under two minutes.'),
            ],
        ];
    }

    private function getTabs(): array
    {
        return [
            'general' => 'General',
            'support' => 'Support',
            'listings' => 'Listings',
            'featured' => 'Featured Ads',
            'registration' => 'Registration',
            'notifications' => 'Notifications',
            'twa' => 'TWA',
            'email' => 'Email / SMTP',
            'security' => 'Security',
            'marketing' => 'Marketing & SEO',
            'ai' => 'AI Suite',
            'license' => 'License',
            'app' => 'App Update',
        ];
    }
}
