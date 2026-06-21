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

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', env('APP_URL').'/auth/google/callback'),
    ],

    /*
    | OpenStreetMap Nominatim (free geocoding). Include contact in User-Agent per policy.
    */
    'nominatim' => [
        'user_agent' => env('NOMINATIM_USER_AGENT', env('APP_NAME', 'Laravel').' ('.env('APP_URL', 'http://localhost').')'),
    ],

    'cac' => [
        'verify_url' => env('CAC_VERIFY_URL'),
        'token' => env('CAC_VERIFY_TOKEN'),
    ],

    'tenor' => [
        'api_key' => env('TENOR_API_KEY'),
    ],

    'giphy' => [
        'api_key' => env('GIPHY_API_KEY'),
    ],

    /*
    | Anthropic (Claude) — in-house AI helper used for quest description suggestions
    | and other assistive features. Set ANTHROPIC_API_KEY to enable; pick your model
    | with ANTHROPIC_MODEL (e.g. claude-sonnet-4-20250514).
    */
    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-20250514'),
        'max_tokens' => (int) env('ANTHROPIC_MAX_TOKENS', 1500),
        'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
        'version' => env('ANTHROPIC_VERSION', '2023-06-01'),
    ],

];
