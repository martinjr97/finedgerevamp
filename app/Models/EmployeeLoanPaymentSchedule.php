<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeLoanPaymentSchedule extends Model
{
    protected $fillable = [
        'employee_loan_id',
        'period_number',
        'due_date',
        'principal_component',
        'interest_component',
        'fee_component',
        'expected_amount',
        'amount_paid',
        'remaining_amount',
        'status',
        'days_overdue',
        'paid_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'principal_component' => 'decimal:2',
            'interest_component' => 'decimal:2',
            'fee_component' => 'decimal:2',
            'expected_amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
            'days_overdue' => 'integer',
            'paid_at' => 'date',
            'metadata' => 'array',
        ];
    }

    public function employeeLoan(): BelongsTo
    {
        return $this->belongsTo(EmployeeLoan::class);
    }

    public function refreshStatus(): void
    {
        if ((float) $this->remaining_amount <= 0.009) {
            $this->status = 'paid';
            $this->paid_at = $this->paid_at ?? now()->toDateString();
            $this->days_overdue = 0;

            return;
        }

        if ((float) $this->amount_paid > 0) {
            $this->status = 'partial';
            $this->paid_at = null;

            return;
        }

        if ($this->due_date->isPast()) {
            $this->status = 'overdue';

            return;
        }

        $this->status = 'upcoming';
        $this->days_overdue = 0;
        $this->paid_at = null;
    }
}
