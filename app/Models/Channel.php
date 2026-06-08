<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\Rule;

class Channel extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPE_MOBILE_WALLET = 'mobile_wallet';

    public const TYPE_BANK = 'bank';

    public const TYPE_CASH = 'cash';

    /**
     * @var list<string>
     */
    public const TYPES = [
        self::TYPE_MOBILE_WALLET,
        self::TYPE_BANK,
        self::TYPE_CASH,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'type',
        'description',
        'can_disburse',
        'can_repay',
        'is_repayment_integrated',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'can_disburse' => 'boolean',
            'can_repay' => 'boolean',
            'is_repayment_integrated' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function typeOptions(): array
    {
        return [
            self::TYPE_MOBILE_WALLET => 'Mobile Wallet',
            self::TYPE_BANK => 'Bank Transfer',
            self::TYPE_CASH => 'Cash',
        ];
    }

    public static function typeValidationRule(): \Illuminate\Validation\Rules\In
    {
        return Rule::in(self::TYPES);
    }

    public function isMobileWallet(): bool
    {
        return $this->type === self::TYPE_MOBILE_WALLET;
    }

    public function isBank(): bool
    {
        return $this->type === self::TYPE_BANK;
    }

    public function isCash(): bool
    {
        return $this->type === self::TYPE_CASH;
    }

    public function typeLabel(): string
    {
        return self::typeOptions()[$this->type] ?? ucfirst(str_replace('_', ' ', (string) $this->type));
    }
}
