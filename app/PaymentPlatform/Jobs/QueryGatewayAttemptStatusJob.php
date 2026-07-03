<?php

namespace App\PaymentPlatform\Jobs;

use App\Models\PaymentGatewayAttempt;
use App\PaymentPlatform\DTOs\GatewayStatusResult;
use App\PaymentPlatform\Enums\GatewayAttemptStatus;
use App\PaymentPlatform\Enums\FinancialJobPriority;
use App\PaymentPlatform\Enums\GatewayDirection;
use App\PaymentPlatform\Jobs\Concerns\InteractsWithGatewayCorrelation;
use App\PaymentPlatform\Services\GatewayIntegrationService;
use App\Support\Queue\FinancialQueue;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class QueryGatewayAttemptStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithGatewayCorrelation, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;

    public int $timeout = 120;

    public function __construct(
        public readonly int $paymentGatewayAttemptId,
    ) {
        $this->tries = (int) config('queues.retries.financial_status', 5);
    }

    public static function dispatchForAttempt(
        int $attemptId,
        ?DateTimeInterface $delay = null,
        FinancialJobPriority $priority = FinancialJobPriority::Polling,
    ): PendingDispatch {
        $attempt = PaymentGatewayAttempt::query()->find($attemptId);
        $direction = $attempt?->direction ?? GatewayDirection::Collection;

        $pending = self::dispatch($attemptId)
            ->onConnection(FinancialQueue::connection())
            ->onQueue(FinancialQueue::queueFor($direction, $priority));

        if ($delay !== null) {
            $pending->delay($delay);
        }

        return $pending;
    }

    protected function horizonJobKind(): string
    {
        $attempt = PaymentGatewayAttempt::query()->find($this->paymentGatewayAttemptId);

        return $attempt?->direction === GatewayDirection::Disbursement
            ? 'disbursement'
            : 'payment';
    }

    public function handle(GatewayIntegrationService $integrationService): void
    {
        $attempt = $this->applyGatewayCorrelationContext($this->paymentGatewayAttemptId);

        if (! $attempt) {
            return;
        }

        $attempt->loadMissing('paymentGateway');

        if ($attempt->isTerminal()) {
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

        $integrationService->handleStatusResult($attempt, new GatewayStatusResult(
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

        self::dispatchForAttempt($attemptId, now()->addSeconds(max(1, $pollInterval)));
    }
}
