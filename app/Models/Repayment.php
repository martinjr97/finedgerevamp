<?php

namespace App\Models;

use App\Support\RepaymentRecoveryMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Repayment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_id',
        'channel_id',
        'repayment_number',
        'total_amount',
        'recovery_method',
        'phone_number',
        'external_reference',
        'external_transaction_id',
        'status',
        'status_message',
        'processed_at',
        'metadata',
        'received_via_type',
        'received_via_id',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'processed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function loanRepayments(): HasMany
    {
        return $this->hasMany(LoanRepayment::class);
    }

    public function loans()
    {
        return $this->belongsToMany(Loan::class, 'loan_repayments')
            ->withPivot(['amount', 'principal_amount', 'interest_amount', 'processing_fee_amount', 'outstanding_balance_before', 'outstanding_balance_after'])
            ->withTimestamps();
    }

    /**
     * Get the bank or wallet where repayment was received
     */
    public function recoveryMethodLabel(): string
    {
        return RepaymentRecoveryMethod::label($this->recovery_method);
    }

    public function receivedVia()
    {
        if ($this->received_via_type === 'bank') {
            return $this->belongsTo(Bank::class, 'received_via_id');
        }

        if ($this->received_via_type === 'wallet') {
            return $this->belongsTo(Wallet::class, 'received_via_id');
        }

        if ($this->received_via_type === 'cash') {
            return $this->belongsTo(CashRegister::class, 'received_via_id');
        }

        return null;
    }

    /**
     * Generate a unique repayment number
     */
    public static function generateRepaymentNumber(): string
    {
        $prefix = 'REP';
        $date = now()->format('Ymd');
        
        // Generate unique repayment number
        do {
            $random = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $repaymentNumber = $prefix . '-' . $date . '-' . $random;
        } while (self::where('repayment_number', $repaymentNumber)->exists());
        
        return $repaymentNumber;
    }
}
