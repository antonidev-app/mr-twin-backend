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

    'accurate' => [
        'client_id' => env('ACCURATE_CLIENT_ID'),
        'client_secret' => env('ACCURATE_CLIENT_SECRET'),
        'redirect_uri' => env('ACCURATE_REDIRECT_URI'),
        'account_base_url' => env('ACCURATE_ACCOUNT_BASE_URL', 'https://account.accurate.id'),
        'scopes' => env('ACCURATE_SCOPES', 'item_view item_category_view'),
        'token_expiry_buffer_seconds' => (int) env('ACCURATE_TOKEN_EXPIRY_BUFFER', 300),
        'session_ttl_minutes' => (int) env('ACCURATE_SESSION_TTL_MINUTES', 25),
    ],

];
