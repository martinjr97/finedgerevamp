<?php

namespace App\Console\Commands;

use App\Sms\Services\SmsHealthService;
use Illuminate\Console\Command;

class SmsHealthCommand extends Command
{
    protected $signature = 'sms:health';

    protected $description = 'Run operational health checks for the SMS gateway platform';

    public function handle(SmsHealthService $healthService): int
    {
        $this->info('FineEdge SMS Gateway Health Check');
        $this->newLine();

        $snapshot = $healthService->snapshot();

        $checks = [
            $this->formatCheck('SMS Enabled', $snapshot['enabled'] ? 'pass' : 'warning', $snapshot['enabled'] ? 'true' : 'false (safe default)'),
            $this->formatCheck('Provider', 'pass', (string) $snapshot['provider']),
            $this->formatCheck('SMS Queue', 'pass', (string) $snapshot['queue']),
            $this->formatCheck('Queue Connection', 'pass', (string) $snapshot['queue_connection']),
            $this->formatCheck('Redis', $snapshot['redis_ok'] ? 'pass' : 'fail', $snapshot['redis_ok'] ? 'reachable' : 'unreachable'),
            $this->formatCheck(
                'Zamtel Configured',
                $snapshot['zamtel_configured'] ? 'pass' : ($snapshot['provider'] === 'zamtel' ? 'warning' : 'pass'),
                $snapshot['zamtel_configured'] ? 'yes' : 'no',
            ),
            $this->formatCheck(
                'Provider Health',
                $snapshot['provider_health']->successful ? 'pass' : 'warning',
                $snapshot['provider_health']->responseMessage ?? 'unknown',
            ),
            $this->formatCheck('Pending SMS', $snapshot['pending'] > 50 ? 'warning' : 'pass', (string) $snapshot['pending']),
            $this->formatCheck('Failed Today', $snapshot['failed_today'] > 0 ? 'warning' : 'pass', (string) $snapshot['failed_today']),
            $this->formatCheck('Skipped Today', 'pass', (string) $snapshot['skipped_today']),
            $this->formatCheck('Sent Today', 'pass', (string) $snapshot['sent_today']),
        ];

        $hasFail = false;
        $hasWarning = false;

        foreach ($checks as $check) {
            $status = strtoupper($check['status']);
            $line = sprintf('%-28s %s', $check['label'].':', $status);

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

        $last = $snapshot['last_successful'];
        $this->newLine();
        $this->line('Last successful SMS: '.($last
            ? '#'.$last->id.' at '.$last->sent_at?->toDateTimeString()
            : 'none'));

        $overall = $healthService->overallStatus($snapshot);
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
    private function formatCheck(string $label, string $status, ?string $detail): array
    {
        return [
            'label' => $label,
            'status' => $status,
            'detail' => $detail,
        ];
    }
}
