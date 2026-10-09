<?php

namespace App\Services\Hr\EmployeeLoans;

use App\Models\EmployeeLoan;
use App\Models\PaymentGatewayAttempt;
use App\Models\PaymentGatewayRoute;
use App\PaymentPlatform\Enums\GatewayRouteKey;
use App\PaymentPlatform\Services\GatewayIntegrationService;
use App\PaymentPlatform\Services\PaymentGatewayRouteService;
use App\Services\Loans\DTOs\AutomaticLoanDisbursementResult;
use App\Services\Loans\DTOs\LoanApprovalAutoDisbursementPreview;
use App\Services\Loans\Enums\AutomaticLoanDisbursementStatus;
use App\Support\PaymentGatewayRoutingAdminUi;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class AutomaticEmployeeLoanDisbursementService
{
    public function __construct(
        private readonly PaymentGatewayRouteService $routeService,
        private readonly EmployeeLoanDisbursementService $disbursementService,
        private readonly GatewayIntegrationService $gatewayIntegrationService,
    ) {}

    public function previewForApproval(EmployeeLoan $loan): LoanApprovalAutoDisbursementPreview
    {
        $disbursementAmount = (float) $loan->principal_amount;
        $destinationLabel = $loan->payoutDestinationSummary() ?: null;
        $routeKey = $this->resolveRouteKey($loan);

        if ($routeKey === null) {
            return LoanApprovalAutoDisbursementPreview::manualOnly($disbursementAmount, $destinationLabel);
        }

        $route = PaymentGatewayRoute::query()
            ->with('paymentGateway')
            ->where('route_key', $routeKey->value)
            ->first();

        if (! $route || ! $route->enabled || ! $route->auto_process) {
            return LoanApprovalAutoDisbursementPreview::manualOnly($disbursementAmount, $destinationLabel);
        }

        $routeLabel = $routeKey->displayLabel();
        $gateway = $route->paymentGateway;
        $gatewayName = $gateway?->name;
        $linkedAccountLabel = $gateway?->linkedAccountLabel();
        $linkedAccountBalance = $gateway?->linkedAccountBalance();
        $resolution = $this->routeService->resolveRouteForEmployeeLoanDisbursement($loan);
        $routeStatus = PaymentGatewayRoutingAdminUi::routeStatus($route);

        if ($resolution->available) {
            return LoanApprovalAutoDisbursementPreview::ready(
                routeLabel: $routeLabel,
                gatewayName: $gatewayName ?? 'Unknown gateway',
                linkedAccountLabel: $linkedAccountLabel ?? 'Not linked',
                linkedAccountBalance: $linkedAccountBalance,
                disbursementAmount: $disbursementAmount,
                destinationLabel: $destinationLabel ?? '—',
                warningMessage: sprintf(
                    'Automatic disbursement is enabled. Once you approve this employee loan, the system will initiate disbursement through %s.',
                    $gatewayName ?? 'the configured gateway',
                ),
                balanceWarning: $resolution->balanceWarning,
            );
        }

        return LoanApprovalAutoDisbursementPreview::configuredNotReady(
            routeLabel: $routeLabel,
            gatewayName: $gatewayName,
            linkedAccountLabel: $linkedAccountLabel,
            linkedAccountBalance: $linkedAccountBalance,
            disbursementAmount: $disbursementAmount,
            destinationLabel: $destinationLabel ?? '—',
            failureReason: $resolution->failureReason ?? 'The configured gateway route is not ready.',
            statusLabel: $routeStatus['label'],
        );
    }

    public function handle(EmployeeLoan $loan): AutomaticLoanDisbursementResult
    {
        $routeKey = $this->resolveRouteKey($loan);

        if ($routeKey === null) {
            return AutomaticLoanDisbursementResult::skipped(
                AutomaticLoanDisbursementStatus::SkippedInvalidDestination,
                'This payout destination does not support automatic gateway disbursement.',
            );
        }

        $route = PaymentGatewayRoute::query()
            ->with('paymentGateway')
            ->where('route_key', $routeKey->value)
            ->first();

        if (! $route) {
            return AutomaticLoanDisbursementResult::skipped(
                AutomaticLoanDisbursementStatus::SkippedNoAutoRoute,
                'No gateway routing configuration exists for this disbursement type.',
                routeKey: $routeKey,
            );
        }

        if (! $route->enabled) {
            return AutomaticLoanDisbursementResult::skipped(
                AutomaticLoanDisbursementStatus::SkippedRouteDisabled,
                'The matching gateway routing row is disabled.',
                $route,
                $routeKey,
            );
        }

        if (! $route->auto_process) {
            return AutomaticLoanDisbursementResult::skipped(
                AutomaticLoanDisbursementStatus::SkippedAutoProcessOff,
                'Automatic processing is disabled for this gateway route.',
                $route,
                $routeKey,
            );
        }

        $resolution = $this->routeService->resolveRouteForEmployeeLoanDisbursement($loan);

        if (! $resolution->available) {
            return AutomaticLoanDisbursementResult::skipped(
                AutomaticLoanDisbursementStatus::SkippedGatewayNotReady,
                $resolution->failureReason ?? 'The configured gateway route is not ready.',
                $route,
                $routeKey,
            );
        }

        try {
            $this->disbursementService->assertNoActiveDisbursementAttempt($loan);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first()
                ?? 'A gateway disbursement attempt is already active for this employee loan.';

            return AutomaticLoanDisbursementResult::skipped(
                AutomaticLoanDisbursementStatus::SkippedExistingAttempt,
                $message,
                $route,
                $routeKey,
            );
        }

        try {
            $result = $this->gatewayIntegrationService->initiateEmployeeLoanDisbursement($loan->fresh());
        } catch (Throwable $e) {
            Log::error('Automatic gateway disbursement failed after employee loan approval.', [
                'employee_loan_id' => $loan->id,
                'loan_number' => $loan->loan_number,
                'route_key' => $routeKey->value,
                'exception' => $e->getMessage(),
            ]);

            return AutomaticLoanDisbursementResult::failed(
                'An unexpected error occurred while initiating gateway disbursement.',
                $route,
                $routeKey,
            );
        }

        if (! ($result['success'] ?? false)) {
            return AutomaticLoanDisbursementResult::failed(
                $result['message'] ?? 'Gateway disbursement could not be initiated.',
                $route,
                $routeKey,
            );
        }

        $attemptId = $result['metadata']['gateway_attempt_id'] ?? null;
        $attempt = $attemptId
            ? PaymentGatewayAttempt::query()->find($attemptId)
            : null;

        $attempt ??= PaymentGatewayAttempt::query()
            ->where('attemptable_type', EmployeeLoan::class)
            ->where('attemptable_id', $loan->id)
            ->latest('id')
            ->first();

        if (! $attempt) {
            return AutomaticLoanDisbursementResult::failed(
                'Gateway disbursement could not be tracked.',
                $route,
                $routeKey,
            );
        }

        if ($loan->fresh()->disbursement_status === 'completed') {
            return AutomaticLoanDisbursementResult::completed($attempt, $route, $routeKey);
        }

        if ($result['metadata']['queued'] ?? false) {
            return AutomaticLoanDisbursementResult::initiated($attempt, $route, $routeKey);
        }

        return AutomaticLoanDisbursementResult::failed(
            $result['message'] ?? 'Gateway did not confirm disbursement.',
            $route,
            $routeKey,
        );
    }

    private function resolveRouteKey(EmployeeLoan $loan): ?GatewayRouteKey
    {
        if ($loan->hasBankDestination()) {
            return GatewayRouteKey::BankDisbursement;
        }

        if ($loan->hasMobileWalletDestination()) {
            return GatewayRouteKey::WalletDisbursement;
        }

        return null;
    }
}
