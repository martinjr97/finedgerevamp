<?php

namespace App\Support;

use App\Models\PaymentGateway;
use App\Models\PaymentGatewayRoute;
use App\PaymentPlatform\Enums\PaymentGatewayStatus;
use App\PaymentPlatform\Services\PaymentGatewayRouteService;

class PaymentGatewayRoutingAdminUi
{
    /**
     * @return array{emoji: string, label: string, class: string, tone: string}
     */
    public static function routeStatus(PaymentGatewayRoute $route): array
    {
        $hasGateway = $route->payment_gateway_id !== null && $route->paymentGateway !== null;

        if (! $route->enabled && ! $hasGateway) {
            return static::statusBadge('⚪', 'Not Configured', 'bg-slate-100 text-slate-600 border-slate-200', 'neutral');
        }

        if (! $route->enabled) {
            return static::statusBadge('🟡', 'Disabled', 'bg-amber-100 text-amber-800 border-amber-200', 'warning');
        }

        if (! $hasGateway) {
            return static::statusBadge('⚪', 'No Gateway Selected', 'bg-slate-100 text-slate-600 border-slate-200', 'neutral');
        }

        $gateway = $route->paymentGateway;

        if (! $gateway->status->isOperational()) {
            return static::statusBadge('🔴', 'Gateway Inactive', 'bg-rose-100 text-rose-800 border-rose-200', 'danger');
        }

        if (! $gateway->isProviderEnabled()) {
            return static::statusBadge('🔴', 'Credentials Disabled', 'bg-rose-100 text-rose-800 border-rose-200', 'danger');
        }

        if (! $gateway->hasLinkedFinancialAccount()) {
            return static::statusBadge('🟠', 'Missing Linked Account', 'bg-orange-100 text-orange-800 border-orange-200', 'warning');
        }

        /** @var PaymentGatewayRouteService $routeService */
        $routeService = app(PaymentGatewayRouteService::class);

        if (! $routeService->gatewayEligibleForRoute($route->route_key, $gateway)) {
            return static::statusBadge('🟠', 'Configuration Warning', 'bg-orange-100 text-orange-800 border-orange-200', 'warning');
        }

        $direction = $route->route_key->direction()->value;

        if ($direction === 'collection' && ! $gateway->isAvailableForCollection()) {
            return static::statusBadge('🔴', 'Gateway Inactive', 'bg-rose-100 text-rose-800 border-rose-200', 'danger');
        }

        if ($direction === 'disbursement' && ! $gateway->isAvailableForDisbursement()) {
            return static::statusBadge('🔴', 'Gateway Inactive', 'bg-rose-100 text-rose-800 border-rose-200', 'danger');
        }

        return static::statusBadge('🟢', 'Ready', 'bg-emerald-100 text-emerald-800 border-emerald-200', 'success');
    }

    public static function yesNo(bool $value): string
    {
        return $value ? 'Yes' : 'No';
    }

    public static function gatewayLabel(PaymentGatewayRoute $route): string
    {
        if (! $route->payment_gateway_id) {
            return '—';
        }

        return $route->paymentGateway?->name ?? 'Unknown gateway';
    }

    public static function linkedAccountLabel(PaymentGatewayRoute $route): string
    {
        if (! $route->paymentGateway) {
            return '—';
        }

        return $route->paymentGateway->linkedAccountLabel() ?? 'Not linked';
    }

    /**
     * @return array{emoji: string, label: string, class: string}
     */
    public static function gatewayHealth(PaymentGateway $gateway): array
    {
        if ($gateway->status === PaymentGatewayStatus::Maintenance) {
            return [
                'emoji' => '🟡',
                'label' => 'Maintenance',
                'class' => 'bg-amber-500/20 text-amber-300 border-amber-400/40',
            ];
        }

        if (! $gateway->status->isOperational() || ! $gateway->isProviderEnabled()) {
            return [
                'emoji' => '🔴',
                'label' => 'Offline',
                'class' => 'bg-rose-500/20 text-rose-300 border-rose-400/40',
            ];
        }

        if (! $gateway->hasLinkedFinancialAccount()) {
            return [
                'emoji' => '🟠',
                'label' => 'Needs Account',
                'class' => 'bg-orange-500/20 text-orange-300 border-orange-400/40',
            ];
        }

        return [
            'emoji' => '🟢',
            'label' => 'Healthy',
            'class' => 'bg-emerald-500/20 text-emerald-300 border-emerald-400/40',
        ];
    }

    /**
     * @return list<array{label: string, enabled: bool}>
     */
    public static function capabilityBadges(PaymentGateway $gateway): array
    {
        $badges = [];

        if ($gateway->supports_collections && $gateway->supports_mobile_money) {
            $badges[] = ['label' => 'Mobile Money Collections', 'enabled' => true];
        }

        if ($gateway->supports_disbursements && $gateway->supports_mobile_money) {
            $badges[] = ['label' => 'Mobile Money Disbursements', 'enabled' => true];
        }

        if ($gateway->supports_collections && $gateway->supports_bank) {
            $badges[] = ['label' => 'Bank Collections', 'enabled' => true];
        }

        if ($gateway->supports_disbursements && $gateway->supports_bank) {
            $badges[] = ['label' => 'Bank Disbursements', 'enabled' => true];
        }

        if ($gateway->supports_collections && ! $gateway->supports_mobile_money && ! $gateway->supports_bank) {
            $badges[] = ['label' => 'Collections', 'enabled' => true];
        }

        if ($gateway->supports_disbursements && ! $gateway->supports_mobile_money && ! $gateway->supports_bank) {
            $badges[] = ['label' => 'Disbursements', 'enabled' => true];
        }

        return $badges;
    }

    /**
     * @return array<string, array{label: string, value: string, tone: string}>
     */
    public static function operationalChecks(PaymentGateway $gateway): array
    {
        return [
            'environment' => [
                'label' => 'Environment',
                'value' => $gateway->isProviderEnabled() ? 'Enabled' : 'Disabled',
                'tone' => $gateway->isProviderEnabled() ? 'success' : 'danger',
            ],
            'credentials' => [
                'label' => 'Credentials',
                'value' => static::credentialsStatus($gateway),
                'tone' => static::credentialsStatus($gateway) === 'Configured' ? 'success' : 'danger',
            ],
            'linked_account' => [
                'label' => 'Linked Account',
                'value' => $gateway->hasLinkedFinancialAccount() ? 'Configured' : 'Missing',
                'tone' => $gateway->hasLinkedFinancialAccount() ? 'success' : 'warning',
            ],
            'health' => [
                'label' => 'Health',
                'value' => static::gatewayHealth($gateway)['label'],
                'tone' => match (static::gatewayHealth($gateway)['label']) {
                    'Healthy' => 'success',
                    'Maintenance' => 'warning',
                    default => 'danger',
                },
            ],
        ];
    }

    /**
     * @return array{emoji: string, label: string, class: string, tone: string}
     */
    private static function statusBadge(string $emoji, string $label, string $class, string $tone): array
    {
        return [
            'emoji' => $emoji,
            'label' => $label,
            'class' => $class,
            'tone' => $tone,
        ];
    }

    private static function credentialsStatus(PaymentGateway $gateway): string
    {
        if ($gateway->code === 'cgrate') {
            $username = config('cgrate.username');
            $password = config('cgrate.password');

            return filled($username) && filled($password) ? 'Configured' : 'Missing';
        }

        return 'Configured';
    }
}
