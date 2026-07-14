<?php

use App\Http\Controllers\SellerVerificationController;
use App\Http\Controllers\Admin\SellerVerificationAdminController;
use Illuminate\Support\Facades\Route;

// Seller verification routes (authenticated users)
Route::middleware('auth')->group(function () {
    // View and submit seller verification
    Route::get('/seller-verification/create', [SellerVerificationController::class, 'create'])
        ->name('seller-verification.create');
    Route::post('/seller-verification', [SellerVerificationController::class, 'store'])
        ->name('seller-verification.store');
    
    // View verification status
    Route::get('/seller-verification', [SellerVerificationController::class, 'index'])
        ->name('seller-verification.index');
    Route::get('/seller-verification/{verification}', [SellerVerificationController::class, 'show'])
        ->name('seller-verification.show');
    
    // Update rejected documents
    Route::post('/seller-verification/{verification}/documents/{document}', [SellerVerificationController::class, 'updateDocument'])
        ->name('seller-verification.update-document');
    
    // Delete verification request
    Route::delete('/seller-verification/{verification}', [SellerVerificationController::class, 'destroy'])
        ->name('seller-verification.destroy');
    
    // Export verification
    Route::get('/seller-verification/{verification}/export', [SellerVerificationController::class, 'export'])
        ->name('seller-verification.export');
});

// Admin routes
Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->group(function () {
    // Admin verification dashboard
    Route::get('/seller-verification', [SellerVerificationAdminController::class, 'index'])
        ->name('admin.seller-verification.index');
    Route::get('/seller-verification/statistics', [SellerVerificationAdminController::class, 'statistics'])
        ->name('admin.seller-verification.statistics');
    Route::get('/seller-verification/export', [SellerVerificationAdminController::class, 'export'])
        ->name('admin.seller-verification.export');
    
    // View verification details
    Route::get('/seller-verification/{verification}', [SellerVerificationAdminController::class, 'show'])
        ->name('admin.seller-verification.show');
    
    // Approve/Reject verification
    Route::post('/seller-verification/{verification}/approve', [SellerVerificationAdminController::class, 'approve'])
        ->name('admin.seller-verification.approve');
    Route::post('/seller-verification/{verification}/reject', [SellerVerificationAdminController::class, 'reject'])
        ->name('admin.seller-verification.reject');
    Route::post('/seller-verification/{verification}/revoke', [SellerVerificationAdminController::class, 'revoke'])
        ->name('admin.seller-verification.revoke');
    
    // Document verification
    Route::post('/seller-verification/{verification}/documents/{document}/verify', [SellerVerificationAdminController::class, 'verifyDocument'])
        ->name('admin.seller-verification.verify-document');
    Route::post('/seller-verification/{verification}/documents/{document}/reject', [SellerVerificationAdminController::class, 'rejectDocument'])
        ->name('admin.seller-verification.reject-document');
    
    // View and download documents
    Route::get('/seller-verification/{verification}/documents/{document}', [SellerVerificationAdminController::class, 'viewDocument'])
        ->name('admin.seller-verification.view-document');
    Route::get('/seller-verification/{verification}/documents/{document}/download', [SellerVerificationAdminController::class, 'downloadDocument'])
        ->name('admin.seller-verification.download-document');
});
