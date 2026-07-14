<?php

namespace App\Http\Controllers;

use App\Models\FeaturedAdPayment;
use App\Models\Listing;
use App\Models\SubscriptionPackage;
use App\Models\SubscriptionPackagePurchase;
use App\Services\FeaturedPayments\FeaturedPaymentService;
use App\Services\SubscriptionPackages\SubscriptionEntitlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class FeaturedAdPaymentController extends Controller
{
    public function checkout(
        Request $request,
        Listing $listing,
        SubscriptionEntitlementService $entitlementService
    ): View|RedirectResponse {
        $redirect = $this->ensureCanFeature($request, $listing);

        if ($redirect) {
            return $redirect;
        }

        $usingPackageFlow = $this->hasFeaturedPackagesConfigured();

        if ($usingPackageFlow) {
            $featuredStats = $entitlementService->packageStats($request->user(), 'featured');

            if (! $featuredStats['has_usable_package']) {
                return redirect()
                    ->route('subscriptions.index')
                    ->with('status', 'An active featured package is required before boosting this listing.');
            }
        }

        $inAppPayment = $this->isInAppPaymentMode($request);
        $gateways = $this->availableGateways($request);
        $defaultGateway = $this->resolveDefaultGateway($gateways);

        return view('payments.checkout', [
            'listing' => $listing->load(['images', 'category']),
            'recentPayments' => $listing->featuredPayments()->latest()->take(6)->get(),
            'dailyRate' => (float) setting('featured_daily_rate', config('featured_ads.daily_rate', 49)),
            'currency' => (string) setting('payment_currency', config('featured_ads.currency', 'INR')),
            'currencySymbol' => (string) setting('site_currency_symbol', 'Rs'),
            'allowedDays' => (array) setting('featured_allowed_days', config('featured_ads.allowed_days', [3, 7, 15, 30])),
            'gateways' => $gateways,
            'defaultGateway' => $defaultGateway,
            'showGatewayOptions' => count($gateways) > 1,
            'inAppPayment' => $inAppPayment,
            'usingPackageFlow' => $usingPackageFlow,
            'activeFeaturedPurchases' => $usingPackageFlow
                ? $entitlementService->activePurchasesForUser($request->user(), 'featured')
                : collect(),
        ]);
    }

    public function initialize(
        Request $request,
        Listing $listing,
        FeaturedPaymentService $featuredPaymentService,
        SubscriptionEntitlementService $entitlementService
    ): View|RedirectResponse {
        $redirect = $this->ensureCanFeature($request, $listing);

        if ($redirect) {
            return $redirect;
        }

        if ($this->hasFeaturedPackagesConfigured()) {
            $purchase = $entitlementService->findUsablePurchase($request->user(), 'featured');

            if (! $purchase) {
                return redirect()
                    ->route('subscriptions.index')
                    ->with('status', 'No active featured package with remaining boosts. Please buy a package first.');
            }

            $consumedPurchase = $entitlementService->consumePurchase(
                $purchase,
                'featured_boost',
                $listing,
                [
                    'source' => 'featured_boost',
                ]
            );

            if (! $consumedPurchase) {
                return redirect()
                    ->route('subscriptions.index')
                    ->with('status', 'Featured quota is no longer available on the selected package.');
            }

            $featureDays = $this->featureDaysFromPackagePurchase($consumedPurchase);

            $featureStart = $listing->featured_until && $listing->featured_until->isFuture()
                ? $listing->featured_until->copy()
                : now();

            $featureUntil = $featureStart->addDays($featureDays);

            $listing->update([
                'is_featured' => true,
                'featured_until' => $featureUntil,
            ]);

            $remainingText = $consumedPurchase->subscriptionPackage?->item_limit_type === 'unlimited'
                ? 'Unlimited'
                : (string) ((int) ($consumedPurchase->remaining_items ?? 0));

            return redirect()
                ->route('listings.show', $listing)
                ->with('status', "Listing boosted successfully for {$featureDays} day(s). Remaining featured quota: {$remainingText}.");
        }

        $allowedDays = (array) setting('featured_allowed_days', config('featured_ads.allowed_days', [3, 7, 15, 30]));
        $gateways = $this->availableGateways($request);
        $gatewayKeys = array_keys($gateways);

        $validated = $request->validate([
            'gateway' => ['nullable', 'string', 'in:'.implode(',', $gatewayKeys)],
            'feature_days' => ['required', 'integer', 'in:'.implode(',', $allowedDays)],
        ]);

        $selectedGateway = (string) ($validated['gateway'] ?? $this->resolveDefaultGateway($gateways));

        if ($this->isSingleGatewayMode()) {
            $selectedGateway = $this->resolveDefaultGateway($gateways);
        }

        if (! in_array($selectedGateway, $gatewayKeys, true)) {
            return back()->withInput()->with('status', 'Selected payment gateway is not available for this checkout.');
        }

        $amount = round(((float) setting('featured_daily_rate', config('featured_ads.daily_rate', 49))) * ((int) $validated['feature_days']), 2);

        if ($amount <= 0) {
            return back()->with('status', 'Invalid featured amount configuration.');
        }

        $payment = FeaturedAdPayment::create([
            'listing_id' => $listing->id,
            'user_id' => $request->user()->id,
            'gateway' => $selectedGateway,
            'merchant_order_id' => $this->createMerchantOrderId($listing),
            'amount' => $amount,
            'currency' => (string) setting('payment_currency', config('featured_ads.currency', 'INR')),
            'feature_days' => (int) $validated['feature_days'],
            'status' => 'initiated',
            'meta' => [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ],
        ]);

        $checkout = $featuredPaymentService->createCheckout($payment);

        $payment->update([
            'meta' => array_merge($payment->meta ?? [], [
                'checkout' => $checkout,
            ]),
        ]);

        $checkoutType = (string) data_get($checkout, 'type');

        if ($checkoutType === 'error') {
            $featuredPaymentService->markFailed($payment, 'checkout_initialization_failed', [
                'checkout' => $checkout,
            ]);

            return back()
                ->withInput()
                ->with('status', (string) data_get($checkout, 'reason', 'Unable to initialize selected payment gateway.'));
        }

        if (in_array($checkoutType, ['redirect', 'mock'], true) && ! data_get($checkout, 'redirect_url')) {
            return back()->with('status', 'Gateway response is incomplete. Please try again.');
        }

        return match ($checkoutType) {
            'redirect' => redirect()->away((string) data_get($checkout, 'redirect_url')),
            'mock' => redirect()->to((string) data_get($checkout, 'redirect_url')),
            'razorpay' => view('payments.razorpay', [
                'listing' => $listing,
                'payment' => $payment,
                'checkout' => $checkout,
            ]),
            'paytm_form' => view('payments.paytm', [
                'listing' => $listing,
                'payment' => $payment,
                'checkout' => $checkout,
            ]),
            default => back()->with('status', 'Unable to initialize payment gateway. Please try again.'),
        };
    }

    public function callbackRazorpay(
        Request $request,
        FeaturedPaymentService $featuredPaymentService
    ): RedirectResponse {
        $payment = $featuredPaymentService->verifyRazorpayCallback($request->all());

        return $this->redirectFromPayment($payment, 'Unable to verify Razorpay callback payload.');
    }

    public function callbackPhonePe(
        Request $request,
        FeaturedPaymentService $featuredPaymentService
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
            return redirect()->route('listings.index')->with('status', 'PhonePe callback payload is missing transaction reference.');
        }

        $payment = $featuredPaymentService->verifyPhonePeByMerchantOrder($merchantOrderId, $payload);

        return $this->redirectFromPayment($payment, 'Unable to verify PhonePe payment status.');
    }

    public function callbackPaytm(
        Request $request,
        FeaturedPaymentService $featuredPaymentService
    ): RedirectResponse {
        $merchantOrderId = (string) ($request->input('ORDERID')
            ?? $request->input('orderId')
            ?? '');

        if ($merchantOrderId === '') {
            return redirect()->route('listings.index')->with('status', 'Paytm callback payload is missing order reference.');
        }

        $payment = $featuredPaymentService->verifyPaytmByMerchantOrder($merchantOrderId, $request->all());

        return $this->redirectFromPayment($payment, 'Unable to verify Paytm payment status.');
    }

    public function mockComplete(
        Request $request,
        FeaturedAdPayment $featuredAdPayment,
        FeaturedPaymentService $featuredPaymentService
    ): RedirectResponse {
        if ($featuredAdPayment->user_id !== $request->user()->id) {
            abort(403, 'You do not have access to this payment flow.');
        }

        if (! $this->isMockCheckoutAllowed($featuredAdPayment)) {
            abort(403, 'Mock payment completion is disabled.');
        }

        if ($featuredAdPayment->status === 'initiated') {
            if ($request->boolean('fail')) {
                $featuredPaymentService->markFailed($featuredAdPayment, 'mock_marked_failed', [
                    'source' => 'mock_gateway',
                ]);
            } else {
                $featuredPaymentService->markPaid(
                    $featuredAdPayment,
                    'MOCK-'.Str::upper(Str::random(12)),
                    ['source' => 'mock_gateway']
                );
            }
        }

        $featuredAdPayment->refresh();

        return $this->redirectFromPayment($featuredAdPayment, 'Unable to complete mock payment.');
    }

    private function ensureCanFeature(Request $request, Listing $listing): ?RedirectResponse
    {
        if (! $listing->isOwnedBy($request->user())) {
            abort(403, 'You can only promote your own listings.');
        }

        if ($listing->status !== 'approved') {
            return redirect()
                ->route('listings.show', $listing)
                ->with('status', 'Only approved listings can be promoted as featured.');
        }

        return null;
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

    private function createMerchantOrderId(Listing $listing): string
    {
        do {
            $merchantOrderId = 'FAD'.now()->format('YmdHis').$listing->id.Str::upper(Str::random(6));
        } while (FeaturedAdPayment::query()->where('merchant_order_id', $merchantOrderId)->exists());

        return $merchantOrderId;
    }

    private function hasFeaturedPackagesConfigured(): bool
    {
        return SubscriptionPackage::query()
            ->where('package_type', 'featured')
            ->where('is_active', true)
            ->exists();
    }

    private function featureDaysFromPackagePurchase(SubscriptionPackagePurchase $purchase): int
    {
        $package = $purchase->subscriptionPackage;

        if (! $package) {
            return 30;
        }

        if ($package->listing_duration_type === 'custom') {
            return max(1, (int) ($package->listing_duration_days ?? 30));
        }

        return 30;
    }

    private function redirectFromPayment(?FeaturedAdPayment $payment, string $fallbackMessage): RedirectResponse
    {
        if (! $payment) {
            return redirect()->route('listings.index')->with('status', $fallbackMessage);
        }

        $payment->loadMissing('listing');

        if (! $payment->listing) {
            return redirect()->route('listings.index')->with('status', $fallbackMessage);
        }

        if ($payment->status === 'paid') {
            return redirect()
                ->route('listings.show', $payment->listing)
                ->with('status', 'Payment successful. Your listing is now featured.');
        }

        return redirect()
            ->route('payments.checkout', $payment->listing)
            ->with('status', 'Payment was not completed. You can try again.');
    }

    private function isMockCheckoutAllowed(FeaturedAdPayment $payment): bool
    {
        if (! (bool) setting('payment_test_mode', false)) {
            return false;
        }

        $checkoutType = (string) data_get($payment->meta, 'checkout.type');

        return $payment->gateway === 'mock' || $checkoutType === 'mock';
    }
}
