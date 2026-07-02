<?php

namespace App\PaymentPlatform\Jobs;

use App\Models\PaymentGatewayAttempt;
use App\PaymentPlatform\Enums\GatewayAttemptStatus;
use App\PaymentPlatform\Services\GatewayIntegrationService;
use App\PaymentPlatform\Support\PaymentQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class QueryGatewayAttemptStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public readonly int $paymentGatewayAttemptId,
    ) {
        $this->onQueue(PaymentQueue::polling());
    }

    public function handle(GatewayIntegrationService $integrationService): void
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

        $now = now();
        $maxAttempts = (int) config('cgrate.max_query_attempts', 20);
        $pollInterval = (int) config('cgrate.poll_interval_seconds', 15);
        $expiryMinutes = (int) config('cgrate.payment_expiry_minutes', 5);

        if ($attempt->next_query_at && $attempt->next_query_at->isFuture()) {
            return;
        }

        $initiatedAt = $attempt->initiated_at ?? $attempt->created_at;
        if ($initiatedAt && $initiatedAt->copy()->addMinutes($expiryMinutes)->lessThanOrEqualTo($now)) {
            $this->expireAttempt($attempt, $integrationService);

            return;
        }

        if ($attempt->query_attempts >= $maxAttempts) {
            $this->expireAttempt($attempt, $integrationService);

            return;
        }

        DB::transaction(function () use ($attempt) {
            $locked = PaymentGatewayAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

            if ($locked->isTerminal()) {
                return;
            }

            $locked->update([
                'query_attempts' => (int) $locked->query_attempts + 1,
            ]);
        });

        $attempt->refresh();

        try {
            $provider = $gateway->resolveProvider();
            $result = $provider->queryStatus($attempt);
        } catch (\Throwable $e) {
            $this->markPendingAndReschedule($attempt->id, $e->getMessage(), $pollInterval);

            return;
        }

        $integrationService->handleStatusResult($attempt, $result);
    }

    private function expireAttempt(PaymentGatewayAttempt $attempt, GatewayIntegrationService $integrationService): void
    {
        DB::transaction(function () use ($attempt) {
            $locked = PaymentGatewayAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

            if ($locked->isTerminal()) {
                return;
            }

            $locked->markExpired('Payment window expired.');
        });

        $attempt->refresh();

        $integrationService->handleStatusResult($attempt, new \App\PaymentPlatform\DTOs\GatewayStatusResult(
            normalizedStatus: 'expired',
            responseMessage: 'Payment window expired.',
        ));
    }

    private function markPendingAndReschedule(int $attemptId, string $message, int $pollInterval): void
    {
        DB::transaction(function () use ($attemptId, $message, $pollInterval) {
            $attempt = PaymentGatewayAttempt::query()->lockForUpdate()->findOrFail($attemptId);

            if ($attempt->isTerminal()) {
                return;
            }

            $attempt->update([
                'status' => GatewayAttemptStatus::Pending,
                'response_message' => $message !== '' ? $message : $attempt->response_message,
                'last_queried_at' => now(),
                'next_query_at' => now()->addSeconds(max(1, $pollInterval)),
            ]);
        });

        if ((string) config('queue.default') === 'sync') {
            return;
        }

        self::dispatch($attemptId)
            ->onQueue(PaymentQueue::polling())
            ->delay(now()->addSeconds(max(1, $pollInterval)));
    }
}
