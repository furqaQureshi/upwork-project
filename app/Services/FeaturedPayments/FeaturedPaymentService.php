<?php

namespace App\Services\FeaturedPayments;

use App\Models\FeaturedAdPayment;
use Illuminate\Support\Facades\Http;

class FeaturedPaymentService
{
    public function createCheckout(FeaturedAdPayment $payment): array
    {
        return match ($payment->gateway) {
            'razorpay' => $this->createRazorpayCheckout($payment),
            'phonepe' => $this->createPhonePeCheckout($payment),
            'paytm' => $this->createPaytmCheckout($payment),
            'mock' => $this->mockCheckout($payment, 'Mock gateway checkout initialized.'),
            default => $this->errorCheckout('Unsupported gateway selected.'),
        };
    }

    public function verifyRazorpayCallback(array $payload): ?FeaturedAdPayment
    {
        $orderId = $payload['razorpay_order_id'] ?? null;
        $paymentId = $payload['razorpay_payment_id'] ?? null;
        $signature = $payload['razorpay_signature'] ?? null;

        if (! $orderId || ! $paymentId || ! $signature) {
            return null;
        }

        $payment = FeaturedAdPayment::query()
            ->where('gateway', 'razorpay')
            ->where('provider_order_id', $orderId)
            ->latest('id')
            ->first();

        if (! $payment) {
            return null;
        }

        $secret = $this->razorpayConfig()['key_secret'];

        if ($secret === '') {
            $this->markFailed($payment, 'missing_razorpay_secret', $payload);

            return $payment;
        }

        $expected = hash_hmac('sha256', $orderId.'|'.$paymentId, $secret);

        if (! hash_equals($expected, $signature)) {
            $this->markFailed($payment, 'razorpay_signature_mismatch', $payload);

            return $payment;
        }

        $this->markPaid($payment, $paymentId, $payload);

        return $payment;
    }

    public function verifyPhonePeByMerchantOrder(string $merchantOrderId, array $payload = []): ?FeaturedAdPayment
    {
        $payment = FeaturedAdPayment::query()
            ->where('gateway', 'phonepe')
            ->where('merchant_order_id', $merchantOrderId)
            ->latest('id')
            ->first();

        if (! $payment) {
            return null;
        }

        $phonePe = $this->phonePeConfig();
        $merchantId = $phonePe['merchant_id'];
        $saltKey = $phonePe['salt_key'];
        $saltIndex = $phonePe['salt_index'];
        $baseUrl = $phonePe['base_url'];

        if ($merchantId === '' || $saltKey === '' || $baseUrl === '') {
            $this->markFailed($payment, 'missing_phonepe_credentials', $payload);

            return $payment;
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
            $this->markFailed($payment, 'phonepe_status_lookup_failed', [
                'request_payload' => $payload,
                'response' => $response->json(),
            ]);

            return $payment;
        }

        $statusPayload = $response->json();
        $success = (bool) data_get($statusPayload, 'success') && data_get($statusPayload, 'code') === 'PAYMENT_SUCCESS';

        if (! $success) {
            $this->markFailed($payment, 'phonepe_payment_not_success', [
                'request_payload' => $payload,
                'status_payload' => $statusPayload,
            ]);

            return $payment;
        }

        $providerPaymentId = data_get($statusPayload, 'data.transactionId')
            ?? data_get($statusPayload, 'data.providerReferenceId')
            ?? $merchantOrderId;

        $this->markPaid($payment, (string) $providerPaymentId, [
            'request_payload' => $payload,
            'status_payload' => $statusPayload,
        ]);

        return $payment;
    }

    public function verifyPaytmByMerchantOrder(string $merchantOrderId, array $payload = []): ?FeaturedAdPayment
    {
        $payment = FeaturedAdPayment::query()
            ->where('gateway', 'paytm')
            ->where('merchant_order_id', $merchantOrderId)
            ->latest('id')
            ->first();

        if (! $payment) {
            return null;
        }

        $paytm = $this->paytmConfig();
        $mid = $paytm['mid'];
        $merchantKey = $paytm['merchant_key'];
        $baseUrl = $paytm['base_url'];

        if ($mid === '' || $merchantKey === '' || $baseUrl === '') {
            $this->markFailed($payment, 'missing_paytm_credentials', $payload);

            return $payment;
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
            $this->markFailed($payment, 'paytm_status_lookup_failed', [
                'request_payload' => $payload,
                'response' => $response->json(),
            ]);

            return $payment;
        }

        $statusPayload = $response->json();
        $resultStatus = data_get($statusPayload, 'body.resultInfo.resultStatus');

        if ($resultStatus !== 'TXN_SUCCESS') {
            $this->markFailed($payment, 'paytm_payment_not_success', [
                'request_payload' => $payload,
                'status_payload' => $statusPayload,
            ]);

            return $payment;
        }

        $providerPaymentId = data_get($statusPayload, 'body.txnId') ?? $merchantOrderId;

        $this->markPaid($payment, (string) $providerPaymentId, [
            'request_payload' => $payload,
            'status_payload' => $statusPayload,
        ]);

        return $payment;
    }

