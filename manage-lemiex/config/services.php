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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | ShipEngine Configuration (Legacy - kept for reference)
    |--------------------------------------------------------------------------
    */
    'shipengine' => [
        'api_key' => env('SHIPENGINE_API_KEY'),
        'test_mode' => env('SHIPENGINE_TEST_MODE', false),
        'test_carrier_id' => env('SHIPENGINE_TEST_CARRIER_ID'),
        'carrier_id' => env('SHIPENGINE_CARRIER_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Shippo Configuration (Active)
    |--------------------------------------------------------------------------
    */
    'shippo' => [
        'api_key' => env('SHIPPO_KEY'),
        'test_mode' => env('SHIPPO_TEST_MODE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Telegram Bot Configuration
    |--------------------------------------------------------------------------
    */
    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_CHAT_ID'),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
        'frontend_url' => env('FRONTEND_URL', 'https://manage.lemiex.us'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Dropbox Configuration
    |--------------------------------------------------------------------------
    */
    'dropbox' => [
        'app_key' => env('DROPBOX_APP_KEY'),
        'app_secret' => env('DROPBOX_APP_SECRET'),
        'refresh_token' => env('DROPBOX_REFRESH_TOKEN'),
    ],

];
