<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanArrearsAccrual extends Model
{
    protected $fillable = [
        'loan_id',
        'loan_payment_schedule_id',
        'accrual_date',
        'arrear_rate',
        'opening_overdue_amount',
        'arrears_charge',
        'days_overdue_on_date',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'accrual_date' => 'date',
            'arrear_rate' => 'decimal:5',
            'opening_overdue_amount' => 'decimal:2',
            'arrears_charge' => 'decimal:2',
            'days_overdue_on_date' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function paymentSchedule(): BelongsTo
    {
        return $this->belongsTo(LoanPaymentSchedule::class, 'loan_payment_schedule_id');
    }
}
