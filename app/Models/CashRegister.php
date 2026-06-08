<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashRegister extends Model
{
    use HasFactory, SoftDeletes;

    public const DEFAULT_CODE = 'MAIN';

    protected $fillable = [
        'name',
        'code',
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

    public function repayments(): HasMany
    {
        return $this->hasMany(Repayment::class, 'received_via_id')
            ->where('received_via_type', 'cash');
    }

    public function updateBalance(float $amount, string $type = 'credit'): void
    {
        if ($type === 'credit') {
            $this->current_balance += $amount;
        } else {
            $this->current_balance -= $amount;
        }

        $this->save();
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->name;
    }
}
