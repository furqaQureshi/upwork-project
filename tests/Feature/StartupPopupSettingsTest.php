<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use Database\Seeders\AppSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StartupPopupSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_startup_popup_settings(): void
    {
        $this->seed(AppSettingsSeeder::class);

        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.settings.update'), [
            'settings_section' => 'notifications',
            'notification_poll_seconds' => '20',
            'notification_email_enabled' => '1',
            'notification_new_message' => '1',
            'notification_listing_approved' => '1',
            'notification_listing_expired' => '1',
            'notification_push_enabled' => '0',
            'startup_popup_enabled' => '1',
            'startup_popup_title' => 'Mega festive offer',
            'startup_popup_message' => 'Save more on your first featured listing this week.',
            'startup_popup_button_label' => 'View offer',
            'startup_popup_link_url' => 'offers/festive-deal',
            'startup_popup_style' => 'festive',
            'startup_popup_open_new_tab' => '1',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertTrue((bool) AppSetting::get('startup_popup_enabled'));
        $this->assertSame('Mega festive offer', AppSetting::get('startup_popup_title'));
        $this->assertSame('Save more on your first featured listing this week.', AppSetting::get('startup_popup_message'));
        $this->assertSame('View offer', AppSetting::get('startup_popup_button_label'));
        $this->assertSame('/offers/festive-deal', AppSetting::get('startup_popup_link_url'));
        $this->assertSame('festive', AppSetting::get('startup_popup_style'));
        $this->assertTrue((bool) AppSetting::get('startup_popup_open_new_tab'));
    }

    public function test_admin_can_upload_startup_popup_image_file(): void
    {
        $this->seed(AppSettingsSeeder::class);
        Storage::fake('public');

        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.settings.update'), [
            'settings_section' => 'notifications',
            'notification_poll_seconds' => '20',
            'notification_email_enabled' => '1',
            'notification_new_message' => '1',
            'notification_listing_approved' => '1',
            'notification_listing_expired' => '1',
            'notification_push_enabled' => '0',
            'startup_popup_enabled' => '1',
            'startup_popup_title' => 'Flash sale',
            'startup_popup_message' => 'Open the deal and claim your discounted boost package.',
            'startup_popup_link_url' => '/featured',
            'startup_popup_image_file' => UploadedFile::fake()->image('startup-popup.jpg', 1200, 900),
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $storedImage = (string) AppSetting::get('startup_popup_image_url');

        $this->assertStringStartsWith('notifications/startup-popups/startup-popup-', $storedImage);
        Storage::disk('public')->assertExists($storedImage);
    }

    public function test_home_page_renders_startup_popup_markup_when_enabled(): void
    {
        $this->seed(AppSettingsSeeder::class);

        AppSetting::set('startup_popup_enabled', true);
        AppSetting::set('startup_popup_title', 'Limited time launch deal');
        AppSetting::set('startup_popup_message', 'Tap through to unlock the startup offer.');
        AppSetting::set('startup_popup_button_label', 'Open deal');
        AppSetting::set('startup_popup_link_url', '/special-offer');
        AppSetting::set('startup_popup_image_url', 'https://cdn.example.com/popup.jpg');
        AppSetting::set('startup_popup_style', 'minimal');
        AppSetting::set('startup_popup_open_new_tab', false);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('data-startup-popup-root', false);
        $response->assertSee('Limited time launch deal');
        $response->assertSee('Tap through to unlock the startup offer.');
        $response->assertSee('/special-offer');
        $response->assertSee('data-startup-popup-style="minimal"', false);
        $response->assertSee('unsell_startup_popup_seen');
    }
}
