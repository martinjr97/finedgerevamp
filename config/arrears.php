<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Arrears engine effective date
    |--------------------------------------------------------------------------
    |
    | Daily arrears accrual only runs for accrual dates on or after this date.
    | Prevents retroactive surprise charges when the engine is first deployed.
    | Historical reconstruction requires an explicit backfill command with
    | --allow-historical-backfill.
    |
    | In production, loans:accrue-arrears refuses to run when this value is missing
    | (unless --allow-historical-backfill is passed deliberately).
    |
    */
    'engine_effective_date' => env('ARREARS_ENGINE_EFFECTIVE_DATE'),

    /*
    |--------------------------------------------------------------------------
    | NPL calendar days after final contractual due date
    |--------------------------------------------------------------------------
    */
    'npl_days_after_final_due' => (int) env('ARREARS_NPL_DAYS_AFTER_FINAL_DUE', 90),

    'timezone' => 'Africa/Lusaka',

];
