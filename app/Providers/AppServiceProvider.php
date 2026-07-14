<?php

namespace App\Providers;

use App\Models\SellerVerification;
use App\Models\User;
use App\Policies\SellerVerificationPolicy;
use App\View\Composers\AdminSettingsComposer;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('admin', fn (User $user): bool => (bool) $user->is_admin);

        Gate::policy(SellerVerification::class, SellerVerificationPolicy::class);

        View::composer('admin.settings.index', AdminSettingsComposer::class);
    }
}
