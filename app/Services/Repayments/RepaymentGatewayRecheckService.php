<?php

namespace App\Services\Repayments;

use App\Models\PaymentGatewayAttempt;
use App\Models\PaymentGatewayLog;
use App\Models\Repayment;
use App\PaymentPlatform\DTOs\GatewayStatusResult;
use App\PaymentPlatform\Enums\GatewayAttemptStatus;
use App\PaymentPlatform\Enums\GatewayDirection;
use App\PaymentPlatform\Services\GatewayIntegrationService;
use App\Services\Repayments\DTOs\RepaymentGatewayRecheckResult;
use App\Services\Repayments\Enums\RepaymentGatewayCollectionStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RepaymentGatewayRecheckService
{
    public function __construct(
        private readonly RepaymentGatewayShowStateService $showStateService,
        private readonly AdminRepaymentGatewayCollectionService $gatewayCollectionService,
        private readonly GatewayIntegrationService $gatewayIntegrationService,
    ) {}

    /**
     * Query-only gateway status recheck. Does not mutate repayment, loans, or finance accounts.
     *
     * @return array{success: bool, flash_key: string, message: string, result?: array<string, mixed>}
     */
    public function recheck(Repayment $repayment): array
    {
        $attempt = $this->showStateService->resolveCollectionAttempt($repayment);

        if (! $attempt) {
            return [
                'success' => false,
                'flash_key' => 'warning',
                'message' => 'No gateway collection attempt was found for this repayment.',
            ];
        }

        $comparison = $this->queryAndCompare($repayment, $attempt);

        if ($comparison->outcome === 'unsupported') {
            return [
                'success' => false,
                'flash_key' => 'warning',
                'message' => $comparison->summary,
            ];
        }

        return [
            'success' => true,
            'flash_key' => 'status',
            'message' => 'Gateway status has been checked.',
            'result' => $comparison->toArray(),
        ];
    }

    /**
     * @return array{success: bool, flash_key: string, message: string}
     */
    public function applySynchronization(Repayment $repayment, string $note): array
    {
        $note = trim($note);
        if ($note === '') {
            throw ValidationException::withMessages([
                'note' => 'An admin note is required before applying gateway synchronization.',
            ]);
        }

        $attempt = $this->showStateService->resolveCollectionAttempt($repayment);
        if (! $attempt) {
            return [
                'success' => false,
                'flash_key' => 'error',
                'message' => 'No gateway collection attempt was found for this repayment.',
            ];
        }

        if ($repayment->status === 'completed' && $repayment->loanRepayments()->exists()) {
            return [
                'success' => false,
                'flash_key' => 'warning',
                'message' => 'This repayment is already completed and synchronized.',
            ];
        }

        $gatewayResult = $this->queryGateway($attempt);
        if ($gatewayResult->normalizedStatus !== 'confirmed') {
            return [
                'success' => false,
                'flash_key' => 'error',
                'message' => 'Gateway synchronization can only be applied when the latest gateway query is confirmed.',
            ];
        }

        DB::transaction(function () use ($repayment, $attempt, $gatewayResult, $note): void {
            $lockedAttempt = PaymentGatewayAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            $lockedRepayment = Repayment::query()->lockForUpdate()->findOrFail($repayment->id);

            if ($lockedRepayment->status === 'completed' && $lockedRepayment->loanRepayments()->exists()) {
                return;
            }

            if ($lockedAttempt->status !== GatewayAttemptStatus::Confirmed) {
                $this->gatewayIntegrationService->handleStatusResult($lockedAttempt, $gatewayResult);
                $lockedAttempt->refresh();
            }

            if ($lockedAttempt->status === GatewayAttemptStatus::Confirmed) {
                $this->gatewayIntegrationService->finalizeConfirmedAttempt($lockedAttempt->fresh());
            }

            $metadata = array_merge($lockedRepayment->fresh()->metadata ?? [], [
                'gateway_sync_applied_at' => now()->toIso8601String(),
                'gateway_sync_applied_by_admin_id' => auth('admin')->id(),
                'gateway_sync_note' => $note,
            ]);

            $lockedRepayment->fresh()->update(['metadata' => $metadata]);

            $gateway = $lockedAttempt->paymentGateway;
            if ($gateway) {
                PaymentGatewayLog::log(
                    $gateway,
                    'repayment.gateway_sync_applied',
                    'Admin applied gateway synchronization for repayment '.$lockedRepayment->repayment_number,
                    $lockedAttempt->fresh(),
                    direction: GatewayDirection::Collection->value,
                    payload: [
                        'repayment_id' => $lockedRepayment->id,
                        'admin_id' => auth('admin')->id(),
                        'note' => $note,
                        'gateway_status' => $gatewayResult->normalizedStatus,
                        'response_code' => $gatewayResult->responseCode,
                    ],
                );
            }
        });

        $repayment->refresh();

        if ($repayment->status === 'completed') {
            return [
                'success' => true,
                'flash_key' => 'status',
                'message' => 'Gateway synchronization applied. Loan balances and finance accounts have been updated.',
            ];
        }

        return [
            'success' => true,
            'flash_key' => 'warning',
            'message' => 'Gateway synchronization was processed, but finance reconciliation may still be required.',
        ];
    }

    /**
     * @return array{success: bool, flash_key: string, message: string}
     */
    public function markGatewayFailed(Repayment $repayment, string $note): array
    {
        $note = trim($note);
        if ($note === '') {
            throw ValidationException::withMessages([
                'note' => 'An admin note is required before marking this repayment as failed.',
            ]);
        }

        $attempt = $this->showStateService->resolveCollectionAttempt($repayment);
        if (! $attempt) {
            return [
                'success' => false,
                'flash_key' => 'error',
                'message' => 'No gateway collection attempt was found for this repayment.',
            ];
        }

        if ($repayment->status === 'completed') {
            return [
                'success' => false,
                'flash_key' => 'error',
                'message' => 'Completed repayments cannot be marked as failed.',
            ];
        }

        $gatewayResult = $this->queryGateway($attempt);
        if (! in_array($gatewayResult->normalizedStatus, ['failed', 'rejected', 'expired'], true)) {
            return [
                'success' => false,
                'flash_key' => 'error',
                'message' => 'Mark as failed is only available when the gateway reports a terminal failure.',
            ];
        }

        $this->gatewayIntegrationService->handleStatusResult($attempt->fresh(), $gatewayResult);

        $metadata = array_merge($repayment->fresh()->metadata ?? [], [
            'gateway_marked_failed_at' => now()->toIso8601String(),
            'gateway_marked_failed_by_admin_id' => auth('admin')->id(),
            'gateway_mark_failed_note' => $note,
        ]);

        $repayment->fresh()->update([
            'metadata' => $metadata,
            'status_message' => $note,
        ]);

        $attempt->refresh()->loadMissing('paymentGateway');
        if ($attempt->paymentGateway) {
            PaymentGatewayLog::log(
                $attempt->paymentGateway,
                'repayment.gateway_marked_failed',
                'Admin marked repayment '.$repayment->repayment_number.' as failed from gateway recheck.',
                $attempt,
                direction: GatewayDirection::Collection->value,
                payload: [
                    'repayment_id' => $repayment->id,
                    'admin_id' => auth('admin')->id(),
                    'note' => $note,
                    'gateway_status' => $gatewayResult->normalizedStatus,
                ],
            );
        }

        return [
            'success' => true,
            'flash_key' => 'status',
            'message' => 'Repayment has been marked as failed based on the gateway result.',
        ];
    }

    /**
     * @deprecated Use applySynchronization()
     *
     * @return array{success: bool, flash_key: string, message: string}
     */
    public function applyGatewayConfirmation(Repayment $repayment): array
    {
        return $this->applySynchronization(
            $repayment,
            'Gateway confirmation applied from legacy action.',
        );
    }

    /**
     * @return array{success: bool, flash_key: string, message: string}
     */
    public function retryCollection(Repayment $repayment): array
    {
        if ($this->gatewayCollectionService->hasActiveCollectionAttempt($repayment)) {
            return [
                'success' => false,
                'flash_key' => 'warning',
                'message' => 'An active gateway collection attempt already exists for this repayment.',
            ];
        }

        $repayment->loadMissing('channel');
        $channel = $repayment->channel;

        if (! $channel) {
            return [
                'success' => false,
                'flash_key' => 'error',
                'message' => 'Repayment channel is missing.',
            ];
        }

        if ($repayment->status === 'completed') {
            return [
                'success' => false,
                'flash_key' => 'error',
                'message' => 'Completed repayments cannot be retried for gateway collection.',
            ];
        }

        $repayment->update([
            'status' => 'processing',
            'status_message' => 'Gateway collection retry initiated.',
            'metadata' => array_merge($repayment->metadata ?? [], [
                'gateway_collection_retry_at' => now()->toIso8601String(),
            ]),
        ]);

        $result = $this->gatewayCollectionService->initiateForRepayment(
            $repayment->fresh(),
            $channel,
            $repayment->phone_number,
        );

        return match ($result->status) {
            RepaymentGatewayCollectionStatus::Initiated => [
                'success' => true,
                'flash_key' => 'status',
                'message' => $result->message,
            ],
            RepaymentGatewayCollectionStatus::FallbackManual => [
                'success' => true,
                'flash_key' => 'warning',
                'message' => $result->message,
            ],
            default => [
                'success' => false,
                'flash_key' => 'error',
                'message' => $result->message,
            ],
        };
    }

    private function queryAndCompare(Repayment $repayment, PaymentGatewayAttempt $attempt): RepaymentGatewayRecheckResult
    {
        try {
            $gatewayResult = $this->queryGateway($attempt);

            return RepaymentGatewayRecheckResult::fromComparison(
                $repayment->fresh(),
                $attempt->fresh(['paymentGateway']),
                $gatewayResult,
            );
        } catch (\Throwable $e) {
            return RepaymentGatewayRecheckResult::unsupported(
                'Could not query the payment gateway: '.$e->getMessage(),
            );
        }
    }

    private function queryGateway(PaymentGatewayAttempt $attempt): GatewayStatusResult
    {
        $attempt->loadMissing('paymentGateway');
        $gateway = $attempt->paymentGateway;

        if (! $gateway) {
            throw new \RuntimeException('Payment gateway is not configured for this attempt.');
        }

        return $gateway->resolveProvider()->queryStatus($attempt);
    }
}
