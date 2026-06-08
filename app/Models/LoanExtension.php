<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanExtension extends Model
{
    use HasFactory;

    public const TYPE_DUE_DATE_EXTENSION = 1;
    public const TYPE_INTEREST_ROLLOVER = 2;
    public const TYPE_RESTRUCTURE = 3;

    public const INTEREST_MODE_CONFIGURED_RATE = 1;
    public const INTEREST_MODE_CUSTOM_RATE = 2;
    public const INTEREST_MODE_FIXED_AMOUNT = 3;

    protected $fillable = [
        'loan_id',
        'extension_type',
        'interest_mode',
        'interest_rate',
        'interest_amount',
        'old_due_date',
        'new_due_date',
        'extension_period',
        'notes',
        'created_by',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'interest_rate' => 'decimal:6',
            'interest_amount' => 'decimal:2',
            'old_due_date' => 'date',
            'new_due_date' => 'date',
            'metadata' => 'array',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public static function typeOptions(): array
    {
        return [
            self::TYPE_DUE_DATE_EXTENSION => 'Due Date Extension',
            self::TYPE_INTEREST_ROLLOVER => 'Interest-Only Extension (Rollover)',
            self::TYPE_RESTRUCTURE => 'Loan Restructure',
        ];
    }

    public static function interestModeOptions(): array
    {
        return [
            self::INTEREST_MODE_CONFIGURED_RATE => 'Use Configured Product Rate',
            self::INTEREST_MODE_CUSTOM_RATE => 'Custom Rate (%)',
            self::INTEREST_MODE_FIXED_AMOUNT => 'Fixed Amount',
        ];
    }

    public function getTypeLabelAttribute(): string
    {
        return self::typeOptions()[$this->extension_type] ?? 'Unknown';
    }

    public function getInterestModeLabelAttribute(): string
    {
        return self::interestModeOptions()[$this->interest_mode] ?? 'Unknown';
    }
}
