<?php

namespace App\PaymentPlatform\Services;

use App\Models\PaymentGatewayAttempt;
use App\PaymentPlatform\Enums\GatewayAttemptStatus;
use App\PaymentPlatform\Enums\GatewayDirection;
use App\Support\Queue\FinancialQueue;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;

class GatewayOperationsMetricsService
{
    public function __construct(
        private readonly FailedFinancialJobService $failedFinancialJobService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function operationalSnapshot(): array
    {
        return [
            'collections' => $this->directionMetrics(GatewayDirection::Collection),
            'disbursements' => $this->directionMetrics(GatewayDirection::Disbursement),
            'failed_financial_jobs' => $this->failedFinancialJobService->count(),
            'redis' => $this->redisStatus(),
            'horizon' => $this->horizonStatus(),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @deprecated Use operationalSnapshot() for the operations dashboard.
     *
     * @return array{
     *     collections: array<string, mixed>,
     *     disbursements: array<string, mixed>
     * }
     */
    public function snapshot(): array
    {
        $operational = $this->operationalSnapshot();

        return [
            'collections' => $operational['collections'],
            'disbursements' => $operational['disbursements'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function directionMetrics(GatewayDirection $direction): array
    {
        $base = PaymentGatewayAttempt::query()->where('direction', $direction);
        $today = Carbon::today();

        $waiting = (clone $base)->where('status', GatewayAttemptStatus::Created)->count();

        $processing = (clone $base)->whereIn('status', [
            GatewayAttemptStatus::Initiated,
            GatewayAttemptStatus::Pending,
        ])->count();

        $failed = (clone $base)->whereIn('status', [
            GatewayAttemptStatus::Failed,
            GatewayAttemptStatus::Rejected,
            GatewayAttemptStatus::Expired,
        ])->count();

        $completedToday = (clone $base)
            ->where('status', GatewayAttemptStatus::Confirmed)
            ->whereDate('confirmed_at', $today)
            ->count();

        $averageSeconds = (clone $base)
            ->where('status', GatewayAttemptStatus::Confirmed)
            ->whereNotNull('initiated_at')
            ->whereNotNull('confirmed_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, initiated_at, confirmed_at)) as avg_seconds')
            ->value('avg_seconds');

        $oldest = (clone $base)
            ->whereIn('status', [
                GatewayAttemptStatus::Created,
                GatewayAttemptStatus::Initiated,
                GatewayAttemptStatus::Pending,
            ])
            ->orderBy('created_at')
            ->first(['id', 'internal_reference', 'status', 'created_at']);

        return [
            'waiting' => $waiting,
            'processing' => $processing,
            'failed' => $failed,
            'completed_today' => $completedToday,
            'completed' => (clone $base)->where('status', GatewayAttemptStatus::Confirmed)->count(),
            'average_seconds' => $averageSeconds !== null ? round((float) $averageSeconds, 1) : null,
            'oldest' => $oldest ? [
                'correlation_id' => $oldest->correlationId(),
                'status' => $oldest->status->value,
                'age_seconds' => $oldest->created_at?->diffInSeconds(now()),
                'created_at' => $oldest->created_at?->toIso8601String(),
            ] : null,
        ];
    }

    /**
     * @return array{default: string, financial: string}
     */
    private function redisStatus(): array
    {
        return [
            'default' => $this->pingRedisConnection('default'),
            'financial' => $this->pingRedisConnection('financial'),
        ];
    }

    private function pingRedisConnection(string $connection): string
    {
        try {
            Redis::connection($connection)->ping();

            return 'ok';
        } catch (\Throwable) {
            return 'unavailable';
        }
    }

    /**
     * @return array{status: string, supervisors: int}
     */
    private function horizonStatus(): array
    {
        try {
            $masters = app(MasterSupervisorRepository::class)->all();

            return [
                'status' => count($masters) > 0 ? 'running' : 'stopped',
                'supervisors' => count($masters),
            ];
        } catch (\Throwable) {
            return [
                'status' => 'unknown',
                'supervisors' => 0,
            ];
        }
    }

    public function pendingQueueDepth(string $queue): int
    {
        if ((string) config('queue.default') === 'sync') {
            return 0;
        }

        try {
            return (int) DB::table('jobs')->where('queue', $queue)->count();
        } catch (\Throwable) {
            return 0;
        }
    }
}
