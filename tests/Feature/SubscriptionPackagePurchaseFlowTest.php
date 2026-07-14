<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Category;
use App\Models\Listing;
use App\Models\SubscriptionPackage;
use App\Models\SubscriptionPackagePurchase;
use App\Models\User;
use App\Services\SubscriptionPackages\SubscriptionEntitlementService;
use App\Services\SubscriptionPackages\SubscriptionPackagePaymentService;
use Database\Seeders\AppSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class SubscriptionPackagePurchaseFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_subscription_packages_page(): void
    {
        $this->seed(AppSettingsSeeder::class);

        $user = User::factory()->create();

        $this->createListingPackage('Basic Pack', 199, 30, 'limited', 10);
        $this->createFeaturedPackage('Boost Pack', 299, 30, 'limited', 5);

        $response = $this->actingAs($user)->get(route('subscriptions.index'));

        $response->assertOk();
        $response->assertSee('Subscription Package');
        $response->assertSee('Upgrade Your Account');

        $plansResponse = $this->actingAs($user)->get(route('subscriptions.plans'));

        $plansResponse->assertOk();
        $plansResponse->assertSee('All Subscription Plans');
        $plansResponse->assertSee('Basic Pack');
        $plansResponse->assertSee('Boost Pack');
    }

    public function test_api_overview_returns_customer_packages_gateway_status_and_story_entitlement(): void
    {
        $this->seed(AppSettingsSeeder::class);

        AppSetting::set('razorpay_key_id', 'rzp_test_overview');
        AppSetting::set('razorpay_key_secret', 'razor-secret-overview');
        AppSetting::set('razorpay_base_url', 'https://api.razorpay.com');

        $user = User::factory()->create();
        $listingPackage = $this->createListingPackage('App Listing Pack', 199, 30, 'limited', 10);
        $featuredPackage = $this->createFeaturedPackage('App Featured Pack', 299, 30, 'limited', 4);
        $storyPackage = $this->createStoryPackage('App Story Pack', 149, 30, 'limited', 8);

        $this->createActivatedPurchase($user, $storyPackage);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/subscriptions/overview');

        $response->assertOk()
            ->assertJsonPath('data.payment_gateway_ready', true)
            ->assertJsonPath('data.payment_gateway_label', 'Razorpay')
            ->assertJsonFragment(['name' => $listingPackage->name])
            ->assertJsonFragment(['name' => $featuredPackage->name])
            ->assertJsonFragment(['name' => $storyPackage->name])
            ->assertJsonFragment(['package_type' => 'story']);

        $this->assertTrue(collect($response->json('data.active_purchases'))->contains(function (array $purchase): bool {
            return data_get($purchase, 'package.package_type') === 'story';
        }));
    }

    public function test_user_can_purchase_listing_package_via_razorpay(): void
    {
        $this->seed(AppSettingsSeeder::class);

        AppSetting::set('razorpay_key_id', 'rzp_test_basic123');
        AppSetting::set('razorpay_key_secret', 'razor-secret-basic');
        AppSetting::set('razorpay_base_url', 'https://api.razorpay.com');

        $user = User::factory()->create();
        $package = $this->createListingPackage('Starter Pack', 299, 30, 'limited', 25, 'standard', 30, false, true, 'limited', 12);

        Http::fake([
            'https://api.razorpay.com/v1/orders' => Http::response(['id' => 'order_basic_001'], 200),
        ]);

        $response = $this->actingAs($user)->post(route('subscriptions.initialize', $package), [
            'gateway' => 'razorpay',
        ]);

        $response->assertOk();
        $response->assertSessionHasNoErrors();

        $purchase = SubscriptionPackagePurchase::query()
            ->where('user_id', $user->id)
            ->where('subscription_package_id', $package->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($purchase);
        $this->assertSame('razorpay', $purchase->gateway);
        $this->assertSame('initiated', $purchase->status);

        $signature = hash_hmac('sha256', 'order_basic_001|pay_basic_001', 'razor-secret-basic');

        $callbackResponse = $this->post(route('subscriptions.callback.razorpay'), [
            'razorpay_order_id' => 'order_basic_001',
            'razorpay_payment_id' => 'pay_basic_001',
            'razorpay_signature' => $signature,
        ]);

        $callbackResponse->assertRedirect(route('subscriptions.index'));

        $activatedPurchase = $purchase->fresh();

        $this->assertNotNull($activatedPurchase);
        $this->assertSame('paid', $activatedPurchase->status);
        $this->assertNotNull($activatedPurchase->paid_at);
        $this->assertNotNull($activatedPurchase->package_started_at);
        $this->assertSame(25, (int) $activatedPurchase->remaining_items);
        $this->assertSame(0, (int) $activatedPurchase->used_items);
        $this->assertSame(12, (int) $activatedPurchase->remaining_ai_items);
        $this->assertSame(0, (int) $activatedPurchase->used_ai_items);
    }

    public function test_user_can_purchase_unlimited_featured_package_via_razorpay(): void
    {
        $this->seed(AppSettingsSeeder::class);

        AppSetting::set('razorpay_key_id', 'rzp_test_unlimited');
        AppSetting::set('razorpay_key_secret', 'razor-secret-unlimited');
        AppSetting::set('razorpay_base_url', 'https://api.razorpay.com');

        $user = User::factory()->create();
        $package = $this->createFeaturedPackage('Unlimited Boost', 999, null, 'unlimited', null);

        Http::fake([
            'https://api.razorpay.com/v1/orders' => Http::response(['id' => 'order_unlimited_001'], 200),
        ]);

        $response = $this->actingAs($user)->post(route('subscriptions.initialize', $package), [
            'gateway' => 'razorpay',
        ]);

        $response->assertOk();
        $response->assertSessionHasNoErrors();

        $purchase = SubscriptionPackagePurchase::query()
            ->where('user_id', $user->id)
            ->where('subscription_package_id', $package->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($purchase);

        $signature = hash_hmac('sha256', 'order_unlimited_001|pay_unlimited_001', 'razor-secret-unlimited');

        $this->post(route('subscriptions.callback.razorpay'), [
            'razorpay_order_id' => 'order_unlimited_001',
            'razorpay_payment_id' => 'pay_unlimited_001',
            'razorpay_signature' => $signature,
        ]);

        $activatedPurchase = $purchase->fresh();

        $this->assertSame('paid', $activatedPurchase?->status);
        $this->assertNull($activatedPurchase->remaining_items);
    }

    public function test_razorpay_callback_with_bad_signature_does_not_activate_purchase(): void
    {
        $this->seed(AppSettingsSeeder::class);

        AppSetting::set('razorpay_key_id', 'rzp_test_failtest');
        AppSetting::set('razorpay_key_secret', 'razor-secret-fail');
        AppSetting::set('razorpay_base_url', 'https://api.razorpay.com');

        $user = User::factory()->create();
        $package = $this->createListingPackage('Fail Pack', 299, 30, 'limited', 25);

        Http::fake([
            'https://api.razorpay.com/v1/orders' => Http::response(['id' => 'order_fail_001'], 200),
        ]);

        $this->actingAs($user)->post(route('subscriptions.initialize', $package), [
            'gateway' => 'razorpay',
        ]);

        $purchase = SubscriptionPackagePurchase::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($purchase);

        $callbackResponse = $this->post(route('subscriptions.callback.razorpay'), [
            'razorpay_order_id' => 'order_fail_001',
            'razorpay_payment_id' => 'pay_fail_001',
            'razorpay_signature' => 'invalid_bad_signature',
        ]);

        $callbackResponse->assertRedirect();

        // Invalid signature → service marks as failed (not activated)
        $this->assertNotSame('paid', $purchase->fresh()->status);
    }

    public function test_listing_package_entitlement_can_be_consumed(): void
    {
        $this->seed(AppSettingsSeeder::class);

        $user = User::factory()->create();
        $package = $this->createListingPackage('Basic Pack', 299, 30, 'limited', 5);

        $purchase = $this->createActivatedPurchase($user, $package);

        $category = $this->createCategory('Electronics');
        $listing = $this->createApprovedListing($user, $category);

        $entitlement = new SubscriptionEntitlementService();
        $consumed = $entitlement->consumePurchase($purchase, 'listing_create', $listing);

        $this->assertNotNull($consumed);
        $this->assertSame(4, (int) $consumed->remaining_items);
        $this->assertSame(1, (int) $consumed->used_items);

        $usage = $purchase->fresh()->usages()->where('usage_type', 'listing_create')->first();

        $this->assertNotNull($usage);
        $this->assertSame($listing->id, $usage->listing_id);
    }

    public function test_consume_is_idempotent_for_same_listing_and_usage_type(): void
    {
        $this->seed(AppSettingsSeeder::class);

        $user = User::factory()->create();
        $package = $this->createListingPackage('Basic Pack', 299, 30, 'limited', 5);

        $purchase = $this->createActivatedPurchase($user, $package);

        $category = $this->createCategory('Electronics');
        $listing = $this->createApprovedListing($user, $category);

        $entitlement = new SubscriptionEntitlementService();

        $entitlement->consumePurchase($purchase, 'listing_create', $listing);
        $entitlement->consumePurchase($purchase, 'listing_create', $listing);

        $freshPurchase = $purchase->fresh();

        $this->assertSame(4, (int) $freshPurchase->remaining_items);
        $this->assertSame(1, (int) $freshPurchase->used_items);

        $this->assertSame(1, $freshPurchase->usages()->where('usage_type', 'listing_create')->count());
    }

    public function test_consume_returns_null_when_no_items_remaining(): void
    {
        $this->seed(AppSettingsSeeder::class);

        $user = User::factory()->create();
        $package = $this->createListingPackage('Tiny Pack', 99, 30, 'limited', 0);

        $purchase = $this->createActivatedPurchase($user, $package, 0);

        $category = $this->createCategory('Vehicles');
        $listing = $this->createApprovedListing($user, $category);

        $entitlement = new SubscriptionEntitlementService();
        $result = $entitlement->consumePurchase($purchase, 'listing_create', $listing);

        $this->assertNull($result);
    }

    public function test_listing_posting_requires_active_package_when_packages_are_configured(): void
    {
        $this->seed(AppSettingsSeeder::class);

        $this->createListingPackage('Starter Pack', 299, 30, 'limited', 25);

        $user = User::factory()->create();
        $category = $this->createCategory('Mobiles');

        $response = $this->actingAs($user)->post(route('listings.store'), [
            'title' => 'iPhone 14 Pro',
            'category_id' => $category->id,
            'price' => '99999',
            'description' => 'Excellent condition phone with full accessories included.',
            'condition' => 'used',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'address' => 'Andheri',
            'images' => [UploadedFile::fake()->image('phone.jpg')],
        ]);

        $response->assertSessionHasErrors('category_id');
        $this->assertDatabaseCount('listings', 0);
    }

    public function test_listing_posting_with_package_requires_approved_seller_verification(): void
    {
        $this->seed(AppSettingsSeeder::class);

        $user = User::factory()->create([
            'seller_verification_status' => 'pending',
            'seller_verified_at' => null,
        ]);
        $category = $this->createCategory('Electronics');

        $package = $this->createListingPackage('Verified Seller Pack', 399, 30, 'limited', 10);
        $this->createActivatedPurchase($user, $package);

        $response = $this->actingAs($user)->post(route('listings.store'), [
            'title' => 'OnePlus 12R 256GB',
            'category_id' => $category->id,
            'price' => '42000',
            'description' => 'Excellent condition phone with bill and box included in the package.',
            'condition' => 'used',
            'city' => 'Noida',
            'state' => 'UP',
            'address' => 'Sector 62',
            'images' => [UploadedFile::fake()->image('phone.jpg')],
        ]);

        $response->assertSessionHasErrors('category_id');
        $this->assertDatabaseCount('listings', 0);
    }

    public function test_listing_posting_consumes_package_quota(): void
    {
        $this->seed(AppSettingsSeeder::class);

        $user = User::factory()->create([
            'seller_verification_status' => 'approved',
            'seller_verified_at' => now(),
        ]);
        $category = $this->createCategory('Electronics');

        $package = $this->createListingPackage('Standard Pack', 299, 30, 'limited', 10);
        $purchase = $this->createActivatedPurchase($user, $package);

        $response = $this->actingAs($user)->post(route('listings.store'), [
            'title' => 'Samsung Galaxy S23',
            'category_id' => $category->id,
            'price' => '55000',
            'description' => 'Brand new sealed unit with all accessories and warranty.',
            'condition' => 'new',
            'city' => 'Delhi',
            'state' => 'Delhi',
            'address' => 'Connaught Place',
            'images' => [UploadedFile::fake()->image('phone.jpg')],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertSame(9, (int) $purchase->fresh()->remaining_items);
        $this->assertSame(1, (int) $purchase->fresh()->used_items);

        $usage = $purchase->fresh()->usages()->where('usage_type', 'listing_create')->first();
        $this->assertNotNull($usage);
    }

    public function test_listing_posting_works_without_packages_when_none_configured(): void
    {
        $this->seed(AppSettingsSeeder::class);

        $user = User::factory()->create();
        $category = $this->createCategory('Furniture');

        $response = $this->actingAs($user)->post(route('listings.store'), [
            'title' => 'Wooden Dining Table Set',
            'category_id' => $category->id,
            'price' => '18000',
            'description' => 'Solid wood dining table with 6 chairs, barely used and in excellent condition.',
            'condition' => 'used',
            'city' => 'Pune',
            'state' => 'Maharashtra',
            'address' => 'Hinjewadi',
            'images' => [UploadedFile::fake()->image('table.jpg')],
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseCount('listings', 1);
    }

    public function test_featured_boost_via_package_deducts_quota(): void
    {
        $this->seed(AppSettingsSeeder::class);

        $user = User::factory()->create();
        $featuredPackage = $this->createFeaturedPackage('Featured Pro', 499, 30, 'limited', 3, 'custom', 15);
        $purchase = $this->createActivatedPurchase($user, $featuredPackage);

        $category = $this->createCategory('Cars');
        $listing = $this->createApprovedListing($user, $category);

        $response = $this->actingAs($user)->post(route('payments.initialize', $listing), [
            'gateway' => 'mock',
        ]);

        $response->assertRedirect(route('listings.show', $listing));
        $response->assertSessionHasNoErrors();

        $listing->refresh();
        $this->assertTrue((bool) $listing->is_featured);
        $this->assertNotNull($listing->featured_until);

        $freshPurchase = $purchase->fresh();
        $this->assertSame(2, (int) $freshPurchase->remaining_items);
        $this->assertSame(1, (int) $freshPurchase->used_items);
    }

    public function test_featured_boost_redirects_to_packages_when_no_active_package(): void
    {
        $this->seed(AppSettingsSeeder::class);

        $user = User::factory()->create();
        $this->createFeaturedPackage('Featured Pro', 499, 30, 'limited', 5);

        $category = $this->createCategory('Cars');
        $listing = $this->createApprovedListing($user, $category);

        $response = $this->actingAs($user)->get(route('payments.checkout', $listing));

        $response->assertRedirect(route('subscriptions.index'));
    }

    public function test_package_payment_razorpay_checkout_and_callback(): void
    {
        $this->seed(AppSettingsSeeder::class);

        AppSetting::set('razorpay_key_id', 'rzp_test_abc123');
        AppSetting::set('razorpay_key_secret', 'razor-secret');
        AppSetting::set('razorpay_base_url', 'https://api.razorpay.com');

        $user = User::factory()->create();
        $package = $this->createListingPackage('Pro Pack', 999, 30, 'limited', 50);

        Http::fake([
            'https://api.razorpay.com/v1/orders' => Http::response([
                'id' => 'order_pkg_12345',
            ], 200),
        ]);

        $response = $this->actingAs($user)->post(route('subscriptions.initialize', $package), [
            'gateway' => 'razorpay',
        ]);

        $response->assertOk();
        $response->assertSee('Razorpay Checkout');

        $purchase = SubscriptionPackagePurchase::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($purchase);
        $this->assertSame('razorpay', $purchase->gateway);
        $this->assertSame('order_pkg_12345', $purchase->fresh()->provider_order_id);

        $signature = hash_hmac('sha256', 'order_pkg_12345|pay_pkg_12345', 'razor-secret');

        $callbackResponse = $this->post(route('subscriptions.callback.razorpay'), [
            'razorpay_order_id' => 'order_pkg_12345',
            'razorpay_payment_id' => 'pay_pkg_12345',
            'razorpay_signature' => $signature,
        ]);

        $callbackResponse->assertRedirect(route('subscriptions.index'));

        $activatedPurchase = $purchase->fresh();

        $this->assertSame('paid', $activatedPurchase?->status);
        $this->assertSame('pay_pkg_12345', $activatedPurchase->provider_payment_id);
        $this->assertSame(50, (int) $activatedPurchase->remaining_items);
    }

    public function test_package_payment_paytm_checkout_and_callback(): void
    {
        $this->seed(AppSettingsSeeder::class);

        AppSetting::set('payment_checkout_mode', 'gateway_redirect');
        AppSetting::set('payment_gateway_selection_mode', 'multiple');
        AppSetting::set('paytm_mid', 'PAYTMMID123');
        AppSetting::set('paytm_merchant_key', 'paytm-merchant-key');
        AppSetting::set('paytm_website', 'WEBSTAGING');
        AppSetting::set('paytm_base_url', 'https://securegw-stage.paytm.in');

        $user = User::factory()->create();
        $package = $this->createListingPackage('Paytm Pack', 799, 30, 'limited', 20);

        Http::fake(function (HttpRequest $request) {
            if (Str::startsWith($request->url(), 'https://securegw-stage.paytm.in/theia/api/v1/initiateTransaction')) {
                return Http::response([
                    'body' => [
                        'txnToken' => 'PAYTM_TXN_TOKEN_PKG',
                    ],
                ], 200);
            }

            if ($request->url() === 'https://securegw-stage.paytm.in/v3/order/status') {
                return Http::response([
                    'body' => [
                        'resultInfo' => [
                            'resultStatus' => 'TXN_SUCCESS',
                        ],
                        'txnId' => 'PAYTM_TXN_PKG_123',
                    ],
                ], 200);
            }

            return Http::response([], 404);
        });

        $response = $this->actingAs($user)->post(route('subscriptions.initialize', $package), [
            'gateway' => 'paytm',
        ]);

        $response->assertOk();
        $response->assertSee('Paytm Checkout');

        $purchase = SubscriptionPackagePurchase::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($purchase);

        $service = new SubscriptionPackagePaymentService();
        $verified = $service->verifyPaytmByMerchantOrder((string) $purchase->merchant_order_id, []);

        $this->assertNotNull($verified);
        $this->assertSame('paid', $verified->fresh()->status);
        $this->assertSame('PAYTM_TXN_PKG_123', $verified->provider_payment_id);
        $this->assertSame(20, (int) $verified->remaining_items);
    }

    public function test_subscription_initialize_marks_purchase_failed_when_selected_gateway_is_not_configured(): void
    {
        $this->seed(AppSettingsSeeder::class);

        AppSetting::set('payment_checkout_mode', 'gateway_redirect');
        AppSetting::set('payment_gateway_selection_mode', 'multiple');
        AppSetting::set('payment_test_mode', false);
        AppSetting::set('paytm_mid', '');
        AppSetting::set('paytm_merchant_key', '');
        AppSetting::set('paytm_base_url', '');

        $user = User::factory()->create();
        $package = $this->createListingPackage('Paytm Missing Credentials Pack', 599, 30, 'limited', 10);

        $response = $this->actingAs($user)->post(route('subscriptions.initialize', $package), [
            'gateway' => 'paytm',
        ]);

        $response->assertRedirect(route('subscriptions.index'));
        $response->assertSessionHas('status', 'Paytm credentials are not configured.');

        $purchase = SubscriptionPackagePurchase::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($purchase);
        $this->assertSame('failed', $purchase->status);
        $this->assertSame('checkout_initialization_failed', data_get($purchase->meta, 'failure_reason'));
        $this->assertSame('error', data_get($purchase->meta, 'checkout.type'));
    }

    public function test_subscription_mock_complete_is_forbidden_for_non_mock_checkout_even_in_test_mode(): void
    {
        $this->seed(AppSettingsSeeder::class);

        AppSetting::set('payment_test_mode', true);

        $user = User::factory()->create();
        $package = $this->createListingPackage('Guarded Mock Pack', 399, 30, 'limited', 5);

        $purchase = SubscriptionPackagePurchase::create([
            'subscription_package_id' => $package->id,
            'user_id' => $user->id,
            'gateway' => 'razorpay',
            'merchant_order_id' => 'SPKGUARD'.Str::upper(Str::random(8)),
            'amount' => 399,
            'currency' => 'INR',
            'status' => 'initiated',
            'meta' => [
                'checkout' => [
                    'type' => 'razorpay',
                ],
            ],
        ]);

        $response = $this->actingAs($user)->get(route('subscriptions.mock.complete', $purchase));

        $response->assertForbidden();
    }

    public function test_subscription_mock_complete_can_finalize_mock_checkout_in_test_mode(): void
    {
        $this->seed(AppSettingsSeeder::class);

        AppSetting::set('payment_test_mode', true);

        $user = User::factory()->create();
        $package = $this->createListingPackage('Sandbox Mock Pack', 499, 30, 'limited', 7);

        $purchase = SubscriptionPackagePurchase::create([
            'subscription_package_id' => $package->id,
            'user_id' => $user->id,
            'gateway' => 'mock',
            'merchant_order_id' => 'SPKMOCK'.Str::upper(Str::random(8)),
            'amount' => 499,
            'currency' => 'INR',
            'status' => 'initiated',
            'meta' => [
                'checkout' => [
                    'type' => 'mock',
                ],
            ],
        ]);

        $response = $this->actingAs($user)->get(route('subscriptions.mock.complete', $purchase));

        $response->assertRedirect(route('subscriptions.index'));

        $freshPurchase = $purchase->fresh();
        $this->assertNotNull($freshPurchase);
        $this->assertSame('paid', $freshPurchase->status);
        $this->assertSame(7, (int) $freshPurchase->remaining_items);
    }

    public function test_entitlement_service_package_stats_are_accurate(): void
    {
        $this->seed(AppSettingsSeeder::class);

        $user = User::factory()->create();
        $category = $this->createCategory('Electronics');

        $package = $this->createListingPackage('Stats Pack', 299, 30, 'limited', 8);
        $purchase = $this->createActivatedPurchase($user, $package);

        $entitlement = new SubscriptionEntitlementService();

        $stats = $entitlement->packageStats($user, 'listing', $category);

        $this->assertTrue($stats['has_active_package']);
        $this->assertTrue($stats['has_usable_package']);
        $this->assertFalse($stats['is_unlimited']);
        $this->assertSame(8, $stats['total_remaining']);

        $featuredStats = $entitlement->packageStats($user, 'featured', $category);
        $this->assertFalse($featuredStats['has_active_package']);
        $this->assertSame(0, $featuredStats['total_remaining']);
    }

    public function test_call_access_requires_an_active_call_enabled_package(): void
    {
        $this->seed(AppSettingsSeeder::class);

        $user = User::factory()->create();
        $entitlement = new SubscriptionEntitlementService();

        $this->assertFalse($entitlement->hasCallAccess($user));

        $standardPackage = $this->createListingPackage('Standard Pack', 299, 30, 'limited', 5);
        $this->createActivatedPurchase($user, $standardPackage);

        $this->assertFalse($entitlement->hasCallAccess($user));

        $callPackage = $this->createListingPackage('Call Pack', 399, 30, 'limited', 5, 'standard', 30, true);
        $this->createActivatedPurchase($user, $callPackage, 0);

        $this->assertTrue($entitlement->hasCallAccess($user));
        $this->assertSame(1, $entitlement->activeCallPurchasesForUser($user)->count());
    }

    public function test_ai_access_requires_an_active_ai_enabled_package(): void
    {
        $this->seed(AppSettingsSeeder::class);

        $user = User::factory()->create();
        $entitlement = new SubscriptionEntitlementService();

        $this->assertFalse($entitlement->hasAiAccess($user));

        $standardPackage = $this->createListingPackage('Standard Pack', 299, 30, 'limited', 5);
        $this->createActivatedPurchase($user, $standardPackage);

        $this->assertFalse($entitlement->hasAiAccess($user));

        $aiPackage = $this->createListingPackage('AI Pack', 399, 30, 'limited', 5, 'standard', 30, false, true, 'limited', 2);
        $this->createActivatedPurchase($user, $aiPackage);

        $this->assertTrue($entitlement->hasAiAccess($user));
        $this->assertSame(1, $entitlement->activeAiPurchasesForUser($user)->count());
        $this->assertNotNull($entitlement->findUsableAiPurchase($user));
    }

    public function test_ai_usage_consumption_respects_ai_quota(): void
    {
        $this->seed(AppSettingsSeeder::class);

        $user = User::factory()->create();
        $package = $this->createListingPackage('AI Credits Pack', 499, 30, 'limited', 5, 'standard', 30, false, true, 'limited', 2);
        $purchase = $this->createActivatedPurchase($user, $package);

        $entitlement = new SubscriptionEntitlementService();

        $first = $entitlement->consumeAiPurchase($purchase, 'ai_listing_draft');
        $second = $entitlement->consumeAiPurchase($purchase, 'ai_listing_draft');
        $third = $entitlement->consumeAiPurchase($purchase, 'ai_listing_draft');

        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertNull($third);

        $freshPurchase = $purchase->fresh();

        $this->assertSame(2, (int) $freshPurchase->used_ai_items);
        $this->assertSame(0, (int) $freshPurchase->remaining_ai_items);
        $this->assertSame(2, $freshPurchase->usages()->where('usage_type', 'ai_listing_draft')->count());
    }

    private function createActivatedPurchase(
        User $user,
        SubscriptionPackage $package,
        ?int $remainingOverride = null,
        ?int $remainingAiOverride = null
    ): SubscriptionPackagePurchase {
        $remainingItems = $remainingOverride ?? (
            $package->item_limit_type === 'unlimited' ? null : (int) $package->item_limit_count
        );

        $remainingAiItems = $remainingAiOverride;

        if ($remainingAiItems === null) {
            if (! $package->allows_ai) {
                $remainingAiItems = 0;
            } elseif ($package->ai_usage_limit_type === 'unlimited') {
                $remainingAiItems = null;
            } else {
                $remainingAiItems = (int) ($package->ai_usage_limit_count ?? 0);
            }
        }

        $durationDays = $package->package_duration_type === 'unlimited' ? null : max(1, (int) ($package->package_duration_days ?? 30));

        return SubscriptionPackagePurchase::create([
            'subscription_package_id' => $package->id,
            'user_id' => $user->id,
            'gateway' => 'mock',
            'merchant_order_id' => 'SPK'.Str::upper(Str::random(14)),
            'amount' => $package->final_price,
            'currency' => 'INR',
            'status' => 'paid',
            'used_items' => 0,
            'remaining_items' => $remainingItems,
            'used_ai_items' => 0,
            'remaining_ai_items' => $remainingAiItems,
            'package_started_at' => now(),
            'package_expires_at' => $durationDays ? now()->addDays($durationDays) : null,
            'paid_at' => now(),
            'activated_at' => now(),
            'meta' => ['source' => 'test'],
        ]);
    }

    private function createListingPackage(
        string $name,
        int $price,
        ?int $durationDays,
        string $limitType,
        ?int $limitCount,
        string $listingDurationType = 'standard',
        ?int $listingDurationDays = 30,
        bool $allowsCall = false,
        bool $allowsAi = false,
        string $aiUsageLimitType = 'limited',
        ?int $aiUsageLimitCount = 0
    ): SubscriptionPackage {
        return SubscriptionPackage::create([
            'name' => $name,
            'package_type' => 'listing',
            'price' => $price,
            'discount_percent' => 0,
            'final_price' => $price,
            'package_duration_type' => $durationDays ? 'limited' : 'unlimited',
            'package_duration_days' => $durationDays,
            'item_limit_type' => $limitType,
            'item_limit_count' => $limitCount,
            'listing_duration_type' => $listingDurationType,
            'listing_duration_days' => $listingDurationDays ?? 30,
            'category_scope' => 'global',
            'allows_call' => $allowsCall,
            'allows_ai' => $allowsAi,
            'ai_usage_limit_type' => $allowsAi ? $aiUsageLimitType : 'limited',
            'ai_usage_limit_count' => $allowsAi && $aiUsageLimitType === 'limited'
                ? (int) ($aiUsageLimitCount ?? 0)
                : null,
            'is_active' => true,
        ]);
    }

    private function createFeaturedPackage(
        string $name,
        int $price,
        ?int $durationDays,
        string $limitType,
        ?int $limitCount,
        string $listingDurationType = 'standard',
        ?int $listingDurationDays = 30,
        bool $allowsCall = false,
        bool $allowsAi = false,
        string $aiUsageLimitType = 'limited',
        ?int $aiUsageLimitCount = 0
    ): SubscriptionPackage {
        return SubscriptionPackage::create([
            'name' => $name,
            'package_type' => 'featured',
            'price' => $price,
            'discount_percent' => 0,
            'final_price' => $price,
            'package_duration_type' => $durationDays ? 'limited' : 'unlimited',
            'package_duration_days' => $durationDays,
            'item_limit_type' => $limitType,
            'item_limit_count' => $limitCount,
            'listing_duration_type' => $listingDurationType,
            'listing_duration_days' => $listingDurationDays ?? 30,
            'category_scope' => 'global',
            'allows_call' => $allowsCall,
            'allows_ai' => $allowsAi,
            'ai_usage_limit_type' => $allowsAi ? $aiUsageLimitType : 'limited',
            'ai_usage_limit_count' => $allowsAi && $aiUsageLimitType === 'limited'
                ? (int) ($aiUsageLimitCount ?? 0)
                : null,
            'is_active' => true,
        ]);
    }

    private function createStoryPackage(
        string $name,
        int $price,
        ?int $durationDays,
        string $limitType,
        ?int $limitCount
    ): SubscriptionPackage {
        return SubscriptionPackage::create([
            'name' => $name,
            'package_type' => 'story',
            'price' => $price,
            'discount_percent' => 0,
            'final_price' => $price,
            'package_duration_type' => $durationDays ? 'limited' : 'unlimited',
            'package_duration_days' => $durationDays,
            'item_limit_type' => $limitType,
            'item_limit_count' => $limitCount,
            'listing_duration_type' => 'custom',
            'listing_duration_days' => 3,
            'category_scope' => 'global',
            'allows_call' => false,
            'allows_ai' => false,
            'ai_usage_limit_type' => 'limited',
            'ai_usage_limit_count' => 0,
            'is_active' => true,
        ]);
    }

    private function createCategory(string $name): Category
    {
        return Category::create([
            'name' => $name.' '.Str::upper(Str::random(4)),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    private function createApprovedListing(User $owner, Category $category): Listing
    {
        return Listing::create([
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'title' => 'Test Listing '.Str::upper(Str::random(4)),
            'slug' => 'test-listing-'.Str::lower(Str::random(10)),
            'description' => 'Clean listing description with enough text for the validation rules.',
            'price' => 50000,
            'currency' => 'INR',
            'condition' => 'used',
            'city' => 'Delhi',
            'state' => 'Delhi',
            'address' => 'Sector 1',
            'status' => 'approved',
            'is_featured' => false,
            'published_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);
    }
}
