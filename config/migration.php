<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Financial migration cutoff
    |--------------------------------------------------------------------------
    |
    | Legacy expenses and manual incomes are imported on or after this date
    | when running migration:financial-data without an explicit --from-date.
    |
    */
    'financial_from_date' => env('MIGRATION_FINANCIAL_FROM_DATE'),
];
