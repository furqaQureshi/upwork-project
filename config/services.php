<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'razorpay' => [
        'key_id' => env('RAZORPAY_KEY_ID'),
        'key_secret' => env('RAZORPAY_KEY_SECRET'),
        'base_url' => env('RAZORPAY_BASE_URL', 'https://api.razorpay.com'),
    ],

    'phonepe' => [
        'merchant_id' => env('PHONEPE_MERCHANT_ID'),
        'salt_key' => env('PHONEPE_SALT_KEY'),
        'salt_index' => env('PHONEPE_SALT_INDEX', '1'),
        'base_url' => env('PHONEPE_BASE_URL', 'https://api-preprod.phonepe.com/apis/pg-sandbox'),
    ],

    'paytm' => [
        'mid' => env('PAYTM_MID'),
        'merchant_key' => env('PAYTM_MERCHANT_KEY'),
        'website' => env('PAYTM_WEBSITE', 'WEBSTAGING'),
        'base_url' => env('PAYTM_BASE_URL', 'https://securegw-stage.paytm.in'),
    ],

    'fcm' => [
        'api_key' => env('FCM_API_KEY'),
        'project_id' => env('FCM_PROJECT_ID'),
        'messaging_sender_id' => env('FCM_MESSAGING_SENDER_ID'),
        'app_id' => env('FCM_APP_ID'),
        'vapid_key' => env('FCM_VAPID_KEY'),
        'service_account_email' => env('FCM_SERVICE_ACCOUNT_EMAIL'),
        'service_account_private_key' => env('FCM_SERVICE_ACCOUNT_PRIVATE_KEY'),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
    ],

    // Legacy fallback key source during migration.
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
    ],

];
