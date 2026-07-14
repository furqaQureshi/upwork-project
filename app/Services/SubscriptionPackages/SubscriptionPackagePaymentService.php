<?php

namespace App\Services\SubscriptionPackages;

use App\Models\SubscriptionPackagePurchase;
use Illuminate\Support\Facades\Http;

class SubscriptionPackagePaymentService
{
    public function createCheckout(SubscriptionPackagePurchase $purchase): array
    {
        return match ($purchase->gateway) {
            'razorpay' => $this->createRazorpayCheckout($purchase),
            'phonepe' => $this->createPhonePeCheckout($purchase),
            'paytm' => $this->createPaytmCheckout($purchase),
            'stripe' => $this->createStripeCheckout($purchase),
            'mock' => $this->mockCheckout($purchase, 'Mock gateway checkout initialized.'),
            default => $this->errorCheckout('Unsupported gateway selected.'),
        };
    }

    public function verifyRazorpayCallback(array $payload): ?SubscriptionPackagePurchase
    {
        $orderId = $payload['razorpay_order_id'] ?? null;
        $paymentId = $payload['razorpay_payment_id'] ?? null;
        $signature = $payload['razorpay_signature'] ?? null;

        if (! $orderId || ! $paymentId || ! $signature) {
            return null;
        }

        $purchase = SubscriptionPackagePurchase::query()
            ->where('gateway', 'razorpay')
            ->where('provider_order_id', $orderId)
            ->latest('id')
            ->first();

        if (! $purchase) {
            return null;
        }

        $secret = $this->razorpayConfig()['key_secret'];

        if ($secret === '') {
            $this->markFailed($purchase, 'missing_razorpay_secret', $payload);

            return $purchase;
        }

        $expected = hash_hmac('sha256', $orderId.'|'.$paymentId, $secret);

        if (! hash_equals($expected, $signature)) {
            $this->markFailed($purchase, 'razorpay_signature_mismatch', $payload);

            return $purchase;
        }

        $this->markPaid($purchase, $paymentId, $payload);

        return $purchase;
    }

    public function verifyPhonePeByMerchantOrder(string $merchantOrderId, array $payload = []): ?SubscriptionPackagePurchase
    {
        $purchase = SubscriptionPackagePurchase::query()
            ->where('gateway', 'phonepe')
            ->where('merchant_order_id', $merchantOrderId)
            ->latest('id')
            ->first();

        if (! $purchase) {
            return null;
        }

        $phonePe = $this->phonePeConfig();
        $merchantId = $phonePe['merchant_id'];
        $saltKey = $phonePe['salt_key'];
        $saltIndex = $phonePe['salt_index'];
        $baseUrl = $phonePe['base_url'];

        if ($merchantId === '' || $saltKey === '' || $baseUrl === '') {
            $this->markFailed($purchase, 'missing_phonepe_credentials', $payload);

            return $purchase;
        }

        $path = '/pg/v1/status/'.$merchantId.'/'.$merchantOrderId;
        $checksum = hash('sha256', $path.$saltKey).'###'.$saltIndex;

        $response = Http::timeout(25)
            ->withHeaders([
                'X-VERIFY' => $checksum,
                'X-MERCHANT-ID' => $merchantId,
                'accept' => 'application/json',
            ])
            ->get($baseUrl.$path);

        if (! $response->successful()) {
            $this->markFailed($purchase, 'phonepe_status_lookup_failed', [
                'request_payload' => $payload,
                'response' => $response->json(),
            ]);

            return $purchase;
        }

        $statusPayload = $response->json();
        $success = (bool) data_get($statusPayload, 'success') && data_get($statusPayload, 'code') === 'PAYMENT_SUCCESS';

        if (! $success) {
            $this->markFailed($purchase, 'phonepe_payment_not_success', [
                'request_payload' => $payload,
                'status_payload' => $statusPayload,
            ]);

            return $purchase;
        }

        $providerPaymentId = data_get($statusPayload, 'data.transactionId')
            ?? data_get($statusPayload, 'data.providerReferenceId')
            ?? $merchantOrderId;

        $this->markPaid($purchase, (string) $providerPaymentId, [
            'request_payload' => $payload,
            'status_payload' => $statusPayload,
        ]);

        return $purchase;
    }

