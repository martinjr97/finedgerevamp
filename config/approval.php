<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Approval Settings
    |--------------------------------------------------------------------------
    |
    | This file controls which actions require approval before being saved.
    | Set to true to require approval, false to save directly.
    |
    */

    'admins' => [
        'create' => env('APPROVE_ADMIN_CREATE', false),
    ],

    'companies' => [
        'create' => env('APPROVE_COMPANY_CREATE', false),
    ],

    'customers' => [
        'create' => env('APPROVE_CUSTOMER_CREATE', true),
    ],

    'loans' => [
        'create' => env('APPROVE_LOAN_CREATE', true),
    ],

    'transfers' => [
        'create' => env('APPROVE_TRANSFER_CREATE', false),
    ],

    'financial_transactions' => [
        'income' => env('APPROVE_INCOME_CREATE', false),
        'expense' => env('APPROVE_EXPENSE_CREATE', false),
    ],
];

