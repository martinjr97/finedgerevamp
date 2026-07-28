<?php

namespace App\Console\Commands;

use App\Support\Queue\ApplicationQueue;
use App\Support\Queue\FinancialQueue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;

class QueuesHealthCommand extends Command
{
    protected $signature = 'queues:health';

    protected $description = 'Verify Redis, Horizon activity, and that supervisors cover SMS/email/payment queues';

    public function handle(): int
    {
        $this->info('FineEdge Queue / Horizon Health Check');
        $this->newLine();

        $checks = [
            $this->checkRedis('default'),
            $this->checkRedis('financial'),
            $this->checkHorizonActive(),
            $this->checkSupervisorCoverage(),
            $this->checkDispatchTargets(),
        ];

        $failed = collect($checks)->contains(fn (array $check): bool => $check['ok'] === false);

        $this->table(
            ['Check', 'Status', 'Detail'],
            collect($checks)->map(fn (array $check): array => [
                $check['name'],
                $check['ok'] ? 'OK' : 'FAIL',
                $check['detail'],
            ])->all(),
        );

        if ($failed) {
            $this->newLine();
            $this->error('One or more queue health checks failed.');
            $this->line('If Redis was restarted, stop the stale Horizon process and run: php artisan horizon');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('All queue health checks passed.');

        return self::SUCCESS;
    }

    /**
     * @return array{name: string, ok: bool, detail: string}
     */
    private function checkRedis(string $connection): array
    {
        $name = "Redis connection [{$connection}]";

        try {
            $pong = Redis::connection($connection)->ping();
            $ok = $pong === true || $pong === 'PONG' || $pong === 1;

            return [
                'name' => $name,
                'ok' => $ok,
                'detail' => $ok ? 'PONG' : 'Unexpected ping response',
            ];
        } catch (\Throwable $e) {
            return [
                'name' => $name,
                'ok' => false,
                'detail' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{name: string, ok: bool, detail: string}
     */
    private function checkHorizonActive(): array
    {
        try {
            /** @var MasterSupervisorRepository $masters */
            $masters = app(MasterSupervisorRepository::class);
            $master = $masters->all();

            if ($master === [] || $master === null) {
                return [
                    'name' => 'Horizon active',
                    'ok' => false,
                    'detail' => 'No master supervisor registered (run: php artisan horizon)',
                ];
            }

            $names = collect($master)->map(fn ($item) => is_object($item) ? ($item->name ?? 'unknown') : (string) $item)->implode(', ');

            return [
                'name' => 'Horizon active',
                'ok' => true,
                'detail' => 'Masters: '.$names,
            ];
        } catch (\Throwable $e) {
            return [
                'name' => 'Horizon active',
                'ok' => false,
                'detail' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{name: string, ok: bool, detail: string}
     */
    private function checkSupervisorCoverage(): array
    {
        $required = [
            'redis' => [
                ApplicationQueue::notifications(),
                ApplicationQueue::reports(),
                ApplicationQueue::default(),
                ApplicationQueue::maintenance(),
            ],
            'redis-financial' => FinancialQueue::allFinancialQueueNames(),
        ];

        $covered = [
            'redis' => [],
            'redis-financial' => [],
        ];

        foreach (config('horizon.defaults', []) as $supervisor) {
            $connection = (string) ($supervisor['connection'] ?? '');
            foreach (($supervisor['queue'] ?? []) as $queue) {
                $covered[$connection][] = (string) $queue;
            }
        }

        $missing = [];
        foreach ($required as $connection => $queues) {
            foreach ($queues as $queue) {
                if (! in_array($queue, $covered[$connection] ?? [], true)) {
                    $missing[] = "{$connection}:{$queue}";
                }
            }
        }

        return [
            'name' => 'Horizon supervisor coverage',
            'ok' => $missing === [],
            'detail' => $missing === []
                ? 'All required queues are assigned to supervisors'
                : 'Missing: '.implode(', ', $missing),
        ];
    }

    /**
     * @return array{name: string, ok: bool, detail: string}
     */
    private function checkDispatchTargets(): array
    {
        $smsQueue = (string) config('sms.queues.sms', ApplicationQueue::notifications());
        $smsConnection = ApplicationQueue::connection();
        $mailQueue = ApplicationQueue::notifications();
        $paymentsHigh = FinancialQueue::paymentsHigh();
        $disbursementsHigh = FinancialQueue::disbursementsHigh();

        $detail = sprintf(
            'SMS=%s:%s; Email notifications=%s:%s; Payments high=%s:%s; Disbursements high=%s:%s',
            $smsConnection,
            $smsQueue,
            ApplicationQueue::connection(),
            $mailQueue,
            FinancialQueue::connection(),
            $paymentsHigh,
            FinancialQueue::connection(),
            $disbursementsHigh,
        );

        $ok = $smsQueue === ApplicationQueue::notifications()
            && in_array($smsQueue, ['notifications'], true);

        return [
            'name' => 'Dispatch targets',
            'ok' => $ok,
            'detail' => $detail,
        ];
    }
}
