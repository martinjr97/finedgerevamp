<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanRepayment extends Model
{
    use HasFactory;

    public const TRANSACTION_TYPE_PAYMENT = 'payment';

    public const TRANSACTION_TYPE_REFUND = 'refund';

    protected $fillable = [
        'repayment_id',
        'loan_id',
        'transaction_type',
        'refund_of_loan_repayment_id',
        'amount',
        'effective_date',
        'principal_amount',
        'interest_amount',
        'processing_fee_amount',
        'arrears_interest_amount',
        'outstanding_balance_before',
        'outstanding_balance_after',
        'notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'effective_date' => 'date',
            'amount' => 'decimal:2',
            'principal_amount' => 'decimal:2',
            'interest_amount' => 'decimal:2',
            'processing_fee_amount' => 'decimal:2',
            'arrears_interest_amount' => 'decimal:2',
            'outstanding_balance_before' => 'decimal:2',
            'outstanding_balance_after' => 'decimal:2',
        ];
    }

    public function repayment(): BelongsTo
    {
        return $this->belongsTo(Repayment::class);
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function refundOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'refund_of_loan_repayment_id');
    }

    public function refunds(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(self::class, 'refund_of_loan_repayment_id');
    }

    public function isRefund(): bool
    {
        return $this->transaction_type === self::TRANSACTION_TYPE_REFUND
            || (float) $this->amount < 0;
    }

    public function isPayment(): bool
    {
        return ! $this->isRefund();
    }

    /**
     * Portion of this payment applied to the repayment schedule (excludes arrears interest bucket).
     */
    public function scheduleAppliedAmount(): float
    {
        if ($this->isRefund()) {
            return 0.0;
        }

        $fromAllocation = self::scheduleAppliedAmountFromAllocation([
            'principal_amount' => (float) $this->principal_amount,
            'interest_amount' => (float) $this->interest_amount,
            'processing_fee_amount' => (float) $this->processing_fee_amount,
        ]);

        if ($fromAllocation > 0) {
            return $fromAllocation;
        }

        return round(max(0,
            (float) $this->amount
            - (float) ($this->arrears_interest_amount ?? 0)
        ), 2);
    }

    /**
     * Business/value date on which this repayment affects loan balances (Lusaka calendar day).
     */
    public function effectiveDate(): Carbon
    {
        $timezone = config('arrears.timezone', 'Africa/Lusaka');

        if ($this->effective_date) {
            return Carbon::parse($this->effective_date, $timezone)->startOfDay();
        }

        $metadata = $this->metadata ?? [];
        if (filled($metadata['effective_date'] ?? null)) {
            return Carbon::parse($metadata['effective_date'], $timezone)->startOfDay();
        }

        if (filled($metadata['settlement_date'] ?? null)) {
            return Carbon::parse($metadata['settlement_date'], $timezone)->startOfDay();
        }

        if ($this->relationLoaded('repayment') ? $this->repayment : $this->repayment()->first()) {
            $repayment = $this->repayment;
            $repaymentMeta = $repayment->metadata ?? [];
            foreach (['effective_date', 'settlement_date', 'value_date'] as $key) {
                if (filled($repaymentMeta[$key] ?? null)) {
                    return Carbon::parse($repaymentMeta[$key], $timezone)->startOfDay();
                }
            }

            if ($repayment->processed_at) {
                return $repayment->processed_at->copy()->timezone($timezone)->startOfDay();
            }
        }

        return $this->created_at->copy()->timezone($timezone)->startOfDay();
    }

    /**
     * Resolve the persisted effective date string for a newly posted loan repayment.
     */
    public static function resolveEffectiveDateString(?Repayment $repayment = null, ?array $loanRepaymentMetadata = null): string
    {
        $timezone = config('arrears.timezone', 'Africa/Lusaka');

        if (is_array($loanRepaymentMetadata)) {
            foreach (['effective_date', 'settlement_date'] as $key) {
                if (filled($loanRepaymentMetadata[$key] ?? null)) {
                    return Carbon::parse($loanRepaymentMetadata[$key], $timezone)->toDateString();
                }
            }
        }

        if ($repayment) {
            $metadata = $repayment->metadata ?? [];
            foreach (['effective_date', 'settlement_date', 'value_date'] as $key) {
                if (filled($metadata[$key] ?? null)) {
                    return Carbon::parse($metadata[$key], $timezone)->toDateString();
                }
            }

            if ($repayment->processed_at) {
                return $repayment->processed_at->copy()->timezone($timezone)->toDateString();
            }
        }

        return Carbon::now($timezone)->toDateString();
    }

    public function scheduleAmountReversedByRefunds(): float
    {
        return round((float) $this->refunds()
            ->get()
            ->sum(fn (self $refund) => (float) data_get($refund->metadata, 'schedule_amount_reversed', 0)), 2);
    }

    /**
     * @param  array{principal_amount?: float, interest_amount?: float, processing_fee_amount?: float, arrears_interest_amount?: float}  $allocation
     */
    public static function scheduleAppliedAmountFromAllocation(array $allocation): float
    {
        return round(max(0,
            (float) ($allocation['principal_amount'] ?? 0)
            + (float) ($allocation['interest_amount'] ?? 0)
            + (float) ($allocation['processing_fee_amount'] ?? 0)
        ), 2);
    }

    public function refundableAmountRemaining(): float
    {
        if (! $this->isPayment() || (float) $this->amount <= 0) {
            return 0.0;
        }

        $alreadyRefunded = abs((float) $this->refunds()->sum('amount'));

        return round(max(0, (float) $this->amount - $alreadyRefunded), 2);
    }

    /**
     * @return array{principal_amount: float, interest_amount: float, processing_fee_amount: float, arrears_interest_amount: float}
     */
    public function calculateRefundComponentSplit(float $refundAmount): array
    {
        $originalAmount = abs((float) $this->amount);
        if ($originalAmount <= 0) {
            return [
                'principal_amount' => 0.0,
                'interest_amount' => 0.0,
                'processing_fee_amount' => 0.0,
                'arrears_interest_amount' => 0.0,
            ];
        }

        $ratio = $refundAmount / $originalAmount;
        $principal = round((float) $this->principal_amount * $ratio, 2);
        $interest = round((float) $this->interest_amount * $ratio, 2);
        $fee = round((float) $this->processing_fee_amount * $ratio, 2);
        $arrears = round((float) ($this->arrears_interest_amount ?? 0) * $ratio, 2);
        $allocated = $principal + $interest + $fee + $arrears;

        if (abs($allocated - $refundAmount) > 0.01) {
            $principal = round($principal + ($refundAmount - $allocated), 2);
            $principal = max(0, $principal);
        }

        return [
            'principal_amount' => $principal,
            'interest_amount' => $interest,
            'processing_fee_amount' => $fee,
            'arrears_interest_amount' => $arrears,
        ];
    }
}
