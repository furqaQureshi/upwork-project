<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use Database\Seeders\AppSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleAnalyticsSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_google_analytics_measurement_id_from_marketing_settings(): void
    {
        $this->seed(AppSettingsSeeder::class);

        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.settings.update'), [
            'settings_section' => 'marketing',
            'seo_google_analytics_id' => 'g-test123abc',
            'seo_meta_description' => 'Analytics-enabled marketplace.',
            'seo_meta_keywords' => 'analytics, marketplace',
            'seo_robots' => 'index,follow',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertSame('G-TEST123ABC', AppSetting::get('seo_google_analytics_id'));
    }

    public function test_home_page_renders_google_analytics_script_when_configured(): void
    {
        $this->seed(AppSettingsSeeder::class);

        AppSetting::set('seo_google_analytics_id', 'G-TEST123ABC');

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('https://www.googletagmanager.com/gtag/js?id=G-TEST123ABC', false);
        $response->assertSee("gtag('config', 'G-TEST123ABC');", false);
    }

    public function test_login_page_renders_google_analytics_script_when_configured(): void
    {
        $this->seed(AppSettingsSeeder::class);

        AppSetting::set('seo_google_analytics_id', 'G-TEST123ABC');

        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('https://www.googletagmanager.com/gtag/js?id=G-TEST123ABC', false);
        $response->assertSee("gtag('config', 'G-TEST123ABC');", false);
    }
}