<?php

return [

    'currency' => env('PAYMENT_CURRENCY', 'NGN'),

    'paystack' => [
        'enabled' => (bool) env('PAYSTACK_ENABLED', false),
        'mode' => env('PAYSTACK_MODE', 'test'),
        'public_key' => env('PAYSTACK_PUBLIC_KEY'),
        'secret_key' => env('PAYSTACK_SECRET_KEY'),
        'webhook_secret' => env('PAYSTACK_WEBHOOK_SECRET'),
        'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
    ],

    /*
    | Fallback when admin_platform_settings is empty (before first save).
    | Live rate is always financial.platform_fee_percent in Platform Settings.
    */
    'platform_fee_percent' => (float) env('PAYMENT_PLATFORM_FEE_PERCENT', 12),

    'withdrawal' => [
        'min_amount_minor' => (int) env('PAYMENT_WITHDRAWAL_MIN_MINOR', 100000),
        'fee_minor' => (int) env('PAYMENT_WITHDRAWAL_FEE_MINOR', 5000),
    ],

];
