<?php

namespace App\PaymentPlatform\Jobs;

use App\Models\PaymentGatewayAttempt;
use App\PaymentPlatform\DTOs\CollectMoneyRequest;
use App\PaymentPlatform\Enums\GatewayAttemptStatus;
use App\PaymentPlatform\Support\PaymentQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class DispatchGatewayCollectionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public readonly int $paymentGatewayAttemptId,
    ) {
        $this->onQueue(PaymentQueue::high());
    }

    public function handle(): void
    {
        $attempt = PaymentGatewayAttempt::query()
            ->with('paymentGateway')
            ->find($this->paymentGatewayAttemptId);

        if (! $attempt || $attempt->isTerminal()) {
            return;
        }

        $gateway = $attempt->paymentGateway;
        if (! $gateway) {
            return;
        }

        $pollInterval = (int) config('cgrate.poll_interval_seconds', 15);

        if ($attempt->status === GatewayAttemptStatus::Created) {
            $attempt->markInitiated();
        }

        if ($attempt->status !== GatewayAttemptStatus::Initiated) {
            $this->schedulePolling($attempt->id, $pollInterval);

            return;
        }

        try {
            $provider = $gateway->resolveProvider();

            $result = $provider->collect(new CollectMoneyRequest(
                internalReference: (string) $attempt->internal_reference,
                paymentMethod: $attempt->payment_method->value,
                amount: (float) $attempt->amount,
                currency: (string) $attempt->currency,
                customerPhone: $attempt->customer_phone,
                providerReference: (string) ($attempt->provider_reference ?? $attempt->internal_reference),
            ));

            DB::transaction(function () use ($attempt, $result) {
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
                    'status' => $result->success ? GatewayAttemptStatus::Pending : GatewayAttemptStatus::Failed,
                    'failed_at' => $result->success ? null : now(),
                    'next_query_at' => $result->success ? now() : null,
                ]);
            });
        } catch (\Throwable $e) {
            DB::transaction(function () use ($attempt, $e) {
                $locked = PaymentGatewayAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

                if ($locked->isTerminal()) {
                    return;
                }

                $locked->update([
                    'status' => GatewayAttemptStatus::Pending,
                    'response_message' => 'Could not confirm initiation response (will retry).',
                    'response_payload' => array_merge((array) ($locked->response_payload ?? []), [
                        'initiation_error' => $e->getMessage(),
                    ]),
                    'next_query_at' => now(),
                ]);
            });
        }

        $attempt->refresh();

        if ($attempt->status === GatewayAttemptStatus::Pending) {
            $this->schedulePolling($attempt->id, $pollInterval);
        }
    }

    private function schedulePolling(int $attemptId, int $pollInterval): void
    {
        if ((string) config('queue.default') === 'sync') {
            return;
        }

        QueryGatewayAttemptStatusJob::dispatch($attemptId)
            ->onQueue(PaymentQueue::polling())
            ->delay(now()->addSeconds(max(1, $pollInterval)));
    }
}
