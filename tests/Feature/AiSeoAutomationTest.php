<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use Database\Seeders\AppSettingsSeeder;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AiSeoAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_seo_optimizer_command_updates_report_and_meta(): void
    {
        $this->seed([AppSettingsSeeder::class, CategorySeeder::class]);

        AppSetting::set('ai_enabled', true);
        AppSetting::set('ai_provider', 'openai');
        AppSetting::set('ai_seo_optimizer_enabled', true);
        AppSetting::set('ai_seo_auto_apply_enabled', true);
        AppSetting::set('ai_seo_audit_interval_minutes', 60);

        $seller = User::factory()->create();
        $category = Category::query()->firstOrFail();

        $this->createApprovedListing($seller, $category, [
            'title' => 'iPhone 14 Pro 128GB Excellent Condition',
            'description' => 'Original box, battery health 92 percent, no repairs, serious buyers only.',
            'city' => 'Bengaluru',
            'views' => 320,
        ]);

        $this->createApprovedListing($seller, $category, [
            'title' => 'Samsung Galaxy S23 Ultra with Warranty',
            'description' => 'Premium phone with complete bill and accessories, lightly used.',
            'city' => 'Mumbai',
            'views' => 410,
        ]);

        $this->artisan('seo:ai-optimize --force')->assertExitCode(0);

        $this->assertNotSame('', trim((string) AppSetting::get('ai_seo_last_run_at', '')));
        $this->assertGreaterThan(0, (int) AppSetting::get('ai_seo_last_score', 0));
        $this->assertNotSame('', trim((string) AppSetting::get('seo_meta_description', '')));

        $keywords = AppSetting::get('ai_seo_last_keywords', []);
        $this->assertIsArray($keywords);
        $this->assertNotEmpty($keywords);
    }

    public function test_admin_can_trigger_ai_seo_audit_from_settings_page(): void
    {
        $this->seed([AppSettingsSeeder::class, CategorySeeder::class]);

        AppSetting::set('ai_enabled', true);
        AppSetting::set('ai_seo_optimizer_enabled', true);
        AppSetting::set('ai_seo_auto_apply_enabled', true);

        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.settings.ai-seo.run'));

        $response->assertRedirect();
        $response->assertSessionHas('settings_section', 'ai');
        $this->assertNotSame('', trim((string) AppSetting::get('ai_seo_last_run_at', '')));
    }

    public function test_public_sitemap_and_robots_routes_are_available(): void
    {
        $this->seed([AppSettingsSeeder::class, CategorySeeder::class]);

        $seller = User::factory()->create();
        $category = Category::query()->firstOrFail();

        $listing = $this->createApprovedListing($seller, $category, [
            'title' => 'Used Royal Enfield Classic 350',
            'city' => 'Pune',
        ]);

        $sitemap = $this->get(route('seo.sitemap'));
        $sitemap->assertOk();
        $sitemap->assertSee(route('listings.show', $listing->slug), false);

        $robots = $this->get(route('seo.robots'));
        $robots->assertOk();
        $robots->assertSee('Sitemap: '.url('/sitemap.xml'));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createApprovedListing(User $seller, Category $category, array $attributes = []): Listing
    {
        return Listing::query()->create(array_merge([
            'user_id' => $seller->id,
            'category_id' => $category->id,
            'title' => 'Listing '.Str::upper(Str::random(4)),
            'slug' => Str::slug('listing '.Str::random(10)),
            'description' => 'Well maintained item with genuine details and transparent pricing.',
            'price' => 25000,
            'currency' => 'INR',
            'condition' => 'used',
            'city' => 'Patna',
            'state' => 'Bihar',
            'status' => 'approved',
            'views' => 150,
            'published_at' => now(),
        ], $attributes));
    }
}
