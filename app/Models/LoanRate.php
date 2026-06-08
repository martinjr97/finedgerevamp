<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoanRate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'loan_rate_type_id',
        'tenure_months',
        'processing_fee_percentage',
        'term_interest_percentage',
        'min_principal',
        'max_principal',
        'daily_rate',
        'weekly_rate',
        'derived_daily_rate',
        'arrear_rate',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'processing_fee_percentage' => 'decimal:2',
            'term_interest_percentage' => 'decimal:4',
            'min_principal' => 'decimal:2',
            'max_principal' => 'decimal:2',
            'daily_rate' => 'decimal:5',
            'weekly_rate' => 'decimal:5',
            'derived_daily_rate' => 'decimal:8',
            'arrear_rate' => 'decimal:5',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Effective daily multiplier for accrual: explicit daily_rate, cached derived, or null.
     */
    public function effectiveDailyRate(): ?float
    {
        if ($this->daily_rate !== null) {
            return (float) $this->daily_rate;
        }

        if ($this->derived_daily_rate !== null) {
            return (float) $this->derived_daily_rate;
        }

        return null;
    }

    /**
     * Whether a principal amount falls within this rate row's amount band (if configured).
     */
    public function matchesPrincipalAmount(float $principal): bool
    {
        if ($this->min_principal !== null && $principal < (float) $this->min_principal) {
            return false;
        }

        if ($this->max_principal !== null && $principal > (float) $this->max_principal) {
            return false;
        }

        return true;
    }

    public function loanRateType(): BelongsTo
    {
        return $this->belongsTo(LoanRateType::class);
    }
}
