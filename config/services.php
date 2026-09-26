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

    /*
    |--------------------------------------------------------------------------
    | PayMongo
    |--------------------------------------------------------------------------
    |
    | Server-only credentials. These are never rendered, logged, or stored on a
    | payment record. Use the test-mode secret key in local development.
    |
    */

    'paymongo' => [
        'secret_key' => env('PAYMONGO_SECRET_KEY'),
        'webhook_secret' => env('PAYMONGO_WEBHOOK_SECRET'),
        'enabled' => (bool) env('PAYMONGO_ENABLED', false),
        // false for test mode, true for live. A webhook event whose livemode
        // does not match is acknowledged and ignored, so a test server can
        // never act on a real payment.
        'expected_livemode' => (bool) env('PAYMONGO_LIVEMODE', false),
        // The payment methods offered on the hosted checkout page. QR Ph is
        // the common Philippine option.
        'payment_method_types' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('PAYMONGO_PAYMENT_METHODS', 'qrph,card'))
        ))),
    ],

];
