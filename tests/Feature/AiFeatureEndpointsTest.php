<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Category;
use App\Models\Conversation;
use App\Models\Listing;
use App\Models\Message;
use App\Models\SubscriptionPackage;
use App\Models\SubscriptionPackagePurchase;
use App\Models\User;
use Database\Seeders\AppSettingsSeeder;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AiFeatureEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_listing_assistant_generates_draft_with_mock_provider(): void
    {
        $this->seed([AppSettingsSeeder::class, CategorySeeder::class]);

        AppSetting::set('ai_enabled', true);
        AppSetting::set('ai_listing_assistant_enabled', true);
        AppSetting::set('ai_provider', 'mock');
        AppSetting::set('ai_force_real_provider', false);

        $user = User::factory()->create();
        $this->createAiEnabledPurchase($user, 10);
        $category = Category::query()->firstOrFail();

        $response = $this->actingAs($user)->postJson(route('ai.listings.generate'), [
            'title' => 'Honda City VX 2019',
            'description' => 'Well maintained car with service records.',
            'category_id' => $category->id,
            'condition' => 'used',
            'city' => 'Patna',
            'state' => 'Bihar',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.provider', 'local')
            ->assertJsonStructure([
                'data' => [
                    'title',
                    'description',
                    'attributes',
                    'price_recommendation' => ['method'],
                    'time_savings_percent' => ['min', 'max'],
                    'quality_improvement_percent',
                ],
            ]);
    }

    public function test_assistant_works_locally_when_ai_suite_master_switch_is_off(): void
    {
        $this->seed([AppSettingsSeeder::class, CategorySeeder::class]);

        AppSetting::set('ai_enabled', false);
        AppSetting::set('ai_compass_enabled', true);
        AppSetting::set('ai_provider', 'mock');
        AppSetting::set('ai_force_real_provider', false);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('ai.compass.chat'), [
            'query' => 'How do I sell my phone quickly?',
            'history' => [],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.provider', 'local');
    }

    public function test_ai_listing_assistant_requires_ai_subscription_credits(): void
    {
        $this->seed([AppSettingsSeeder::class, CategorySeeder::class]);

        AppSetting::set('ai_enabled', true);
        AppSetting::set('ai_listing_assistant_enabled', true);
        AppSetting::set('ai_provider', 'mock');
        AppSetting::set('ai_force_real_provider', false);

        $user = User::factory()->create();
        $category = Category::query()->firstOrFail();

        $response = $this->actingAs($user)->postJson(route('ai.listings.generate'), [
            'title' => 'Honda City VX 2019',
            'description' => 'Well maintained car with service records.',
            'category_id' => $category->id,
        ]);

        $response
            ->assertForbidden()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'AI credits are exhausted. Buy or renew a package with AI access to continue.');
    }

    public function test_compassgpt_returns_property_recommendations(): void
    {
        $this->seed([AppSettingsSeeder::class, CategorySeeder::class]);

        AppSetting::set('ai_enabled', true);
        AppSetting::set('ai_compass_enabled', true);
        AppSetting::set('ai_provider', 'mock');
        AppSetting::set('ai_force_real_provider', false);

        $user = User::factory()->create();
        $this->createAiEnabledPurchase($user, 10);
        $seller = User::factory()->create();

        $property = Category::query()->where('name', 'Property')->firstOrFail();

        Listing::create([
            'user_id' => $seller->id,
            'category_id' => $property->id,
            'title' => '2BHK Apartment near Railway Station',
            'slug' => Str::slug('2BHK Apartment near Railway Station '.Str::random(6)),
            'description' => 'Spacious 2BHK in Gaya under 15 lakh near station with parking.',
            'price' => 1450000,
            'currency' => 'INR',
            'condition' => 'used',
            'city' => 'Gaya',
            'state' => 'Bihar',
            'status' => 'approved',
            'published_at' => now(),
        ]);

        $response = $this->actingAs($user)->postJson(route('ai.compass.chat'), [
            'query' => '2BHK in Gaya under 15 lakh near station',
            'history' => [],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonCount(1, 'data.recommendations');
    }

    public function test_ai_fraud_detection_blocks_scam_chat_message(): void
    {
        $this->seed([AppSettingsSeeder::class, CategorySeeder::class]);

        AppSetting::set('ai_enabled', true);
        AppSetting::set('ai_fraud_detection_enabled', true);
        AppSetting::set('ai_block_scam_messages', true);
        AppSetting::set('ai_confidence_threshold', 40);

        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $category = Category::query()->firstOrFail();

        $listing = Listing::create([
            'user_id' => $seller->id,
            'category_id' => $category->id,
            'title' => 'Premium Phone for Sale',
            'slug' => Str::slug('Premium Phone for Sale '.Str::random(6)),
            'description' => 'Excellent condition phone with bill and box.',
            'price' => 22000,
            'currency' => 'INR',
            'condition' => 'used',
            'city' => 'Patna',
            'state' => 'Bihar',
            'status' => 'approved',
            'published_at' => now(),
        ]);

        $conversation = Conversation::create([
            'listing_id' => $listing->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'last_message_at' => now(),
        ]);

        $response = $this->actingAs($buyer)->post(route('chat.message', $conversation), [
            'body' => 'Send OTP and advance payment now. Scan this QR code before meeting.',
        ]);

        $response->assertSessionHasErrors('body');
        $this->assertSame(0, Message::query()->count());
    }

    private function createAiEnabledPurchase(User $user, int $aiCredits): SubscriptionPackagePurchase
    {
        $package = SubscriptionPackage::create([
            'name' => 'AI Pack '.Str::upper(Str::random(4)),
            'package_type' => 'listing',
            'price' => 299,
            'discount_percent' => 0,
            'final_price' => 299,
            'package_duration_type' => 'limited',
            'package_duration_days' => 30,
            'item_limit_type' => 'limited',
            'item_limit_count' => 5,
            'listing_duration_type' => 'standard',
            'listing_duration_days' => 30,
            'category_scope' => 'global',
            'allows_ai' => true,
            'ai_usage_limit_type' => 'limited',
            'ai_usage_limit_count' => $aiCredits,
            'is_active' => true,
        ]);

        return SubscriptionPackagePurchase::create([
            'subscription_package_id' => $package->id,
            'user_id' => $user->id,
            'gateway' => 'mock',
            'merchant_order_id' => 'SPK'.Str::upper(Str::random(14)),
            'amount' => $package->final_price,
            'currency' => 'INR',
            'status' => 'paid',
            'used_items' => 0,
            'remaining_items' => 5,
            'used_ai_items' => 0,
            'remaining_ai_items' => $aiCredits,
            'package_started_at' => now(),
            'package_expires_at' => now()->addDays(30),
            'paid_at' => now(),
            'activated_at' => now(),
            'meta' => ['source' => 'test'],
        ]);
    }
}
