<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use App\Models\UserFeatureAccess;
use Database\Seeders\AppSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FeatureAccessLimitSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_free_call_and_map_limits_in_listings_settings(): void
    {
        $this->seed(AppSettingsSeeder::class);

        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.settings.update'), [
            'settings_section' => 'listings',
            'listing_moderation_enabled' => '1',
            'listing_allow_guest_view' => '1',
            'listing_expiry_days' => '60',
            'listing_max_images' => '8',
            'listing_max_per_user' => '20',
            'listing_description_min' => '20',
            'listing_price_required' => '0',
            'listing_location_required' => '0',
            'listing_allow_negotiation' => '1',
            'listing_auto_renew' => '0',
            'listing_allow_bump' => '1',
            'location_nearby_radius_km' => '30',
            'location_default_country' => 'IN',
            'free_call_access_limit' => '3',
            'free_map_access_limit' => '2',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertSame(3, AppSetting::get('free_call_access_limit'));
        $this->assertSame(2, AppSetting::get('free_map_access_limit'));
    }

    public function test_free_call_limit_blocks_after_configured_quota_without_subscription(): void
    {
        $this->seed(AppSettingsSeeder::class);
        AppSetting::set('free_call_access_limit', 1);

        $seller = User::factory()->create([
            'phone' => '+919999999999',
        ]);
        $buyer = User::factory()->create();
        $listing = $this->createApprovedListing($seller);

        $firstAttempt = $this->actingAs($buyer)->get(route('listings.start-call', $listing));

        $firstAttempt->assertStatus(302);
        $this->assertSame('tel:+919999999999', (string) $firstAttempt->headers->get('Location'));

        $secondAttempt = $this->actingAs($buyer)->get(route('listings.start-call', $listing));

        $secondAttempt->assertRedirect(route('subscriptions.index', ['feature' => 'call']));

        $usage = UserFeatureAccess::query()
            ->where('user_id', $buyer->id)
            ->where('feature', 'call')
            ->first();

        $this->assertNotNull($usage);
        $this->assertSame(1, $usage->used_count);
    }

    public function test_free_map_limit_blocks_after_configured_quota_without_subscription(): void
    {
        $this->seed(AppSettingsSeeder::class);
        AppSetting::set('free_map_access_limit', 1);

        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $listing = $this->createApprovedListing($seller, [
            'latitude' => 12.9716,
            'longitude' => 77.5946,
        ]);

        $firstAttempt = $this->actingAs($buyer)->get(route('listings.open-map', $listing));

        $firstAttempt->assertStatus(302);
        $firstLocation = (string) $firstAttempt->headers->get('Location');
        $this->assertTrue(Str::startsWith($firstLocation, 'https://www.google.com/maps?q='));

        $secondAttempt = $this->actingAs($buyer)->get(route('listings.open-map', $listing));

        $secondAttempt->assertRedirect(route('subscriptions.index', ['feature' => 'call']));

        $usage = UserFeatureAccess::query()
            ->where('user_id', $buyer->id)
            ->where('feature', 'map')
            ->first();

        $this->assertNotNull($usage);
        $this->assertSame(1, $usage->used_count);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createApprovedListing(User $owner, array $attributes = []): Listing
    {
        $category = Category::query()->create([
            'name' => 'Mobiles '.Str::upper(Str::random(4)),
            'slug' => 'mobiles-'.Str::lower(Str::random(8)),
            'icon' => null,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        return Listing::query()->create(array_merge([
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'title' => 'iPhone '.Str::upper(Str::random(4)),
            'slug' => 'iphone-'.Str::lower(Str::random(10)),
            'description' => 'Well maintained phone with original accessories and bill.',
            'price' => 35000,
            'price_type' => 'fixed',
            'condition' => 'used',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'address' => 'Indiranagar',
            'status' => 'approved',
            'published_at' => now(),
        ], $attributes));
    }
}
