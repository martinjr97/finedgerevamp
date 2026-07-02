<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\InteractsWithCGrateConfig;
use App\PaymentPlatform\Providers\CGrate\CGrateClient;
use App\PaymentPlatform\Providers\CGrate\CGrateException;
use App\PaymentPlatform\Support\CGrateUatLogger;
use Illuminate\Console\Command;

class CGrateCashDepositIssuersCommand extends Command
{
    use InteractsWithCGrateConfig;

    protected $signature = 'cgrate:cash-deposit-issuers
                            {--json : Output issuer list as JSON}';

    protected $description = 'Fetch available cGrate cash-deposit issuer names for UAT bank/MM mapping';

    public function handle(CGrateClient $client, CGrateUatLogger $logger): int
    {
        if ($failure = $this->ensureCGrateConfigured()) {
            return $failure;
        }

        $this->info('Fetching available cash deposit issuers from cGrate...');
        $this->line('Endpoint: '.rtrim((string) config('cgrate.base_url'), '/').(string) config('cgrate.soap.endpoint_path'));

        try {
            $result = $client->getAvailableCashDepositIssuers();
        } catch (CGrateException $e) {
            $logger->log('issuer_discovery.failed', [
                'message' => $e->getMessage(),
                'context' => $e->context,
            ]);

            $this->error('Issuer discovery failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $issuers = $result['issuers'] ?? [];
        $raw = $result['raw'] ?? [];

        $logger->log('issuer_discovery.success', [
            'issuer_count' => count($issuers),
            'issuers' => $issuers,
            'raw' => $raw,
        ]);

        if ((bool) $this->option('json')) {
            $this->line(json_encode([
                'issuers' => $issuers,
                'raw' => $raw,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        if ($issuers === []) {
            $this->warn('No issuer names could be normalized. Check storage/logs/cgrate-uat.log for raw XML excerpt.');
        } else {
            $this->info('Available issuers ('.count($issuers).'):');
            foreach ($issuers as $issuer) {
                $this->line('  - '.$issuer);
            }
        }

        if ($raw !== []) {
            $this->newLine();
            $this->comment('Raw metadata (safe):');
            if (isset($raw['response_code'])) {
                $this->line('  response_code: '.($raw['response_code'] ?? 'null'));
            }
            if (! empty($raw['response_message'])) {
                $this->line('  response_message: '.$raw['response_message']);
            }
            $this->line('  See docs/CGRATE-DISBURSEMENT-UAT.md to map financial_institutions.name → issuerName.');
        }

        return self::SUCCESS;
    }
}
