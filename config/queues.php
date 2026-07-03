<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Queue Connections
    |--------------------------------------------------------------------------
    |
    | Named queue connection identifiers used when dispatching jobs.
    |
    */

    'connections' => [
        'default' => env('QUEUE_CONNECTION', 'redis'),
        'financial' => env('FINANCIAL_QUEUE_CONNECTION', 'redis-financial'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Names
    |--------------------------------------------------------------------------
    */

    'names' => [
        'payments_high' => env('PAYMENTS_QUEUE_HIGH', 'payments-high'),
        'payments' => env('PAYMENTS_QUEUE', 'payments'),
        'disbursements_high' => env('DISBURSEMENTS_QUEUE_HIGH', 'disbursements-high'),
        'disbursements' => env('DISBURSEMENTS_QUEUE', 'disbursements'),
        'notifications' => env('NOTIFICATIONS_QUEUE', 'notifications'),
        'reports' => env('REPORTS_QUEUE', 'reports'),
        'maintenance' => env('MAINTENANCE_QUEUE', 'maintenance'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry Counts
    |--------------------------------------------------------------------------
    */

    'retries' => [
        'financial_initiation' => 1,
        'financial_status' => 5,
        'notifications' => 3,
        'reports' => 2,
        'maintenance' => 2,
    ],

];
