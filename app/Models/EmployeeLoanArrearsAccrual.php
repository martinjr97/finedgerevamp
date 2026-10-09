<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeLoanArrearsAccrual extends Model
{
    protected $fillable = [
        'employee_loan_id',
        'employee_loan_payment_schedule_id',
        'accrual_date',
        'base_amount',
        'interest_amount',
        'rate_used',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'accrual_date' => 'date',
            'base_amount' => 'decimal:2',
            'interest_amount' => 'decimal:2',
            'rate_used' => 'decimal:5',
            'metadata' => 'array',
        ];
    }

    public function employeeLoan(): BelongsTo
    {
        return $this->belongsTo(EmployeeLoan::class);
    }

    public function paymentSchedule(): BelongsTo
    {
        return $this->belongsTo(EmployeeLoanPaymentSchedule::class, 'employee_loan_payment_schedule_id');
    }
}
