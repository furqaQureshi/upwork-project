<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\AiController;
use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\HomeController;
use App\Http\Controllers\Api\V1\ListingController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\SellerVerificationController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\SupportController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1');
    Route::post('/auth/register', [AuthController::class, 'register'])
        ->middleware('throttle:10,1');
    Route::post('/auth/otp/send', [AuthController::class, 'sendOtp'])
        ->middleware('throttle:5,1');
    Route::post('/auth/otp/verify', [AuthController::class, 'verifyOtp'])
        ->middleware('throttle:10,1');

    Route::get('/home', [HomeController::class, 'index']);
    Route::get('/categories', [HomeController::class, 'categories']);
    Route::get('/categories/{category}/custom-fields', [HomeController::class, 'categoryCustomFields']);
    Route::get('/listings', [ListingController::class, 'index']);
    Route::get('/listings/{listing}', [ListingController::class, 'show']);
    Route::get('/listings/{listing}/similar', [ListingController::class, 'similar']);
    Route::get('/support/faqs', [SupportController::class, 'faqs']);
    Route::get('/app/version', [HomeController::class, 'appVersion']);
    Route::post('/ai/assistant/chat', [AiController::class, 'compassChat']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/logout-all', [AuthController::class, 'logoutAllDevices']);

        Route::get('/my/listings', [ListingController::class, 'myListings']);
        Route::post('/listings', [ListingController::class, 'store']);
        Route::patch('/listings/{listing}', [ListingController::class, 'update']);
        Route::delete('/listings/{listing}', [ListingController::class, 'destroy']);
        Route::post('/ai/listings/generate', [AiController::class, 'generateListingDraft']);
        Route::post('/ai/listings/price-recommendation', [AiController::class, 'recommendPrice']);
        Route::post('/ai/listings/moderation-precheck', [AiController::class, 'moderationPrecheck']);
        Route::post('/ai/listings/translate', [AiController::class, 'translateListingContent']);
        Route::post('/listings/{listing}/mark-sold', [ListingController::class, 'markSold']);
        Route::post('/listings/{listing}/boost-featured', [ListingController::class, 'boostFeatured']);
        Route::post('/listings/{listing}/favorite', [ListingController::class, 'toggleFavorite']);
        Route::get('/my/favorites', [ListingController::class, 'myFavorites']);

        Route::get('/chats', [ChatController::class, 'index']);
        Route::post('/chats', [ChatController::class, 'store']);
        Route::get('/chats/{conversation}/messages', [ChatController::class, 'messages']);
        Route::post('/chats/{conversation}/messages', [ChatController::class, 'sendMessage']);

        Route::get('/profile', [ProfileController::class, 'show']);
        Route::patch('/profile', [ProfileController::class, 'update']);

        Route::get('/subscriptions/overview', [SubscriptionController::class, 'overview']);
        Route::post('/subscriptions/packages/{subscriptionPackage}/buy', [SubscriptionController::class, 'buy']);
        Route::post('/subscriptions/payments/razorpay/verify', [SubscriptionController::class, 'verifyRazorpayPayment']);
        Route::post('/subscriptions/payments/stripe/verify', [PaymentController::class, 'verifyStripePayment']);
        Route::get('/payments/methods', [PaymentController::class, 'paymentMethods']);

        Route::get('/seller/verification/status', [SellerVerificationController::class, 'status']);
        Route::post('/seller/verification/submit', [SellerVerificationController::class, 'submit']);
        Route::get('/seller/verifications', [SellerVerificationController::class, 'listVerifications']);
        Route::get('/seller/verifications/{id}', [SellerVerificationController::class, 'showVerification']);
        Route::delete('/seller/verifications/{id}', [SellerVerificationController::class, 'destroyVerification']);
        Route::get('/seller/dashboard', [SellerVerificationController::class, 'dashboard']);
        Route::get('/seller/profile', [SellerVerificationController::class, 'profile']);
        Route::get('/seller/analytics', [SellerVerificationController::class, 'analytics']);

        Route::get('/support/tickets', [SupportController::class, 'index']);
        Route::post('/support/tickets', [SupportController::class, 'store']);
    });

    // Stripe webhook - outside auth middleware
    Route::post('/webhooks/stripe', [PaymentController::class, 'stripeWebhook']);
});
