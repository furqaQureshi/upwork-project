<?php

use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\CustomFieldController as AdminCustomFieldController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\FreePostLimitController as AdminFreePostLimitController;
use App\Http\Controllers\Admin\LegalContentController as AdminLegalContentController;
use App\Http\Controllers\Admin\PushDeliveryLogController as AdminPushDeliveryLogController;
use App\Http\Controllers\Admin\SubscriptionPackageController as AdminSubscriptionPackageController;
use App\Http\Controllers\Admin\ListingModerationController;
use App\Http\Controllers\Admin\PushNotificationController as AdminPushNotificationController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\SellerController as AdminSellerController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AiController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\FeaturedAdPaymentController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LegalContentController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\ListingController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PwaController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\SellerDashboardController;
use App\Http\Controllers\SubscriptionPackagePurchaseController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/categories', [HomeController::class, 'categories'])->name('categories.index');
Route::get('/categories/{category:slug}', [HomeController::class, 'showCategory'])->name('categories.show');
Route::get('/menu', [HomeController::class, 'menu'])->name('menu.index');
Route::get('/terms-and-conditions', [LegalContentController::class, 'terms'])->name('legal.terms');
Route::get('/privacy-policy', [LegalContentController::class, 'privacy'])->name('legal.privacy');
Route::get('/refund-and-cancellation-policy', [LegalContentController::class, 'refund'])->name('legal.refund');
Route::get('/content-policy', [LegalContentController::class, 'contentPolicy'])->name('legal.content-policy');
Route::get('/data-deletion-policy', [LegalContentController::class, 'dataDeletion'])->name('legal.data-deletion');
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('seo.sitemap');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('seo.robots');
Route::get('/ads.txt', [SeoController::class, 'adsTxt'])->name('seo.ads-txt');
Route::get('/manifest.webmanifest', [PwaController::class, 'manifest'])->name('pwa.manifest');
Route::get('/.well-known/assetlinks.json', [PwaController::class, 'assetLinks'])->name('twa.assetlinks');
Route::get('/.well-known/apple-app-site-association', [PwaController::class, 'appleAppSiteAssociation'])->name('ios.aasa');
Route::get('/media-files/{path}', function (string $path) {
    if (str_contains($path, '..')) {
        abort(404);
    }

    $normalizedPath = ltrim($path, '/');
    if (! Storage::disk('public')->exists($normalizedPath)) {
        abort(404);
    }

    return response()->file(Storage::disk('public')->path($normalizedPath));
})->where('path', '.*')->name('media.public');

Route::prefix('api/location')->name('api.location.')->group(function (): void {
    Route::get('/countries', [LocationController::class, 'countries'])->name('countries');
    Route::get('/states', [LocationController::class, 'states'])->name('states');
    Route::get('/cities', [LocationController::class, 'cities'])->name('cities');
    Route::get('/areas', [LocationController::class, 'areas'])->name('areas');
});

Route::view('/offline', 'offline')->name('offline');

