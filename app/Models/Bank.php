<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bank extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'account_number',
        'account_name',
        'bank_name',
        'branch',
        'currency',
        'opening_balance',
        'current_balance',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'current_balance' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get all financial transactions where this bank is the source
     */
    public function sourceTransactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class, 'source_id')
            ->where('source_type', 'bank');
    }

    /**
     * Get all financial transactions where this bank is the destination
     */
    public function destinationTransactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class, 'destination_id')
            ->where('destination_type', 'bank');
    }

    /**
     * Get all financial transactions (both source and destination)
     */
    public function transactions()
    {
        return FinancialTransaction::where(function($query) {
            $query->where(function($q) {
                $q->where('source_type', 'bank')->where('source_id', $this->id);
            })->orWhere(function($q) {
                $q->where('destination_type', 'bank')->where('destination_id', $this->id);
            });
        });
    }

    /**
     * Get all repayments received via this bank
     */
    public function repayments(): HasMany
    {
        return $this->hasMany(Repayment::class, 'received_via_id')
            ->where('received_via_type', 'bank');
    }

    /**
     * Get all loans disbursed via this bank
     */
    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class, 'disbursed_via_id')
            ->where('disbursed_via_type', 'bank');
    }

    /**
     * Update bank balance
     */
    public function updateBalance(float $amount, string $type = 'credit'): void
    {
        if ($type === 'credit') {
            $this->current_balance += $amount;
        } else {
            $this->current_balance -= $amount;
        }
        $this->save();
    }

    /**
     * Get display name
     */
    public function getDisplayNameAttribute(): string
    {
        return "{$this->name} - {$this->account_number} ({$this->bank_name})";
    }
}
