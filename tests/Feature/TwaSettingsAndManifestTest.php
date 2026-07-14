<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use Database\Seeders\AppSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TwaSettingsAndManifestTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_settings_page_exposes_relative_twa_runtime_endpoints_for_validator(): void
    {
        $this->seed(AppSettingsSeeder::class);

        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.settings.index'));

        $response->assertOk();
        $response->assertSee('data-manifest-url="/manifest.webmanifest"', false);
        $response->assertSee('data-assetlinks-url="/.well-known/assetlinks.json"', false);
    }

    public function test_admin_settings_page_uses_configured_app_url_for_twa_output_links(): void
    {
        $this->seed(AppSettingsSeeder::class);

        AppSetting::set('app_url', 'https://market.example.test');

        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.settings.index'));

        $response->assertOk();
        $response->assertSee('data-app-url="https://market.example.test"', false);
        $response->assertSee('https://market.example.test/manifest.webmanifest', false);
        $response->assertSee('https://market.example.test/.well-known/assetlinks.json', false);
    }

    public function test_admin_can_save_twa_settings_and_fingerprints(): void
    {
        $this->seed(AppSettingsSeeder::class);

        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $fingerprint = 'AA:BB:CC:DD:EE:FF:00:11:22:33:44:55:66:77:88:99:AA:BB:CC:DD:EE:FF:00:11:22:33:44:55:66:77:88:99';

        $response = $this->actingAs($admin)->post(route('admin.settings.update'), [
            'settings_section' => 'twa',
            'twa_enabled' => '1',
            'twa_name' => 'Unisell Android',
            'twa_short_name' => 'UnisellA',
            'twa_description' => 'Android shell for Unisell marketplace.',
            'twa_start_url' => 'app/home',
            'twa_scope' => 'app',
            'twa_display' => 'standalone',
            'twa_orientation' => 'portrait',
            'twa_theme_color' => '#f97316',
            'twa_background_color' => '#ffffff',
            'twa_package_name' => 'com.example.unisell',
            'twa_sha256_fingerprints_text' => $fingerprint."\n".strtolower($fingerprint),
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertTrue((bool) AppSetting::get('twa_enabled'));
        $this->assertSame('Unisell Android', AppSetting::get('twa_name'));
        $this->assertSame('/app/home', AppSetting::get('twa_start_url'));
        $this->assertSame('/app', AppSetting::get('twa_scope'));
        $this->assertSame('com.example.unisell', AppSetting::get('twa_package_name'));
        $this->assertSame([$fingerprint], AppSetting::get('twa_sha256_fingerprints'));
    }

    public function test_twa_enabled_requires_package_name(): void
    {
        $this->seed(AppSettingsSeeder::class);

        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $fingerprint = 'AA:BB:CC:DD:EE:FF:00:11:22:33:44:55:66:77:88:99:AA:BB:CC:DD:EE:FF:00:11:22:33:44:55:66:77:88:99';

        $response = $this->actingAs($admin)->post(route('admin.settings.update'), [
            'settings_section' => 'twa',
            'twa_enabled' => '1',
            'twa_package_name' => '',
            'twa_sha256_fingerprints_text' => $fingerprint,
        ]);

        $response->assertSessionHasErrors(['twa_package_name']);
    }

    public function test_twa_enabled_requires_fingerprints(): void
    {
        $this->seed(AppSettingsSeeder::class);

        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.settings.update'), [
            'settings_section' => 'twa',
            'twa_enabled' => '1',
            'twa_package_name' => 'com.example.unisell',
            'twa_sha256_fingerprints_text' => '',
        ]);

        $response->assertSessionHasErrors(['twa_sha256_fingerprints_text']);
    }

    public function test_manifest_endpoint_uses_saved_twa_settings(): void
    {
        $this->seed(AppSettingsSeeder::class);

        AppSetting::set('twa_name', 'Unisell Android');
        AppSetting::set('twa_short_name', 'UnisellA');
        AppSetting::set('twa_description', 'Trusted Web Activity shell.');
        AppSetting::set('twa_start_url', 'app/home');
        AppSetting::set('twa_scope', 'https://example.test/mobile');
        AppSetting::set('twa_display', 'fullscreen');
        AppSetting::set('twa_orientation', 'portrait');
        AppSetting::set('twa_theme_color', '#aa11cc');
        AppSetting::set('twa_background_color', '#001122');

        $response = $this->get(route('pwa.manifest'));

        $response->assertOk();
        $response->assertJsonPath('name', 'Unisell Android');
        $response->assertJsonPath('short_name', 'UnisellA');
        $response->assertJsonPath('description', 'Trusted Web Activity shell.');
        $response->assertJsonPath('start_url', '/app/home');
        $response->assertJsonPath('scope', 'https://example.test/mobile');
        $response->assertJsonPath('display', 'fullscreen');
        $response->assertJsonPath('orientation', 'portrait');
        $response->assertJsonPath('theme_color', '#AA11CC');
        $response->assertJsonPath('background_color', '#001122');
    }

    public function test_manifest_endpoint_resolves_local_twa_icons_to_media_file_urls(): void
    {
        $this->seed(AppSettingsSeeder::class);
        Storage::fake('public');

        Storage::disk('public')->put('twa/icons/icon.png', 'icon');
        Storage::disk('public')->put('twa/icons/maskable.png', 'maskable');

        AppSetting::set('twa_icon_url', 'twa/icons/icon.png');
        AppSetting::set('twa_icon_maskable_url', 'twa/icons/maskable.png');

        $response = $this->get(route('pwa.manifest'));

        $response->assertOk();
        $icons = $response->json('icons');

        $this->assertIsArray($icons);
        $this->assertNotEmpty($icons);
        $this->assertStringContainsString('/media-files/twa/icons/icon.png', (string) ($icons[0]['src'] ?? ''));
        $this->assertStringContainsString('/media-files/twa/icons/maskable.png', (string) ($icons[1]['src'] ?? ''));
    }

    public function test_manifest_endpoint_resolves_storage_prefixed_and_absolute_local_twa_icon_paths(): void
    {
        $this->seed(AppSettingsSeeder::class);
        Storage::fake('public');

        Storage::disk('public')->put('twa/icons/icon-prefixed.png', 'icon');
        Storage::disk('public')->put('twa/icons/maskable-prefixed.png', 'maskable');

        AppSetting::set('twa_icon_url', '/storage/twa/icons/icon-prefixed.png');
        AppSetting::set('twa_icon_maskable_url', '/twa/icons/maskable-prefixed.png');

        $response = $this->get(route('pwa.manifest'));

        $response->assertOk();
        $icons = $response->json('icons');

        $this->assertIsArray($icons);
        $this->assertNotEmpty($icons);
        $this->assertStringContainsString('/media-files/twa/icons/icon-prefixed.png', (string) ($icons[0]['src'] ?? ''));
        $this->assertStringContainsString('/media-files/twa/icons/maskable-prefixed.png', (string) ($icons[1]['src'] ?? ''));
    }

    public function test_media_files_route_serves_public_disk_files(): void
    {
        $this->seed(AppSettingsSeeder::class);
        Storage::fake('public');

        Storage::disk('public')->put('twa/icons/proxy-check.txt', 'ok-proxy');

        $response = $this->get('/media-files/twa/icons/proxy-check.txt');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/plain; charset=UTF-8');
    }

    public function test_assetlinks_endpoint_returns_empty_array_when_disabled(): void
    {
        $this->seed(AppSettingsSeeder::class);

        $response = $this->get(route('twa.assetlinks'));

        $response->assertOk();
        $response->assertExactJson([]);
    }

    public function test_assetlinks_endpoint_returns_android_relation_when_enabled_and_valid(): void
    {
        $this->seed(AppSettingsSeeder::class);

        $fingerprint = 'aa:bb:cc:dd:ee:ff:00:11:22:33:44:55:66:77:88:99:aa:bb:cc:dd:ee:ff:00:11:22:33:44:55:66:77:88:99';

        AppSetting::set('twa_enabled', true);
        AppSetting::set('twa_package_name', 'com.example.unisell');
        AppSetting::set('twa_sha256_fingerprints', [$fingerprint, 'INVALID-FINGERPRINT']);

        $response = $this->get(route('twa.assetlinks'));

        $response->assertOk();
        $response->assertExactJson([
            [
                'relation' => [
                    'delegate_permission/common.handle_all_urls',
                ],
                'target' => [
                    'namespace' => 'android_app',
                    'package_name' => 'com.example.unisell',
                    'sha256_cert_fingerprints' => [
                        strtoupper($fingerprint),
                    ],
                ],
            ],
        ]);
    }
}
