<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeLoanAccrual extends Model
{
    protected $fillable = [
        'employee_loan_id',
        'accrual_date',
        'principal_balance',
        'interest_amount',
        'cumulative_interest',
        'total_balance',
        'accrual_period',
        'rate_used',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'accrual_date' => 'date',
            'principal_balance' => 'decimal:2',
            'interest_amount' => 'decimal:2',
            'cumulative_interest' => 'decimal:2',
            'total_balance' => 'decimal:2',
            'rate_used' => 'decimal:8',
        ];
    }

    public function employeeLoan(): BelongsTo
    {
        return $this->belongsTo(EmployeeLoan::class);
    }
}
