<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use Database\Seeders\AppSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SiteBrandingSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_two_part_multicolor_brand_text_settings(): void
    {
        $this->seed(AppSettingsSeeder::class);

        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.settings.update'), [
            'settings_section' => 'general',
            'site_name' => 'Unsell',
            'site_tagline' => 'Buy & Sell Anything',
            'app_url' => 'https://market.example.test',
            'site_logo_size_px' => 52,
            'site_brand_text_part_1' => 'Unisell',
            'site_brand_text_part_2' => 'NEW',
            'site_brand_text_color_1' => '#1e40af',
            'site_brand_text_color_2' => '#ea580c',
            'site_brand_text_spacing' => 'space',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertSame('Unisell', AppSetting::get('site_brand_text_part_1'));
        $this->assertSame('NEW', AppSetting::get('site_brand_text_part_2'));
        $this->assertSame('#1E40AF', AppSetting::get('site_brand_text_color_1'));
        $this->assertSame('#EA580C', AppSetting::get('site_brand_text_color_2'));
        $this->assertSame('space', AppSetting::get('site_brand_text_spacing'));
        $this->assertSame('https://market.example.test', AppSetting::get('app_url'));
        $this->assertSame(52, AppSetting::get('site_logo_size_px'));
    }

    public function test_home_and_login_use_configured_logo_size_setting(): void
    {
        $this->seed(AppSettingsSeeder::class);

        AppSetting::set('site_logo_size_px', 58);

        $homeResponse = $this->get(route('home'));
        $homeResponse->assertOk();
        $homeResponse->assertSee('style="width: 58px; height: 58px;"', false);

        $loginResponse = $this->get(route('login'));
        $loginResponse->assertOk();
        $loginResponse->assertSee('style="width: 58px; height: 58px;"', false);
    }

    public function test_home_and_login_render_two_part_multicolor_brand_title_without_space_by_default(): void
    {
        $this->seed(AppSettingsSeeder::class);

        AppSetting::set('site_brand_text_part_1', 'Unisell');
        AppSetting::set('site_brand_text_part_2', 'NEW');
        AppSetting::set('site_brand_text_color_1', '#1E40AF');
        AppSetting::set('site_brand_text_color_2', '#EA580C');
        AppSetting::set('site_brand_text_spacing', 'none');

        $homeResponse = $this->get(route('home'));
        $homeResponse->assertOk();
        $homeResponse->assertSee('data-brand-title="split"', false);
        $homeResponse->assertSee('<span style="color: #1E40AF;">Unisell</span><span style="color: #EA580C;">NEW</span>', false);

        $loginResponse = $this->get(route('login'));
        $loginResponse->assertOk();
        $loginResponse->assertSee('data-brand-title="split"', false);
        $loginResponse->assertSee('<span style="color: #1E40AF;">Unisell</span><span style="color: #EA580C;">NEW</span>', false);
    }

    public function test_home_and_login_render_two_part_multicolor_brand_title_with_single_space_option(): void
    {
        $this->seed(AppSettingsSeeder::class);

        AppSetting::set('site_brand_text_part_1', 'Unisell');
        AppSetting::set('site_brand_text_part_2', 'NEW');
        AppSetting::set('site_brand_text_color_1', '#1E40AF');
        AppSetting::set('site_brand_text_color_2', '#EA580C');
        AppSetting::set('site_brand_text_spacing', 'space');

        $homeResponse = $this->get(route('home'));
        $homeResponse->assertOk();
        $homeResponse->assertSee('<span style="color: #1E40AF;">Unisell</span><span style="color: #EA580C;"> NEW</span>', false);

        $loginResponse = $this->get(route('login'));
        $loginResponse->assertOk();
        $loginResponse->assertSee('<span style="color: #1E40AF;">Unisell</span><span style="color: #EA580C;"> NEW</span>', false);
    }

    public function test_general_settings_page_shows_two_spacing_options_for_brand_text(): void
    {
        $this->seed(AppSettingsSeeder::class);

        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.settings.index'));

        $response->assertOk();
        $response->assertSee('name="app_url"', false);
        $response->assertSee('name="site_logo_size_px"', false);
        $response->assertSee('name="site_brand_text_spacing"', false);
        $response->assertSee('>No space<', false);
        $response->assertSee('>Single space<', false);
    }

    public function test_general_settings_page_shows_download_link_for_existing_logo(): void
    {
        $this->seed(AppSettingsSeeder::class);
        Storage::fake('public');

        Storage::disk('public')->put('branding/site-logo-test.png', 'logo');
        AppSetting::set('site_logo', 'branding/site-logo-test.png');

        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.settings.index'));

        $response->assertOk();
        $response->assertSee('Download existing logo');
        $response->assertSee('/storage/branding/site-logo-test.png', false);
    }

    public function test_general_settings_page_includes_live_branding_preview_bindings(): void
    {
        $this->seed(AppSettingsSeeder::class);

        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.settings.index'));

        $response->assertOk();
        $response->assertSee('@change="onBrandingFileChanged($event, \'logo\')"', false);
        $response->assertSee('@change="onBrandingFileChanged($event, \'favicon\')"', false);
        $response->assertSee('siteLogoPreview', false);
        $response->assertSee('siteFaviconPreview', false);
    }

    public function test_admin_can_upload_site_logo_and_browser_icon_from_general_settings(): void
    {
        $this->seed(AppSettingsSeeder::class);
        Storage::fake('public');

        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.settings.update'), [
            'settings_section' => 'general',
            'site_name' => 'Unsell',
            'site_tagline' => 'Buy & Sell Anything',
            'site_logo_file' => UploadedFile::fake()->image('site-logo.png', 480, 180),
            'site_favicon_file' => UploadedFile::fake()->image('site-favicon.png', 128, 128),
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $siteLogo = (string) AppSetting::get('site_logo');
        $siteFavicon = (string) AppSetting::get('site_favicon');

        $this->assertStringStartsWith('branding/site-logo-', $siteLogo);
        $this->assertStringStartsWith('branding/site-favicon-', $siteFavicon);
        Storage::disk('public')->assertExists($siteLogo);
        Storage::disk('public')->assertExists($siteFavicon);
    }

    public function test_home_page_renders_configured_site_logo_and_favicon(): void
    {
        $this->seed(AppSettingsSeeder::class);
        Storage::fake('public');

        Storage::disk('public')->put('branding/site-logo-test.png', 'logo');
        Storage::disk('public')->put('branding/site-favicon-test.png', 'icon');

        AppSetting::set('site_logo', 'branding/site-logo-test.png');
        AppSetting::set('site_favicon', 'branding/site-favicon-test.png');

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('/storage/branding/site-logo-test.png', false);
        $response->assertSee('/storage/branding/site-favicon-test.png', false);
    }

    public function test_login_page_renders_configured_site_logo_and_favicon(): void
    {
        $this->seed(AppSettingsSeeder::class);
        Storage::fake('public');

        Storage::disk('public')->put('branding/site-logo-test.png', 'logo');
        Storage::disk('public')->put('branding/site-favicon-test.png', 'icon');

        AppSetting::set('site_logo', 'branding/site-logo-test.png');
        AppSetting::set('site_favicon', 'branding/site-favicon-test.png');

        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('/storage/branding/site-logo-test.png', false);
        $response->assertSee('/storage/branding/site-favicon-test.png', false);
    }
}