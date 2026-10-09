<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeLoanRepayment extends Model
{
    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_PAYROLL = 'payroll';

    public const SOURCE_BANK = 'bank';

    public const SOURCE_CASH = 'cash';

    public const SOURCE_ADJUSTMENT = 'adjustment';

    protected $fillable = [
        'employee_loan_id',
        'amount',
        'principal_amount',
        'interest_amount',
        'processing_fee_amount',
        'arrears_interest_amount',
        'effective_date',
        'processed_at',
        'payment_method',
        'repayment_source',
        'reference',
        'processed_by',
        'status',
        'notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'principal_amount' => 'decimal:2',
            'interest_amount' => 'decimal:2',
            'processing_fee_amount' => 'decimal:2',
            'arrears_interest_amount' => 'decimal:2',
            'effective_date' => 'date',
            'processed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function employeeLoan(): BelongsTo
    {
        return $this->belongsTo(EmployeeLoan::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'processed_by');
    }
}
