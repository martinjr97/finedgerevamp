<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Legacy parallel-run polling (temporary — disable after full cutover)
    |--------------------------------------------------------------------------
    */
    'enabled' => (bool) env('LEGACY_PARALLEL_RUN_ENABLED', false),

    'loan_polling_enabled' => (bool) env('LEGACY_LOAN_POLLING_ENABLED', false),

    'repayment_polling_enabled' => (bool) env('LEGACY_REPAYMENT_POLLING_ENABLED', false),

    'expense_polling_enabled' => (bool) env('LEGACY_EXPENSE_POLLING_ENABLED', false),

    'customer_polling_enabled' => (bool) env('LEGACY_CUSTOMER_POLLING_ENABLED', false),

    /** Apply treasury balance updates when importing parallel-run loans/repayments/expenses. */
    'finance_on_import_enabled' => (bool) env('LEGACY_PARALLEL_RUN_FINANCE_ENABLED', false),

    /** Minimum legacy loan id at parallel-run start (skip bulk portfolio). */
    'loan_watermark_id' => env('LEGACY_LOAN_POLL_WATERMARK') !== null
        ? (int) env('LEGACY_LOAN_POLL_WATERMARK')
        : null,

    /** Poll interval label for docs — actual schedule lives in bootstrap/app.php */
    'poll_interval_minutes' => (int) env('LEGACY_POLL_INTERVAL_MINUTES', 30),

    /** Max rows processed per poll cycle (safety cap). */
    'poll_batch_limit' => (int) env('LEGACY_POLL_BATCH_LIMIT', 200),

    /** Max repayment inbox rows promoted per sync cycle. */
    'repayment_sync_batch_limit' => (int) env('LEGACY_REPAYMENT_SYNC_BATCH_LIMIT', 100),

    /** Max expense inbox rows promoted per sync cycle. */
    'expense_sync_batch_limit' => (int) env('LEGACY_EXPENSE_SYNC_BATCH_LIMIT', 100),

    /** Max customer inbox rows promoted per sync cycle. */
    'customer_sync_batch_limit' => (int) env('LEGACY_CUSTOMER_SYNC_BATCH_LIMIT', 50),

    /** Only poll legacy expenses on/after this date (parallel-run window). */
    'financial_from_date' => env('LEGACY_PARALLEL_RUN_FINANCIAL_FROM_DATE'),

    /** Fallback wallet when legacy LOAN-DISB expense row is missing. */
    'default_disbursement_wallet_code' => env('LEGACY_PARALLEL_RUN_DEFAULT_WALLET_CODE', 'KAZANG'),

    'default_disbursement_wallet_id' => env('LEGACY_PARALLEL_RUN_DEFAULT_WALLET_ID') !== null
        ? (int) env('LEGACY_PARALLEL_RUN_DEFAULT_WALLET_ID')
        : null,
];
