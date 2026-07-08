<?php

namespace App\PaymentPlatform\Jobs;

use App\Models\PaymentGatewayAttempt;
use App\PaymentPlatform\DTOs\DisburseMoneyRequest;
use App\PaymentPlatform\DTOs\GatewayStatusResult;
use App\PaymentPlatform\Enums\GatewayAttemptStatus;
use App\PaymentPlatform\Jobs\Concerns\InteractsWithGatewayCorrelation;
use App\PaymentPlatform\Services\GatewayIntegrationService;
use App\Support\Queue\FinancialQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class DispatchGatewayDisbursementJob implements ShouldQueue
{
    use Dispatchable, InteractsWithGatewayCorrelation, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;

    public int $timeout;

    public function __construct(
        public readonly int $paymentGatewayAttemptId,
    ) {
        $this->tries = (int) config('queues.retries.financial_initiation', 1);
        $this->timeout = (int) config('cgrate.disbursement_timeout', 120);
        $this->onConnection(FinancialQueue::connection())
            ->onQueue(FinancialQueue::disbursementsHigh());
    }

    protected function horizonJobKind(): string
    {
        return 'disbursement';
    }

    public function handle(GatewayIntegrationService $integrationService): void
    {
        $attempt = $this->applyGatewayCorrelationContext($this->paymentGatewayAttemptId);

        if (! $attempt || $attempt->isTerminal()) {
            return;
        }

        $gateway = $attempt->paymentGateway;
        if (! $gateway) {
            return;
        }

        $issuerNameForPayload = (string) $attempt->issuer_name;

        if ($attempt->status === GatewayAttemptStatus::Created) {
            $attempt->markInitiated([
                'customer_account' => $attempt->customer_account,
                'issuer_name' => $attempt->issuer_name,
                'issuer_name_sent' => $issuerNameForPayload,
                'amount' => $attempt->amount,
            ]);
        }

        if ($attempt->status !== GatewayAttemptStatus::Initiated) {
            return;
        }

        try {
            $provider = $gateway->resolveDisbursementProvider();

            $result = $provider->disburse(new DisburseMoneyRequest(
                internalReference: (string) $attempt->internal_reference,
                paymentMethod: $attempt->payment_method->value,
                amount: (float) $attempt->amount,
                currency: (string) $attempt->currency,
                customerAccount: (string) $attempt->customer_account,
                issuerName: $issuerNameForPayload,
                providerReference: (string) ($attempt->provider_reference ?? $attempt->internal_reference),
            ));

            DB::transaction(function () use ($attempt, $result, $issuerNameForPayload) {
                $locked = PaymentGatewayAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

                if ($locked->isTerminal()) {
                    return;
                }

                $locked->update([
                    'provider_reference' => $result->providerReference ?? $locked->provider_reference,
                    'provider_transaction_id' => $result->providerTransactionId ?? $locked->provider_transaction_id,
                    'response_code' => $result->responseCode,
                    'response_message' => $result->responseMessage,
                    'response_payload' => $result->rawPayload,
                    'request_payload' => array_merge((array) ($locked->request_payload ?? []), [
                        'disburse' => [
                            'customer_account' => $locked->customer_account,
                            'issuer_name' => $locked->issuer_name,
                            'issuer_name_sent' => $issuerNameForPayload,
                        ],
                    ]),
                    'next_query_at' => null,
                ]);
            });

            $attempt->refresh();

            $integrationService->handleStatusResult($attempt, new GatewayStatusResult(
                normalizedStatus: $result->normalizedStatus,
                providerTransactionId: $result->providerTransactionId,
                responseCode: $result->responseCode,
                responseMessage: $result->responseMessage,
                rawPayload: $result->rawPayload,
            ));
        } catch (\Throwable $e) {
            DB::transaction(function () use ($attempt, $e) {
                $locked = PaymentGatewayAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

                if ($locked->isTerminal()) {
                    return;
                }

                $locked->update([
                    'status' => GatewayAttemptStatus::Failed,
                    'response_message' => 'Could not complete cGrate disbursement: '.$e->getMessage(),
                    'response_payload' => array_merge((array) ($locked->response_payload ?? []), [
                        'initiation_error' => $e->getMessage(),
                    ]),
                    'failed_at' => now(),
                    'next_query_at' => null,
                ]);
            });

            $attempt->refresh();
            $integrationService->handleDisbursementFailure($attempt);
        }
    }
}
