<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payment gateway (placeholder until provider is chosen)
    |--------------------------------------------------------------------------
    */
    'driver' => env('ESCROW_PAYMENT_DRIVER', env('PAYSTACK_ENABLED', false) ? 'paystack' : 'stub'),

    'webhook_secret' => env('ESCROW_WEBHOOK_SECRET'),

    'currency_default' => 'NGN',
];
