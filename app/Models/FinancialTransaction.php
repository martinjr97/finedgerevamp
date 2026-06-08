<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinancialTransaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'transaction_number',
        'transaction_date',
        'type',
        'category',
        'description',
        'amount',
        'source_type',
        'source_id',
        'destination_type',
        'destination_id',
        'reference_number',
        'notes',
        'metadata',
        'created_by',
        'approval_status',
        'approved_by',
        'approved_at',
        'approval_notes',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'amount' => 'decimal:2',
            'metadata' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * Get the source bank (if source_type is bank)
     */
    public function sourceBank(): BelongsTo
    {
        return $this->belongsTo(Bank::class, 'source_id');
    }

    /**
     * Get the source wallet (if source_type is wallet)
     */
    public function sourceWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'source_id');
    }

    /**
     * Get the destination bank (if destination_type is bank)
     */
    public function destinationBank(): BelongsTo
    {
        return $this->belongsTo(Bank::class, 'destination_id');
    }

    /**
     * Get the destination wallet (if destination_type is wallet)
     */
    public function destinationWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'destination_id');
    }

    /**
     * Get the source (bank or wallet) - accessor
     */
    public function getSourceAttribute()
    {
        if ($this->source_type === 'bank') {
            return $this->sourceBank;
        } elseif ($this->source_type === 'wallet') {
            return $this->sourceWallet;
        }
        return null;
    }

    /**
     * Get the destination (bank or wallet) - accessor
     */
    public function getDestinationAttribute()
    {
        if ($this->destination_type === 'bank') {
            return $this->destinationBank;
        } elseif ($this->destination_type === 'wallet') {
            return $this->destinationWallet;
        }
        return null;
    }

    /**
     * Get the admin who created this transaction
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    /**
     * Get the admin who approved this transaction
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by');
    }

    /**
     * Generate a unique transaction number
     */
    public static function generateTransactionNumber(string $type): string
    {
        $prefix = match($type) {
            'income' => 'INC',
            'expense' => 'EXP',
            'transfer' => 'TRF',
            default => 'TXN',
        };
        
        $date = now()->format('Ymd');
        
        do {
            $random = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $transactionNumber = $prefix . '-' . $date . '-' . $random;
        } while (self::where('transaction_number', $transactionNumber)->exists());
        
        return $transactionNumber;
    }

    /**
     * Update balances after transaction creation
     */
    public function updateBalances(): void
    {
        if ($this->type === 'income' && $this->destination_type && $this->destination_id) {
            $destination = $this->destination_type === 'bank' 
                ? Bank::find($this->destination_id)
                : Wallet::find($this->destination_id);
            
            if ($destination) {
                $destination->updateBalance($this->amount, 'credit');
            }
        } elseif ($this->type === 'expense' && $this->source_type && $this->source_id) {
            $source = $this->source_type === 'bank'
                ? Bank::find($this->source_id)
                : Wallet::find($this->source_id);
            
            if ($source) {
                $source->updateBalance($this->amount, 'debit');
            }
        } elseif ($this->type === 'transfer' && $this->source_type && $this->source_id && $this->destination_type && $this->destination_id) {
            $source = $this->source_type === 'bank'
                ? Bank::find($this->source_id)
                : Wallet::find($this->source_id);
            
            $destination = $this->destination_type === 'bank'
                ? Bank::find($this->destination_id)
                : Wallet::find($this->destination_id);
            
            if ($source) {
                $source->updateBalance($this->amount, 'debit');
            }
            
            if ($destination) {
                $destination->updateBalance($this->amount, 'credit');
            }
        }
    }
}
