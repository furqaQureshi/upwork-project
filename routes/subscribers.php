<?php

use App\Http\Controllers\Admin\SubscriberController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])->name('admin.')->prefix('admin')->group(function () {
    // Subscriber Management Routes
    Route::resource('subscribers', SubscriberController::class)->only(['index', 'show']);
    
    Route::post('subscribers/{subscriber}/renew', [SubscriberController::class, 'renew'])->name('subscribers.renew');
    Route::post('subscribers/{subscriber}/upgrade/{package}', [SubscriberController::class, 'upgrade'])->name('subscribers.upgrade');
    Route::delete('subscribers/{subscriber}/cancel', [SubscriberController::class, 'cancel'])->name('subscribers.cancel');
    Route::get('subscribers/export', [SubscriberController::class, 'export'])->name('subscribers.export');
});
