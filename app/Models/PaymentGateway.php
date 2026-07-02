<?php

namespace App\Models;

use App\PaymentPlatform\Contracts\DisbursementGatewayInterface;
use App\PaymentPlatform\Contracts\PaymentGatewayInterface;
use App\PaymentPlatform\Enums\FinancialAccountType;
use App\PaymentPlatform\Enums\PaymentGatewayStatus;
use App\PaymentPlatform\Enums\PaymentGatewayType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentGateway extends Model
{
    protected $fillable = [
        'name',
        'code',
        'provider_class',
        'type',
        'status',
        'priority',
        'is_default',
        'supports_collections',
        'supports_disbursements',
        'supports_mobile_money',
        'supports_bank',
        'supports_callbacks',
        'supports_polling',
        'financial_account_type',
        'financial_account_id',
        'config',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'type' => PaymentGatewayType::class,
            'status' => PaymentGatewayStatus::class,
            'financial_account_type' => FinancialAccountType::class,
            'is_default' => 'boolean',
            'supports_collections' => 'boolean',
            'supports_disbursements' => 'boolean',
            'supports_mobile_money' => 'boolean',
            'supports_bank' => 'boolean',
            'supports_callbacks' => 'boolean',
            'supports_polling' => 'boolean',
            'config' => 'array',
            'metadata' => 'array',
        ];
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(PaymentGatewayAttempt::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(PaymentGatewayLog::class);
    }

    public function linkedFinancialAccount(): ?Model
    {
        if (! $this->financial_account_type || ! $this->financial_account_id) {
            return null;
        }

        return match ($this->financial_account_type) {
            FinancialAccountType::Bank => Bank::find($this->financial_account_id),
            FinancialAccountType::Wallet => Wallet::find($this->financial_account_id),
            default => null,
        };
    }

    public function hasLinkedFinancialAccount(): bool
    {
        $account = $this->linkedFinancialAccount();

        return $account !== null && (bool) ($account->is_active ?? true);
    }

    public function isAvailableForCollection(): bool
    {
        if (! $this->status->isOperational()) {
            return false;
        }

        if (! $this->supports_collections) {
            return false;
        }

        if (! in_array($this->type, [PaymentGatewayType::Collection, PaymentGatewayType::Both], true)) {
            return false;
        }

        return $this->isProviderEnabled();
    }

    public function isAvailableForDisbursement(): bool
    {
        if (! $this->status->isOperational()) {
            return false;
        }

        if (! $this->supports_disbursements) {
            return false;
        }

        if (! in_array($this->type, [PaymentGatewayType::Disbursement, PaymentGatewayType::Both], true)) {
            return false;
        }

        return $this->isProviderEnabled();
    }

    public function resolveProvider(): PaymentGatewayInterface
    {
        $class = $this->provider_class;

        if (! class_exists($class)) {
            throw new \RuntimeException("Payment gateway provider class [{$class}] does not exist.");
        }

        $provider = app($class);

        if (! $provider instanceof PaymentGatewayInterface) {
            throw new \RuntimeException("Provider [{$class}] must implement PaymentGatewayInterface.");
        }

        return $provider;
    }

    public function resolveDisbursementProvider(): DisbursementGatewayInterface
    {
        $provider = $this->resolveProvider();

        if (! $provider instanceof DisbursementGatewayInterface) {
            throw new \RuntimeException("Provider [{$this->provider_class}] must implement DisbursementGatewayInterface.");
        }

        return $provider;
    }

    public function isProviderEnabled(): bool
    {
        if ($this->code === 'cgrate') {
            return (bool) config('cgrate.enabled', false);
        }

        return true;
    }

    public function linkedAccountBalance(): ?float
    {
        $account = $this->linkedFinancialAccount();

        if (! $account) {
            return null;
        }

        return (float) ($account->current_balance ?? 0);
    }

    public function linkedAccountLabel(): ?string
    {
        $account = $this->linkedFinancialAccount();

        if (! $account) {
            return null;
        }

        $type = $this->financial_account_type?->value ?? 'account';

        return ucfirst($type).': '.$account->name;
    }
}
