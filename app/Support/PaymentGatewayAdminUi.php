<?php

namespace App\Support;

use App\Models\Loan;
use App\Models\PaymentGateway;
use App\Models\PaymentGatewayAttempt;
use App\Models\Repayment;
use App\PaymentPlatform\Enums\FinancialAccountType;
use App\PaymentPlatform\Enums\GatewayAttemptStatus;
use App\PaymentPlatform\Enums\GatewayDirection;
use App\PaymentPlatform\Enums\PaymentGatewayStatus;
use App\PaymentPlatform\Enums\PaymentGatewayType;

class PaymentGatewayAdminUi
{
    /**
     * @return array{emoji: string, label: string, class: string}
     */
    public static function statusIndicator(PaymentGatewayStatus $status): array
    {
        return match ($status) {
            PaymentGatewayStatus::Active => [
                'emoji' => '🟢',
                'label' => 'Active',
                'class' => 'bg-emerald-500/20 text-emerald-300 border-emerald-400/40',
            ],
            PaymentGatewayStatus::Maintenance => [
                'emoji' => '🟡',
                'label' => 'Maintenance',
                'class' => 'bg-amber-500/20 text-amber-300 border-amber-400/40',
            ],
            PaymentGatewayStatus::Disabled => [
                'emoji' => '🔴',
                'label' => 'Disabled',
                'class' => 'bg-rose-500/20 text-rose-300 border-rose-400/40',
            ],
            PaymentGatewayStatus::Inactive => [
                'emoji' => '⚪',
                'label' => 'Inactive',
                'class' => 'bg-slate-500/20 text-slate-300 border-slate-400/40',
            ],
        };
    }

    public static function typeLabel(PaymentGatewayType $type): string
    {
        return match ($type) {
            PaymentGatewayType::Collection => 'Collections',
            PaymentGatewayType::Disbursement => 'Disbursements',
            PaymentGatewayType::Both => 'Both',
        };
    }

    public static function providerDisplayName(PaymentGateway $gateway): string
    {
        $class = class_basename($gateway->provider_class ?? '');

        if ($class === '') {
            return '—';
        }

        return preg_replace('/PaymentGateway$/', '', $class) ?: $class;
    }

    /**
     * @return list<string>
     */
    public static function supportedFeatures(PaymentGateway $gateway): array
    {
        $features = [];

        if ($gateway->supports_mobile_money) {
            $features[] = 'Mobile Money';
        }

        if ($gateway->supports_bank) {
            $features[] = 'Bank';
        }

        if ($gateway->supports_collections) {
            $features[] = 'Collections';
        }

        if ($gateway->supports_disbursements) {
            $features[] = 'Disbursements';
        }

        if ($gateway->supports_polling) {
            $features[] = 'Polling';
        }

        if ($gateway->supports_callbacks) {
            $features[] = 'Callbacks';
        }

        return $features;
    }

    public static function financialAccountShowUrl(PaymentGateway $gateway): ?string
    {
        if (! $gateway->financial_account_type || ! $gateway->financial_account_id) {
            return null;
        }

        return match ($gateway->financial_account_type) {
            FinancialAccountType::Bank => route('admin.banks.show', $gateway->financial_account_id),
            FinancialAccountType::Wallet => route('admin.wallets.show', $gateway->financial_account_id),
            default => null,
        };
    }

    public static function financialAccountTypeLabel(PaymentGateway $gateway): ?string
    {
        return match ($gateway->financial_account_type) {
            FinancialAccountType::Bank => 'Bank',
            FinancialAccountType::Wallet => 'Wallet',
            default => null,
        };
    }

    /**
     * @return list<array{key: string, value: string}>
     */
    public static function sanitizedConfigEntries(PaymentGateway $gateway): array
    {
        $config = $gateway->config ?? [];
        $entries = [];

        foreach ($config as $key => $value) {
            $entries[] = [
                'key' => (string) $key,
                'value' => static::formatConfigValue($key, $value),
            ];
        }

        return $entries;
    }

    /**
     * @return array<string, string|null>
     */
    public static function statusDescriptions(): array
    {
        return [
            PaymentGatewayStatus::Active->value => 'Gateway is operational and available for automated processing.',
            PaymentGatewayStatus::Inactive->value => 'Gateway is temporarily turned off. Manual processing remains available.',
            PaymentGatewayStatus::Maintenance->value => 'Gateway is under maintenance. New automated requests are paused.',
            PaymentGatewayStatus::Disabled->value => 'Gateway is disabled and must not be used for new automated requests.',
        ];
    }

    public static function attemptAdminUrl(PaymentGatewayAttempt $attempt): ?string
    {
        $attemptable = $attempt->attemptable;

        if ($attemptable instanceof Loan) {
            return route('admin.loans.show', $attemptable);
        }

        if ($attemptable instanceof Repayment && $attemptable->customer_id) {
            return route('admin.customers.show', $attemptable->customer_id);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function cgrateSummary(PaymentGateway $gateway): array
    {
        $lastAttempt = PaymentGatewayAttempt::query()
            ->where('payment_gateway_id', $gateway->id)
            ->latest()
            ->first();

        $lastSuccessfulCollection = PaymentGatewayAttempt::query()
            ->where('payment_gateway_id', $gateway->id)
            ->where('direction', GatewayDirection::Collection)
            ->whereIn('status', [GatewayAttemptStatus::Confirmed, GatewayAttemptStatus::Completed])
            ->latest('confirmed_at')
            ->first();

        $lastSuccessfulDisbursement = PaymentGatewayAttempt::query()
            ->where('payment_gateway_id', $gateway->id)
            ->where('direction', GatewayDirection::Disbursement)
            ->whereIn('status', [GatewayAttemptStatus::Confirmed, GatewayAttemptStatus::Completed])
            ->latest('confirmed_at')
            ->first();

        return [
            'linked_wallet' => $gateway->linkedAccountLabel(),
            'wallet_balance' => $gateway->linkedAccountBalance(),
            'collections_status' => $gateway->supports_collections && $gateway->isAvailableForCollection()
                ? 'Available'
                : ($gateway->supports_collections ? 'Enabled (not operational)' : 'Disabled'),
            'disbursement_status' => $gateway->supports_disbursements && $gateway->isAvailableForDisbursement()
                ? 'Available'
                : ($gateway->supports_disbursements ? 'Enabled (not operational)' : 'Disabled'),
            'last_attempt' => $lastAttempt,
            'last_successful_collection' => $lastSuccessfulCollection,
            'last_successful_disbursement' => $lastSuccessfulDisbursement,
        ];
    }

    private static function formatConfigValue(string $key, mixed $value): string
    {
        if (static::isSensitiveConfigKey($key)) {
            return '••••••••';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '—';
        }

        if ($value === null || $value === '') {
            return '—';
        }

        return (string) $value;
    }

    private static function isSensitiveConfigKey(string $key): bool
    {
        $normalized = strtolower($key);

        foreach (['password', 'secret', 'token', 'api_key', 'apikey', 'credential', 'private_key'] as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }
}
