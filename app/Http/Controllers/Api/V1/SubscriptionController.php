<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPackage;
use App\Models\SubscriptionPackagePurchase;
use App\Services\SubscriptionPackages\SubscriptionEntitlementService;
use App\Services\SubscriptionPackages\SubscriptionPackagePaymentService;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SubscriptionController extends Controller
{
    public function overview(Request $request, SubscriptionEntitlementService $entitlementService): JsonResponse
    {
        $user = $request->user();

        $packages = SubscriptionPackage::query()
            ->with('category.parent')
            ->where('is_active', true)
            ->orderBy('package_type')
            ->orderBy('final_price')
            ->orderBy('name')
            ->get();

        $recentPurchases = $user->subscriptionPackagePurchases()
            ->with('subscriptionPackage.category.parent')
            ->latest('id')
            ->take(20)
            ->get();

        $activePurchases = $entitlementService->activePackagePurchasesForUser($user);
        $gatewayDiagnostics = $this->gatewayDiagnostics();

        return response()->json([
            'data' => [
                'currency_symbol' => (string) setting('site_currency_symbol', 'Rs'),
                'payment_gateway' => (string) setting('payment_gateway', 'razorpay'),
                'payment_gateway_label' => $gatewayDiagnostics['label'],
                'payment_gateway_ready' => $gatewayDiagnostics['ready'],
                'payment_gateway_message' => $gatewayDiagnostics['message'],
                'available_gateways' => $this->availableGatewaySummaries(),
                'support_email' => trim((string) setting('contact_email', '')),
                'support_phone' => trim((string) setting('support_phone', '')),
                'plans_url' => route('subscriptions.plans', absolute: true),
                'manage_url' => route('subscriptions.index', absolute: true),
                'active_purchases' => $activePurchases
                    ->map(fn (SubscriptionPackagePurchase $purchase): array => $this->serializePurchase($purchase))
                    ->values(),
                'recent_purchases' => $recentPurchases
                    ->map(fn (SubscriptionPackagePurchase $purchase): array => $this->serializePurchase($purchase))
                    ->values(),
                'available_packages' => $packages
                    ->map(fn (SubscriptionPackage $package): array => $this->serializePackage($package))
                    ->values(),
            ],
        ]);
    }

    public function buy(
        Request $request,
        SubscriptionPackage $subscriptionPackage,
        SubscriptionPackagePaymentService $paymentService
    ): JsonResponse {
        if (! $subscriptionPackage->is_active) {
            return response()->json([
                'message' => 'This package is currently inactive.',
            ], 422);
        }

        $gateways = $this->availableInAppGateways();
        $gatewayKeys = array_keys($gateways);

        $validated = $request->validate([
            'gateway' => ['nullable', 'string', 'in:'.implode(',', $gatewayKeys)],
        ]);

        $selectedGateway = (string) ($validated['gateway'] ?? $this->resolveDefaultGateway($gateways));

        if (! in_array($selectedGateway, $gatewayKeys, true)) {
            return response()->json([
                'message' => 'Selected payment gateway is not available.',
            ], 422);
        }

        $amount = round((float) $subscriptionPackage->final_price, 2);
        if ($amount < 0) {
            return response()->json([
                'message' => 'Invalid package pricing configuration.',
            ], 422);
        }

        $purchase = SubscriptionPackagePurchase::create([
            'subscription_package_id' => $subscriptionPackage->id,
            'user_id' => $request->user()->id,
            'gateway' => $selectedGateway,
            'merchant_order_id' => $this->createMerchantOrderId($subscriptionPackage),
            'amount' => $amount,
            'currency' => (string) setting('payment_currency', config('featured_ads.currency', 'INR')),
            'status' => 'initiated',
            'meta' => [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'source' => 'mobile_app',
            ],
        ]);

        if ($amount <= 0) {
            $paymentService->markPaid(
                $purchase,
                'FREE-'.Str::upper(Str::random(10)),
                ['source' => 'free_package_activation_mobile']
            );
            $purchase->refresh()->loadMissing('subscriptionPackage.category.parent');

            return response()->json([
                'message' => 'Package activated successfully.',
                'data' => [
                    'purchase' => $this->serializePurchase($purchase),
                ],
            ]);
        }

        $checkout = $paymentService->createCheckout($purchase);

        $purchase->update([
            'meta' => array_merge($purchase->meta ?? [], [
                'checkout' => $checkout,
            ]),
        ]);

        $checkoutType = (string) data_get($checkout, 'type');

        if ($checkoutType === 'error') {
            $paymentService->markFailed($purchase, 'checkout_initialization_failed', [
                'checkout' => $checkout,
                'source' => 'mobile_app',
            ]);

            return response()->json([
                'message' => (string) data_get($checkout, 'reason', 'Unable to initialize selected payment gateway.'),
            ], 422);
        }

        if ($checkoutType === 'razorpay') {
            return response()->json([
                'message' => 'Razorpay checkout initialized.',
                'data' => [
                    'purchase_id' => $purchase->id,
                    'checkout' => [
                        'type' => 'razorpay',
                        'key_id' => (string) data_get($checkout, 'key_id', ''),
                        'order_id' => (string) data_get($checkout, 'order_id', ''),
                        'amount' => (float) $purchase->amount,
                        'currency' => (string) $purchase->currency,
                        'package_name' => (string) $subscriptionPackage->name,
                        'merchant_order_id' => (string) $purchase->merchant_order_id,
                    ],
                ],
            ]);
        }

        if ($checkoutType === 'stripe') {
            return response()->json([
                'message' => 'Stripe checkout initialized.',
                'data' => [
                    'purchase_id' => $purchase->id,
                    'checkout' => [
                        'type' => 'stripe',
                        'client_secret' => (string) data_get($checkout, 'client_secret', ''),
                        'payment_intent_id' => (string) data_get($checkout, 'payment_intent_id', ''),
                        'public_key' => (string) data_get($checkout, 'public_key', ''),
                        'amount' => (float) data_get($checkout, 'amount', 0),
                        'currency' => (string) data_get($checkout, 'currency', ''),
                        'package_name' => (string) $subscriptionPackage->name,
                        'merchant_order_id' => (string) $purchase->merchant_order_id,
                    ],
                ],
            ]);
        }

        if ($checkoutType === 'mock') {
            $paymentService->markPaid(
                $purchase,
                'MOCK-'.Str::upper(Str::random(12)),
                ['source' => 'mock_gateway_mobile_app']
            );
            $purchase->refresh()->loadMissing('subscriptionPackage.category.parent');

            return response()->json([
                'message' => 'Package activated successfully (mock payment).',
                'data' => [
                    'purchase' => $this->serializePurchase($purchase),
                ],
            ]);
        }

        $paymentService->markFailed($purchase, 'external_checkout_required_but_disallowed', [
            'checkout' => $checkout,
            'source' => 'mobile_app',
        ]);

        return response()->json([
            'message' => 'This gateway requires external checkout, which is disabled for in-app purchase. Enable mock gateway for direct in-app activation.',
        ], 422);
    }

    public function verifyRazorpayPayment(
        Request $request,
        SubscriptionPackagePaymentService $paymentService
    ): JsonResponse {
        $validated = $request->validate([
            'razorpay_order_id' => ['required', 'string', 'max:255'],
            'razorpay_payment_id' => ['required', 'string', 'max:255'],
            'razorpay_signature' => ['required', 'string', 'max:255'],
        ]);

        $purchase = $paymentService->verifyRazorpayCallback($validated);

        if (! $purchase) {
            throw ValidationException::withMessages([
                'payment' => ['Unable to verify Razorpay payment.'],
            ]);
        }

        if ((int) $purchase->user_id !== (int) $request->user()->id) {
            return response()->json([
                'message' => 'Payment does not belong to this user.',
            ], 403);
        }

        $purchase->loadMissing('subscriptionPackage.category.parent');

        if ($purchase->status !== 'paid') {
            return response()->json([
                'message' => 'Payment verification failed.',
                'data' => [
                    'purchase' => $this->serializePurchase($purchase),
                ],
            ], 422);
        }

        return response()->json([
            'message' => 'Payment verified. Package activated successfully.',
            'data' => [
                'purchase' => $this->serializePurchase($purchase),
            ],
        ]);
    }

    private function availableInAppGateways(): array
    {
        $gateways = [
            'razorpay' => 'Razorpay',
        ];

        // Add Stripe if configured
        if (config('stripe.secret_key') || config('stripe.public_key')) {
            $gateways['stripe'] = 'Stripe';
        }

        if ((bool) setting('payment_test_mode', false)) {
            $gateways['mock'] = 'Mock (testing)';
        }

        return $gateways;
    }

    private function availableGatewaySummaries(): array
    {
        $gatewayDiagnostics = $this->gatewayDiagnostics();

        return collect($this->availableInAppGateways())
            ->map(fn (string $label, string $key): array => [
                'key' => $key,
                'label' => $label,
                'is_default' => $key === $this->resolveDefaultGateway($this->availableInAppGateways()),
                'is_ready' => $key === 'mock' || ($key === (string) setting('payment_gateway', 'razorpay') && $gatewayDiagnostics['ready']),
            ])
            ->values()
            ->all();
    }

    private function gatewayDiagnostics(): array
    {
        $gateway = $this->resolveDefaultGateway($this->availableInAppGateways());

        if ($gateway === 'mock') {
            return [
                'label' => 'Mock (testing)',
                'ready' => true,
                'message' => 'Test checkout is enabled.',
            ];
        }

        $keyId = trim((string) setting('razorpay_key_id', config('services.razorpay.key_id')));
        $keySecret = trim((string) setting('razorpay_key_secret', config('services.razorpay.key_secret')));
        $baseUrl = trim((string) setting('razorpay_base_url', config('services.razorpay.base_url')));
        $ready = $keyId !== '' && $keySecret !== '' && $baseUrl !== '';

        return [
            'label' => 'Razorpay',
            'ready' => $ready,
            'message' => $ready
                ? 'Razorpay checkout is configured for in-app payments.'
                : 'Razorpay credentials are missing in admin payment settings.',
        ];
    }

    private function resolveDefaultGateway(array $gateways): string
    {
        $defaultGateway = (string) setting('payment_gateway', 'razorpay');

        if (! array_key_exists($defaultGateway, $gateways)) {
            if (array_key_exists('razorpay', $gateways)) {
                return 'razorpay';
            }

            $defaultGateway = (string) (array_key_first($gateways) ?? 'razorpay');
        }

        return $defaultGateway;
    }

    private function createMerchantOrderId(SubscriptionPackage $package): string
    {
        do {
            $merchantOrderId = 'SPK'.now()->format('YmdHis').$package->id.Str::upper(Str::random(6));
        } while (SubscriptionPackagePurchase::query()->where('merchant_order_id', $merchantOrderId)->exists());

        return $merchantOrderId;
    }

    private function serializePackage(SubscriptionPackage $package): array
    {
        return [
            'id' => $package->id,
            'name' => $package->name,
            'package_type' => $package->package_type,
            'package_type_label' => $package->package_type_label,
            'final_price' => (float) $package->final_price,
            'package_duration_label' => $package->package_duration_label,
            'item_limit_label' => $package->item_limit_label,
            'listing_duration_label' => $package->listing_duration_label,
            'ai_usage_limit_label' => $package->ai_usage_limit_label,
            'allows_call' => (bool) $package->allows_call,
            'allows_ai' => (bool) $package->allows_ai,
            'icon_url' => $package->icon_url,
            'category_name' => $package->category?->name,
            'category_scope' => $package->category_scope,
            'is_seller_verification' => (bool) $package->is_seller_verification,
            'seller_tier' => (string) ($package->seller_tier ?? ''),
            'seller_tier_label' => $package->seller_tier_label,
            'seller_badge_label' => $package->resolved_seller_badge_label,
            'required_documents' => array_values(array_filter((array) ($package->required_documents ?? []))),
            'key_points' => array_values(array_filter((array) ($package->key_points ?? []))),
        ];
    }

    private function serializePurchase(SubscriptionPackagePurchase $purchase): array
    {
        $package = $purchase->subscriptionPackage;

        return [
            'id' => $purchase->id,
            'status' => $purchase->status,
            'gateway' => $purchase->gateway,
            'merchant_order_id' => $purchase->merchant_order_id,
            'provider_payment_id' => $purchase->provider_payment_id,
            'amount' => (float) $purchase->amount,
            'currency' => $purchase->currency,
            'paid_at' => optional($purchase->paid_at)?->toIso8601String(),
            'activated_at' => optional($purchase->activated_at)?->toIso8601String(),
            'package_started_at' => optional($purchase->package_started_at)?->toIso8601String(),
            'package_expires_at' => optional($purchase->package_expires_at)?->toIso8601String(),
            'remaining_items' => (int) ($purchase->remaining_items ?? 0),
            'remaining_ai_items' => (int) ($purchase->remaining_ai_items ?? 0),
            'used_items' => (int) ($purchase->used_items ?? 0),
            'used_ai_items' => (int) ($purchase->used_ai_items ?? 0),
            'is_active' => $purchase->isActive(),
            'package' => $package ? $this->serializePackage($package) : null,
        ];
    }
}