    public function markPaid(FeaturedAdPayment $payment, string $providerPaymentId, array $payload = []): void
    {
        if ($payment->status === 'paid') {
            return;
        }

        $listing = $payment->listing()->first();

        if (! $listing) {
            return;
        }

        $featureStart = $listing->featured_until && $listing->featured_until->isFuture()
            ? $listing->featured_until->copy()
            : now();

        $featureUntil = $featureStart->addDays($payment->feature_days);

        $payment->update([
            'status' => 'paid',
            'provider_payment_id' => $providerPaymentId,
            'paid_at' => now(),
            'expires_at' => $featureUntil,
            'callback_payload' => $payload,
        ]);

        $listing->update([
            'is_featured' => true,
            'featured_until' => $featureUntil,
            'last_featured_payment_id' => $payment->id,
        ]);
    }

    public function markFailed(FeaturedAdPayment $payment, string $reason, array $payload = []): void
    {
        if ($payment->status === 'paid') {
            return;
        }

        $meta = $payment->meta ?? [];
        $meta['failure_reason'] = $reason;

        $payment->update([
            'status' => 'failed',
            'meta' => $meta,
            'callback_payload' => $payload,
        ]);
    }

    private function createRazorpayCheckout(FeaturedAdPayment $payment): array
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
                'amount' => (int) round(((float) $payment->amount) * 100),
                'currency' => $payment->currency,
                'receipt' => $payment->merchant_order_id,
                'notes' => [
                    'payment_id' => $payment->id,
                    'listing_id' => $payment->listing_id,
                ],
            ]);

        $orderId = data_get($response->json(), 'id');

        if (! $response->successful() || ! $orderId) {
            return $this->errorCheckout('Razorpay order creation failed.', [
                'status_code' => $response->status(),
                'response' => $response->json(),
            ]);
        }

        $payment->update([
            'provider_order_id' => (string) $orderId,
            'provider_reference' => (string) $orderId,
        ]);

        return [
            'type' => 'razorpay',
            'key_id' => $keyId,
            'order_id' => (string) $orderId,
        ];
    }

    private function createPhonePeCheckout(FeaturedAdPayment $payment): array
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
            'merchantTransactionId' => $payment->merchant_order_id,
            'merchantUserId' => 'USER_'.$payment->user_id,
            'amount' => (int) round(((float) $payment->amount) * 100),
            'redirectUrl' => route('payments.callback.phonepe'),
            'redirectMode' => 'POST',
            'callbackUrl' => route('payments.callback.phonepe'),
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

        $payment->update([
            'provider_order_id' => $payment->merchant_order_id,
            'provider_reference' => $payment->merchant_order_id,
        ]);

        return [
            'type' => 'redirect',
            'redirect_url' => (string) $redirectUrl,
        ];
    }

    private function createPaytmCheckout(FeaturedAdPayment $payment): array
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
            'orderId' => $payment->merchant_order_id,
            'callbackUrl' => route('payments.callback.paytm'),
            'txnAmount' => [
                'value' => number_format((float) $payment->amount, 2, '.', ''),
                'currency' => $payment->currency,
            ],
            'userInfo' => [
                'custId' => 'USER_'.$payment->user_id,
            ],
        ];

        $signature = hash_hmac('sha256', json_encode($body, JSON_UNESCAPED_SLASHES), $merchantKey);

        $response = Http::timeout(25)
            ->acceptJson()
            ->post($baseUrl.'/theia/api/v1/initiateTransaction?mid='.$mid.'&orderId='.$payment->merchant_order_id, [
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

        $payment->update([
            'provider_order_id' => $payment->merchant_order_id,
            'provider_reference' => $payment->merchant_order_id,
        ]);

        return [
            'type' => 'paytm_form',
            'action_url' => $baseUrl.'/theia/api/v1/showPaymentPage',
            'mid' => $mid,
            'order_id' => $payment->merchant_order_id,
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

    private function mockCheckout(FeaturedAdPayment $payment, string $reason): array
    {
        return [
            'type' => 'mock',
            'redirect_url' => route('payments.mock.complete', $payment),
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
