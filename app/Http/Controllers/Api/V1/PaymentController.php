<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\SubscriptionPackagePurchase;
use App\Services\SubscriptionPackages\SubscriptionPackagePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PaymentController extends \App\Http\Controllers\Controller
{
    /**
     * Verify Stripe payment and complete purchase
     */
    public function verifyStripePayment(
        Request $request,
        SubscriptionPackagePaymentService $paymentService
    ): JsonResponse {
        $validated = $request->validate([
            'payment_intent_id' => ['required', 'string', 'max:255'],
        ]);

        $purchase = $paymentService->verifyStripePaymentIntent($validated['payment_intent_id']);

        if (! $purchase) {
            throw ValidationException::withMessages([
                'payment' => ['Unable to verify Stripe payment.'],
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

    /**
     * Handle Stripe webhook events
     */
    public function stripeWebhook(
        Request $request,
        SubscriptionPackagePaymentService $paymentService
    ): JsonResponse {
        $payload = $request->getContent();
        $sig_header = $request->header('stripe-signature');
        $endpoint_secret = config('stripe.webhook_secret');

        if (! $endpoint_secret) {
            Log::warning('Stripe webhook secret not configured');
            return response()->json(['received' => true]);
        }

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $sig_header,
                $endpoint_secret
            );
        } catch (\UnexpectedValueException $e) {
            Log::error('Invalid Stripe webhook payload', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Log::error('Invalid Stripe webhook signature', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        // Handle the event
        $purchase = $paymentService->handleStripeWebhook($event);

        if ($purchase) {
            Log::info('Stripe webhook processed successfully', [
                'purchase_id' => $purchase->id,
                'event_type' => $event['type'],
                'status' => $purchase->status,
            ]);
        }

        return response()->json(['received' => true]);
    }

    /**
     * Get payment method options for checkout
     */
    public function paymentMethods(): JsonResponse
    {
        $methods = [
            'card' => [
                'enabled' => true,
                'icon' => 'credit-card',
            ],
        ];

        // You can add more payment methods as needed
        // 'ideal' => ['enabled' => true, 'icon' => 'ideal'],
        // 'sepa_debit' => ['enabled' => true, 'icon' => 'sepa'],

        return response()->json([
            'data' => $methods,
        ]);
    }

    /**
     * Serialize purchase for response
     */
    private function serializePurchase(SubscriptionPackagePurchase $purchase): array
    {
        $package = $purchase->subscriptionPackage;

        return [
            'id' => $purchase->id,
            'status' => $purchase->status,
            'amount' => (float) $purchase->amount,
            'currency' => $purchase->currency,
            'gateway' => $purchase->gateway,
            'merchant_order_id' => $purchase->merchant_order_id,
            'provider_order_id' => $purchase->provider_order_id,
            'provider_payment_id' => $purchase->provider_payment_id,
            'paid_at' => $purchase->paid_at?->toIso8601String(),
            'activated_at' => $purchase->activated_at?->toIso8601String(),
            'package_started_at' => $purchase->package_started_at?->toIso8601String(),
            'package_expires_at' => $purchase->package_expires_at?->toIso8601String(),
            'remaining_items' => $purchase->remaining_items,
            'remaining_ai_items' => $purchase->remaining_ai_items,
            'package' => $package ? [
                'id' => $package->id,
                'name' => $package->name,
                'category' => $package->category?->only(['id', 'name']),
                'description' => $package->description,
                'price' => (float) $package->price,
                'discount_price' => (float) ($package->discount_price ?? 0),
                'final_price' => (float) $package->final_price,
            ] : null,
        ];
    }
}
