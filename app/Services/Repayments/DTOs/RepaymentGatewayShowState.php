<?php

namespace App\Services\Repayments\DTOs;

use App\Models\PaymentGatewayAttempt;
use App\Models\Repayment;
use Carbon\CarbonInterface;

readonly class RepaymentGatewayShowState
{
    /**
     * @param  'gateway_processing'|'gateway_reconciliation_required'|'gateway_failed_expired'|'manual_pending'|'manual_processing'|'completed'|'failed'|'cancelled'  $panelType
     */
    public function __construct(
        public string $panelType,
        public string $title,
        public string $message,
        public bool $isGatewayOriented,
        public bool $showRecheckAction,
        public bool $showRetryCollectionAction,
        public bool $showManualReconciliationAction,
        public bool $showApplyGatewayConfirmationAction,
        public ?PaymentGatewayAttempt $gatewayAttempt,
        public ?string $gatewayName,
        public ?CarbonInterface $expiresAt,
        public ?int $secondsUntilExpiry,
        public bool $queuePollingActive,
    ) {}

    public static function fromRepayment(Repayment $repayment, ?PaymentGatewayAttempt $attempt): self
    {
        $metadata = $repayment->metadata ?? [];
        $requiresReconciliation = (bool) ($metadata['requires_finance_reconciliation'] ?? false);
        $submissionMode = (string) ($metadata['submission_mode'] ?? '');
        $isGatewaySubmission = $submissionMode === 'gateway_collection'
            || filled($repayment->payment_gateway_attempt_id)
            || $attempt !== null;

        $gatewayName = $attempt?->paymentGateway?->name;
        $expiresAt = self::resolveExpiryAt($attempt);
        $secondsUntilExpiry = $expiresAt !== null
            ? max(0, now()->diffInSeconds($expiresAt, false))
            : null;

        $queuePollingActive = $attempt !== null
            && ! $attempt->isTerminal()
            && (string) config('queue.default') !== 'sync';

        if ($repayment->status === 'completed') {
            return new self(
                panelType: 'completed',
                title: 'Repayment completed',
                message: 'This repayment has been applied to the loan and finance account.',
                isGatewayOriented: $isGatewaySubmission,
                showRecheckAction: $attempt !== null,
                showRetryCollectionAction: false,
                showManualReconciliationAction: false,
                showApplyGatewayConfirmationAction: false,
                gatewayAttempt: $attempt,
                gatewayName: $gatewayName,
                expiresAt: $expiresAt,
                secondsUntilExpiry: $secondsUntilExpiry,
                queuePollingActive: false,
            );
        }

        if ($repayment->status === 'pending' && ! $isGatewaySubmission) {
            return new self(
                panelType: 'manual_pending',
                title: 'Manual repayment pending approval',
                message: 'This repayment was recorded manually and requires admin review before loan balances are updated.',
                isGatewayOriented: false,
                showRecheckAction: false,
                showRetryCollectionAction: false,
                showManualReconciliationAction: false,
                showApplyGatewayConfirmationAction: false,
                gatewayAttempt: $attempt,
                gatewayName: $gatewayName,
                expiresAt: $expiresAt,
                secondsUntilExpiry: $secondsUntilExpiry,
                queuePollingActive: false,
            );
        }

        if ($repayment->status === 'processing' && $attempt && $attempt->status->isSuccessful() && $requiresReconciliation) {
            return new self(
                panelType: 'gateway_reconciliation_required',
                title: 'Gateway confirmed — finance reconciliation required',
                message: 'The gateway confirmed this payment, but finance posting could not complete automatically. Apply gateway confirmation after verifying the linked account, or use manual reconciliation if you have independent proof.',
                isGatewayOriented: true,
                showRecheckAction: true,
                showRetryCollectionAction: false,
                showManualReconciliationAction: true,
                showApplyGatewayConfirmationAction: true,
                gatewayAttempt: $attempt,
                gatewayName: $gatewayName,
                expiresAt: $expiresAt,
                secondsUntilExpiry: $secondsUntilExpiry,
                queuePollingActive: false,
            );
        }

        if ($repayment->status === 'processing' && $attempt && ! $attempt->isTerminal()) {
            return new self(
                panelType: 'gateway_processing',
                title: 'Gateway collection in progress',
                message: 'A collection request was sent via '.($gatewayName ?? 'the configured gateway').'. The customer must approve the payment prompt. The system checks gateway status automatically.',
                isGatewayOriented: true,
                showRecheckAction: true,
                showRetryCollectionAction: false,
                showManualReconciliationAction: true,
                showApplyGatewayConfirmationAction: false,
                gatewayAttempt: $attempt,
                gatewayName: $gatewayName,
                expiresAt: $expiresAt,
                secondsUntilExpiry: $secondsUntilExpiry,
                queuePollingActive: $queuePollingActive,
            );
        }

        if ($repayment->status === 'failed' && $attempt && $attempt->isTerminal()) {
            return new self(
                panelType: 'gateway_failed_expired',
                title: 'Gateway collection failed or expired',
                message: $repayment->status_message ?? 'The payment prompt was not confirmed by the provider.',
                isGatewayOriented: true,
                showRecheckAction: true,
                showRetryCollectionAction: true,
                showManualReconciliationAction: true,
                showApplyGatewayConfirmationAction: false,
                gatewayAttempt: $attempt,
                gatewayName: $gatewayName,
                expiresAt: $expiresAt,
                secondsUntilExpiry: $secondsUntilExpiry,
                queuePollingActive: false,
            );
        }

        if ($repayment->status === 'processing') {
            return new self(
                panelType: 'manual_processing',
                title: 'Repayment processing',
                message: 'This repayment is awaiting provider confirmation. Use manual reconciliation only if you have independently verified the payment result.',
                isGatewayOriented: $isGatewaySubmission,
                showRecheckAction: $attempt !== null,
                showRetryCollectionAction: false,
                showManualReconciliationAction: true,
                showApplyGatewayConfirmationAction: false,
                gatewayAttempt: $attempt,
                gatewayName: $gatewayName,
                expiresAt: $expiresAt,
                secondsUntilExpiry: $secondsUntilExpiry,
                queuePollingActive: $queuePollingActive,
            );
        }

        if ($repayment->status === 'failed') {
            return new self(
                panelType: 'failed',
                title: 'Repayment failed',
                message: $repayment->status_message ?? 'This repayment could not be completed.',
                isGatewayOriented: $isGatewaySubmission,
                showRecheckAction: false,
                showRetryCollectionAction: $isGatewaySubmission,
                showManualReconciliationAction: false,
                showApplyGatewayConfirmationAction: false,
                gatewayAttempt: $attempt,
                gatewayName: $gatewayName,
                expiresAt: $expiresAt,
                secondsUntilExpiry: $secondsUntilExpiry,
                queuePollingActive: false,
            );
        }

        return new self(
            panelType: 'cancelled',
            title: ucfirst((string) $repayment->status),
            message: $repayment->status_message ?? 'No further action is required.',
            isGatewayOriented: $isGatewaySubmission,
            showRecheckAction: false,
            showRetryCollectionAction: false,
            showManualReconciliationAction: false,
            showApplyGatewayConfirmationAction: false,
            gatewayAttempt: $attempt,
            gatewayName: $gatewayName,
            expiresAt: $expiresAt,
            secondsUntilExpiry: $secondsUntilExpiry,
            queuePollingActive: false,
        );
    }

    public static function resolveExpiryAt(?PaymentGatewayAttempt $attempt): ?CarbonInterface
    {
        if (! $attempt || $attempt->isTerminal()) {
            return null;
        }

        $startedAt = $attempt->initiated_at ?? $attempt->created_at;
        if (! $startedAt) {
            return null;
        }

        $expiryMinutes = (int) config('cgrate.payment_expiry_minutes', 5);

        return $startedAt->copy()->addMinutes($expiryMinutes);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'panel_type' => $this->panelType,
            'title' => $this->title,
            'message' => $this->message,
            'is_gateway_oriented' => $this->isGatewayOriented,
            'show_recheck_action' => $this->showRecheckAction,
            'show_retry_collection_action' => $this->showRetryCollectionAction,
            'show_manual_reconciliation_action' => $this->showManualReconciliationAction,
            'show_apply_gateway_confirmation_action' => $this->showApplyGatewayConfirmationAction,
            'gateway_name' => $this->gatewayName,
            'expires_at' => $this->expiresAt?->toIso8601String(),
            'seconds_until_expiry' => $this->secondsUntilExpiry,
            'queue_polling_active' => $this->queuePollingActive,
        ];
    }
}
