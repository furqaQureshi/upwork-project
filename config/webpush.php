<?php

return [
    'vapid' => [
        'subject' => env('WEB_PUSH_VAPID_SUBJECT', 'mailto:admin@example.com'),
        'public_key' => env('WEB_PUSH_VAPID_PUBLIC_KEY'),
        'private_key' => env('WEB_PUSH_VAPID_PRIVATE_KEY'),
    ],

    'ttl' => (int) env('WEB_PUSH_TTL', 43200),
];
