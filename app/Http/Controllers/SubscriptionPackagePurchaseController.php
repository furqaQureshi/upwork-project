<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPackage;
use App\Models\SubscriptionPackagePurchase;
use App\Services\SubscriptionPackages\SubscriptionEntitlementService;
use App\Services\SubscriptionPackages\SubscriptionPackagePaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SubscriptionPackagePurchaseController extends Controller
{
    public function index(Request $request, SubscriptionEntitlementService $entitlementService): View|RedirectResponse
    {
        $feature = $request->string('feature')->toString();

        if (in_array($feature, ['call', 'ai'], true)) {
            return redirect()->route('subscriptions.plans', ['feature' => $feature]);
        }

        $user = $request->user();

        $activeListingPurchases = $entitlementService->activePurchasesForUser($user, 'listing');
        $activeFeaturedPurchases = $entitlementService->activePurchasesForUser($user, 'featured');
        $activeCallPurchases = $entitlementService->activeCallPurchasesForUser($user);
        $activeAiPurchases = $entitlementService->activeAiPurchasesForUser($user);

        $activeSubscriptions = $activeListingPurchases
            ->concat($activeFeaturedPurchases)
            ->concat($activeCallPurchases)
            ->concat($activeAiPurchases)
            ->unique('id')
            ->sortBy(function (SubscriptionPackagePurchase $purchase): int {
                return $purchase->package_expires_at
                    ? $purchase->package_expires_at->getTimestamp()
                    : PHP_INT_MAX;
            })
            ->values();

        $primarySubscription = $activeSubscriptions->first();

        return view('subscriptions.index', [
            'activeSubscriptions' => $activeSubscriptions,
            'primarySubscription' => $primarySubscription,
            'hasActiveSubscription' => $activeSubscriptions->isNotEmpty(),
            'userIsSellerVerified' => (bool) $user->is_seller_verified,
            'activeListingPurchases' => $activeListingPurchases,
            'activeFeaturedPurchases' => $activeFeaturedPurchases,
            'activeCallPurchases' => $activeCallPurchases,
            'activeAiPurchases' => $activeAiPurchases,
        ]);
    }

    public function plans(Request $request, SubscriptionEntitlementService $entitlementService): View
    {
        $user = $request->user();
        $feature = $request->string('feature')->toString();
        $inAppPayment = $this->isInAppPaymentMode($request);
        $gateways = $this->availableGateways($request);
        $defaultGateway = $this->resolveDefaultGateway($gateways);

        $packages = SubscriptionPackage::query()
            ->with('category.parent')
            ->where('is_active', true)
            ->orderBy('package_type')
            ->orderBy('final_price')
            ->orderBy('name')
            ->get();

        $listingPackages = $packages->where('package_type', 'listing');
        $featuredPackages = $packages->where('package_type', 'featured');

        if ($feature === 'call') {
            $listingPackages = $listingPackages
                ->sortByDesc(fn (SubscriptionPackage $package): int => (int) $package->allows_call)
                ->values();

            $featuredPackages = $featuredPackages
                ->sortByDesc(fn (SubscriptionPackage $package): int => (int) $package->allows_call)
                ->values();
        }

        if ($feature === 'ai') {
            $listingPackages = $listingPackages
                ->sortByDesc(fn (SubscriptionPackage $package): int => (int) $package->allows_ai)
                ->values();

            $featuredPackages = $featuredPackages
                ->sortByDesc(fn (SubscriptionPackage $package): int => (int) $package->allows_ai)
                ->values();
        }

        $recentPurchases = $user->subscriptionPackagePurchases()
            ->with('subscriptionPackage.category.parent')
            ->latest('id')
            ->take(15)
            ->get();

        return view('subscriptions.plans', [
            'listingPackages' => $listingPackages->values(),
            'featuredPackages' => $featuredPackages->values(),
            'activeListingPurchases' => $entitlementService->activePurchasesForUser($user, 'listing'),
            'activeFeaturedPurchases' => $entitlementService->activePurchasesForUser($user, 'featured'),
            'activeCallPurchases' => $entitlementService->activeCallPurchasesForUser($user),
            'activeAiPurchases' => $entitlementService->activeAiPurchasesForUser($user),
            'recentPurchases' => $recentPurchases,
            'gateways' => $gateways,
            'defaultGateway' => $defaultGateway,
            'showGatewayOptions' => count($gateways) > 1,
            'inAppPayment' => $inAppPayment,
            'currencySymbol' => (string) setting('site_currency_symbol', 'Rs'),
        ]);
    }

    public function initialize(
        Request $request,
        SubscriptionPackage $subscriptionPackage,
        SubscriptionPackagePaymentService $paymentService
    ): View|RedirectResponse {
        if (! $subscriptionPackage->is_active) {
            return back()->with('status', 'This package is currently inactive.');
        }

        $gateways = $this->availableGateways($request);
        $gatewayKeys = array_keys($gateways);

        $validated = $request->validate([
            'gateway' => ['nullable', 'string', 'in:'.implode(',', $gatewayKeys)],
        ]);

        $selectedGateway = (string) ($validated['gateway'] ?? $this->resolveDefaultGateway($gateways));

        if ($this->isSingleGatewayMode()) {
            $selectedGateway = $this->resolveDefaultGateway($gateways);
        }

        if (! in_array($selectedGateway, $gatewayKeys, true)) {
            return back()->withInput()->with('status', 'Selected payment gateway is not available for this checkout.');
        }

        $amount = round((float) $subscriptionPackage->final_price, 2);

        if ($amount < 0) {
            return back()->with('status', 'Invalid package pricing configuration.');
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
            ],
        ]);

        if ($amount <= 0) {
            $paymentService->markPaid($purchase, 'FREE-'.Str::upper(Str::random(10)), [
                'source' => 'free_package_activation',
            ]);

            return redirect()
                ->route('subscriptions.index')
                ->with('status', 'Package activated successfully.');
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
            ]);

            return redirect()
                ->route('subscriptions.index')
                ->with('status', (string) data_get($checkout, 'reason', 'Unable to initialize selected payment gateway.'));
        }

        if (in_array($checkoutType, ['redirect', 'mock'], true) && ! data_get($checkout, 'redirect_url')) {
            return back()->with('status', 'Gateway response is incomplete. Please try again.');
        }

        return match ($checkoutType) {
            'redirect' => redirect()->away((string) data_get($checkout, 'redirect_url')),
            'mock' => redirect()->to((string) data_get($checkout, 'redirect_url')),
            'razorpay' => view('subscriptions.razorpay', [
                'subscriptionPackage' => $subscriptionPackage,
                'purchase' => $purchase,
                'checkout' => $checkout,
            ]),
            'paytm_form' => view('subscriptions.paytm', [
                'subscriptionPackage' => $subscriptionPackage,
                'purchase' => $purchase,
                'checkout' => $checkout,
            ]),
            default => back()->with('status', 'Unable to initialize payment gateway. Please try again.'),
        };
    }

    public function callbackRazorpay(
        Request $request,
        SubscriptionPackagePaymentService $paymentService
    ): RedirectResponse {
        $purchase = $paymentService->verifyRazorpayCallback($request->all());

        return $this->redirectFromPurchase($purchase, 'Unable to verify Razorpay callback payload.');
    }

    public function callbackPhonePe(
        Request $request,
        SubscriptionPackagePaymentService $paymentService
    ): RedirectResponse {
        $payload = $request->all();
        $merchantOrderId = '';

        $encodedResponse = (string) $request->input('response', '');

        if ($encodedResponse !== '') {
            $decodedString = base64_decode($encodedResponse, true);
            $decodedPayload = $decodedString ? json_decode($decodedString, true) : null;

            if (is_array($decodedPayload)) {
                $payload['decoded_response'] = $decodedPayload;
                $merchantOrderId = (string) (data_get($decodedPayload, 'data.merchantTransactionId')
                    ?? data_get($decodedPayload, 'merchantTransactionId')
                    ?? '');
            }
        }

        if ($merchantOrderId === '') {
            $merchantOrderId = (string) ($request->input('merchantTransactionId')
                ?? $request->input('merchant_order_id')
                ?? '');
        }

        if ($merchantOrderId === '') {
            return redirect()->route('subscriptions.index')->with('status', 'PhonePe callback payload is missing transaction reference.');
        }

        $purchase = $paymentService->verifyPhonePeByMerchantOrder($merchantOrderId, $payload);

        return $this->redirectFromPurchase($purchase, 'Unable to verify PhonePe payment status.');
    }

    public function callbackPaytm(
        Request $request,
        SubscriptionPackagePaymentService $paymentService
    ): RedirectResponse {
        $merchantOrderId = (string) ($request->input('ORDERID')
            ?? $request->input('orderId')
            ?? '');

        if ($merchantOrderId === '') {
            return redirect()->route('subscriptions.index')->with('status', 'Paytm callback payload is missing order reference.');
        }

        $purchase = $paymentService->verifyPaytmByMerchantOrder($merchantOrderId, $request->all());

        return $this->redirectFromPurchase($purchase, 'Unable to verify Paytm payment status.');
    }

    public function mockComplete(
        Request $request,
        SubscriptionPackagePurchase $subscriptionPackagePurchase,
        SubscriptionPackagePaymentService $paymentService
    ): RedirectResponse {
        if ($subscriptionPackagePurchase->user_id !== $request->user()->id) {
            abort(403, 'You do not have access to this payment flow.');
        }

        if (! $this->isMockCheckoutAllowed($subscriptionPackagePurchase)) {
            abort(403, 'Mock payment completion is disabled.');
        }

        if ($subscriptionPackagePurchase->status === 'initiated') {
            if ($request->boolean('fail')) {
                $paymentService->markFailed($subscriptionPackagePurchase, 'mock_marked_failed', [
                    'source' => 'mock_gateway',
                ]);
            } else {
                $paymentService->markPaid(
                    $subscriptionPackagePurchase,
                    'MOCK-'.Str::upper(Str::random(12)),
                    ['source' => 'mock_gateway']
                );
            }
        }

        $subscriptionPackagePurchase->refresh();

        return $this->redirectFromPurchase($subscriptionPackagePurchase, 'Unable to complete mock payment.');
    }

    private function gatewayLabels(): array
    {
        $gateways = [
            'razorpay' => 'Razorpay',
            'phonepe' => 'PhonePe',
            'paytm' => 'Paytm',
        ];

        if ((bool) setting('payment_test_mode', false)) {
            $gateways['mock'] = 'Mock (testing)';
        }

        return $gateways;
    }

    private function availableGateways(Request $request): array
    {
        $gateways = $this->gatewayLabels();

        if ($this->isInAppPaymentMode($request)) {
            $inAppGateways = [
                'razorpay' => $gateways['razorpay'] ?? 'Razorpay',
            ];

            if (array_key_exists('mock', $gateways)) {
                $inAppGateways['mock'] = $gateways['mock'];
            }

            $gateways = $inAppGateways;
        }

        if ($this->isSingleGatewayMode()) {
            $defaultGateway = $this->resolveDefaultGateway($gateways);

            return [
                $defaultGateway => $gateways[$defaultGateway],
            ];
        }

        return $gateways;
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

    private function isSingleGatewayMode(): bool
    {
        return strtolower((string) setting('payment_gateway_selection_mode', 'single')) !== 'multiple';
    }

    private function checkoutMode(): string
    {
        $mode = strtolower((string) setting('payment_checkout_mode', 'inapp_only'));

        if (! in_array($mode, ['inapp_only', 'gateway_redirect'], true)) {
            return 'inapp_only';
        }

        return $mode;
    }

    private function isInAppPaymentMode(Request $request): bool
    {
        if ($this->checkoutMode() === 'inapp_only') {
            return true;
        }

        if ($request->boolean('in_app_payment')) {
            return true;
        }

        $xRequestedWith = strtolower((string) $request->header('X-Requested-With', ''));

        if ($xRequestedWith !== '' && $xRequestedWith !== 'xmlhttprequest') {
            return true;
        }

        $userAgent = strtolower((string) $request->userAgent());

        if ($userAgent === '') {
            return false;
        }

        if (str_contains($userAgent, '; wv') || str_contains($userAgent, ';wv')) {
            return true;
        }

        return str_contains($userAgent, 'android')
            && str_contains($userAgent, 'version/')
            && str_contains($userAgent, 'chrome/')
            && str_contains($userAgent, 'mobile safari');
    }

    private function createMerchantOrderId(SubscriptionPackage $package): string
    {
        do {
            $merchantOrderId = 'SPK'.now()->format('YmdHis').$package->id.Str::upper(Str::random(6));
        } while (SubscriptionPackagePurchase::query()->where('merchant_order_id', $merchantOrderId)->exists());

        return $merchantOrderId;
    }

    private function redirectFromPurchase(?SubscriptionPackagePurchase $purchase, string $fallbackMessage): RedirectResponse
    {
        if (! $purchase) {
            return redirect()->route('subscriptions.index')->with('status', $fallbackMessage);
        }

        if ($purchase->status === 'paid') {
            return redirect()
                ->route('subscriptions.index')
                ->with('status', 'Payment successful. Your package has been activated.');
        }

        return redirect()
            ->route('subscriptions.index')
            ->with('status', 'Payment was not completed. You can try again.');
    }

    private function isMockCheckoutAllowed(SubscriptionPackagePurchase $purchase): bool
    {
        if (! (bool) setting('payment_test_mode', false)) {
            return false;
        }

        $checkoutType = (string) data_get($purchase->meta, 'checkout.type');

        return $purchase->gateway === 'mock' || $checkoutType === 'mock';
    }
}
