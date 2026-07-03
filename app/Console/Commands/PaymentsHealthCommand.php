<?php

namespace App\Console\Commands;

use App\Models\PaymentGatewayRoute;
use App\PaymentPlatform\Enums\GatewayAttemptStatus;
use App\PaymentPlatform\Enums\GatewayDirection;
use App\PaymentPlatform\Enums\GatewayRouteKey;
use App\PaymentPlatform\Services\FailedFinancialJobService;
use App\PaymentPlatform\Services\GatewayOperationsMetricsService;
use App\Support\Queue\FinancialQueue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;

class PaymentsHealthCommand extends Command
{
    protected $signature = 'payments:health';

    protected $description = 'Run operational health checks for payment queues, Horizon, and gateway routing';

    public function handle(
        GatewayOperationsMetricsService $metricsService,
        FailedFinancialJobService $failedFinancialJobService,
    ): int {
        $this->info('FineEdge Payment Platform Health Check');
        $this->newLine();

        $checks = [
            $this->checkRedis('default', 'Redis (default)'),
            $this->checkRedis('financial', 'Financial Redis'),
            $this->checkQueueConnection(),
            $this->checkHorizon(),
            $this->checkScheduler(),
            $this->checkQueueName('Payments high queue', FinancialQueue::paymentsHigh()),
            $this->checkQueueName('Payments polling queue', FinancialQueue::payments()),
            $this->checkQueueName('Disbursements high queue', FinancialQueue::disbursementsHigh()),
            $this->checkQueueName('Disbursements polling queue', FinancialQueue::disbursements()),
            $this->checkFailedJobs($failedFinancialJobService->count()),
            $this->checkPendingAttempts(GatewayDirection::Collection, 'Pending collection attempts'),
            $this->checkPendingAttempts(GatewayDirection::Disbursement, 'Pending disbursement attempts'),
            $this->checkCgrateEnabled(),
            $this->checkGatewayRoutingConfigured(),
            $this->checkRouteReady(GatewayRouteKey::WalletDisbursement, 'Wallet disbursement route'),
            $this->checkRouteReady(GatewayRouteKey::BankDisbursement, 'Bank disbursement route'),
        ];

        $hasFail = false;
        $hasWarning = false;

        foreach ($checks as $check) {
            $status = strtoupper($check['status']);
            $line = sprintf('%-42s %s', $check['label'].':', $status);

            match ($check['status']) {
                'fail' => $this->error($line.($check['detail'] ? ' — '.$check['detail'] : '')),
                'warning' => $this->warn($line.($check['detail'] ? ' — '.$check['detail'] : '')),
                default => $this->line('<fg=green>'.$line.'</>'.($check['detail'] ? ' — '.$check['detail'] : '')),
            };

            if ($check['status'] === 'fail') {
                $hasFail = true;
            }
            if ($check['status'] === 'warning') {
                $hasWarning = true;
            }
        }

        $this->newLine();
        $snapshot = $metricsService->operationalSnapshot();
        $this->line('Collections processing: '.$snapshot['collections']['processing']);
        $this->line('Disbursements processing: '.$snapshot['disbursements']['processing']);
        $this->newLine();

        if ($hasFail) {
            $this->error('Overall: FAIL');

            return self::FAILURE;
        }

        if ($hasWarning) {
            $this->warn('Overall: WARNING');

            return self::SUCCESS;
        }

        $this->info('Overall: PASS');

        return self::SUCCESS;
    }

    /**
     * @return array{label: string, status: string, detail: ?string}
     */
    private function checkRedis(string $connection, string $label): array
    {
        try {
            Redis::connection($connection)->ping();

            return ['label' => $label, 'status' => 'pass', 'detail' => 'Connected'];
        } catch (\Throwable $e) {
            return ['label' => $label, 'status' => 'fail', 'detail' => $e->getMessage()];
        }
    }

    /**
     * @return array{label: string, status: string, detail: ?string}
     */
    private function checkQueueConnection(): array
    {
        $connection = (string) config('queue.default');

        if ($connection === 'sync') {
            return ['label' => 'Queue connection', 'status' => 'warning', 'detail' => 'QUEUE_CONNECTION=sync (not production-ready)'];
        }

        if (in_array($connection, ['redis', 'database'], true)) {
            return ['label' => 'Queue connection', 'status' => 'pass', 'detail' => $connection];
        }

        return ['label' => 'Queue connection', 'status' => 'warning', 'detail' => $connection];
    }

