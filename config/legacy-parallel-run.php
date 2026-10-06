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
];