    public function verifyPaytmByMerchantOrder(string $merchantOrderId, array $payload = []): ?SubscriptionPackagePurchase
    {
        $purchase = SubscriptionPackagePurchase::query()
            ->where('gateway', 'paytm')
            ->where('merchant_order_id', $merchantOrderId)
            ->latest('id')
            ->first();

        if (! $purchase) {
            return null;
        }

        $paytm = $this->paytmConfig();
        $mid = $paytm['mid'];
        $merchantKey = $paytm['merchant_key'];
        $baseUrl = $paytm['base_url'];

        if ($mid === '' || $merchantKey === '' || $baseUrl === '') {
            $this->markFailed($purchase, 'missing_paytm_credentials', $payload);

            return $purchase;
        }

        $body = [
            'mid' => $mid,
            'orderId' => $merchantOrderId,
        ];

        $signature = hash_hmac('sha256', json_encode($body, JSON_UNESCAPED_SLASHES), $merchantKey);

        $response = Http::timeout(25)
            ->acceptJson()
            ->post($baseUrl.'/v3/order/status', [
                'body' => $body,
                'head' => [
                    'signature' => $signature,
                ],
            ]);

        if (! $response->successful()) {
            $this->markFailed($purchase, 'paytm_status_lookup_failed', [
                'request_payload' => $payload,
                'response' => $response->json(),
            ]);

            return $purchase;
        }

        $statusPayload = $response->json();
        $resultStatus = data_get($statusPayload, 'body.resultInfo.resultStatus');

        if ($resultStatus !== 'TXN_SUCCESS') {
            $this->markFailed($purchase, 'paytm_payment_not_success', [
                'request_payload' => $payload,
                'status_payload' => $statusPayload,
            ]);

            return $purchase;
        }

        $providerPaymentId = data_get($statusPayload, 'body.txnId') ?? $merchantOrderId;

        $this->markPaid($purchase, (string) $providerPaymentId, [
            'request_payload' => $payload,
            'status_payload' => $statusPayload,
        ]);

        return $purchase;
    }

    public function markPaid(SubscriptionPackagePurchase $purchase, string $providerPaymentId, array $payload = []): void
    {
        if ($purchase->status === 'paid') {
            return;
        }

        $package = $purchase->subscriptionPackage()->first();

        if (! $package || ! $package->is_active) {
            $this->markFailed($purchase, 'inactive_or_missing_package', $payload);

            return;
        }

        $startedAt = now();
        $expiresAt = $package->package_duration_type === 'unlimited'
            ? null
            : $startedAt->copy()->addDays(max(1, (int) ($package->package_duration_days ?? 1)));

        $remainingItems = $package->item_limit_type === 'unlimited'
            ? null
            : max(0, (int) ($package->item_limit_count ?? 0));

        $remainingAiItems = null;
        if (! $package->allows_ai) {
            $remainingAiItems = 0;
        } elseif ($package->ai_usage_limit_type === 'limited') {
            $remainingAiItems = max(0, (int) ($package->ai_usage_limit_count ?? 0));
        }

        $purchase->update([
            'status' => 'paid',
            'provider_payment_id' => $providerPaymentId,
            'paid_at' => now(),
            'activated_at' => now(),
            'package_started_at' => $startedAt,
            'package_expires_at' => $expiresAt,
            'remaining_items' => $remainingItems,
            'used_ai_items' => 0,
            'remaining_ai_items' => $remainingAiItems,
            'callback_payload' => $payload,
        ]);
    }

    public function markFailed(SubscriptionPackagePurchase $purchase, string $reason, array $payload = []): void
    {
        if ($purchase->status === 'paid') {
            return;
        }

        $meta = $purchase->meta ?? [];
        $meta['failure_reason'] = $reason;

        $purchase->update([
            'status' => 'failed',
            'meta' => $meta,
            'callback_payload' => $payload,
        ]);
    }

