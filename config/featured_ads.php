<?php

return [
    'currency' => env('FEATURED_AD_CURRENCY', 'INR'),

    'daily_rate' => (float) env('FEATURED_AD_DAILY_RATE', 49),

    'allowed_days' => [3, 7, 15, 30],

    'notification_poll_seconds' => (int) env('NOTIFICATION_POLL_SECONDS', 20),
];
