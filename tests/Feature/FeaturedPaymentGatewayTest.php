<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Category;
use App\Models\FeaturedAdPayment;
use App\Models\Listing;
use App\Models\User;
use App\Services\FeaturedPayments\FeaturedPaymentService;
use Database\Seeders\AppSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class FeaturedPaymentGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_gateway_credentials_for_razorpay_phonepe_and_paytm(): void
    {
        $this->seed(AppSettingsSeeder::class);

        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.settings.update'), [
            'settings_section' => 'featured',
            'featured_daily_rate' => '59',
            'featured_allowed_days' => [3, 7, 15],
            'payment_gateway' => 'paytm',
            'payment_currency' => 'INR',
            'payment_test_mode' => '1',
            'razorpay_key_id' => 'rzp_test_abc123',
            'razorpay_key_secret' => 'razor-secret',
            'razorpay_base_url' => 'https://api.razorpay.com',
            'phonepe_merchant_id' => 'PHONEPEMID123',
            'phonepe_salt_key' => 'phonepe-salt-key',
            'phonepe_salt_index' => '1',
            'phonepe_base_url' => 'https://api-preprod.phonepe.com/apis/pg-sandbox',
            'paytm_mid' => 'PAYTMMID123',
            'paytm_merchant_key' => 'paytm-merchant-key',
            'paytm_website' => 'WEBSTAGING',
            'paytm_base_url' => 'https://securegw-stage.paytm.in',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertSame('paytm', AppSetting::get('payment_gateway'));
        $this->assertSame('rzp_test_abc123', AppSetting::get('razorpay_key_id'));
        $this->assertSame('PHONEPEMID123', AppSetting::get('phonepe_merchant_id'));
        $this->assertSame('PAYTMMID123', AppSetting::get('paytm_mid'));
        $this->assertSame('WEBSTAGING', AppSetting::get('paytm_website'));
    }

    public function test_payment_initialize_uses_currency_from_featured_settings(): void
    {
        $this->seed(AppSettingsSeeder::class);

        AppSetting::set('payment_currency', 'USD');
        AppSetting::set('featured_daily_rate', 10);
        AppSetting::set('featured_allowed_days', [3, 7, 15]);

        $owner = User::factory()->create();
        $listing = $this->createApprovedListing($owner);

        $response = $this->actingAs($owner)->post(route('payments.initialize', $listing), [
            'gateway' => 'razorpay',
            'feature_days' => 7,
        ]);

        $response->assertRedirect();

        $payment = FeaturedAdPayment::query()->latest('id')->first();

        $this->assertNotNull($payment);
        $this->assertSame('USD', $payment->currency);
    }

    public function test_razorpay_checkout_and_callback_are_verified_with_settings_credentials(): void
    {
        $this->seed(AppSettingsSeeder::class);

        AppSetting::set('razorpay_key_id', 'rzp_test_abc123');
        AppSetting::set('razorpay_key_secret', 'razor-secret');
        AppSetting::set('razorpay_base_url', 'https://api.razorpay.com');

        [$payment, $listing] = $this->createPaymentForGateway('razorpay');

        Http::fake([
            'https://api.razorpay.com/v1/orders' => Http::response([
                'id' => 'order_12345',
            ], 200),
        ]);

        $service = new FeaturedPaymentService();

        $checkout = $service->createCheckout($payment);

        $this->assertSame('razorpay', $checkout['type']);
        $this->assertSame('order_12345', $payment->fresh()->provider_order_id);

        $signature = hash_hmac('sha256', 'order_12345|pay_12345', 'razor-secret');

        $verified = $service->verifyRazorpayCallback([
            'razorpay_order_id' => 'order_12345',
            'razorpay_payment_id' => 'pay_12345',
            'razorpay_signature' => $signature,
        ]);

        $this->assertNotNull($verified);
        $this->assertSame('paid', $verified->fresh()->status);
        $this->assertSame('pay_12345', $verified->provider_payment_id);
        $this->assertTrue($listing->fresh()->is_featured);
    }

    public function test_phonepe_checkout_and_callback_status_verification_mark_payment_paid(): void
    {
        $this->seed(AppSettingsSeeder::class);

        AppSetting::set('phonepe_merchant_id', 'PHONEPEMID123');
        AppSetting::set('phonepe_salt_key', 'phonepe-salt-key');
        AppSetting::set('phonepe_salt_index', '1');
        AppSetting::set('phonepe_base_url', 'https://api-preprod.phonepe.com/apis/pg-sandbox');

        [$payment, $listing] = $this->createPaymentForGateway('phonepe');

        Http::fake(function (HttpRequest $request) use ($payment) {
            if ($request->url() === 'https://api-preprod.phonepe.com/apis/pg-sandbox/pg/v1/pay') {
                return Http::response([
                    'success' => true,
                    'data' => [
                        'instrumentResponse' => [
                            'redirectInfo' => [
                                'url' => 'https://phonepe.test/pay/redirect/123',
                            ],
                        ],
                    ],
                ], 200);
            }

            if ($request->url() === 'https://api-preprod.phonepe.com/apis/pg-sandbox/pg/v1/status/PHONEPEMID123/'.$payment->merchant_order_id) {
                return Http::response([
                    'success' => true,
                    'code' => 'PAYMENT_SUCCESS',
                    'data' => [
                        'transactionId' => 'PHONEPE_TXN_123',
                    ],
                ], 200);
            }

            return Http::response([], 404);
        });

        $service = new FeaturedPaymentService();

        $checkout = $service->createCheckout($payment);

        $this->assertSame('redirect', $checkout['type']);
        $this->assertSame('https://phonepe.test/pay/redirect/123', $checkout['redirect_url']);

        $verified = $service->verifyPhonePeByMerchantOrder($payment->merchant_order_id, [
            'callback_source' => 'test',
        ]);

        $this->assertNotNull($verified);
        $this->assertSame('paid', $verified->fresh()->status);
        $this->assertSame('PHONEPE_TXN_123', $verified->provider_payment_id);
        $this->assertTrue($listing->fresh()->is_featured);
    }

    public function test_paytm_checkout_and_callback_status_verification_mark_payment_paid(): void
    {
        $this->seed(AppSettingsSeeder::class);

        AppSetting::set('paytm_mid', 'PAYTMMID123');
        AppSetting::set('paytm_merchant_key', 'paytm-merchant-key');
        AppSetting::set('paytm_website', 'WEBSTAGING');
        AppSetting::set('paytm_base_url', 'https://securegw-stage.paytm.in');

        [$payment, $listing] = $this->createPaymentForGateway('paytm');

        Http::fake(function (HttpRequest $request) use ($payment) {
            if (Str::startsWith($request->url(), 'https://securegw-stage.paytm.in/theia/api/v1/initiateTransaction')) {
                return Http::response([
                    'body' => [
                        'txnToken' => 'PAYTM_TXN_TOKEN_123',
                    ],
                ], 200);
            }

            if ($request->url() === 'https://securegw-stage.paytm.in/v3/order/status') {
                return Http::response([
                    'body' => [
                        'resultInfo' => [
                            'resultStatus' => 'TXN_SUCCESS',
                        ],
                        'txnId' => 'PAYTM_TXN_123',
                    ],
                ], 200);
            }

            return Http::response([], 404);
        });

        $service = new FeaturedPaymentService();

        $checkout = $service->createCheckout($payment);

        $this->assertSame('paytm_form', $checkout['type']);
        $this->assertSame('PAYTMMID123', $checkout['mid']);
        $this->assertSame('PAYTM_TXN_TOKEN_123', $checkout['txn_token']);

        $verified = $service->verifyPaytmByMerchantOrder($payment->merchant_order_id, [
            'callback_source' => 'test',
        ]);

        $this->assertNotNull($verified);
        $this->assertSame('paid', $verified->fresh()->status);
        $this->assertSame('PAYTM_TXN_123', $verified->provider_payment_id);
        $this->assertTrue($listing->fresh()->is_featured);
    }

    public function test_initialize_marks_payment_failed_when_selected_gateway_is_not_configured(): void
    {
        $this->seed(AppSettingsSeeder::class);

        AppSetting::set('payment_test_mode', false);
        AppSetting::set('razorpay_key_id', '');
        AppSetting::set('razorpay_key_secret', '');
        AppSetting::set('razorpay_base_url', '');

        $owner = User::factory()->create();
        $listing = $this->createApprovedListing($owner);

        $response = $this->actingAs($owner)
            ->from(route('payments.checkout', $listing))
            ->post(route('payments.initialize', $listing), [
                'gateway' => 'razorpay',
                'feature_days' => 7,
            ]);

        $response->assertRedirect(route('payments.checkout', $listing));
        $response->assertSessionHas('status', 'Razorpay credentials are not configured.');

        $payment = FeaturedAdPayment::query()->latest('id')->first();

        $this->assertNotNull($payment);
        $this->assertSame('failed', $payment->status);
        $this->assertSame('checkout_initialization_failed', data_get($payment->meta, 'failure_reason'));
        $this->assertSame('error', data_get($payment->meta, 'checkout.type'));
    }

    public function test_mock_complete_is_forbidden_for_non_mock_checkout_even_in_test_mode(): void
    {
        $this->seed(AppSettingsSeeder::class);

        AppSetting::set('payment_test_mode', true);

        [$payment] = $this->createPaymentForGateway('razorpay');

        $response = $this->actingAs($payment->user)->get(route('payments.mock.complete', $payment));

        $response->assertForbidden();
    }

    public function test_mock_complete_can_finalize_mock_checkout_in_test_mode(): void
    {
        $this->seed(AppSettingsSeeder::class);

        AppSetting::set('payment_test_mode', true);

        $owner = User::factory()->create();
        $listing = $this->createApprovedListing($owner);

        $payment = FeaturedAdPayment::query()->create([
            'listing_id' => $listing->id,
            'user_id' => $owner->id,
            'gateway' => 'mock',
            'merchant_order_id' => 'FADMOCK'.Str::upper(Str::random(8)),
            'amount' => 140.00,
            'currency' => 'INR',
            'feature_days' => 7,
            'status' => 'initiated',
            'meta' => [
                'checkout' => [
                    'type' => 'mock',
                ],
            ],
        ]);

        $response = $this->actingAs($owner)->get(route('payments.mock.complete', $payment));

        $response->assertRedirect(route('listings.show', $listing));
        $this->assertSame('paid', $payment->fresh()->status);
    }

    /**
     * @return array{0: FeaturedAdPayment, 1: Listing}
     */
    private function createPaymentForGateway(string $gateway): array
    {
        $owner = User::factory()->create();
        $listing = $this->createApprovedListing($owner);

        $payment = FeaturedAdPayment::create([
            'listing_id' => $listing->id,
            'user_id' => $owner->id,
            'gateway' => $gateway,
            'merchant_order_id' => 'FADTEST'.Str::upper(Str::random(8)),
            'amount' => 140.00,
            'currency' => 'INR',
            'feature_days' => 7,
            'status' => 'initiated',
            'meta' => [
                'source' => 'test',
            ],
        ]);

        return [$payment, $listing];
    }

    private function createApprovedListing(User $owner): Listing
    {
        $category = Category::query()->create([
            'name' => 'Mobiles '.Str::upper(Str::random(4)),
            'slug' => 'mobiles-'.Str::lower(Str::random(8)),
            'icon' => null,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        return Listing::query()->create([
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'title' => 'iPhone 14 Pro '.Str::upper(Str::random(4)),
            'slug' => 'iphone-14-pro-'.Str::lower(Str::random(10)),
            'description' => 'Excellent condition listing for featured payment testing.',
            'price' => 99999,
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
