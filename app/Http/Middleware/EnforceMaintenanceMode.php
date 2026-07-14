<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceMaintenanceMode
{
    public function handle(Request $request, Closure $next): Response
    {
        // Always exempt admin panel, offline page, health endpoint, and auth routes.
        if (
            $request->is('admin', 'admin/*') ||
            $request->is('offline', 'up') ||
            $request->is('login', 'logout', 'register', 'forgot-password', 'reset-password/*')
        ) {
            return $next($request);
        }

        try {
            $inMaintenance = \App\Models\AppSetting::get('maintenance_mode', false);
        } catch (\Throwable) {
            // Table not yet migrated — treat as not in maintenance.
            return $next($request);
        }

        if (! $inMaintenance) {
            return $next($request);
        }

        // Admins can always access the site.
        if ($request->user()?->is_admin) {
            return $next($request);
        }

        return response()->view('offline', [
            'siteName' => (string) \App\Models\AppSetting::get('site_name', config('app.name', 'Unsell')),
            'maintenanceTitle' => (string) \App\Models\AppSetting::get('maintenance_title', 'We\'ll be back soon'),
            'maintenanceMessage' => (string) \App\Models\AppSetting::get('maintenance_message', 'The marketplace is temporarily unavailable. Please try again shortly.'),
            'supportEmail' => (string) \App\Models\AppSetting::get('contact_email', ''),
            'supportPhone' => (string) \App\Models\AppSetting::get('support_phone', ''),
        ], 503);
    }
}
