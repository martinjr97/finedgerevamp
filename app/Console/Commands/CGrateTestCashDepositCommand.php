<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\InteractsWithCGrateConfig;
use App\PaymentPlatform\Providers\CGrate\CGrateClient;
use App\PaymentPlatform\Providers\CGrate\CGrateException;
use App\PaymentPlatform\Support\CGrateUatLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CGrateTestCashDepositCommand extends Command
{
    use InteractsWithCGrateConfig;

    protected $signature = 'cgrate:test-cash-deposit
                            {--amount=5 : Amount in ZMW}
                            {--account= : Customer account (mobile or bank account number)}
                            {--issuer= : Issuer name exactly as cGrate expects (e.g. Airtel, MTN, bank name)}
                            {--reference= : Depositor reference (auto-generated if omitted)}
                            {--query : Query status after initiate using queryCustomerPayment}
                            {--dry-run : Show payload only; do not call cGrate}
                            {--force : Skip confirmation prompt for live payout}';

    protected $description = 'Provider-level UAT for processCashDeposit (does not touch loans or FineEdge wallets)';

    public function handle(CGrateClient $client, CGrateUatLogger $logger): int
    {
        if ($failure = $this->ensureCGrateConfigured()) {
            return $failure;
        }

        $account = trim((string) $this->option('account'));
        $issuer = trim((string) $this->option('issuer'));

        if ($account === '' || $issuer === '') {
            $this->error('Both --account and --issuer are required.');

            return self::FAILURE;
        }

        $amount = number_format((float) $this->option('amount'), 2, '.', '');
        if ((float) $amount <= 0) {
            $this->error('--amount must be greater than zero.');

            return self::FAILURE;
        }

        $reference = trim((string) $this->option('reference'));
        if ($reference === '') {
            $reference = 'FINEDGE-UAT-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6));
        }

        $payload = [
            'transactionAmount' => $amount,
            'customerAccount' => $account,
            'issuerName' => $issuer,
            'depositorReference' => $reference,
        ];

        $this->table(['Field', 'Value'], collect($payload)->map(fn ($v, $k) => [$k, $v])->values()->all());

        $this->warn('This command sends a REAL cGrate cash deposit when not using --dry-run.');
        $this->warn('It does NOT update loans or debit FineEdge wallets — provider UAT only.');

        if ((bool) $this->option('dry-run')) {
            $logger->log('test_cash_deposit.dry_run', ['payload' => $payload]);

            $this->info('Dry run complete. No request sent.');
            $this->line('depositorReference: '.$reference);

            return self::SUCCESS;
        }

        if (! (bool) $this->option('force')) {
            if (! $this->confirm('Send live processCashDeposit to cGrate with the payload above?', false)) {
                $this->comment('Aborted.');

                return self::SUCCESS;
            }
        }

        $logger->log('test_cash_deposit.initiate.request', ['payload' => $payload]);

        try {
            $response = $client->processCashDeposit(
                transactionAmount: $amount,
                customerAccount: $account,
                issuerName: $issuer,
                depositorReference: $reference,
            );
        } catch (CGrateException $e) {
            $logger->log('test_cash_deposit.initiate.failed', [
                'payload' => $payload,
                'message' => $e->getMessage(),
                'context' => $e->context,
            ]);

            $this->error('processCashDeposit failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $logger->log('test_cash_deposit.initiate.response', [
            'payload' => $payload,
            'response' => $response->toArray(),
        ]);

        $this->info('processCashDeposit response:');
        $this->line('  depositorReference: '.$reference);
        $this->line('  responseCode: '.($response->responseCode ?? 'null'));
        $this->line('  responseMessage: '.$response->responseMessage);
        $this->line('  paymentId: '.($response->paymentId ?? 'null'));
        $this->comment('Record these values in docs/CGRATE-DISBURSEMENT-UAT.md response capture table.');

        if ((bool) $this->option('query')) {
            return $this->runStatusQuery($client, $logger, $reference);
        }

        return self::SUCCESS;
    }

    private function runStatusQuery(CGrateClient $client, CGrateUatLogger $logger, string $reference): int
    {
        $this->newLine();
        $this->info('Querying queryCustomerPayment('.$reference.')...');

        $logger->log('test_cash_deposit.query.request', [
            'payment_reference' => $reference,
        ]);

        try {
            $response = $client->queryCustomerPayment($reference);
        } catch (CGrateException $e) {
            $logger->log('test_cash_deposit.query.failed', [
                'payment_reference' => $reference,
                'message' => $e->getMessage(),
                'context' => $e->context,
            ]);

            $this->error('queryCustomerPayment failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $logger->log('test_cash_deposit.query.response', [
            'payment_reference' => $reference,
            'response' => $response->toArray(),
        ]);

        $this->info('queryCustomerPayment response:');
        $this->line('  responseCode: '.($response->responseCode ?? 'null'));
        $this->line('  responseMessage: '.$response->responseMessage);
        $this->line('  paymentId: '.($response->paymentId ?? 'null'));

        return self::SUCCESS;
    }
}
