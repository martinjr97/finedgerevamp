<?php

namespace App\Services\Repayments\DTOs;

use App\Models\PaymentGatewayAttempt;
use App\Models\Repayment;
use App\PaymentPlatform\DTOs\GatewayStatusResult;
use App\PaymentPlatform\Enums\GatewayAttemptStatus;

readonly class RepaymentGatewayRecheckResult
{
    /**
     * @param  'pending'|'confirmed_synchronized'|'confirmed_needs_sync'|'failed'|'unknown'|'unsupported'  $outcome
     */
    public function __construct(
        public string $outcome,
        public string $summary,
        public string $localRepaymentStatus,
        public string $localAttemptStatus,
        public string $gatewayNormalizedStatus,
        public bool $statusChanged,
        public ?int $responseCode,
        public ?string $responseMessage,
        public ?string $providerTransactionId,
        public ?string $gatewayName,
        public ?string $internalReference,
        public ?string $providerReference,
        public ?string $recheckedAt,
        public ?string $localLastQueriedAt,
        public bool $showApplySynchronization,
        public bool $showMarkFailed,
        public bool $readOnly,
        public string $recommendedAction,
    ) {}

    public static function fromComparison(
        Repayment $repayment,
        PaymentGatewayAttempt $attempt,
        GatewayStatusResult $gatewayResult,
    ): self {
        $gatewayStatus = (string) $gatewayResult->normalizedStatus;
        $localRepaymentStatus = (string) $repayment->status;
        $localAttemptStatus = $attempt->status->value;
        $gatewayName = $attempt->paymentGateway?->name;

        $localNormalized = self::mapLocalToNormalized($repayment, $attempt);
        $statusChanged = $localNormalized !== $gatewayStatus
            || ($gatewayStatus === 'confirmed' && $localRepaymentStatus !== 'completed');

        $outcome = self::resolveOutcome($repayment, $attempt, $gatewayStatus);
        $summary = self::resolveSummary($outcome);
        $recommendedAction = self::resolveRecommendedAction($outcome);

        return new self(
            outcome: $outcome,
            summary: $summary,
            localRepaymentStatus: $localRepaymentStatus,
            localAttemptStatus: $localAttemptStatus,
            gatewayNormalizedStatus: $gatewayStatus,
            statusChanged: $statusChanged,
            responseCode: $gatewayResult->responseCode,
            responseMessage: $gatewayResult->responseMessage,
            providerTransactionId: $gatewayResult->providerTransactionId ?? $attempt->provider_transaction_id,
            gatewayName: $gatewayName,
            internalReference: $attempt->internal_reference,
            providerReference: $attempt->provider_reference,
            recheckedAt: now()->toIso8601String(),
            localLastQueriedAt: $attempt->last_queried_at?->toIso8601String(),
            showApplySynchronization: $outcome === 'confirmed_needs_sync',
            showMarkFailed: in_array($outcome, ['failed'], true)
                && $localRepaymentStatus !== 'failed',
            readOnly: in_array($outcome, ['pending', 'confirmed_synchronized', 'unknown'], true),
            recommendedAction: $recommendedAction,
        );
    }

    public static function unsupported(string $reason): self
    {
        return new self(
            outcome: 'unsupported',
            summary: $reason,
            localRepaymentStatus: '',
            localAttemptStatus: '',
            gatewayNormalizedStatus: 'unknown',
            statusChanged: false,
            responseCode: null,
            responseMessage: null,
            providerTransactionId: null,
            gatewayName: null,
            internalReference: null,
            providerReference: null,
            recheckedAt: now()->toIso8601String(),
            localLastQueriedAt: null,
            showApplySynchronization: false,
            showMarkFailed: false,
            readOnly: true,
            recommendedAction: 'Recheck is not available for this repayment.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'outcome' => $this->outcome,
            'summary' => $this->summary,
            'local_repayment_status' => $this->localRepaymentStatus,
            'local_attempt_status' => $this->localAttemptStatus,
            'gateway_normalized_status' => $this->gatewayNormalizedStatus,
            'status_changed' => $this->statusChanged,
            'response_code' => $this->responseCode,
            'response_message' => $this->responseMessage,
            'provider_transaction_id' => $this->providerTransactionId,
            'gateway_name' => $this->gatewayName,
            'internal_reference' => $this->internalReference,
            'provider_reference' => $this->providerReference,
            'rechecked_at' => $this->recheckedAt,
            'local_last_queried_at' => $this->localLastQueriedAt,
            'show_apply_synchronization' => $this->showApplySynchronization,
            'show_mark_failed' => $this->showMarkFailed,
            'read_only' => $this->readOnly,
            'recommended_action' => $this->recommendedAction,
        ];
    }

    private static function mapLocalToNormalized(Repayment $repayment, PaymentGatewayAttempt $attempt): string
    {
        if ($repayment->status === 'completed') {
            return 'confirmed';
        }

        if ($repayment->status === 'failed') {
            return match ($attempt->status) {
                GatewayAttemptStatus::Rejected => 'rejected',
                GatewayAttemptStatus::Expired => 'expired',
                default => 'failed',
            };
        }

        return match ($attempt->status) {
            GatewayAttemptStatus::Confirmed, GatewayAttemptStatus::Completed => 'confirmed',
            GatewayAttemptStatus::Rejected => 'rejected',
            GatewayAttemptStatus::Failed => 'failed',
            GatewayAttemptStatus::Expired => 'expired',
            default => 'pending',
        };
    }

    /**
     * @return 'pending'|'confirmed_synchronized'|'confirmed_needs_sync'|'failed'|'unknown'
     */
    private static function resolveOutcome(Repayment $repayment, PaymentGatewayAttempt $attempt, string $gatewayStatus): string
    {
        if (in_array($gatewayStatus, ['pending', 'unknown'], true)) {
            return $gatewayStatus === 'pending' ? 'pending' : 'unknown';
        }

        if (in_array($gatewayStatus, ['failed', 'rejected', 'expired'], true)) {
            return 'failed';
        }

        if ($gatewayStatus === 'confirmed') {
            if ($repayment->status === 'completed' && $repayment->loanRepayments()->exists()) {
                return 'confirmed_synchronized';
            }

            return 'confirmed_needs_sync';
        }

        return 'unknown';
    }

    private static function resolveSummary(string $outcome): string
    {
        return match ($outcome) {
            'pending' => 'Gateway still pending. No local update is required.',
            'confirmed_synchronized' => 'Gateway confirms payment and FineEdge is already synchronized. No action required.',
            'confirmed_needs_sync' => 'Gateway confirms payment, but FineEdge has not yet synchronized this repayment.',
            'failed' => 'Gateway reports the payment failed, was rejected, or expired.',
            'unknown' => 'No terminal gateway response received. Continue polling or retry later.',
            default => 'Gateway status could not be determined.',
        };
    }

    private static function resolveRecommendedAction(string $outcome): string
    {
        return match ($outcome) {
            'pending' => 'Wait for customer approval or background polling. Recheck again later if needed.',
            'confirmed_synchronized' => 'No action required.',
            'confirmed_needs_sync' => 'Review the gateway response, then apply synchronization with an admin note.',
            'failed' => 'Mark the repayment as failed if you agree with the gateway result.',
            'unknown' => 'Retry recheck later or wait for automatic polling.',
            default => 'Review gateway details and contact support if needed.',
        };
    }
}
