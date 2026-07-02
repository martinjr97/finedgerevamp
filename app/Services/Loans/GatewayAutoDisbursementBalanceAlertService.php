<?php

namespace App\Services\Loans;

use App\Models\Admin;
use App\Models\Channel;
use App\Models\Loan;
use App\Models\PaymentGatewayRoute;
use App\PaymentPlatform\Enums\FinancialAccountType;
use App\PaymentPlatform\Enums\GatewayRouteKey;
use App\Services\Loans\DTOs\GatewayAutoDisbursementBalanceAlert;

class GatewayAutoDisbursementBalanceAlertService
{
    /**
     * @return list<GatewayAutoDisbursementBalanceAlert>
     */
    public function alertsForAdmin(Admin $admin): array
    {
        $routes = PaymentGatewayRoute::query()
            ->with('paymentGateway')
            ->whereIn('route_key', [
                GatewayRouteKey::WalletDisbursement->value,
                GatewayRouteKey::BankDisbursement->value,
            ])
            ->where('enabled', true)
            ->where('auto_process', true)
            ->get();

        $alerts = [];

        foreach ($routes as $route) {
            $gateway = $route->paymentGateway;

            if (! $gateway || ! $gateway->hasLinkedFinancialAccount()) {
                continue;
            }

            $accountType = $gateway->financial_account_type ?? FinancialAccountType::Wallet;

            if (! $this->adminCanViewAccountType($admin, $accountType)) {
                continue;
            }

            $balance = $gateway->linkedAccountBalance() ?? 0.0;
            $exposure = $this->pendingExposureForRoute($route->route_key);
            $needsAlert = $balance <= 0 || ($exposure > 0 && $balance < $exposure);

            if (! $needsAlert) {
                continue;
            }

            $linkedLabel = $gateway->linkedAccountLabel() ?? 'Linked account';
            $routeLabel = $route->route_key->displayLabel();

            $message = $balance <= 0
                ? sprintf(
                    'Automatic disbursement is enabled on %s via %s, but the system balance for %s is %s. Gateway disbursement requests will still be sent; update the system balance or load float with the provider if needed.',
                    $routeLabel,
                    $gateway->name,
                    $linkedLabel,
                    'ZMW '.number_format($balance, 2),
                )
                : sprintf(
                    'Automatic disbursement is enabled on %s via %s, but the system balance for %s (%s) is below pending/processing exposure (%s). Gateway requests will still be sent; the provider may accept or reject them.',
                    $routeLabel,
                    $gateway->name,
                    $linkedLabel,
                    'ZMW '.number_format($balance, 2),
                    'ZMW '.number_format($exposure, 2),
                );

            $alerts[] = new GatewayAutoDisbursementBalanceAlert(
                routeLabel: $routeLabel,
                gatewayName: $gateway->name,
                linkedAccountLabel: $linkedLabel,
                accountType: $accountType->value,
                systemBalance: $balance,
                exposureAmount: $exposure,
                message: $message,
                manageUrl: $this->manageUrlForGateway($gateway, $accountType),
            );
        }

        return $alerts;
    }

    private function adminCanViewAccountType(Admin $admin, FinancialAccountType $accountType): bool
    {
        return match ($accountType) {
            FinancialAccountType::Bank => $admin->can('banks.view'),
            FinancialAccountType::Wallet => $admin->can('wallets.view'),
        };
    }

    private function pendingExposureForRoute(GatewayRouteKey $routeKey): float
    {
        $channelType = match ($routeKey) {
            GatewayRouteKey::BankDisbursement => Channel::TYPE_BANK,
            default => Channel::TYPE_MOBILE_WALLET,
        };

        return (float) Loan::query()
            ->where('status', 'approved')
            ->whereIn('disbursement_status', ['pending', 'processing', 'failed'])
            ->where('disbursement_channel_type', $channelType)
            ->sum('principal_amount');
    }

    private function manageUrlForGateway($gateway, FinancialAccountType $accountType): ?string
    {
        if ($accountType === FinancialAccountType::Wallet && $gateway->financial_account_id) {
            return route('admin.wallets.show', $gateway->financial_account_id);
        }

        if ($accountType === FinancialAccountType::Bank && $gateway->financial_account_id) {
            return route('admin.banks.show', $gateway->financial_account_id);
        }

        return route('admin.payment-gateways.show', $gateway);
    }
}
