<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Stripe Configuration
    |--------------------------------------------------------------------------
    |
    | Stripe payment gateway configuration.
    | Values can be overridden from database settings (admin panel).
    | .env file values serve as fallback/defaults.
    |
    */

    'public_key' => env('STRIPE_PUBLIC_KEY', ''),
    'secret_key' => env('STRIPE_SECRET_KEY', ''),
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET', ''),
];
