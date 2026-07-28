<?php

return [
    /*
    | SMS_ENABLED=false              → do not send and do not log SMS
    | SMS_ENABLED=true + provider=log → write SMS to the application log
    | SMS_ENABLED=true + provider=zamtel → send via Zamtel
    */
    'enabled' => (bool) env('SMS_ENABLED', false),

    'provider' => env('SMS_PROVIDER', 'log'),

    // Display/from label only — not used as the Zamtel API senderId.
    'from' => env('SMS_FROM', env('ZAMTEL_SMS_SENDER_ID', '')),

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
        // Exact ZAMTEL_SMS_SENDER_ID from .env (case-sensitive). No FineEdge / SMS_FROM fallback.
        'sender_id' => (string) env('ZAMTEL_SMS_SENDER_ID', ''),
        'timeout' => (int) env('ZAMTEL_SMS_TIMEOUT', 30),
        'connect_timeout' => (int) env('ZAMTEL_SMS_CONNECT_TIMEOUT', 10),
        'verify_ssl' => (bool) env('ZAMTEL_SMS_VERIFY_SSL', true),
    ],
];
