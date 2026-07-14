<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use Database\Seeders\AppSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HomeBannerSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_home_banner_mode_image_url_and_text_fields(): void
    {
        $this->seed(AppSettingsSeeder::class);

        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.settings.update'), [
            'settings_section' => 'general',
            'home_banner_mode' => 'image',
            'home_banner_images' => [
                'https://cdn.example.com/banner/home-1.jpg',
                'https://cdn.example.com/banner/home-2.jpg',
            ],
            'home_banner_slide_1_badge' => 'Weekend Picks',
            'home_banner_slide_1_title' => 'Best cars near you',
            'home_banner_slide_1_desc' => 'Top local deals, verified sellers.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertSame('image', AppSetting::get('home_banner_mode'));
        $this->assertSame([
            'https://cdn.example.com/banner/home-1.jpg',
            'https://cdn.example.com/banner/home-2.jpg',
        ], AppSetting::get('home_banner_images'));
        $this->assertSame('https://cdn.example.com/banner/home-1.jpg', AppSetting::get('home_banner_image_url'));
        $this->assertSame('Weekend Picks', AppSetting::get('home_banner_slide_1_badge'));
        $this->assertSame('Best cars near you', AppSetting::get('home_banner_slide_1_title'));
        $this->assertSame('Top local deals, verified sellers.', AppSetting::get('home_banner_slide_1_desc'));
    }

    public function test_home_page_uses_image_banner_when_image_mode_is_enabled(): void
    {
        $this->seed(AppSettingsSeeder::class);

        AppSetting::set('home_banner_mode', 'image');
        AppSetting::set('home_banner_images', [
            'https://cdn.example.com/banner/home-1.jpg',
            'https://cdn.example.com/banner/home-2.jpg',
        ]);
        AppSetting::set('home_banner_image_url', 'https://cdn.example.com/banner/home-1.jpg');

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('https://cdn.example.com/banner/home-1.jpg');
        $response->assertSee('https://cdn.example.com/banner/home-2.jpg');
        $response->assertDontSee('Trending Deals');
    }

    public function test_admin_can_upload_home_banner_image_file(): void
    {
        $this->seed(AppSettingsSeeder::class);
        Storage::fake('public');

        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.settings.update'), [
            'settings_section' => 'general',
            'home_banner_mode' => 'image',
            'home_banner_image_files' => [
                UploadedFile::fake()->image('banner-1.jpg', 1200, 500),
                UploadedFile::fake()->image('banner-2.jpg', 1200, 500),
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $storedImages = AppSetting::get('home_banner_images');
        $this->assertIsArray($storedImages);
        $this->assertCount(2, $storedImages);
        $this->assertStringStartsWith('banners/home-banner-', (string) $storedImages[0]);
        $this->assertStringStartsWith('banners/home-banner-', (string) $storedImages[1]);
        Storage::disk('public')->assertExists((string) $storedImages[0]);
        Storage::disk('public')->assertExists((string) $storedImages[1]);
        $this->assertSame((string) $storedImages[0], AppSetting::get('home_banner_image_url'));
    }

    public function test_home_page_uses_edited_text_slider_content(): void
    {
        $this->seed(AppSettingsSeeder::class);

        AppSetting::set('home_banner_mode', 'text');
        AppSetting::set('home_banner_slide_2_title', 'Fast verified selling');

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('Fast verified selling');
    }

    public function test_admin_can_save_app_banner_images_for_mobile_api(): void
    {
        $this->seed(AppSettingsSeeder::class);
        Storage::fake('public');

        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.settings.update'), [
            'settings_section' => 'general',
            'app_banner_image_files' => [
                UploadedFile::fake()->image('app-banner-1.jpg', 1400, 420),
                UploadedFile::fake()->image('app-banner-2.jpg', 900, 900),
            ],
            'app_banner_display_seconds' => 7,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $storedImages = AppSetting::get('app_banner_images');
        $this->assertIsArray($storedImages);
        $this->assertCount(2, $storedImages);
        $this->assertStringStartsWith('banners/app-banner-', (string) $storedImages[0]);
        $this->assertStringStartsWith('banners/app-banner-', (string) $storedImages[1]);
        Storage::disk('public')->assertExists((string) $storedImages[0]);
        Storage::disk('public')->assertExists((string) $storedImages[1]);
        $this->assertSame(7, (int) AppSetting::get('app_banner_display_seconds'));
    }

    public function test_home_api_prefers_app_banner_images_even_in_text_mode(): void
    {
        $this->seed(AppSettingsSeeder::class);

        AppSetting::set('home_banner_mode', 'text');
        AppSetting::set('app_banner_images', [
            'https://cdn.example.com/app-banner-1.jpg',
            'https://cdn.example.com/app-banner-2.jpg',
        ]);
        AppSetting::set('app_banner_display_seconds', 6);

        $response = $this->getJson('/api/v1/home');

        $response->assertOk();
        $response->assertJsonPath('settings.banner_images.0', 'https://cdn.example.com/app-banner-1.jpg');
        $response->assertJsonPath('settings.banner_images.1', 'https://cdn.example.com/app-banner-2.jpg');
        $response->assertJsonPath('settings.banner_display_seconds', 6);
    }

    public function test_admin_can_remove_and_reorder_existing_app_banners_without_reupload(): void
    {
        $this->seed(AppSettingsSeeder::class);

        AppSetting::set('app_banner_images', [
            'banners/app-banner-a.jpg',
            'banners/app-banner-b.jpg',
            'banners/app-banner-c.jpg',
        ]);

        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.settings.update'), [
            'settings_section' => 'general',
            'app_banner_existing_images' => [
                'banners/app-banner-c.jpg',
                'banners/app-banner-a.jpg',
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertSame([
            'banners/app-banner-c.jpg',
            'banners/app-banner-a.jpg',
        ], AppSetting::get('app_banner_images'));
    }
}
