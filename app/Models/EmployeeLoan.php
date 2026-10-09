<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeLoan extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING_APPROVAL = 'pending_approval';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SETTLED = 'settled';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_WRITTEN_OFF = 'written_off';

    protected $fillable = [
        'loan_number',
        'employee_id',
        'loan_rate_id',
        'status',
        'disbursement_status',
        'principal_amount',
        'processing_fee',
        'processing_fee_percentage',
        'daily_rate',
        'weekly_rate',
        'quoted_term_rate',
        'arrear_rate',
        'interest_behavior',
        'accrual_type',
        'accrual_period',
        'tenure_months',
        'repayment_frequency',
        'interest_accrued',
        'total_amount',
        'amount_paid',
        'outstanding_balance',
        'application_date',
        'approval_date',
        'loan_start_date',
        'first_payment_date',
        'loan_end_date',
        'last_payment_date',
        'last_accrual_date',
        'arrears_last_accrual_date',
        'approved_at',
        'disbursed_at',
        'created_by',
        'approved_by',
        'disbursed_by',
        'disbursement_reference',
        'disbursement_notes',
        'payment_gateway_attempt_id',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
        'submitted_at',
        'disbursed_via_type',
        'disbursed_via_id',
        'disbursement_destination_snapshot',
        'settlement_amount',
        'loan_settled_date',
        'settlement_date',
        'performance_status',
        'npl_at',
        'purpose',
        'notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'principal_amount' => 'decimal:2',
            'processing_fee' => 'decimal:2',
            'processing_fee_percentage' => 'decimal:2',
            'daily_rate' => 'decimal:8',
            'weekly_rate' => 'decimal:8',
            'quoted_term_rate' => 'decimal:4',
            'arrear_rate' => 'decimal:5',
            'interest_accrued' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'outstanding_balance' => 'decimal:2',
            'application_date' => 'date',
            'approval_date' => 'date',
            'loan_start_date' => 'date',
            'first_payment_date' => 'date',
            'loan_end_date' => 'date',
            'last_payment_date' => 'date',
            'last_accrual_date' => 'date',
            'arrears_last_accrual_date' => 'date',
            'approved_at' => 'datetime',
            'disbursed_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'npl_at' => 'datetime',
            'submitted_at' => 'datetime',
            'settlement_amount' => 'decimal:2',
            'loan_settled_date' => 'date',
            'settlement_date' => 'date',
            'disbursement_destination_snapshot' => 'array',
            'metadata' => 'array',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function payoutDestinationSummary(bool $maskAccount = true): ?string
    {
        $snapshot = $this->disbursement_destination_snapshot;
        if (! is_array($snapshot) || $snapshot === []) {
            return null;
        }

        $bank = $snapshot['bank_name'] ?? '—';
        $name = $snapshot['account_name'] ?? '—';
        $number = $maskAccount
            ? ($snapshot['account_number_masked'] ?? '—')
            : ($snapshot['account_number'] ?? $snapshot['account_number_masked'] ?? '—');

        return "{$bank} · {$name} · {$number}";
    }

    public function loanRate(): BelongsTo
    {
        return $this->belongsTo(LoanRate::class);
    }

    public function paymentSchedules(): HasMany
    {
        return $this->hasMany(EmployeeLoanPaymentSchedule::class)->orderBy('period_number');
    }

    public function repayments(): HasMany
    {
        return $this->hasMany(EmployeeLoanRepayment::class);
    }

    public function accruals(): HasMany
    {
        return $this->hasMany(EmployeeLoanAccrual::class);
    }

    public function arrearsAccruals(): HasMany
    {
        return $this->hasMany(EmployeeLoanArrearsAccrual::class);
    }

    public function rejector(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'rejected_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by');
    }

    public function disburser(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'disbursed_by');
    }

    /**
     * Live employee loans in repayment (mirrors customer Loan::scopeActivePortfolio).
     */
    public function scopeActivePortfolio(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_ACTIVE)
            ->where('disbursement_status', 'completed');
    }

    public function scopePendingApproval(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING_APPROVAL);
    }

    public function scopeSettled(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SETTLED);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->disbursement_status === 'completed';
    }

    /**
     * @return array<string, mixed>
     */
    public function disbursementSnapshot(): array
    {
        $snapshot = $this->disbursement_destination_snapshot;

        return is_array($snapshot) ? $snapshot : [];
    }

    public function hasBankDestination(): bool
    {
        $snapshot = $this->disbursementSnapshot();
        $method = $snapshot['payout_method'] ?? $snapshot['account_type'] ?? null;

        return $method === 'bank';
    }

    public function hasMobileWalletDestination(): bool
    {
        $snapshot = $this->disbursementSnapshot();

        return ($snapshot['payout_method'] ?? $snapshot['account_type'] ?? null) === 'mobile_money';
    }

    public function canDisburseViaGateway(): bool
    {
        return $this->hasBankDestination() || $this->hasMobileWalletDestination();
    }

    public function applyDisbursementCompleted(?\Carbon\Carbon $disbursedAt = null): void
    {
        $this->update([
            'status' => self::STATUS_ACTIVE,
            'disbursement_status' => 'completed',
            'disbursed_at' => $disbursedAt ?? now(),
            'loan_start_date' => $this->loan_start_date ?? now()->toDateString(),
        ]);
    }
}
