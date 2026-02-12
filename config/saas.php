<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SaaS Configuration
    |--------------------------------------------------------------------------
    */

    'plans' => [
        'monthly' => [
            'name' => 'Monthly',
            'price' => 999,
            'duration_days' => 30,
        ],
        'yearly' => [
            'name' => 'Yearly',
            'price' => 9999,
            'duration_days' => 365,
        ],
    ],

    'default_commission_rate' => 5.00,

    'payment_gateways' => [
        'bkash' => [
            'enabled' => env('BKASH_ENABLED', false),
            'app_key' => env('BKASH_APP_KEY'),
            'app_secret' => env('BKASH_APP_SECRET'),
            'username' => env('BKASH_USERNAME'),
            'password' => env('BKASH_PASSWORD'),
            'sandbox' => env('BKASH_SANDBOX', true),
        ],
        'sslcommerz' => [
            'enabled' => env('SSLCOMMERZ_ENABLED', false),
            'store_id' => env('SSLCOMMERZ_STORE_ID'),
            'store_password' => env('SSLCOMMERZ_STORE_PASSWORD'),
            'sandbox' => env('SSLCOMMERZ_SANDBOX', true),
        ],
    ],

    'wifi_enforcement' => env('WIFI_ENFORCEMENT_ENABLED', true),

    'order' => [
        'auto_cancel_minutes' => 30,
    ],
];
