<?php

return [
    'enabled' => (bool) env('KAZANG_ENABLED', false),

    'api' => [
        'host' => env('KAZANG_API_HOST', 'api.kazang.net'),
        'path' => env('KAZANG_API_PATH', '/apimanager/api_v2/'),
        'username' => env('KAZANG_USERNAME', ''),
        'password' => env('KAZANG_PASSWORD', ''),
        'channel' => env('KAZANG_CHANNEL', 'Starlabs'),
        'timeout' => (int) env('KAZANG_TIMEOUT', 60),
    ],

    'product_ids' => [
        'airtel' => env('KAZANG_PRODUCT_AIRTEL', '5308'),
        'mtn' => env('KAZANG_PRODUCT_MTN', '5360'),
        'zamtel' => env('KAZANG_PRODUCT_ZAMTEL', '5305'),
    ],

    'amqp' => [
        'host' => env('KAZANG_AMQP_HOST', env('AMQP_HOST', 'localhost')),
        'port' => (int) env('KAZANG_AMQP_PORT', env('AMQP_PORT', 5672)),
        'username' => env('KAZANG_AMQP_USERNAME', env('AMQP_USERNAME', 'guest')),
        'password' => env('KAZANG_AMQP_PASSWORD', env('AMQP_PASSWORD', 'guest')),
        'queues' => [
            'airtel' => env('KAZANG_AMQP_QUEUE_AIRTEL', 'CollectionsAirtel'),
            'mtn' => env('KAZANG_AMQP_QUEUE_MTN', 'CollectionsMTN'),
            'zamtel' => env('KAZANG_AMQP_QUEUE_ZAMTEL', 'CollectionsZamtel'),
        ],
    ],

    'callback' => [
        'username' => env('KAZANG_CALLBACK_USERNAME', ''),
        'password' => env('KAZANG_CALLBACK_PASSWORD', ''),
        'success_status' => env('KAZANG_CALLBACK_SUCCESS_STATUS', '301'),
    ],

    'default_currency' => env('KAZANG_DEFAULT_CURRENCY', 'ZMW'),

    // kwacha = use balance from authClient as-is; minor_units = divide by 100
    'balance_mode' => env('KAZANG_BALANCE_MODE', 'kwacha'),
];
