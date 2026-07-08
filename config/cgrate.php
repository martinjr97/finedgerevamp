<?php

$uatDefaultForceDisbursementIssuerName = str_contains(
    (string) env('CGRATE_BASE_URL', 'https://test.543.cgrate.co.zm'),
    'test.543'
);

return [
    'enabled' => (bool) env('CGRATE_ENABLED', false),

    'base_url' => env('CGRATE_BASE_URL', 'https://test.543.cgrate.co.zm'),
    'username' => env('CGRATE_USERNAME', ''),
    'password' => env('CGRATE_PASSWORD', ''),

    'timeout' => (int) env('CGRATE_TIMEOUT', 30),
    'connect_timeout' => (int) env('CGRATE_CONNECT_TIMEOUT', 10),
    'verify_ssl' => (bool) env('CGRATE_VERIFY_SSL', true),

    'poll_interval_seconds' => (int) env('CGRATE_POLL_INTERVAL_SECONDS', 15),
    'max_query_attempts' => (int) env('CGRATE_MAX_QUERY_ATTEMPTS', 20),
    'payment_expiry_minutes' => (int) env('CGRATE_PAYMENT_EXPIRY_MINUTES', 5),

    'default_currency' => env('CGRATE_DEFAULT_CURRENCY', 'ZMW'),

    'amount_mode' => env('CGRATE_AMOUNT_MODE', 'kwacha_decimal'),
    'msisdn_format' => env('CGRATE_MSISDN_FORMAT', 'local'),

    'soap' => [
        'endpoint_path' => env('CGRATE_ENDPOINT_PATH', '/Konik/KonikWs'),
        'namespace' => env('CGRATE_SOAP_NAMESPACE', 'http://konik.cgrate.com'),
        'content_type' => env('CGRATE_CONTENT_TYPE', 'application/soap+xml; charset=utf-8'),
    ],

    'unknown_fail_after_attempts' => (int) env('CGRATE_UNKNOWN_FAIL_AFTER_ATTEMPTS', 20),

    'issuer_name_map' => [
        'MTN_MONEY' => 'MTN',
        'AIRTEL_MONEY' => 'Airtel',
        'ZAMTEL_MONEY' => 'Zamtel',
    ],

    'uat' => [
        'log_enabled' => (bool) env('CGRATE_UAT_LOG_ENABLED', true),
        'log_channel' => env('CGRATE_UAT_LOG_CHANNEL', 'cgrate_uat'),
        'force_disbursement_issuer_name' => filter_var(
            env('CGRATE_UAT_FORCE_DISBURSEMENT_ISSUER_NAME', $uatDefaultForceDisbursementIssuerName ? '1' : '0'),
            FILTER_VALIDATE_BOOLEAN
        ),
        'disbursement_issuer_name' => (string) env('CGRATE_UAT_DISBURSEMENT_ISSUER_NAME', '543'),
    ],

    'callback' => [
        'enabled' => (bool) env('CGRATE_CALLBACK_ENABLED', false),
        'token' => env('CGRATE_CALLBACK_TOKEN'),
        'allowed_ips' => array_values(array_filter(array_map(
            static fn ($ip) => trim((string) $ip),
            explode(',', (string) env('CGRATE_CALLBACK_ALLOWED_IPS', ''))
        ))),
    ],
];