    private function createRazorpayCheckout(SubscriptionPackagePurchase $purchase): array
    {
        $razorpay = $this->razorpayConfig();
        $keyId = $razorpay['key_id'];
        $keySecret = $razorpay['key_secret'];
        $baseUrl = $razorpay['base_url'];

        if ($keyId === '' || $keySecret === '' || $baseUrl === '') {
            return $this->errorCheckout('Razorpay credentials are not configured.');
        }

        $response = Http::timeout(25)
            ->withBasicAuth($keyId, $keySecret)
            ->acceptJson()
            ->post($baseUrl.'/v1/orders', [
                'amount' => (int) round(((float) $purchase->amount) * 100),
                'currency' => $purchase->currency,
                'receipt' => $purchase->merchant_order_id,
                'notes' => [
                    'purchase_id' => $purchase->id,
                    'subscription_package_id' => $purchase->subscription_package_id,
                ],
            ]);

        $orderId = data_get($response->json(), 'id');

        if (! $response->successful() || ! $orderId) {
            return $this->errorCheckout('Razorpay order creation failed.', [
                'status_code' => $response->status(),
                'response' => $response->json(),
            ]);
        }

        $purchase->update([
            'provider_order_id' => (string) $orderId,
            'provider_reference' => (string) $orderId,
        ]);

        return [
            'type' => 'razorpay',
            'key_id' => $keyId,
            'order_id' => (string) $orderId,
        ];
    }

    private function createPhonePeCheckout(SubscriptionPackagePurchase $purchase): array
    {
        $phonePe = $this->phonePeConfig();
        $merchantId = $phonePe['merchant_id'];
        $saltKey = $phonePe['salt_key'];
        $saltIndex = $phonePe['salt_index'];
        $baseUrl = $phonePe['base_url'];

        if ($merchantId === '' || $saltKey === '' || $baseUrl === '') {
            return $this->errorCheckout('PhonePe credentials are not configured.');
        }

        $payload = [
            'merchantId' => $merchantId,
            'merchantTransactionId' => $purchase->merchant_order_id,
            'merchantUserId' => 'USER_'.$purchase->user_id,
            'amount' => (int) round(((float) $purchase->amount) * 100),
            'redirectUrl' => route('subscriptions.callback.phonepe'),
            'redirectMode' => 'POST',
            'callbackUrl' => route('subscriptions.callback.phonepe'),
            'paymentInstrument' => [
                'type' => 'PAY_PAGE',
            ],
        ];

        $encodedPayload = base64_encode(json_encode($payload, JSON_UNESCAPED_SLASHES));
        $checksum = hash('sha256', $encodedPayload.'/pg/v1/pay'.$saltKey).'###'.$saltIndex;

        $response = Http::timeout(25)
            ->withHeaders([
                'accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-VERIFY' => $checksum,
            ])
            ->post($baseUrl.'/pg/v1/pay', [
                'request' => $encodedPayload,
            ]);

        $redirectUrl = data_get($response->json(), 'data.instrumentResponse.redirectInfo.url');

        if (! $response->successful() || ! $redirectUrl) {
            return $this->errorCheckout('PhonePe payment initialization failed.', [
                'status_code' => $response->status(),
                'response' => $response->json(),
            ]);
        }

        $purchase->update([
            'provider_order_id' => $purchase->merchant_order_id,
            'provider_reference' => $purchase->merchant_order_id,
        ]);

        return [
            'type' => 'redirect',
            'redirect_url' => (string) $redirectUrl,
        ];
    }

