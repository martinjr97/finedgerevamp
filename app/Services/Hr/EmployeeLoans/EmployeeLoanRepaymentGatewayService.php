<?php

namespace App\Services\Hr\EmployeeLoans;

use App\Models\Channel;
use App\Models\EmployeeLoan;
use App\Models\EmployeeLoanRepayment;
use App\Models\PaymentGatewayAttempt;
use App\PaymentPlatform\Enums\GatewayAttemptStatus;
use App\PaymentPlatform\Enums\GatewayDirection;
use App\PaymentPlatform\Services\GatewayIntegrationService;
use App\Services\Repayments\AdminRepaymentGatewayCollectionService;
use App\Services\Repayments\DTOs\RepaymentGatewayCollectionResult;

class EmployeeLoanRepaymentGatewayService
{
    public function __construct(
        private readonly AdminRepaymentGatewayCollectionService $gatewayPreviewService,
        private readonly GatewayIntegrationService $gatewayIntegrationService,
        private readonly EmployeeLoanPricingService $pricingService,
    ) {}

    /**
     * @return array<int, \App\Services\Repayments\DTOs\RepaymentGatewayCollectionPreview>
     */
    public function previewsForChannels(iterable $channels, EmployeeLoan $loan, ?float $amount = null, ?string $phone = null): array
    {
        $productId = $this->pricingService->employeeProduct()->id;

        $previews = [];
        foreach ($channels as $channel) {
            $previews[$channel->id] = $this->gatewayPreviewService->previewForChannel(
                $channel,
                $amount,
                $phone,
                $productId,
            );
        }

        return $previews;
    }

    public function initiate(EmployeeLoanRepayment $repayment, Channel $channel, ?string $phone): RepaymentGatewayCollectionResult
    {
        $repayment->loadMissing('employeeLoan.employee');
        $loan = $repayment->employeeLoan;
        $productId = $this->pricingService->employeeProduct()->id;

        $preview = $this->gatewayPreviewService->previewForChannel(
            $channel,
            (float) $repayment->amount,
            $phone,
            $productId,
        );

        if (! $preview->ready) {
            if ($preview->applicable && $preview->reason) {
                return $preview->fallbackToManual
                    ? RepaymentGatewayCollectionResult::fallbackManual($preview->reason)
                    : RepaymentGatewayCollectionResult::failed($preview->reason);
            }

            return RepaymentGatewayCollectionResult::manualPending();
        }

        if ($this->hasActiveCollectionAttempt($repayment)) {
            $message = 'An active gateway collection attempt already exists for this repayment.';

            return $preview->fallbackToManual
                ? RepaymentGatewayCollectionResult::fallbackManual($message)
                : RepaymentGatewayCollectionResult::failed($message);
        }

        $paymentResult = $this->gatewayIntegrationService->initiateEmployeeLoanRepaymentCollection(
            $repayment,
            $channel,
            $phone,
        );

        if (! ($paymentResult['success'] ?? false)) {
            $reason = $paymentResult['message'] ?? 'Gateway collection could not be initiated.';

            return $preview->fallbackToManual
                ? RepaymentGatewayCollectionResult::fallbackManual($reason)
                : RepaymentGatewayCollectionResult::failed($reason);
        }

        $gatewayMetadata = is_array($paymentResult['metadata'] ?? null) ? $paymentResult['metadata'] : [];

        return RepaymentGatewayCollectionResult::initiated(
            gatewayName: $preview->gatewayName ?? 'gateway',
            gatewayMetadata: $gatewayMetadata,
            reference: $paymentResult['reference'] ?? null,
            transactionId: $paymentResult['transaction_id'] ?? null,
        );
    }

    public function hasActiveCollectionAttempt(EmployeeLoanRepayment $repayment): bool
    {
        return PaymentGatewayAttempt::query()
            ->where('attemptable_type', EmployeeLoanRepayment::class)
            ->where('attemptable_id', $repayment->id)
            ->where('direction', GatewayDirection::Collection)
            ->whereIn('status', [
                GatewayAttemptStatus::Created,
                GatewayAttemptStatus::Initiated,
                GatewayAttemptStatus::Pending,
            ])
            ->exists();
    }
}
