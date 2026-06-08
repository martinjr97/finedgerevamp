<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoanRateType extends Model
{
    use HasFactory, SoftDeletes;

    public const INTEREST_BEHAVIOR_DAILY_ACCRUAL = 'daily_accrual';

    public const INTEREST_BEHAVIOR_UPFRONT_FLAT = 'upfront_flat';

    public const INTEREST_BEHAVIOR_AMORTIZED = 'amortized';

    public const RATE_INPUT_DAILY_MULTIPLIER = 'daily_multiplier';

    public const RATE_INPUT_WEEKLY_MULTIPLIER = 'weekly_multiplier';

    public const RATE_INPUT_TERM_PERCENTAGE = 'term_percentage';

    public const ACCRUAL_PERIOD_DAILY = 'daily';

    public const ACCRUAL_PERIOD_WEEKLY = 'weekly';

    protected $fillable = [
        'loan_product_id',
        'name',
        'code',
        'description',
        'accrual_period',
        'interest_behavior',
        'rate_input_mode',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Whether this rate type uses legacy daily/weekly multiplier rows only.
     */
    public function usesLegacyMultiplierInput(): bool
    {
        return in_array($this->rate_input_mode, [
            self::RATE_INPUT_DAILY_MULTIPLIER,
            self::RATE_INPUT_WEEKLY_MULTIPLIER,
        ], true);
    }

    /**
     * Whether interest should be booked upfront (flat) rather than via daily cron.
     */
    public function booksInterestUpfront(): bool
    {
        return $this->interest_behavior === self::INTEREST_BEHAVIOR_UPFRONT_FLAT;
    }

    public function loanProduct(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class);
    }

    public function loanRates(): HasMany
    {
        return $this->hasMany(LoanRate::class)->orderBy('tenure_months');
    }
}