    private function createPaytmCheckout(SubscriptionPackagePurchase $purchase): array
    {
        $paytm = $this->paytmConfig();
        $mid = $paytm['mid'];
        $merchantKey = $paytm['merchant_key'];
        $baseUrl = $paytm['base_url'];

        if ($mid === '' || $merchantKey === '' || $baseUrl === '') {
            return $this->errorCheckout('Paytm credentials are not configured.');
        }

        $body = [
            'requestType' => 'Payment',
            'mid' => $mid,
            'websiteName' => $paytm['website'],
            'orderId' => $purchase->merchant_order_id,
            'callbackUrl' => route('subscriptions.callback.paytm'),
            'txnAmount' => [
                'value' => number_format((float) $purchase->amount, 2, '.', ''),
                'currency' => $purchase->currency,
            ],
            'userInfo' => [
                'custId' => 'USER_'.$purchase->user_id,
            ],
        ];

        $signature = hash_hmac('sha256', json_encode($body, JSON_UNESCAPED_SLASHES), $merchantKey);

        $response = Http::timeout(25)
            ->acceptJson()
            ->post($baseUrl.'/theia/api/v1/initiateTransaction?mid='.$mid.'&orderId='.$purchase->merchant_order_id, [
                'body' => $body,
                'head' => [
                    'signature' => $signature,
                ],
            ]);

        $txnToken = data_get($response->json(), 'body.txnToken');

        if (! $response->successful() || ! $txnToken) {
            return $this->errorCheckout('Paytm transaction initialization failed.', [
                'status_code' => $response->status(),
                'response' => $response->json(),
            ]);
        }

        $purchase->update([
            'provider_order_id' => $purchase->merchant_order_id,
            'provider_reference' => $purchase->merchant_order_id,
        ]);

        return [
            'type' => 'paytm_form',
            'action_url' => $baseUrl.'/theia/api/v1/showPaymentPage',
            'mid' => $mid,
            'order_id' => $purchase->merchant_order_id,
            'txn_token' => (string) $txnToken,
        ];
    }

    private function razorpayConfig(): array
    {
        return [
            'key_id' => $this->settingString('razorpay_key_id', config('services.razorpay.key_id')),
            'key_secret' => $this->settingString('razorpay_key_secret', config('services.razorpay.key_secret')),
            'base_url' => $this->normalizeBaseUrl($this->settingString('razorpay_base_url', config('services.razorpay.base_url'))),
        ];
    }

    private function phonePeConfig(): array
    {
        return [
            'merchant_id' => $this->settingString('phonepe_merchant_id', config('services.phonepe.merchant_id')),
            'salt_key' => $this->settingString('phonepe_salt_key', config('services.phonepe.salt_key')),
            'salt_index' => $this->settingString('phonepe_salt_index', config('services.phonepe.salt_index', '1')),
            'base_url' => $this->normalizeBaseUrl($this->settingString('phonepe_base_url', config('services.phonepe.base_url'))),
        ];
    }

    private function paytmConfig(): array
    {
        return [
            'mid' => $this->settingString('paytm_mid', config('services.paytm.mid')),
            'merchant_key' => $this->settingString('paytm_merchant_key', config('services.paytm.merchant_key')),
            'website' => $this->settingString('paytm_website', config('services.paytm.website', 'WEBSTAGING')),
            'base_url' => $this->normalizeBaseUrl($this->settingString('paytm_base_url', config('services.paytm.base_url'))),
        ];
    }

    public function verifyStripePaymentIntent(string $paymentIntentId, array $payload = []): ?SubscriptionPackagePurchase
    {
        $purchase = SubscriptionPackagePurchase::query()
            ->where('gateway', 'stripe')
            ->where('provider_payment_id', $paymentIntentId)
            ->latest('id')
            ->first();

        if (! $purchase) {
            return null;
        }

        if ($purchase->status === 'paid') {
            return $purchase;
        }

        $stripe = $this->stripeConfig();
        if ($stripe['secret_key'] === '') {
            $this->markFailed($purchase, 'missing_stripe_credentials', $payload);
            return $purchase;
        }

        try {
            \Stripe\Stripe::setApiKey($stripe['secret_key']);
            $intent = \Stripe\PaymentIntent::retrieve($paymentIntentId);

            if ($intent->status === 'succeeded') {
                $this->markPaid($purchase, $paymentIntentId, $payload);
            } elseif (in_array($intent->status, ['canceled', 'processing', 'requires_action', 'requires_capture', 'requires_confirmation', 'requires_payment_method'])) {
                $this->markFailed($purchase, 'stripe_payment_not_completed', [
                    'status' => $intent->status,
                    'payload' => $payload,
                ]);
            }
        } catch (\Stripe\Exception\ApiErrorException $e) {
            $this->markFailed($purchase, 'stripe_api_error', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);
        }

        return $purchase;
    }

