<?php

namespace App\Sms\Services;

use App\Models\SmsMessage;
use App\Sms\Support\SmsGatewayManager;
use App\Support\Queue\ApplicationQueue;
use Illuminate\Support\Facades\Redis;

class SmsHealthService
{
    public function __construct(
        private readonly SmsGatewayManager $gatewayManager,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $provider = (string) config('sms.provider', 'log');
        $queue = (string) config('sms.queues.sms', 'notifications');
        $connection = ApplicationQueue::connection();

        $health = $this->gatewayManager->resolve($provider)->healthCheck();

        return [
            'enabled' => (bool) config('sms.enabled', false),
            'provider' => $provider,
            'queue' => $queue,
            'queue_connection' => $connection,
            'redis_ok' => $this->checkRedis($connection),
            'zamtel_configured' => $this->isZamtelConfigured(),
            'provider_health' => $health,
            'sent_today' => $this->countToday('sent'),
            'failed_today' => $this->countToday('failed'),
            'skipped_today' => $this->countToday('skipped'),
            'pending' => SmsMessage::query()->where('status', 'queued')->count(),
            'last_successful' => SmsMessage::query()
                ->where('status', 'sent')
                ->latest('sent_at')
                ->first(),
            'recent_failures' => SmsMessage::query()
                ->where('status', 'failed')
                ->latest('failed_at')
                ->limit(5)
                ->get(),
        ];
    }

    /**
     * @return array{status: string, label: string, detail: ?string}
     */
    public function overallStatus(array $snapshot): array
    {
        if (! $snapshot['redis_ok']) {
            return ['status' => 'fail', 'label' => 'Overall', 'detail' => 'Queue Redis connection failed'];
        }

        if ($snapshot['enabled'] && $snapshot['provider'] === 'zamtel' && ! $snapshot['zamtel_configured']) {
            return ['status' => 'fail', 'label' => 'Overall', 'detail' => 'SMS enabled with Zamtel but credentials missing'];
        }

        if ($snapshot['failed_today'] > 0) {
            return ['status' => 'warning', 'label' => 'Overall', 'detail' => $snapshot['failed_today'].' failed SMS today'];
        }

        if ($snapshot['enabled'] && ! $snapshot['provider_health']->successful) {
            return ['status' => 'warning', 'label' => 'Overall', 'detail' => 'Provider health check reported issues'];
        }

        return ['status' => 'pass', 'label' => 'Overall', 'detail' => null];
    }

    private function isZamtelConfigured(): bool
    {
        return trim((string) config('sms.zamtel.api_key')) !== ''
            && trim((string) config('sms.zamtel.sender_id')) !== '';
    }

    private function checkRedis(string $connection): bool
    {
        try {
            $redisConnection = config("queue.connections.{$connection}.connection", 'default');

            return (bool) Redis::connection($redisConnection)->ping();
        } catch (\Throwable) {
            return false;
        }
    }

    private function countToday(string $status): int
    {
        return SmsMessage::query()
            ->where('status', $status)
            ->whereDate('created_at', today())
            ->count();
    }
}