    /**
     * @return array{label: string, status: string, detail: ?string}
     */
    private function checkHorizon(): array
    {
        if ((string) config('queue.default') === 'sync') {
            return ['label' => 'Horizon running', 'status' => 'warning', 'detail' => 'Skipped (sync queue)'];
        }

        try {
            $masters = app(MasterSupervisorRepository::class)->all();
            $count = count($masters);

            return $count > 0
                ? ['label' => 'Horizon running', 'status' => 'pass', 'detail' => $count.' master supervisor(s)']
                : ['label' => 'Horizon running', 'status' => 'fail', 'detail' => 'No Horizon masters detected'];
        } catch (\Throwable $e) {
            return ['label' => 'Horizon running', 'status' => 'warning', 'detail' => $e->getMessage()];
        }
    }

    /**
     * @return array{label: string, status: string, detail: ?string}
     */
    private function checkScheduler(): array
    {
        $lastHeartbeat = Cache::get('operations:scheduler:last_heartbeat');

        if (! $lastHeartbeat) {
            return ['label' => 'Scheduler running', 'status' => 'warning', 'detail' => 'No recent heartbeat (ensure cron runs schedule:run)'];
        }

        $age = now()->diffInMinutes($lastHeartbeat);
        if ($age > 3) {
            return ['label' => 'Scheduler running', 'status' => 'warning', 'detail' => 'Last heartbeat '.$age.' minutes ago'];
        }

        return ['label' => 'Scheduler running', 'status' => 'pass', 'detail' => 'Heartbeat '.$lastHeartbeat->diffForHumans()];
    }

    /**
     * @return array{label: string, status: string, detail: ?string}
     */
    private function checkQueueName(string $label, string $queue): array
    {
        return ['label' => $label, 'status' => 'pass', 'detail' => $queue];
    }

    /**
     * @return array{label: string, status: string, detail: ?string}
     */
    private function checkFailedJobs(int $count): array
    {
        if ($count === 0) {
            return ['label' => 'Failed financial jobs', 'status' => 'pass', 'detail' => '0'];
        }

        if ($count < 5) {
            return ['label' => 'Failed financial jobs', 'status' => 'warning', 'detail' => (string) $count];
        }

        return ['label' => 'Failed financial jobs', 'status' => 'fail', 'detail' => (string) $count];
    }

    /**
     * @return array{label: string, status: string, detail: ?string}
     */
    private function checkPendingAttempts(GatewayDirection $direction, string $label): array
    {
        $count = \App\Models\PaymentGatewayAttempt::query()
            ->where('direction', $direction)
            ->whereIn('status', [
                GatewayAttemptStatus::Created,
                GatewayAttemptStatus::Initiated,
                GatewayAttemptStatus::Pending,
            ])
            ->count();

        return ['label' => $label, 'status' => 'pass', 'detail' => (string) $count];
    }

    /**
     * @return array{label: string, status: string, detail: ?string}
     */
    private function checkCgrateEnabled(): array
    {
        $enabled = (bool) config('cgrate.enabled');

        return $enabled
            ? ['label' => 'cGrate enabled', 'status' => 'pass', 'detail' => 'true']
            : ['label' => 'cGrate enabled', 'status' => 'warning', 'detail' => 'false'];
    }

    /**
     * @return array{label: string, status: string, detail: ?string}
     */
    private function checkGatewayRoutingConfigured(): array
    {
        $count = PaymentGatewayRoute::query()->where('enabled', true)->count();

        return $count > 0
            ? ['label' => 'Gateway routing configured', 'status' => 'pass', 'detail' => $count.' enabled route(s)']
            : ['label' => 'Gateway routing configured', 'status' => 'warning', 'detail' => 'No enabled routes'];
    }

    /**
     * @return array{label: string, status: string, detail: ?string}
     */
    private function checkRouteReady(GatewayRouteKey $routeKey, string $label): array
    {
        $route = PaymentGatewayRoute::query()
            ->where('route_key', $routeKey->value)
            ->where('enabled', true)
            ->whereNotNull('payment_gateway_id')
            ->first();

        return $route
            ? ['label' => $label, 'status' => 'pass', 'detail' => 'Ready']
            : ['label' => $label, 'status' => 'warning', 'detail' => 'Not configured'];
    }
}