    public function handleStripeWebhook(array $event): ?SubscriptionPackagePurchase
    {
        $type = $event['type'] ?? null;
        $data = $event['data']['object'] ?? [];

        if ($type === 'payment_intent.succeeded') {
            $paymentIntentId = $data['id'] ?? null;

            if (! $paymentIntentId) {
                return null;
            }

            $purchase = SubscriptionPackagePurchase::query()
                ->where('gateway', 'stripe')
                ->where('provider_payment_id', $paymentIntentId)
                ->orWhere('provider_order_id', $paymentIntentId)
                ->latest('id')
                ->first();

            if ($purchase && $purchase->status !== 'paid') {
                $this->markPaid($purchase, $paymentIntentId, [
                    'webhook_event' => $event,
                ]);
            }

            return $purchase;
        } elseif ($type === 'payment_intent.payment_failed') {
            $paymentIntentId = $data['id'] ?? null;

            if (! $paymentIntentId) {
                return null;
            }

            $purchase = SubscriptionPackagePurchase::query()
                ->where('gateway', 'stripe')
                ->where('provider_payment_id', $paymentIntentId)
                ->orWhere('provider_order_id', $paymentIntentId)
                ->latest('id')
                ->first();

            if ($purchase && $purchase->status !== 'paid') {
                $this->markFailed($purchase, 'stripe_payment_failed', [
                    'webhook_event' => $event,
                ]);
            }

            return $purchase;
        }

        return null;
    }

    private function createStripeCheckout(SubscriptionPackagePurchase $purchase): array
    {
        $stripe = $this->stripeConfig();

        if ($stripe['secret_key'] === '' || $stripe['public_key'] === '') {
            return $this->errorCheckout('Stripe credentials are not configured.');
        }

        try {
            \Stripe\Stripe::setApiKey($stripe['secret_key']);

            $metadata = [
                'purchase_id' => $purchase->id,
                'subscription_package_id' => $purchase->subscription_package_id,
                'user_id' => $purchase->user_id,
                'merchant_order_id' => $purchase->merchant_order_id,
            ];

            $intentData = [
                'amount' => (int) round(((float) $purchase->amount) * 100),
                'currency' => strtolower($purchase->currency),
                'description' => 'Subscription Package - Order #'.$purchase->merchant_order_id,
                'metadata' => $metadata,
                'statement_descriptor' => substr(config('stripe.statement_descriptor', 'Unisell Marketplace'), 0, 22),
            ];

            $paymentIntent = \Stripe\PaymentIntent::create($intentData);

            $purchase->update([
                'provider_order_id' => $paymentIntent->id,
                'provider_payment_id' => $paymentIntent->id,
                'provider_reference' => $paymentIntent->id,
                'meta' => [
                    'client_secret' => $paymentIntent->client_secret,
                ],
            ]);

            return [
                'type' => 'stripe',
                'client_secret' => $paymentIntent->client_secret,
                'payment_intent_id' => $paymentIntent->id,
                'public_key' => $stripe['public_key'],
                'amount' => (float) $purchase->amount,
                'currency' => $purchase->currency,
            ];
        } catch (\Stripe\Exception\ApiErrorException $e) {
            return $this->errorCheckout('Stripe payment initialization failed: '.$e->getMessage(), [
                'error' => $e->getMessage(),
                'code' => $e->getError()->code ?? null,
            ]);
        } catch (\Exception $e) {
            return $this->errorCheckout('An error occurred while initializing Stripe payment.', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function stripeConfig(): array
    {
        return [
            'public_key' => $this->settingString('stripe_public_key', config('stripe.public_key')),
            'secret_key' => $this->settingString('stripe_secret_key', config('stripe.secret_key')),
            'webhook_secret' => $this->settingString('stripe_webhook_secret', config('stripe.webhook_secret')),
        ];
    }

    private function settingString(string $key, mixed $fallback = ''): string
    {
        $value = trim((string) setting($key, ''));

        if ($value !== '') {
            return $value;
        }

        return trim((string) $fallback);
    }

    private function normalizeBaseUrl(mixed $baseUrl): string
    {
        return rtrim(trim((string) $baseUrl), '/');
    }

    private function mockCheckout(SubscriptionPackagePurchase $purchase, string $reason): array
    {
        return [
            'type' => 'mock',
            'redirect_url' => route('subscriptions.mock.complete', $purchase),
            'reason' => $reason,
        ];
    }

    private function errorCheckout(string $reason, array $meta = []): array
    {
        return [
            'type' => 'error',
            'reason' => $reason,
            'meta' => $meta,
        ];
    }
}