Route::get('/dashboard', function () {
    return redirect()->route('listings.index');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('listings', ListingController::class)->except(['show']);
    Route::post('/listings/{listing}/mark-sold', [ListingController::class, 'markSold'])->name('listings.mark-sold');
    Route::get('/listings/{listing:slug}/contact/call', [ListingController::class, 'startCall'])->name('listings.start-call');
    Route::get('/listings/{listing:slug}/location/map', [ListingController::class, 'openMap'])->name('listings.open-map');
    Route::post('/listings/{listing}/favorite', [FavoriteController::class, 'toggle'])->name('favorites.toggle');
    Route::post('/listings/{listing}/report', [ListingController::class, 'report'])->name('listings.report');
    Route::get('/listings/{listing}/feature', [FeaturedAdPaymentController::class, 'checkout'])->name('payments.checkout');
    Route::post('/listings/{listing}/feature', [FeaturedAdPaymentController::class, 'initialize'])->name('payments.initialize');
    Route::get('/payments/mock/{featuredAdPayment}', [FeaturedAdPaymentController::class, 'mockComplete'])->name('payments.mock.complete');

    Route::get('/subscriptions/packages', [SubscriptionPackagePurchaseController::class, 'index'])->name('subscriptions.index');
    Route::get('/subscriptions/packages/plans', [SubscriptionPackagePurchaseController::class, 'plans'])->name('subscriptions.plans');
    Route::post('/subscriptions/packages/{subscriptionPackage}/buy', [SubscriptionPackagePurchaseController::class, 'initialize'])->name('subscriptions.initialize');
    Route::get('/subscriptions/mock/{subscriptionPackagePurchase}', [SubscriptionPackagePurchaseController::class, 'mockComplete'])->name('subscriptions.mock.complete');

    Route::get('/ai/compass', [AiController::class, 'compass'])->name('ai.compass');
    Route::post('/ai/compass/chat', [AiController::class, 'compassChat'])->name('ai.compass.chat');
    Route::get('/ai/autoiq', [AiController::class, 'autoiq'])->name('ai.autoiq');
    Route::get('/ai/navigator', [AiController::class, 'navigator'])->name('ai.navigator');
    Route::post('/ai/listings/generate', [AiController::class, 'generateListingDraft'])->name('ai.listings.generate');
    Route::post('/ai/listings/price-recommendation', [AiController::class, 'recommendPrice'])->name('ai.listings.price-recommendation');
    Route::post('/ai/jobs/cv-match', [AiController::class, 'cvMatch'])->name('ai.jobs.cv-match');
    Route::get('/ai/listings/{listing}/similar', [AiController::class, 'similarListings'])->name('ai.listings.similar');

    Route::get('/chat', [ConversationController::class, 'index'])->name('chat.index');
    Route::post('/chat/listing/{listing}', [ConversationController::class, 'storeFromListing'])->name('chat.from-listing');
    Route::get('/chat/{conversation}', [ConversationController::class, 'show'])->name('chat.show');
    Route::get('/chat/{conversation}/messages', [ConversationController::class, 'fetchMessages'])->name('chat.messages');
    Route::post('/chat/{conversation}/message', [ConversationController::class, 'sendMessage'])->name('chat.message');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('/notifications/{notificationId}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

    Route::post('/push/subscriptions', [PushSubscriptionController::class, 'store'])->name('push-subscriptions.store');
    Route::delete('/push/subscriptions', [PushSubscriptionController::class, 'destroy'])->name('push-subscriptions.destroy');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::get('/profile/edit-form', [ProfileController::class, 'editForm'])->name('profile.edit-form');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Seller Dashboard Routes
    Route::prefix('seller')->name('seller.')->group(function () {
        Route::get('/dashboard', [SellerDashboardController::class, 'index'])->name('dashboard');
        Route::get('/analytics', [SellerDashboardController::class, 'analytics'])->name('analytics');
        Route::get('/verification', [SellerDashboardController::class, 'verification'])->name('verification');
        Route::get('/profile', [SellerDashboardController::class, 'profile'])->name('profile');
        Route::put('/profile', [SellerDashboardController::class, 'updateProfile'])->name('profile.update');
    });
});

Route::get('/listings/{listing:slug}', [ListingController::class, 'show'])->name('listings.show');

Route::post('/payments/callback/razorpay', [FeaturedAdPaymentController::class, 'callbackRazorpay'])->name('payments.callback.razorpay');
Route::match(['GET', 'POST'], '/payments/callback/phonepe', [FeaturedAdPaymentController::class, 'callbackPhonePe'])->name('payments.callback.phonepe');
Route::match(['GET', 'POST'], '/payments/callback/paytm', [FeaturedAdPaymentController::class, 'callbackPaytm'])->name('payments.callback.paytm');

Route::post('/subscriptions/callback/razorpay', [SubscriptionPackagePurchaseController::class, 'callbackRazorpay'])->name('subscriptions.callback.razorpay');
Route::match(['GET', 'POST'], '/subscriptions/callback/phonepe', [SubscriptionPackagePurchaseController::class, 'callbackPhonePe'])->name('subscriptions.callback.phonepe');
Route::match(['GET', 'POST'], '/subscriptions/callback/paytm', [SubscriptionPackagePurchaseController::class, 'callbackPaytm'])->name('subscriptions.callback.paytm');

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'verified', 'admin'])
    ->group(function (): void {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::resource('categories', AdminCategoryController::class);
        Route::resource('custom-fields', AdminCustomFieldController::class);
        Route::resource('subscription-packages', AdminSubscriptionPackageController::class)->except(['destroy']);

        Route::get('/listings', [ListingModerationController::class, 'index'])->name('listings.index');
        Route::get('/listings/export', [ListingModerationController::class, 'export'])->name('listings.export');
        Route::post('/listings/bulk-delete', [ListingModerationController::class, 'bulkDestroy'])->name('listings.bulk-destroy');
        Route::get('/listings/{listing}', [ListingModerationController::class, 'show'])->name('listings.show');
        Route::delete('/listings/{listing}', [ListingModerationController::class, 'destroy'])->name('listings.destroy');
        Route::post('/listings/{listing}/approve', [ListingModerationController::class, 'approve'])->name('listings.approve');
        Route::post('/listings/{listing}/reject', [ListingModerationController::class, 'reject'])->name('listings.reject');
        Route::post('/listings/{listing}/featured', [ListingModerationController::class, 'toggleFeatured'])->name('listings.featured');

        Route::post('/reports/{report}/resolve', [ListingModerationController::class, 'resolveReport'])->name('reports.resolve');
        Route::post('/reports/{report}/dismiss', [ListingModerationController::class, 'dismissReport'])->name('reports.dismiss');

        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [AdminUserController::class, 'create'])->name('users.create');
        Route::get('/users/export', [AdminUserController::class, 'export'])->name('users.export');
        Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
        Route::post('/users/{user}/toggle-admin', [AdminUserController::class, 'toggleAdmin'])->name('users.toggle-admin');
        Route::post('/users/{user}/toggle-block', [AdminUserController::class, 'toggleBlock'])->name('users.toggle-block');
        Route::post('/users/{user}/test-push', [AdminUserController::class, 'testPush'])->name('users.test-push');

        Route::get('/push-notifications/create', [AdminPushNotificationController::class, 'create'])->name('push-notifications.create');
        Route::post('/push-notifications', [AdminPushNotificationController::class, 'store'])->name('push-notifications.store');
        Route::get('/push-delivery-logs', [AdminPushDeliveryLogController::class, 'index'])->name('push-delivery-logs.index');

        Route::get('/sellers', [AdminSellerController::class, 'index'])->name('sellers.index');
        Route::get('/sellers/{seller}', [AdminSellerController::class, 'show'])->name('sellers.show');
        Route::post('/sellers/{seller}/toggle-block', [AdminSellerController::class, 'toggleBlock'])->name('sellers.toggle-block');
        Route::post('/sellers/{seller}/test-push', [AdminSellerController::class, 'testPush'])->name('sellers.test-push');
        Route::post('/sellers/{seller}/verification/approve', [AdminSellerController::class, 'approveVerification'])->name('sellers.verification.approve');
        Route::post('/sellers/{seller}/verification/reject', [AdminSellerController::class, 'rejectVerification'])->name('sellers.verification.reject');

        Route::get('/settings', [AdminSettingsController::class, 'index'])->name('settings.index');
        Route::post('/settings', [AdminSettingsController::class, 'update'])->name('settings.update');
        Route::post('/settings/ai-seo/run', [AdminSettingsController::class, 'runAiSeoAudit'])->name('settings.ai-seo.run');
        Route::post('/settings/license/verify', [AdminSettingsController::class, 'verifyLicense'])->name('settings.license.verify');
        Route::get('/legal-content', [AdminLegalContentController::class, 'index'])->name('legal-content.index');
        Route::post('/legal-content', [AdminLegalContentController::class, 'update'])->name('legal-content.update');

        Route::resource('free-post-limits', AdminFreePostLimitController::class)
            ->only(['index', 'store', 'update', 'destroy']);
    });

require __DIR__.'/auth.php';
require __DIR__.'/seller-verification.php';
