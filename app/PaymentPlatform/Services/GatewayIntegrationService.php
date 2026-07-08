<?php

namespace App\PaymentPlatform\Services;

use App\Models\PaymentGateway;
use App\Models\PaymentGatewayAttempt;
use App\Models\PaymentGatewayLog;
use App\Models\Loan;
use App\Models\Repayment;
use App\PaymentPlatform\Enums\GatewayAttemptPurpose;
use App\PaymentPlatform\Enums\GatewayAttemptStatus;
use App\PaymentPlatform\Enums\GatewayDirection;
use App\PaymentPlatform\Jobs\DispatchGatewayCollectionJob;
use App\PaymentPlatform\Jobs\DispatchGatewayDisbursementJob;
use App\PaymentPlatform\Enums\FinancialJobPriority;
use App\PaymentPlatform\Jobs\QueryGatewayAttemptStatusJob;
use App\PaymentPlatform\Support\CGrateIssuerNameResolver;
use App\PaymentPlatform\Support\CGrateUatDisbursementIssuer;
use App\Services\CustomerNotificationService;
use App\Services\Loans\LoanDisbursementService;
use App\Services\RepaymentProcessingService;
use App\Services\Repayments\RepaymentFinancePostingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class GatewayIntegrationService
{
    public function __construct(
        private readonly GatewaySelectionService $selectionService,
        private readonly RepaymentProcessingService $repaymentProcessingService,
        private readonly RepaymentFinancePostingService $financePostingService,
        private readonly LoanDisbursementService $loanDisbursementService,
        private readonly CGrateIssuerNameResolver $issuerNameResolver,
        private readonly PaymentGatewayDestinationMappingResolver $destinationMappingResolver,
        private readonly CustomerNotificationService $customerNotificationService,
    ) {}

    /**
     * @return array{success: bool, reference?: string, transaction_id?: string, message?: string, metadata?: array}
     */
    public function initiateCollection(Repayment $repayment, \App\Models\Channel $channel, ?string $phoneNumber): array
    {
        $gateway = $this->selectionService->selectForCollection($channel);

        if (! $gateway) {
            return [
                'success' => false,
                'message' => 'No payment gateway is currently available for this channel. Please try again later or contact support.',
            ];
        }

        $paymentMethod = $this->selectionService->mapChannelToPaymentMethod($channel);

        if (! $paymentMethod) {
            return [
                'success' => false,
                'message' => 'This repayment channel does not support automated payment processing.',
            ];
        }

        $attempt = DB::transaction(function () use ($repayment, $gateway, $paymentMethod, $phoneNumber) {
            $attempt = PaymentGatewayAttempt::create([
                'payment_gateway_id' => $gateway->id,
                'direction' => GatewayDirection::Collection,
                'purpose' => GatewayAttemptPurpose::LoanRepayment,
                'attemptable_type' => Repayment::class,
                'attemptable_id' => $repayment->id,
                'internal_reference' => 'TEMP-'.$repayment->id.'-'.now()->timestamp,
                'payment_method' => $paymentMethod,
                'amount' => $repayment->total_amount,
                'currency' => (string) config('cgrate.default_currency', 'ZMW'),
                'customer_phone' => $phoneNumber ?? $repayment->phone_number,
                'status' => GatewayAttemptStatus::Created,
            ]);

            $internalRef = PaymentGatewayAttempt::generateInternalReference($repayment->id, $attempt->id);
            $attempt->update([
                'internal_reference' => $internalRef,
                'provider_reference' => $internalRef,
            ]);

            $repayment->update([
                'payment_gateway_attempt_id' => $attempt->id,
                'metadata' => array_merge($repayment->metadata ?? [], [
                    'gateway_code' => $gateway->code,
                    'gateway_attempt_id' => $attempt->id,
                ]),
            ]);

            PaymentGatewayLog::log(
                $gateway,
                'collection.initiated',
                'Gateway collection attempt created for repayment '.$repayment->repayment_number,
                $attempt,
                direction: GatewayDirection::Collection->value,
            );

            return $attempt;
        });

        if ((string) config('queue.default') === 'sync') {
            DispatchGatewayCollectionJob::dispatchSync($attempt->id);
        } else {
            DispatchGatewayCollectionJob::dispatch($attempt->id);
        }

        $attempt->refresh();

        return [
            'success' => true,
            'reference' => $attempt->provider_reference ?? $attempt->internal_reference,
            'transaction_id' => $attempt->provider_transaction_id,
            'message' => 'Payment prompt sent. Approve the prompt on your device to complete the repayment.',
            'metadata' => [
                'gateway_code' => $gateway->code,
                'gateway_attempt_id' => $attempt->id,
                'gateway_initiated_at' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * @return array{success: bool, reference?: string, transaction_id?: string, message?: string, metadata?: array}
     */
    public function initiateDisbursement(Loan $loan): array
    {
        try {
            $this->loanDisbursementService->assertNoActiveDisbursementAttempt($loan);
            $this->loanDisbursementService->assertCanDisburse($loan);
            $resolved = $this->issuerNameResolver->resolveForLoan($loan);
        } catch (ValidationException $e) {
            return [
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?? 'Cannot initiate gateway disbursement.',
            ];
        }

        $gateway = $this->selectionService->selectForDisbursement($loan, requireLinkedAccount: true);

        if (! $gateway) {
            $resolution = app(PaymentGatewayRouteService::class)->resolveRouteForDisbursement($loan);
            $failureReason = $resolution->failureReason;

            return [
                'success' => false,
                'message' => $failureReason
                    ?? 'No disbursement gateway is available. Ensure cGrate is active with a linked wallet, or use manual disbursement.',
            ];
        }

        $paymentMethod = $resolved['payment_method'];
        $issuerNameForAttempt = (string) $resolved['issuer_name'];

        // Fail-fast validation for gateway-specific destination identifiers.
        if ($gateway->code === 'cgrate' && ! CGrateUatDisbursementIssuer::isForced()) {
            if ($paymentMethod === 'bank') {
                $loan->loadMissing(['disbursementFinancialInstitution']);
                $bankName = (string) ($loan->disbursementFinancialInstitution?->name ?? 'selected bank');
                $bankId = (int) $loan->disbursement_financial_institution_id;

                $mapping = $this->destinationMappingResolver->resolve(
                    $gateway,
                    'bank',
                    $bankId,
                    null,
                    'issuerName'
                )['mapping'];

                if (! $mapping) {
                    return [
                        'success' => false,
                        'message' => 'No cGrate issuerName mapping has been configured for '.$bankName.'. Configure the bank mapping before using cGrate disbursement.',
                    ];
                }

                if ($mapping->isVerificationRequired()) {
                    return [
                        'success' => false,
                        'message' => 'The cGrate issuerName mapping for '.$bankName.' requires verification before use.',
                    ];
                }

                $issuerNameForAttempt = (string) $mapping->gateway_value;
            } elseif ($paymentMethod === 'mobile_money') {
                $channelId = (int) ($loan->channel_id ?? 0);

                $mapping = $this->destinationMappingResolver->resolve(
                    $gateway,
                    'mobile_money',
                    null,
                    $channelId,
                    'issuerName'
                )['mapping'];

                if ($mapping) {
                    if ($mapping->isVerificationRequired()) {
                        return [
                            'success' => false,
                            'message' => 'The cGrate issuerName mapping requires verification before use.',
                        ];
                    }

                    $issuerNameForAttempt = (string) $mapping->gateway_value;
                }
            }
        }

        $issuerNameForAttempt = CGrateUatDisbursementIssuer::applyToIssuerName(
            $issuerNameForAttempt,
            (string) $gateway->code
        );

        $attempt = DB::transaction(function () use ($loan, $gateway, $resolved, $paymentMethod, $issuerNameForAttempt) {
            $this->loanDisbursementService->assertNoActiveDisbursementAttempt($loan->fresh());

            $attempt = PaymentGatewayAttempt::create([
                'payment_gateway_id' => $gateway->id,
                'direction' => GatewayDirection::Disbursement,
                'purpose' => GatewayAttemptPurpose::LoanDisbursement,
                'attemptable_type' => Loan::class,
                'attemptable_id' => $loan->id,
                'internal_reference' => 'TEMP-OUT-'.$loan->id.'-'.now()->timestamp,
                'payment_method' => $paymentMethod,
                'amount' => $loan->principal_amount,
                'currency' => (string) config('cgrate.default_currency', 'ZMW'),
                'customer_phone' => $loan->disbursement_phone_number,
                'customer_account' => $resolved['customer_account'],
                'destination_account' => $resolved['customer_account'],
                'issuer_name' => (string) $issuerNameForAttempt,
                'source_account' => $gateway->linkedAccountLabel(),
                'status' => GatewayAttemptStatus::Created,
            ]);

            $internalRef = PaymentGatewayAttempt::generateDisbursementInternalReference($loan->id, $attempt->id);
            $attempt->update([
                'internal_reference' => $internalRef,
                'provider_reference' => $internalRef,
            ]);

            $this->loanDisbursementService->markGatewayProcessing($loan->fresh(), $attempt);

            PaymentGatewayLog::log(
                $gateway,
                'disbursement.initiated',
                'Gateway disbursement attempt created for loan '.$loan->loan_number,
                $attempt,
                direction: GatewayDirection::Disbursement->value,
            );

            return $attempt;
        });

        DispatchGatewayDisbursementJob::dispatch($attempt->id);

        $reference = $attempt->provider_reference ?? $attempt->internal_reference;

        return [
            'success' => true,
            'reference' => $reference,
            'transaction_id' => $attempt->provider_transaction_id,
            'message' => 'Disbursement request submitted to cGrate. The loan will update once cGrate responds.',
            'metadata' => [
                'gateway_code' => $gateway->code,
                'gateway_attempt_id' => $attempt->id,
                'issuer_name' => $attempt->issuer_name,
                'customer_account' => $attempt->customer_account,
                'gateway_initiated_at' => now()->toIso8601String(),
                'queued' => true,
            ],
        ];
    }

    public function handleStatusResult(PaymentGatewayAttempt $attempt, \App\PaymentPlatform\DTOs\GatewayStatusResult $result): void
    {
        $attempt->refresh();

        if ($attempt->isTerminal()) {
            return;
        }

        $normalized = $result->normalizedStatus;

        if ($attempt->direction === GatewayDirection::Disbursement) {
            if (in_array($normalized, ['pending', 'unknown'], true)) {
                $normalized = 'failed';
            }
        } else {
            $unknownFailAfter = (int) config('cgrate.unknown_fail_after_attempts', 20);

            if ($normalized === 'unknown' && $attempt->query_attempts < $unknownFailAfter) {
                $normalized = 'pending';
            } elseif ($normalized === 'unknown') {
                $normalized = 'expired';
            }
        }

        DB::transaction(function () use ($attempt, $result, $normalized) {
            $locked = PaymentGatewayAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

            if ($locked->isTerminal()) {
                return;
            }

            $locked->update([
                'response_code' => $result->responseCode,
                'response_message' => $result->responseMessage,
                'provider_transaction_id' => $result->providerTransactionId ?? $locked->provider_transaction_id,
                'response_payload' => $result->rawPayload,
                'last_queried_at' => now(),
            ]);

            match ($normalized) {
                'confirmed' => $this->markAttemptConfirmed($locked),
                'rejected' => $locked->markRejected($result->responseMessage, $result->responseCode),
                'failed' => $locked->markFailed($result->responseMessage, $result->responseCode),
                'expired' => $locked->markExpired($result->responseMessage),
                default => $locked->markPending($result->responseMessage, $result->rawPayload),
            };
        });

        $attempt->refresh();

        if ($attempt->status === GatewayAttemptStatus::Confirmed) {
            if ($attempt->direction === GatewayDirection::Disbursement) {
                $this->finalizeConfirmedDisbursement($attempt);
            } else {
                $this->finalizeConfirmedAttempt($attempt);
            }
        } elseif ($attempt->status === GatewayAttemptStatus::Pending) {
            if ($attempt->direction !== GatewayDirection::Disbursement) {
                $this->scheduleQuery($attempt);
            }
        } elseif (in_array($attempt->status, [GatewayAttemptStatus::Failed, GatewayAttemptStatus::Rejected, GatewayAttemptStatus::Expired], true)) {
            if ($attempt->direction === GatewayDirection::Disbursement) {
                $this->handleDisbursementFailure($attempt);
            } else {
                $this->handleFailedAttempt($attempt);
            }
        }
    }

    public function finalizeConfirmedDisbursement(PaymentGatewayAttempt $attempt): void
    {
        $attempt->refresh()->loadMissing(['paymentGateway', 'attemptable']);

        if ($attempt->status !== GatewayAttemptStatus::Confirmed) {
            return;
        }

        /** @var Loan|null $loan */
        $loan = $attempt->attemptable;
        if (! $loan instanceof Loan) {
            return;
        }

        if ($loan->disbursement_status === 'completed') {
            return;
        }

        $gateway = $attempt->paymentGateway;
        if (! $gateway) {
            return;
        }

        DB::transaction(function () use ($attempt, $loan, $gateway) {
            $lockedAttempt = PaymentGatewayAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

            if ($lockedAttempt->status !== GatewayAttemptStatus::Confirmed) {
                return;
            }

            $loan->refresh();

            if ($loan->disbursement_status === 'completed') {
                return;
            }

            PaymentGatewayLog::log(
                $gateway,
                'disbursement.confirmed',
                'Gateway confirmed disbursement for loan '.$loan->loan_number,
                $lockedAttempt,
                direction: GatewayDirection::Disbursement->value,
            );
        });

        $this->loanDisbursementService->completeGatewayDisbursement($loan->fresh(), $attempt->fresh());
    }

    public function handleDisbursementFailure(PaymentGatewayAttempt $attempt): void
    {
        /** @var Loan|null $loan */
        $loan = $attempt->attemptable;
        if (! $loan instanceof Loan) {
            return;
        }

        if ($loan->disbursement_status === 'completed') {
            return;
        }

        $this->loanDisbursementService->markDisbursementFailed($loan, $attempt);
    }

    public function finalizeConfirmedAttempt(PaymentGatewayAttempt $attempt): void
    {
        $attempt->refresh()->loadMissing(['paymentGateway', 'attemptable']);

        if ($attempt->status !== GatewayAttemptStatus::Confirmed) {
            return;
        }

        /** @var Repayment|null $repayment */
        $repayment = $attempt->attemptable;
        if (! $repayment instanceof Repayment) {
            return;
        }

        if ($repayment->status === 'completed' && $repayment->loanRepayments()->exists()) {
            return;
        }

        $gateway = $attempt->paymentGateway;
        if (! $gateway) {
            return;
        }

        DB::transaction(function () use ($attempt, $repayment, $gateway) {
            $lockedAttempt = PaymentGatewayAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

            if ($lockedAttempt->status !== GatewayAttemptStatus::Confirmed) {
                return;
            }

            $repayment->refresh();

            if ($repayment->status === 'completed' && $repayment->loanRepayments()->exists()) {
                return;
            }

            PaymentGatewayLog::log(
                $gateway,
                'collection.confirmed',
                'Gateway confirmed payment for repayment '.$repayment->repayment_number,
                $lockedAttempt,
                direction: GatewayDirection::Collection->value,
            );

            if ($gateway->hasLinkedFinancialAccount()) {
                $accountType = $gateway->financial_account_type->value;
                $accountId = (int) $gateway->financial_account_id;

                $this->repaymentProcessingService->finalizeIntegratedRepayment(
                    $repayment,
                    [
                        'reference' => $lockedAttempt->provider_reference ?? $lockedAttempt->internal_reference,
                        'transaction_id' => $lockedAttempt->provider_transaction_id,
                        'message' => $lockedAttempt->response_message ?? 'Payment confirmed by provider and repayment completed.',
                        'metadata' => [
                            'gateway_code' => $gateway->code,
                            'gateway_attempt_id' => $lockedAttempt->id,
                            'provider_confirmed_at' => now()->toIso8601String(),
                        ],
                    ],
                    'Gateway confirmed repayment'
                );

                $this->financePostingService->creditReceivedAccount(
                    $repayment->fresh(),
                    $accountType,
                    $accountId
                );
            } else {
                $metadata = array_merge($repayment->metadata ?? [], [
                    'requires_finance_reconciliation' => true,
                    'gateway_confirmed_at' => now()->toIso8601String(),
                    'gateway_attempt_id' => $lockedAttempt->id,
                ]);

                $repayment->update([
                    'external_reference' => $lockedAttempt->provider_reference ?? $repayment->external_reference,
                    'external_transaction_id' => $lockedAttempt->provider_transaction_id ?? $repayment->external_transaction_id,
                    'status' => 'processing',
                    'status_message' => 'Payment confirmed by gateway but requires finance reconciliation (no linked account).',
                    'metadata' => $metadata,
                ]);
            }
        });

        $repayment->refresh();

        if ($repayment->status === 'completed') {
            try {
                $this->customerNotificationService->sendRepaymentCompleted(
                    $repayment->fresh(['customer', 'channel', 'loanRepayments.loan']),
                    'provider_confirmation'
                );
            } catch (\Throwable $e) {
                Log::error('Failed to send gateway-confirmed repayment notifications', [
                    'repayment_id' => $repayment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function processCallback(string $gatewayCode, string $reference, array $payload): bool
    {
        $gateway = PaymentGateway::query()->where('code', $gatewayCode)->first();
        if (! $gateway) {
            return false;
        }

        $attempt = PaymentGatewayAttempt::query()
            ->where('payment_gateway_id', $gateway->id)
            ->where(function ($q) use ($reference) {
                $q->where('provider_reference', $reference)
                    ->orWhere('internal_reference', $reference);
            })
            ->first();

        if (! $attempt) {
            return false;
        }

        $attempt->update([
            'callback_payload' => $payload,
        ]);

        PaymentGatewayLog::log(
            $gateway,
            'callback.received',
            'Callback received for reference '.$reference,
            $attempt,
            direction: $attempt->direction->value,
            payload: $payload,
        );

        if ($attempt->direction !== GatewayDirection::Disbursement) {
            if ((string) config('queue.default') === 'sync') {
                QueryGatewayAttemptStatusJob::dispatchSync($attempt->id);
            } else {
                QueryGatewayAttemptStatusJob::dispatchForAttempt($attempt->id, null, FinancialJobPriority::High);
            }
        }

        return true;
    }

    private function markAttemptConfirmed(PaymentGatewayAttempt $attempt): void
    {
        $attempt->markConfirmed(
            $attempt->provider_transaction_id,
            $attempt->response_payload
        );
    }

    private function handleFailedAttempt(PaymentGatewayAttempt $attempt): void
    {
        /** @var Repayment|null $repayment */
        $repayment = $attempt->attemptable;
        if (! $repayment instanceof Repayment) {
            return;
        }

        if ($repayment->status === 'completed') {
            return;
        }

        $repayment->update([
            'status' => 'failed',
            'status_message' => $attempt->response_message ?? 'Payment failed at gateway.',
            'metadata' => array_merge($repayment->metadata ?? [], [
                'gateway_failed_at' => now()->toIso8601String(),
                'gateway_attempt_status' => $attempt->status->value,
            ]),
        ]);
    }

    private function scheduleQuery(PaymentGatewayAttempt $attempt): void
    {
        if ((string) config('queue.default') === 'sync') {
            return;
        }

        $pollInterval = (int) config('cgrate.poll_interval_seconds', 15);

        QueryGatewayAttemptStatusJob::dispatchForAttempt($attempt->id)
            ->delay(now()->addSeconds(max(1, $pollInterval)));
    }
}
