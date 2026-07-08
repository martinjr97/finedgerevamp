<?php

return [
    'enabled' => (bool) env('SMS_ENABLED', false),

    'provider' => env('SMS_PROVIDER', 'log'),

    'from' => env('SMS_FROM', env('ZAMTEL_SMS_SENDER_ID', 'FineEdge')),

    'max_length' => (int) env('SMS_MAX_LENGTH', 159),

    'queues' => [
        'sms' => env('NOTIFICATIONS_SMS_QUEUE', env('NOTIFICATIONS_QUEUE', 'notifications')),
        'listener' => env('NOTIFICATIONS_LISTENER_QUEUE', 'default'),
    ],

    'alerts' => [
        'low_balance_threshold' => (int) env('SMS_LOW_BALANCE_THRESHOLD', 100),
        'critical_balance_threshold' => (int) env('SMS_CRITICAL_BALANCE_THRESHOLD', 10),
        'emails' => array_values(array_filter(array_map(
            static fn (string $email): string => trim($email),
            explode(',', (string) env('SMS_ALERT_EMAILS', '')),
        ), static fn (string $email): bool => $email !== '')),
    ],

    'zamtel' => [
        'base_url' => env('ZAMTEL_SMS_BASE_URL', 'https://bulksms.zamtel.co.zm'),
        'api_key' => env('ZAMTEL_SMS_API_KEY', ''),
        'sender_id' => env('ZAMTEL_SMS_SENDER_ID', env('SMS_FROM', 'FineEdge')),
        'timeout' => (int) env('ZAMTEL_SMS_TIMEOUT', 30),
        'connect_timeout' => (int) env('ZAMTEL_SMS_CONNECT_TIMEOUT', 10),
        'verify_ssl' => (bool) env('ZAMTEL_SMS_VERIFY_SSL', true),
    ],
];
