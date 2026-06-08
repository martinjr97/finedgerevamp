<?php

namespace App\Models;

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
        'principal_amount',
        'interest_amount',
        'processing_fee_amount',
        'outstanding_balance_before',
        'outstanding_balance_after',
        'notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'amount' => 'decimal:2',
            'principal_amount' => 'decimal:2',
            'interest_amount' => 'decimal:2',
            'processing_fee_amount' => 'decimal:2',
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

    public function refundableAmountRemaining(): float
    {
        if (! $this->isPayment() || (float) $this->amount <= 0) {
            return 0.0;
        }

        $alreadyRefunded = abs((float) $this->refunds()->sum('amount'));

        return round(max(0, (float) $this->amount - $alreadyRefunded), 2);
    }

    /**
     * @return array{principal_amount: float, interest_amount: float, processing_fee_amount: float}
     */
    public function calculateRefundComponentSplit(float $refundAmount): array
    {
        $originalAmount = abs((float) $this->amount);
        if ($originalAmount <= 0) {
            return [
                'principal_amount' => 0.0,
                'interest_amount' => 0.0,
                'processing_fee_amount' => 0.0,
            ];
        }

        $ratio = $refundAmount / $originalAmount;
        $principal = round((float) $this->principal_amount * $ratio, 2);
        $interest = round((float) $this->interest_amount * $ratio, 2);
        $fee = round((float) $this->processing_fee_amount * $ratio, 2);
        $allocated = $principal + $interest + $fee;

        if (abs($allocated - $refundAmount) > 0.01) {
            $principal = round($principal + ($refundAmount - $allocated), 2);
            $principal = max(0, $principal);
        }

        return [
            'principal_amount' => $principal,
            'interest_amount' => $interest,
            'processing_fee_amount' => $fee,
        ];
    }
}
