<?php
// app/Http/Controllers/Admin/SettingsController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Services\AI\SeoRankService;
use App\Services\Admin\SettingsIndexViewData;
use App\Services\LicenseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    private function getSettingSchema(): array
    {
        return config('settings.schema', []);
    }

    private function getSectionKeys(): array
    {
        return config('settings.sections', []);
    }

    public function index(SettingsIndexViewData $viewData): View
    {
        return view('admin.settings.index', $viewData->toArray());
    }

    public function update(Request $request): RedirectResponse
    {
        $section = (string) $request->input('settings_section', 'general');

        $sectionKeys = $this->getSectionKeys();

        if (!array_key_exists($section, $sectionKeys)) {
            abort(422, 'Unknown settings section.');
        }

        $keysForSection = $sectionKeys[$section];
        $schema = $this->getSettingSchema();
        $rulesForSection = $this->buildValidationRules($section, $keysForSection);
        $validated = $request->validate($rulesForSection);

        $this->processSectionValidations($section, $request, $validated);

        foreach ($keysForSection as $key) {
            $meta = $schema[$key];

            if ($meta['type'] === 'boolean') {
                $rawValue = $request->has($key) ? $request->input($key) : '0';
                $value = filter_var($rawValue, FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
            } elseif ($meta['type'] === 'secret') {
                $rawSecret = trim((string) ($validated[$key] ?? $request->input($key) ?? ''));
                if ($rawSecret === '') {
                    continue;
                }
                $value = $rawSecret;
            } elseif ($meta['type'] === 'json') {
                $rawJson = array_values((array) ($validated[$key] ?? []));
                if ($key === 'featured_allowed_days') {
                    $rawJson = array_values(array_map('intval', $rawJson));
                } else {
                    $rawJson = array_values(array_map('strval', $rawJson));
                }
                $value = json_encode($rawJson);
            } elseif (array_key_exists($key, $validated)) {
                $value = (string) ($validated[$key] ?? '');
            } else {
                continue;
            }

            AppSetting::updateOrCreate(
                ['key' => $key],
                [
                    'value' => $value,
                    'type' => $meta['type'] === 'secret' ? 'string' : $meta['type'],
                    'group' => $meta['group'],
                ]
            );
        }

        AppSetting::clearCache();

        return back()
            ->with('status', ucfirst(str_replace('_', ' ', $section)) . ' settings saved successfully.')
            ->with('settings_section', $section);
    }

    public function runAiSeoAudit(SeoRankService $seoRankService): RedirectResponse
    {
        $result = $seoRankService->runAuditAndOptimize(true);

        $status = match ((string) ($result['status'] ?? '')) {
            'completed' => 'AI SEO audit completed. Score: ' . (int) ($result['score'] ?? 0) . '/100. Provider: ' . (string) ($result['provider'] ?? 'heuristic') . '.',
            default => (string) ($result['message'] ?? 'AI SEO audit was skipped.'),
        };

        return back()
            ->with('status', $status)
            ->with('settings_section', 'ai');
    }

    public function verifyLicense(Request $request, LicenseService $licenseService): RedirectResponse
    {
        $validated = $request->validate([
            'codecanyon_purchase_code' => 'required|string|max:100',
            'codecanyon_buyer_username' => 'required|string|max:50',
            'codecanyon_personal_token' => 'nullable|string|max:100',
        ]);

        $purchaseCode = trim($validated['codecanyon_purchase_code']);
        $buyerUsername = trim($validated['codecanyon_buyer_username']);

        $result = $licenseService->verifyPurchaseCode($purchaseCode, $buyerUsername);

        if ($result['valid']) {
            $licenseService->updateLicenseStatus(true, $result['data'] ?? []);
            if (!empty($validated['codecanyon_personal_token'])) {
                AppSetting::setValue('codecanyon_personal_token', encrypt($validated['codecanyon_personal_token']));
            }

            return back()
                ->with('status', 'License verified successfully!')
                ->with('settings_section', 'license');
        } else {
            return back()
                ->withErrors(['codecanyon_purchase_code' => $result['message']])
                ->with('settings_section', 'license');
        }
    }

    private function buildValidationRules(string $section, array $keysForSection): array
    {
        $allRules = $this->getAllValidationRules();

        $rulesForSection = [];
        foreach ($keysForSection as $key) {
            if (isset($allRules[$key])) {
                $rulesForSection[$key] = $allRules[$key];
            }
            $wildcardKey = $key . '.*';
            if (isset($allRules[$wildcardKey])) {
                $rulesForSection[$wildcardKey] = $allRules[$wildcardKey];
            }
        }

        if ($section === 'general') {
            $rulesForSection['app_banner_existing_images'] = ['nullable', 'array', 'max:10'];
            $rulesForSection['app_banner_existing_images.*'] = ['nullable', 'string', 'max:500'];
            $rulesForSection['app_banner_image_files'] = ['nullable', 'array', 'max:10'];
            $rulesForSection['app_banner_image_files.*'] = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:6144'];
            $rulesForSection['home_banner_image_files'] = ['nullable', 'array', 'max:10'];
            $rulesForSection['home_banner_image_files.*'] = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:6144'];
            $rulesForSection['home_banner_image_file'] = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:6144'];
        }

        if ($section === 'support') {
            $rulesForSection['support_faqs_text'] = ['nullable', 'string', 'max:30000'];
        }

        if ($section === 'notifications') {
            $rulesForSection['fcm_service_account_json_file'] = ['nullable', 'file', 'mimes:json,txt', 'max:1024'];
            $rulesForSection['notification_sound_file'] = ['nullable', 'file', 'mimes:mp3,wav,ogg,m4a', 'max:5120'];
            $rulesForSection['startup_popup_image_file'] = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:6144'];
        }

        if ($section === 'twa') {
            $rulesForSection['twa_sha256_fingerprints_text'] = ['nullable', 'string', 'max:6000'];
            $rulesForSection['twa_icon_file'] = ['nullable', 'image', 'mimes:png', 'max:2048'];
            $rulesForSection['twa_icon_maskable_file'] = ['nullable', 'image', 'mimes:png', 'max:2048'];
        }

        return $rulesForSection;
    }

    private function getAllValidationRules(): array
    {
        return [
            'site_name' => ['nullable', 'string', 'max:120'],
            'site_tagline' => ['nullable', 'string', 'max:200'],
            'site_logo' => ['nullable', 'string', 'max:500'],
            'site_favicon' => ['nullable', 'string', 'max:500'],
            'site_address' => ['nullable', 'string', 'max:300'],
            'contact_email' => ['nullable', 'email', 'max:200'],
            'support_phone' => ['nullable', 'string', 'max:30'],
            'site_currency' => ['nullable', 'string', 'max:10', Rule::in(['INR', 'USD', 'EUR', 'GBP', 'AED', 'SGD', 'AUD', 'CAD'])],
            'site_currency_symbol' => ['nullable', 'string', 'max:10'],
            'social_facebook' => ['nullable', 'url', 'max:500'],
            'social_instagram' => ['nullable', 'url', 'max:500'],
            'social_twitter' => ['nullable', 'url', 'max:500'],
            'social_whatsapp' => ['nullable', 'string', 'max:20'],
            'social_youtube' => ['nullable', 'url', 'max:500'],
            'app_google_play_url' => ['nullable', 'url', 'max:500'],
            'app_store_url' => ['nullable', 'url', 'max:500'],
            'maintenance_mode' => ['nullable', 'boolean'],
            'maintenance_title' => ['nullable', 'string', 'max:160'],
            'maintenance_message' => ['nullable', 'string', 'max:600'],
            'home_banner_mode' => ['nullable', 'string', Rule::in(['text', 'image'])],
            'home_banner_image_url' => ['nullable', 'string', 'max:500'],
            'home_banner_images' => ['nullable', 'array', 'max:10'],
            'home_banner_images.*' => ['nullable', 'string', 'max:500'],
            'home_banner_image_positions' => ['nullable', 'array', 'max:10'],
            'home_banner_image_positions.*' => ['nullable', 'string', Rule::in(['center', 'top', 'bottom', 'left', 'right'])],
            'home_banner_image_fits' => ['nullable', 'array', 'max:10'],
            'home_banner_image_fits.*' => ['nullable', 'string', Rule::in(['cover', 'contain'])],
            'home_banner_display_seconds' => ['nullable', 'integer', 'min:1', 'max:60'],
            'app_banner_images' => ['nullable', 'array', 'max:10'],
            'app_banner_images.*' => ['nullable', 'string', 'max:500'],
            'app_banner_display_seconds' => ['nullable', 'integer', 'min:1', 'max:60'],
            'home_banner_slide_1_badge' => ['nullable', 'string', 'max:80'],
            'home_banner_slide_1_title' => ['nullable', 'string', 'max:160'],
            'home_banner_slide_1_desc' => ['nullable', 'string', 'max:260'],
            'home_banner_slide_2_badge' => ['nullable', 'string', 'max:80'],
            'home_banner_slide_2_title' => ['nullable', 'string', 'max:160'],
            'home_banner_slide_2_desc' => ['nullable', 'string', 'max:260'],
            'home_banner_slide_3_badge' => ['nullable', 'string', 'max:80'],
            'home_banner_slide_3_title' => ['nullable', 'string', 'max:160'],
            'home_banner_slide_3_desc' => ['nullable', 'string', 'max:260'],
            'listing_moderation_enabled' => ['nullable', 'boolean'],
            'listing_allow_guest_view' => ['nullable', 'boolean'],
            'listing_expiry_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'listing_max_images' => ['nullable', 'integer', 'min:1', 'max:30'],
            'listing_max_per_user' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'listing_description_min' => ['nullable', 'integer', 'min:0', 'max:2000'],
            'listing_price_required' => ['nullable', 'boolean'],
            'listing_location_required' => ['nullable', 'boolean'],
            'listing_allow_negotiation' => ['nullable', 'boolean'],
            'listing_auto_renew' => ['nullable', 'boolean'],
            'listing_allow_bump' => ['nullable', 'boolean'],
            'location_nearby_radius_km' => ['nullable', 'integer', 'min:1', 'max:500'],
            'location_default_country' => ['nullable', 'string', 'size:2'],
            'free_call_access_limit' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'free_map_access_limit' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'google_maps_api_key' => ['nullable', 'string', 'max:255'],
            'featured_daily_rate' => ['nullable', 'integer', 'min:0'],
            'featured_allowed_days' => ['nullable', 'array', 'min:1'],
            'featured_allowed_days.*' => ['required', 'integer', 'min:1', 'max:365'],
            'payment_gateway' => ['nullable', 'string', Rule::in(['razorpay', 'phonepe', 'paytm', 'mock'])],
            'payment_checkout_mode' => ['nullable', 'string', Rule::in(['inapp_only', 'gateway_redirect'])],
            'payment_gateway_selection_mode' => ['nullable', 'string', Rule::in(['single', 'multiple'])],
            'payment_currency' => ['nullable', 'string', Rule::in(['INR', 'USD', 'EUR', 'GBP', 'AED', 'SGD', 'AUD', 'CAD'])],
            'payment_test_mode' => ['nullable', 'boolean'],
            'razorpay_key_id' => ['nullable', 'string', 'max:100'],
            'razorpay_key_secret' => ['nullable', 'string', 'max:100'],
            'razorpay_base_url' => ['nullable', 'url', 'max:255'],
            'phonepe_merchant_id' => ['nullable', 'string', 'max:100'],
            'phonepe_salt_key' => ['nullable', 'string', 'max:120'],
            'phonepe_salt_index' => ['nullable', 'string', 'max:10'],
            'phonepe_base_url' => ['nullable', 'url', 'max:255'],
            'paytm_mid' => ['nullable', 'string', 'max:80'],
            'paytm_merchant_key' => ['nullable', 'string', 'max:120'],
            'paytm_website' => ['nullable', 'string', 'max:50'],
            'paytm_base_url' => ['nullable', 'url', 'max:255'],
            'registration_enabled' => ['nullable', 'boolean'],
            'email_verification_required' => ['nullable', 'boolean'],
            'phone_verification_required' => ['nullable', 'boolean'],
            'auth_login_email_enabled' => ['nullable', 'boolean'],
            'auth_login_mobile_enabled' => ['nullable', 'boolean'],
            'auth_register_email_enabled' => ['nullable', 'boolean'],
            'auth_register_mobile_enabled' => ['nullable', 'boolean'],
            'firebase_auth_domain' => ['nullable', 'string', 'max:255'],
            'google_oauth_enabled' => ['nullable', 'boolean'],
            'google_oauth_client_id' => ['nullable', 'string', 'max:255'],
            'google_oauth_client_secret' => ['nullable', 'string', 'max:255'],
            'facebook_oauth_enabled' => ['nullable', 'boolean'],
            'facebook_oauth_app_id' => ['nullable', 'string', 'max:100'],
            'facebook_oauth_app_secret' => ['nullable', 'string', 'max:100'],
            'notification_poll_seconds' => ['nullable', 'integer', 'min:5', 'max:300'],
            'notification_email_enabled' => ['nullable', 'boolean'],
            'notification_new_message' => ['nullable', 'boolean'],
            'notification_listing_approved' => ['nullable', 'boolean'],
            'notification_listing_expired' => ['nullable', 'boolean'],
            'notification_push_enabled' => ['nullable', 'boolean'],
            'notification_sound_url' => ['nullable', 'string', 'max:500'],
            'startup_popup_enabled' => ['nullable', 'boolean'],
            'startup_popup_title' => ['nullable', 'string', 'max:160'],
            'startup_popup_message' => ['nullable', 'string', 'max:600'],
            'startup_popup_image_url' => ['nullable', 'string', 'max:500'],
            'startup_popup_link_url' => ['nullable', 'string', 'max:500'],
            'startup_popup_button_label' => ['nullable', 'string', 'max:80'],
            'startup_popup_style' => ['nullable', 'string', Rule::in(['minimal', 'premium', 'festive'])],
            'startup_popup_open_new_tab' => ['nullable', 'boolean'],
            'fcm_api_key' => ['nullable', 'string', 'max:255'],
            'fcm_project_id' => ['nullable', 'string', 'max:120'],
            'fcm_messaging_sender_id' => ['nullable', 'string', 'max:64'],
            'fcm_app_id' => ['nullable', 'string', 'max:255'],
            'fcm_vapid_key' => ['nullable', 'string', 'max:255'],
            'fcm_service_account_email' => ['nullable', 'email', 'max:255'],
            'fcm_service_account_private_key' => ['nullable', 'string', 'max:10000'],
            'fcm_server_key' => ['nullable', 'string', 'max:255'],
            'twa_enabled' => ['nullable', 'boolean'],
            'twa_name' => ['nullable', 'string', 'max:120'],
            'twa_short_name' => ['nullable', 'string', 'max:40'],
            'twa_description' => ['nullable', 'string', 'max:320'],
            'twa_start_url' => ['nullable', 'string', 'max:500'],
            'twa_scope' => ['nullable', 'string', 'max:500'],
            'twa_display' => ['nullable', 'string', Rule::in(['fullscreen', 'standalone', 'minimal-ui', 'browser'])],
            'twa_orientation' => ['nullable', 'string', Rule::in(['any', 'natural', 'landscape', 'landscape-primary', 'landscape-secondary', 'portrait', 'portrait-primary', 'portrait-secondary'])],
            'twa_theme_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'twa_background_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'twa_package_name' => ['nullable', 'string', 'max:200', 'regex:/^[A-Za-z][A-Za-z0-9_]*(\.[A-Za-z][A-Za-z0-9_]*)+$/'],
            'twa_sha256_fingerprints' => ['nullable', 'array', 'max:20'],
            'twa_sha256_fingerprints.*' => ['nullable', 'string', 'regex:/^[A-F0-9]{2}(?::[A-F0-9]{2}){31}$/'],
            'twa_icon_url' => ['nullable', 'string', 'max:500'],
            'twa_icon_maskable_url' => ['nullable', 'string', 'max:500'],
            'twa_navigation_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'twa_splash_fade_duration' => ['nullable', 'integer', 'min:0', 'max:3000'],
            'twa_app_version_name' => ['nullable', 'string', 'max:40', 'regex:/^[0-9]+(\.[0-9]+){1,2}$/'],
            'twa_app_version_code' => ['nullable', 'integer', 'min:1', 'max:2100000000'],
            'twa_min_sdk_version' => ['nullable', 'integer', Rule::in([19, 21, 23, 24, 26, 28, 29, 30, 31, 32, 33, 34])],
            'twa_signing_key_alias' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z][A-Za-z0-9_-]*$/'],
            'twa_keystore_store_type' => ['nullable', 'string', Rule::in(['JKS', 'PKCS12'])],
            'twa_keystore_password' => ['nullable', 'string', 'max:200'],
            'twa_key_password' => ['nullable', 'string', 'max:200'],
            'twa_key_full_name' => ['nullable', 'string', 'max:255'],
            'twa_key_org' => ['nullable', 'string', 'max:255'],
            'twa_key_org_unit' => ['nullable', 'string', 'max:255'],
            'twa_key_country' => ['nullable', 'string', 'max:2', 'regex:/^[A-Za-z]{2}$/'],
            'twa_key_state' => ['nullable', 'string', 'max:255'],
            'twa_key_city' => ['nullable', 'string', 'max:255'],
            'mail_driver' => ['nullable', 'string', Rule::in(['log', 'smtp', 'mailgun', 'ses', 'sendmail'])],
            'mail_from_name' => ['nullable', 'string', 'max:100'],
            'mail_from_address' => ['nullable', 'email', 'max:200'],
            'mail_smtp_host' => ['nullable', 'string', 'max:255'],
            'mail_smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mail_smtp_username' => ['nullable', 'string', 'max:255'],
            'mail_smtp_password' => ['nullable', 'string', 'max:255'],
            'mail_smtp_encryption' => ['nullable', 'string', Rule::in(['tls', 'ssl', 'starttls', 'none'])],
            'mail_mailgun_domain' => ['nullable', 'string', 'max:255'],
            'mail_mailgun_secret' => ['nullable', 'string', 'max:255'],
            'recaptcha_enabled' => ['nullable', 'boolean'],
            'recaptcha_version' => ['nullable', 'string', Rule::in(['v2', 'v3'])],
            'recaptcha_site_key' => ['nullable', 'string', 'max:100'],
            'recaptcha_secret_key' => ['nullable', 'string', 'max:100'],
            'max_login_attempts' => ['nullable', 'integer', 'min:1', 'max:100'],
            'login_lockout_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'seo_meta_description' => ['nullable', 'string', 'max:320'],
            'seo_meta_keywords' => ['nullable', 'string', 'max:500'],
            'seo_robots' => ['nullable', 'string', Rule::in(['index,follow', 'noindex,follow', 'noindex,nofollow'])],
            'seo_google_site_verification' => ['nullable', 'string', 'max:255'],
            'seo_bing_site_verification' => ['nullable', 'string', 'max:255'],
            'seo_google_analytics_id' => ['nullable', 'string', 'max:40'],
            'facebook_pixel_id' => ['nullable', 'string', 'max:50'],
            'og_image_url' => ['nullable', 'url', 'max:500'],
            'adsense_enabled' => ['nullable', 'boolean'],
            'adsense_client_id' => ['nullable', 'string', 'max:80'],
            'adsense_slot_id' => ['nullable', 'string', 'max:255'],
            'adsense_banner_slot_top' => ['nullable', 'string', 'max:255'],
            'adsense_banner_slot_bottom' => ['nullable', 'string', 'max:255'],
            'adsense_banner_slot_guest' => ['nullable', 'string', 'max:255'],
            'adsense_native_slot_id' => ['nullable', 'string', 'max:255'],
            'adsense_article_slot_id' => ['nullable', 'string', 'max:255'],
            'adsense_display_slot_id' => ['nullable', 'string', 'max:255'],
            'adsense_feed_rows_interval' => ['nullable', 'integer', 'min:1', 'max:10'],
            'adsense_locations' => ['nullable', 'array'],
            'adsense_locations.*' => ['required', Rule::in(['top', 'bottom', 'guest', 'native', 'feed', 'article', 'display'])],
            'adsense_interstitial_enabled' => ['nullable', 'boolean'],
            'adsense_interstitial_slot_id' => ['nullable', 'string', 'max:255'],
            'adsense_interstitial_clicks' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'adsense_reward_enabled' => ['nullable', 'boolean'],
            'adsense_reward_slot_id' => ['nullable', 'string', 'max:255'],
            'adsense_reward_slot_id_secondary' => ['nullable', 'string', 'max:255'],
            'adsense_app_open_slot_id' => ['nullable', 'string', 'max:255'],
            'adsense_reward_clicks' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'ai_enabled' => ['nullable', 'boolean'],
            'ai_provider' => ['nullable', 'string', Rule::in(['mock', 'gemini'])],
            'ai_force_real_provider' => ['nullable', 'boolean'],
            'ai_gemini_api_key' => ['nullable', 'string', 'max:255'],
            'ai_gemini_model' => ['nullable', 'string', 'max:80'],
            'ai_gemini_vision_model' => ['nullable', 'string', 'max:80'],
            'ai_listing_assistant_enabled' => ['nullable', 'boolean'],
            'ai_compass_enabled' => ['nullable', 'boolean'],
            'ai_autoiq_enabled' => ['nullable', 'boolean'],
            'ai_fraud_detection_enabled' => ['nullable', 'boolean'],
            'ai_block_suspicious_chat_images' => ['nullable', 'boolean'],
            'ai_block_scam_messages' => ['nullable', 'boolean'],
            'ai_personalization_enabled' => ['nullable', 'boolean'],
            'ai_job_matching_enabled' => ['nullable', 'boolean'],
            'ai_duplicate_detection_enabled' => ['nullable', 'boolean'],
            'ai_price_recommendation_enabled' => ['nullable', 'boolean'],
            'ai_descriptron_enabled' => ['nullable', 'boolean'],
            'ai_image_optimization_enabled' => ['nullable', 'boolean'],
            'ai_ar_car_features_enabled' => ['nullable', 'boolean'],
            'ai_compass_max_results' => ['nullable', 'integer', 'min:1', 'max:20'],
            'ai_confidence_threshold' => ['nullable', 'integer', 'min:1', 'max:100'],
            'ai_time_savings_min' => ['nullable', 'integer', 'min:1', 'max:100'],
            'ai_time_savings_max' => ['nullable', 'integer', 'min:1', 'max:100'],
            'ai_quality_improvement_max' => ['nullable', 'integer', 'min:1', 'max:100'],
            'ai_seo_optimizer_enabled' => ['nullable', 'boolean'],
            'ai_seo_auto_apply_enabled' => ['nullable', 'boolean'],
            'ai_seo_generate_sitemap' => ['nullable', 'boolean'],
            'ai_seo_audit_interval_minutes' => ['nullable', 'integer', 'min:5', 'max:1440'],
            'ai_seo_lookback_days' => ['nullable', 'integer', 'min:7', 'max:180'],
            'ai_seo_max_keywords' => ['nullable', 'integer', 'min:5', 'max:40'],
            'app_latest_version' => ['nullable', 'string', 'max:20', 'regex:/^\d+\.\d+\.\d+$/'],
            'app_min_version' => ['nullable', 'string', 'max:20', 'regex:/^\d+\.\d+\.\d+$/'],
            'app_play_store_url' => ['nullable', 'url', 'max:500'],
            'app_force_update_msg' => ['nullable', 'string', 'max:300'],
        ];
    }

    private function processSectionValidations(string $section, Request $request, array &$validated): void
    {
        if ($section === 'general') {
            $this->processGeneralSection($request, $validated);
        }

        if ($section === 'support') {
            $this->processSupportSection($request, $validated);
        }

        if ($section === 'registration') {
            $this->processRegistrationSection($request, $validated);
        }

        if ($section === 'notifications') {
            $this->processNotificationsSection($request, $validated);
        }

        if ($section === 'twa') {
            $this->processTwaSection($request, $validated);
        }

        if ($section === 'featured') {
            $this->processFeaturedSection($request, $validated);
        }

        if ($section === 'marketing') {
            $this->processMarketingSection($request, $validated);
        }

        if ($section === 'ai') {
            $this->processAiSection($request, $validated);
        }
    }

    private function processGeneralSection(Request $request, array &$validated): void
    {
        $storedImages = array_values(array_filter(
            array_map(fn($value): string => trim((string) $value), (array) AppSetting::get('home_banner_images', [])),
            fn(string $value): bool => $value !== ''
        ));

        $legacyStoredImage = trim((string) AppSetting::get('home_banner_image_url', ''));
        if ($storedImages === [] && $legacyStoredImage !== '') {
            $storedImages[] = $legacyStoredImage;
        }

        $incomingImages = array_values(array_filter(
            array_map(fn($value): string => trim((string) $value), (array) ($validated['home_banner_images'] ?? [])),
            fn(string $value): bool => $value !== ''
        ));

        $legacyIncomingImage = trim((string) ($validated['home_banner_image_url'] ?? ''));
        if ($legacyIncomingImage !== '' && $incomingImages === []) {
            $incomingImages[] = $legacyIncomingImage;
        }

        $uploadedImages = (array) $request->file('home_banner_image_files', []);
        if ($request->hasFile('home_banner_image_file')) {
            $uploadedImages[0] = $request->file('home_banner_image_file');
        }

        foreach ($uploadedImages as $index => $uploadedImage) {
            if (!$uploadedImage) {
                continue;
            }

            $previousAtIndex = trim((string) ($incomingImages[$index] ?? ''));
            if ($previousAtIndex !== '') {
                $this->deleteStoredBannerIfLocal($previousAtIndex);
            }

            $extension = strtolower((string) $uploadedImage->getClientOriginalExtension());
            if ($extension === '') {
                $extension = 'jpg';
            }

            $fileName = 'home-banner-' . now()->format('YmdHis') . '-' . Str::random(8) . '.' . $extension;
            $storedPath = $uploadedImage->storeAs('banners', $fileName, 'public');
            $incomingImages[$index] = $storedPath;
        }

        $incomingImages = array_values(array_filter(
            array_map(fn($value): string => trim((string) $value), $incomingImages),
            fn(string $value): bool => $value !== ''
        ));

        foreach (array_diff($storedImages, $incomingImages) as $removedImage) {
            $this->deleteStoredBannerIfLocal((string) $removedImage);
        }

        $validated['home_banner_images'] = $incomingImages;
        $validated['home_banner_image_url'] = $incomingImages[0] ?? '';

        $allowedPositions = ['center', 'top', 'bottom', 'left', 'right'];
        $rawPositions = array_values((array) ($validated['home_banner_image_positions'] ?? []));
        $incomingPositions = [];
        foreach ($incomingImages as $i => $_) {
            $pos = (string) ($rawPositions[$i] ?? 'center');
            $incomingPositions[] = in_array($pos, $allowedPositions, true) ? $pos : 'center';
        }
        $validated['home_banner_image_positions'] = $incomingPositions;

        $rawFits = array_values((array) ($validated['home_banner_image_fits'] ?? []));
        $incomingFits = [];
        foreach ($incomingImages as $i => $_) {
            $fit = (string) ($rawFits[$i] ?? 'cover');
            $incomingFits[] = in_array($fit, ['cover', 'contain'], true) ? $fit : 'cover';
        }
        $validated['home_banner_image_fits'] = $incomingFits;

        $storedAppBannerImages = array_values(array_filter(
            array_map(fn($value): string => trim((string) $value), (array) AppSetting::get('app_banner_images', [])),
            fn(string $value): bool => $value !== ''
        ));

        $incomingExistingAppBannerImages = array_values(array_filter(
            array_map(fn($value): string => trim((string) $value), (array) ($validated['app_banner_existing_images'] ?? [])),
            fn(string $value): bool => $value !== ''
        ));

        $incomingAppBannerImages = [];
        foreach ($incomingExistingAppBannerImages as $existingImage) {
            if (in_array($existingImage, $storedAppBannerImages, true) && !in_array($existingImage, $incomingAppBannerImages, true)) {
                $incomingAppBannerImages[] = $existingImage;
            }
        }

        if ($incomingExistingAppBannerImages === []) {
            $incomingAppBannerImages = $storedAppBannerImages;
        }

        $uploadedAppBannerImages = (array) $request->file('app_banner_image_files', []);
        if (count($uploadedAppBannerImages) > 0) {
            foreach ($uploadedAppBannerImages as $uploadedImage) {
                if (!$uploadedImage) {
                    continue;
                }

                $extension = strtolower((string) $uploadedImage->getClientOriginalExtension());
                if ($extension === '') {
                    $extension = 'jpg';
                }

                $fileName = 'app-banner-' . now()->format('YmdHis') . '-' . Str::random(8) . '.' . $extension;
                $storedPath = $uploadedImage->storeAs('banners', $fileName, 'public');
                $incomingAppBannerImages[] = $storedPath;
            }
        }

        $incomingAppBannerImages = array_values(array_slice(array_filter(
            array_map(fn($value): string => trim((string) $value), $incomingAppBannerImages),
            fn(string $value): bool => $value !== ''
        ), 0, 10));

        foreach (array_diff($storedAppBannerImages, $incomingAppBannerImages) as $removedImage) {
            $this->deleteStoredBannerIfLocal((string) $removedImage);
        }

        $validated['app_banner_images'] = $incomingAppBannerImages;
    }

    private function processSupportSection(Request $request, array &$validated): void
    {
        $faqText = trim((string) ($validated['support_faqs_text'] ?? $request->input('support_faqs_text', '')));
        $faqRows = preg_split('/\r\n|\r|\n/', $faqText) ?: [];
        $parsedFaqs = [];

        foreach ($faqRows as $lineNumber => $faqRow) {
            $row = trim((string) $faqRow);
            if ($row === '') {
                continue;
            }

            $parts = explode('||', $row, 2);
            if (count($parts) !== 2) {
                throw ValidationException::withMessages([
                    'support_faqs_text' => 'Each FAQ line must use: Question || Answer (error on line ' . ($lineNumber + 1) . ').',
                ]);
            }

            $question = trim((string) ($parts[0] ?? ''));
            $answer = trim((string) ($parts[1] ?? ''));

            if ($question === '' || $answer === '') {
                throw ValidationException::withMessages([
                    'support_faqs_text' => 'FAQ question and answer cannot be empty (error on line ' . ($lineNumber + 1) . ').',
                ]);
            }

            if (mb_strlen($question) > 180 || mb_strlen($answer) > 1200) {
                throw ValidationException::withMessages([
                    'support_faqs_text' => 'FAQ line ' . ($lineNumber + 1) . ' exceeds the allowed length.',
                ]);
            }

            $parsedFaqs[] = [
                'question' => $question,
                'answer' => $answer,
            ];
        }

        $validated['support_faqs'] = array_slice($parsedFaqs, 0, 40);
    }

    private function processRegistrationSection(Request $request, array &$validated): void
    {
        $loginEmailEnabled = $request->boolean('auth_login_email_enabled');
        $loginMobileEnabled = $request->boolean('auth_login_mobile_enabled');
        $registerEmailEnabled = $request->boolean('auth_register_email_enabled');
        $registerMobileEnabled = $request->boolean('auth_register_mobile_enabled');

        if (!$loginEmailEnabled && !$loginMobileEnabled) {
            throw ValidationException::withMessages([
                'auth_login_email_enabled' => 'Enable at least one login method (email or mobile OTP).',
            ]);
        }

        if (!$registerEmailEnabled && !$registerMobileEnabled) {
            throw ValidationException::withMessages([
                'auth_register_email_enabled' => 'Enable at least one registration method (email or mobile OTP).',
            ]);
        }

        $validated['firebase_auth_domain'] = trim((string) ($validated['firebase_auth_domain'] ?? ''));
    }

    private function processNotificationsSection(Request $request, array &$validated): void
    {
        if ($request->hasFile('fcm_service_account_json_file')) {
            $serviceAccountFile = $request->file('fcm_service_account_json_file');
            $decodedServiceAccount = json_decode((string) file_get_contents((string) $serviceAccountFile->getRealPath()), true);

            if (!is_array($decodedServiceAccount)) {
                throw ValidationException::withMessages([
                    'fcm_service_account_json_file' => 'Uploaded file must contain valid JSON.',
                ]);
            }

            $jsonProjectId = trim((string) ($decodedServiceAccount['project_id'] ?? ''));
            $jsonClientEmail = trim((string) ($decodedServiceAccount['client_email'] ?? ''));
            $jsonPrivateKey = (string) ($decodedServiceAccount['private_key'] ?? '');

            if ($jsonProjectId === '' || $jsonClientEmail === '' || trim($jsonPrivateKey) === '') {
                throw ValidationException::withMessages([
                    'fcm_service_account_json_file' => 'Service account JSON must include project_id, client_email, and private_key.',
                ]);
            }

            $validated['fcm_project_id'] = $jsonProjectId;
            $validated['fcm_service_account_email'] = $jsonClientEmail;
            $validated['fcm_service_account_private_key'] = $jsonPrivateKey;
        }

        $storedSound = trim((string) AppSetting::get('notification_sound_url', ''));

        if ($request->hasFile('notification_sound_file')) {
            $soundFile = $request->file('notification_sound_file');
            $extension = strtolower((string) $soundFile->getClientOriginalExtension());
            if ($extension === '') {
                $extension = 'mp3';
            }

            $fileName = 'notification-sound-' . now()->format('YmdHis') . '-' . Str::random(8) . '.' . $extension;
            $storedPath = $soundFile->storeAs('notifications/sounds', $fileName, 'public');
            $validated['notification_sound_url'] = $storedPath;

            if ($storedSound !== '' && $storedSound !== $storedPath) {
                $this->deleteStoredNotificationSoundIfLocal($storedSound);
            }
        } elseif (array_key_exists('notification_sound_url', $validated)) {
            $incomingSound = trim((string) ($validated['notification_sound_url'] ?? ''));
            $validated['notification_sound_url'] = $incomingSound;

            if ($storedSound !== '' && $incomingSound !== $storedSound) {
                $this->deleteStoredNotificationSoundIfLocal($storedSound);
            }
        }

        $validated['startup_popup_title'] = trim((string) ($validated['startup_popup_title'] ?? ''));
        $validated['startup_popup_message'] = trim((string) ($validated['startup_popup_message'] ?? ''));
        $validated['startup_popup_button_label'] = trim((string) ($validated['startup_popup_button_label'] ?? ''));
        $validated['startup_popup_link_url'] = $this->normalizeStartupPopupLink((string) ($validated['startup_popup_link_url'] ?? ''));

        $storedPopupImage = trim((string) AppSetting::get('startup_popup_image_url', ''));

        if ($request->hasFile('startup_popup_image_file')) {
            $popupImageFile = $request->file('startup_popup_image_file');
            $extension = strtolower((string) $popupImageFile->getClientOriginalExtension());
            if ($extension === '') {
                $extension = 'jpg';
            }

            $fileName = 'startup-popup-' . now()->format('YmdHis') . '-' . Str::random(8) . '.' . $extension;
            $storedPath = $popupImageFile->storeAs('notifications/startup-popups', $fileName, 'public');
            $validated['startup_popup_image_url'] = $storedPath;

            if ($storedPopupImage !== '' && $storedPopupImage !== $storedPath) {
                $this->deleteStoredStartupPopupImageIfLocal($storedPopupImage);
            }
        } elseif (array_key_exists('startup_popup_image_url', $validated)) {
            $incomingPopupImage = trim((string) ($validated['startup_popup_image_url'] ?? ''));
            $validated['startup_popup_image_url'] = $incomingPopupImage;

            if ($storedPopupImage !== '' && $incomingPopupImage !== $storedPopupImage) {
                $this->deleteStoredStartupPopupImageIfLocal($storedPopupImage);
            }
        }

        if ($request->boolean('startup_popup_enabled')) {
            $hasPopupContent = $validated['startup_popup_title'] !== ''
                || $validated['startup_popup_message'] !== ''
                || trim((string) ($validated['startup_popup_image_url'] ?? '')) !== '';

            if (!$hasPopupContent) {
                throw ValidationException::withMessages([
                    'startup_popup_title' => 'Provide a title, message, or image when startup popup is enabled.',
                ]);
            }
        }
    }

    private function processTwaSection(Request $request, array &$validated): void
    {
        $fingerprintsText = (string) ($validated['twa_sha256_fingerprints_text'] ?? $request->input('twa_sha256_fingerprints_text', ''));
        $splitFingerprints = preg_split('/[\r\n,]+/', strtoupper($fingerprintsText)) ?: [];
        $fingerprints = array_values(array_filter(
            array_map(fn($value): string => trim((string) $value), $splitFingerprints),
            fn(string $value): bool => $value !== ''
        ));

        $invalidFingerprints = array_values(array_filter($fingerprints, fn(string $value): bool => preg_match('/^[A-F0-9]{2}(?::[A-F0-9]{2}){31}$/', $value) !== 1));

        if ($invalidFingerprints !== []) {
            throw ValidationException::withMessages([
                'twa_sha256_fingerprints_text' => 'Invalid SHA-256 fingerprint format detected. Use AA:BB:CC:... style values.',
            ]);
        }

        $fingerprints = array_values(array_unique($fingerprints));
        $validated['twa_sha256_fingerprints'] = $fingerprints;
        $validated['twa_start_url'] = $this->normalizeTwaPath((string) ($validated['twa_start_url'] ?? '/'));
        $validated['twa_scope'] = $this->normalizeTwaPath((string) ($validated['twa_scope'] ?? '/'));

        if ($request->boolean('twa_enabled')) {
            if (trim((string) ($validated['twa_package_name'] ?? '')) === '') {
                throw ValidationException::withMessages([
                    'twa_package_name' => 'Android package name is required when TWA is enabled.',
                ]);
            }

            if ($fingerprints === []) {
                throw ValidationException::withMessages([
                    'twa_sha256_fingerprints_text' => 'At least one SHA-256 certificate fingerprint is required when TWA is enabled.',
                ]);
            }
        }

        foreach ([['twa_icon_file', 'twa_icon_url'], ['twa_icon_maskable_file', 'twa_icon_maskable_url']] as [$fileKey, $settingKey]) {
            if ($request->hasFile($fileKey)) {
                $iconFile = $request->file($fileKey);
                $slug = $fileKey === 'twa_icon_file' ? 'icon' : 'maskable';
                $fileName = 'twa-' . $slug . '-' . now()->format('YmdHis') . '-' . Str::random(8) . '.png';
                $storedPath = $iconFile->storeAs('twa/icons', $fileName, 'public');
                $previousIconUrl = trim((string) AppSetting::get($settingKey, ''));
                if ($previousIconUrl !== '') {
                    $this->deleteStoredTwaIconIfLocal($previousIconUrl);
                }
                $validated[$settingKey] = $storedPath;
            } elseif (array_key_exists($settingKey, $validated)) {
                $incomingIconUrl = trim((string) ($validated[$settingKey] ?? ''));
                $previousIconUrl = trim((string) AppSetting::get($settingKey, ''));
                if ($incomingIconUrl === '' && $previousIconUrl !== '') {
                    $this->deleteStoredTwaIconIfLocal($previousIconUrl);
                }
                $validated[$settingKey] = $incomingIconUrl;
            }
        }

        if (isset($validated['twa_key_country']) && trim((string) ($validated['twa_key_country'] ?? '')) !== '') {
            $validated['twa_key_country'] = strtoupper(trim((string) $validated['twa_key_country']));
        }
    }

    private function processFeaturedSection(Request $request, array &$validated): void
    {
        $gateway = (string) ($validated['payment_gateway'] ?? 'mock');
        $stored = fn(string $key): string => trim((string) AppSetting::get($key, ''));
        $incoming = fn(string $key): string => trim((string) ($request->input($key) ?? ''));

        $credentialErrors = match ($gateway) {
            'razorpay' => [
                'razorpay_key_id' => $incoming('razorpay_key_id') === '' && $stored('razorpay_key_id') === ''
                    ? 'Razorpay Key ID is required for Razorpay gateway.'
                    : null,
                'razorpay_key_secret' => $incoming('razorpay_key_secret') === '' && $stored('razorpay_key_secret') === ''
                    ? 'Razorpay Key Secret is required for Razorpay gateway.'
                    : null,
            ],
            'phonepe' => [
                'phonepe_merchant_id' => $incoming('phonepe_merchant_id') === '' && $stored('phonepe_merchant_id') === ''
                    ? 'PhonePe Merchant ID is required for PhonePe gateway.'
                    : null,
                'phonepe_salt_key' => $incoming('phonepe_salt_key') === '' && $stored('phonepe_salt_key') === ''
                    ? 'PhonePe Salt Key is required for PhonePe gateway.'
                    : null,
            ],
            'paytm' => [
                'paytm_mid' => $incoming('paytm_mid') === '' && $stored('paytm_mid') === ''
                    ? 'Paytm MID is required for Paytm gateway.'
                    : null,
                'paytm_merchant_key' => $incoming('paytm_merchant_key') === '' && $stored('paytm_merchant_key') === ''
                    ? 'Paytm Merchant Key is required for Paytm gateway.'
                    : null,
            ],
            default => [],
        };

        $credentialErrors = array_filter($credentialErrors, fn(?string $message): bool => $message !== null);

        if ($credentialErrors !== []) {
            throw ValidationException::withMessages($credentialErrors);
        }
    }

    private function processMarketingSection(Request $request, array &$validated): void
    {
        $adsEnabled = $request->boolean('adsense_enabled');
        $adsLocations = array_values(array_map('strval', (array) ($validated['adsense_locations'] ?? [])));
        $interstitialEnabled = $request->boolean('adsense_interstitial_enabled');
        $rewardEnabled = $request->boolean('adsense_reward_enabled');

        if (array_key_exists('seo_google_analytics_id', $validated)) {
            $validated['seo_google_analytics_id'] = strtoupper(trim((string) $validated['seo_google_analytics_id']));
        }

        if (array_key_exists('adsense_client_id', $validated)) {
            $normalizedClientId = trim((string) $validated['adsense_client_id']);
            if (preg_match('/ca-pub-(\d+)/i', $normalizedClientId, $matches) === 1) {
                $normalizedClientId = 'ca-pub-' . (string) ($matches[1] ?? '');
            } elseif (preg_match('/ca-app-pub-(\d+)(?:[~\/]\d+)?/i', $normalizedClientId, $matches) === 1) {
                $normalizedClientId = 'ca-pub-' . (string) ($matches[1] ?? '');
            }
            $validated['adsense_client_id'] = $normalizedClientId;
        }

        $normalizeSlotId = function (string $value): string {
            $normalized = trim($value);
            if ($normalized === '') {
                return '';
            }
            if (preg_match('/data-ad-slot=["\']?(\d+)/i', $normalized, $matches) === 1) {
                return (string) ($matches[1] ?? '');
            }
            if (preg_match('/[\/~](\d+)$/', $normalized, $matches) === 1) {
                return (string) ($matches[1] ?? '');
            }
            return $normalized;
        };

        foreach ([
            'adsense_slot_id', 'adsense_banner_slot_top', 'adsense_banner_slot_bottom',
            'adsense_banner_slot_guest', 'adsense_native_slot_id', 'adsense_article_slot_id',
            'adsense_display_slot_id', 'adsense_interstitial_slot_id', 'adsense_reward_slot_id',
            'adsense_reward_slot_id_secondary', 'adsense_app_open_slot_id',
        ] as $slotKey) {
            if (array_key_exists($slotKey, $validated)) {
                $validated[$slotKey] = $normalizeSlotId((string) ($validated[$slotKey] ?? ''));
            }
        }

        if ($adsEnabled) {
            if (preg_match('/^ca-pub-\d+$/', (string) ($validated['adsense_client_id'] ?? '')) !== 1
                || (string) ($validated['adsense_client_id'] ?? '') === 'ca-pub-2738051455706993') {
                throw ValidationException::withMessages([
                    'adsense_client_id' => 'Enter your own valid AdSense web publisher ID, such as ca-pub-1234567890123456.',
                ]);
            }

            $slotRules = [];
            $slotMessages = [];
            $locationSlotKeyMap = [
                'top' => 'adsense_banner_slot_top',
                'bottom' => 'adsense_banner_slot_bottom',
                'guest' => 'adsense_banner_slot_guest',
                'native' => 'adsense_native_slot_id',
                'feed' => 'adsense_native_slot_id',
                'article' => 'adsense_article_slot_id',
                'display' => 'adsense_display_slot_id',
            ];

            foreach (array_keys($locationSlotKeyMap) as $loc) {
                if (in_array($loc, $adsLocations, true)) {
                    $slotKey = $locationSlotKeyMap[$loc];
                    $slotRules[$slotKey] = ['required', 'string', 'max:255'];
                    $slotMessages[$slotKey . '.required'] = ucfirst($loc) . ' banner slot ID is required.';
                }
            }
            if ($slotRules !== []) {
                $validator = validator($request->all(), $slotRules, $slotMessages);
                if ($validator->fails()) {
                    throw new ValidationException($validator);
                }
            }
        }

        if (($interstitialEnabled || $rewardEnabled) && !$adsEnabled) {
            throw ValidationException::withMessages([
                'adsense_enabled' => 'Enable Google Ads before enabling interstitial or reward ads.',
            ]);
        }
    }

    private function processAiSection(Request $request, array &$validated): void
    {
        $timeMin = (int) ($validated['ai_time_savings_min'] ?? AppSetting::get('ai_time_savings_min', 35));
        $timeMax = (int) ($validated['ai_time_savings_max'] ?? AppSetting::get('ai_time_savings_max', 55));
        $aiEnabled = $request->boolean('ai_enabled');
        $provider = strtolower(trim((string) ($validated['ai_provider'] ?? AppSetting::get('ai_provider', 'gemini'))));
        $forceRealProvider = $request->boolean('ai_force_real_provider');
        $requiresGeminiKey = $aiEnabled && ($provider === 'gemini' || $forceRealProvider);

        if ($requiresGeminiKey) {
            $incomingGeminiKey = trim((string) ($request->input('ai_gemini_api_key') ?? ''));
            $storedGeminiKey = trim((string) AppSetting::get('ai_gemini_api_key', ''));
            $envGeminiKey = trim((string) config('services.gemini.api_key', env('GEMINI_API_KEY', '')));

            if ($incomingGeminiKey === '' && $storedGeminiKey === '' && $envGeminiKey === '') {
                throw ValidationException::withMessages([
                    'ai_gemini_api_key' => 'Gemini API key is required when provider is Gemini or forced real mode is enabled.',
                ]);
            }
        }

        if ($timeMin > $timeMax) {
            throw ValidationException::withMessages([
                'ai_time_savings_max' => 'Time savings max (%) must be greater than or equal to time savings min (%).',
            ]);
        }
    }

    private function prepareAllowedDays(callable $field, callable $val): array
    {
        $allowedDaysInput = old('featured_allowed_days');
        if (is_array($allowedDaysInput)) {
            return array_values(array_map('intval', $allowedDaysInput));
        }
        $default = [3, 7, 15, 30];
        $stored = json_decode($val('featured_allowed_days', json_encode($default)), true);
        return is_array($stored) ? $stored : $default;
    }

    private function prepareAdLocations(callable $field, callable $val): array
    {
        $adLocationsInput = old('adsense_locations');
        if (is_array($adLocationsInput)) {
            $locations = array_values(array_map('strval', $adLocationsInput));
        } else {
            $default = ['display', 'guest', 'feed', 'article'];
            $locations = json_decode($val('adsense_locations', json_encode($default)), true) ?: $default;
        }
        if (in_array('native', $locations, true) && !in_array('feed', $locations, true)) {
            $locations[] = 'feed';
        }
        return $locations;
    }

    private function prepareSupportFaqs(callable $val): string
    {
        $rawSupportFaqs = json_decode((string) $val('support_faqs', '[]'), true);
        if (!is_array($rawSupportFaqs)) {
            $rawSupportFaqs = [];
        }
        $supportFaqLines = collect($rawSupportFaqs)
            ->map(function ($entry): string {
                $question = trim((string) data_get($entry, 'question', ''));
                $answer = trim((string) data_get($entry, 'answer', ''));
                return $question !== '' && $answer !== '' ? $question . ' || ' . $answer : '';
            })
            ->filter(fn(string $line): bool => $line !== '')
            ->values()
            ->all();
        return old('support_faqs_text', implode(PHP_EOL, $supportFaqLines));
    }

    private function prepareJsonArray(callable $field, callable $val, string $key): array
    {
        $input = old($key);
        if (is_array($input)) {
            return array_values(array_map('strval', $input));
        }
        $stored = json_decode((string) $val($key, '[]'), true);
        return is_array($stored) ? $stored : [];
    }

    private function prepareTwaFingerprints(callable $field, callable $val): string
    {
        $storedFingerprints = json_decode((string) $val('twa_sha256_fingerprints', '[]'), true);
        if (!is_array($storedFingerprints)) {
            $storedFingerprints = [];
        }
        $fingerprintInput = old('twa_sha256_fingerprints_text');
        return is_string($fingerprintInput)
            ? $fingerprintInput
            : implode(PHP_EOL, array_values(array_map('strval', $storedFingerprints)));
    }

    private function prepareHomeBannerImages(callable $field, callable $val): array
    {
        $rawImages = old('home_banner_images');
        if (!is_array($rawImages)) {
            $rawImages = json_decode((string) $val('home_banner_images', '[]'), true);
        }
        if (!is_array($rawImages)) {
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

    private function prepareHomeBannerPositions(callable $field, callable $val, array $images): array
    {
        $allowedPositions = ['center', 'top', 'bottom', 'left', 'right'];
        $rawPositions = old('home_banner_image_positions');
        if (!is_array($rawPositions)) {
            $rawPositions = json_decode((string) $val('home_banner_image_positions', '[]'), true);
        }
        if (!is_array($rawPositions)) {
            $rawPositions = [];
        }
        $positions = array_values(array_map(
            fn($v): string => in_array((string) $v, $allowedPositions, true) ? (string) $v : 'center',
            $rawPositions
        ));
        while (count($positions) < count($images)) {
            $positions[] = 'center';
        }
        return $positions;
    }

    private function prepareHomeBannerFits(callable $field, callable $val, array $images): array
    {
        $rawFits = old('home_banner_image_fits');
        if (!is_array($rawFits)) {
            $rawFits = json_decode((string) $val('home_banner_image_fits', '[]'), true);
        }
        if (!is_array($rawFits)) {
            $rawFits = [];
        }
        $fits = array_values(array_map(
            fn($v): string => in_array((string) $v, ['cover', 'contain'], true) ? (string) $v : 'cover',
            $rawFits
        ));
        while (count($fits) < count($images)) {
            $fits[] = 'cover';
        }
        return $fits;
    }

    private function prepareAppBannerImages(callable $field, callable $val): array
    {
        $rawImages = old('app_banner_images');
        if (!is_array($rawImages)) {
            $rawImages = json_decode((string) $val('app_banner_images', '[]'), true);
        }
        if (!is_array($rawImages)) {
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
        if ($seconds < 1 || $seconds > 60) {
            return 5;
        }
        return $seconds;
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

    private function getMediaUrl(?string $path): string
    {
        if (!$path || trim($path) === '') {
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

    private function normalizeTwaPath(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '/';
        }
        if (preg_match('/^https?:\/\//i', $trimmed) === 1) {
            return $trimmed;
        }
        return '/' . ltrim($trimmed, '/');
    }

    private function normalizeStartupPopupLink(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }
        if (preg_match('/^https?:\/\//i', $trimmed) === 1 || str_starts_with($trimmed, '/')) {
            return $trimmed;
        }
        return '/' . ltrim($trimmed, '/');
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

    private function deleteStoredBannerIfLocal(string $path): void
    {
        $normalized = trim($path);
        if ($normalized === '' || str_starts_with($normalized, 'http://') || str_starts_with($normalized, 'https://') || str_starts_with($normalized, '/')) {
            return;
        }
        Storage::disk('public')->delete($normalized);
    }

    private function deleteStoredNotificationSoundIfLocal(string $path): void
    {
        $normalized = trim($path);
        if ($normalized === '' || str_starts_with($normalized, 'http://') || str_starts_with($normalized, 'https://') || str_starts_with($normalized, '/')) {
            return;
        }
        Storage::disk('public')->delete($normalized);
    }

    private function deleteStoredStartupPopupImageIfLocal(string $path): void
    {
        $normalized = trim($path);
        if ($normalized === '' || str_starts_with($normalized, 'http://') || str_starts_with($normalized, 'https://') || str_starts_with($normalized, '/')) {
            return;
        }
        Storage::disk('public')->delete($normalized);
    }

    private function deleteStoredTwaIconIfLocal(string $path): void
    {
        $normalized = trim($path);
        if ($normalized === '' || str_starts_with($normalized, 'http://') || str_starts_with($normalized, 'https://') || str_starts_with($normalized, '/')) {
            return;
        }
        Storage::disk('public')->delete($normalized);
    }
}
