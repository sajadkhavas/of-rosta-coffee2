<?php

return [
    'sms' => [
        'driver' => env('SMS_DRIVER'),
        'api_key' => env('SMS_API_KEY'),
        'enabled' => (bool) env('ROSTA_SMS_ENABLED', false),
    ],
    'payment' => [
        'driver' => env('PAYMENT_DRIVER'),
        'merchant_id' => env('PAYMENT_MERCHANT_ID'),
        'callback_url' => env('PAYMENT_CALLBACK_URL'),
        'enabled' => (bool) env('ROSTA_PAYMENT_ENABLED', false),
    ],
];
