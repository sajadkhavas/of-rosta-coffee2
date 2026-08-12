<?php

return [
    'sms' => [
        'driver' => env('SMS_DRIVER', 'disabled'),
        'enabled' => filter_var(env('ROSTA_OTP_ENABLED', false), FILTER_VALIDATE_BOOL),
    ],
    'payment' => [
        'driver' => env('PAYMENT_DRIVER'),
        'merchant_id' => env('PAYMENT_MERCHANT_ID'),
        'callback_url' => env('PAYMENT_CALLBACK_URL'),
        'enabled' => (bool) env('ROSTA_PAYMENT_ENABLED', false),
    ],
];
