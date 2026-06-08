<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Global Audit Logging Toggle
    |--------------------------------------------------------------------------
    */
    'enabled' => env('AUDIT_LOG_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Model Classes Excluded From Automatic Audit Logging
    |--------------------------------------------------------------------------
    */
    'excluded_models' => [
        App\Models\AuditLog::class,
        App\Models\AdminLoginAudit::class,
        App\Models\CustomerLoginAudit::class,
        App\Models\AdminAccountAudit::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Fields Excluded From Old/New Value Snapshots
    |--------------------------------------------------------------------------
    */
    'excluded_fields' => [
        'password',
        'remember_token',
        'updated_at',
        'created_at',
    ],
];
