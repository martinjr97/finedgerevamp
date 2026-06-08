<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\CustomerRegistrationRequest;
use App\Models\Loan;
use App\Models\Repayment;
use App\Models\Wallet;
use App\Support\PhoneNumberFormatter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class AuditZambianPhoneFormatsCommand extends Command
{
    protected $signature = 'phones:audit-zambian-format {--limit=20 : Max invalid samples to print per field}';

    protected $description = 'Report customer and disbursement phone values that do not match 260XXXXXXXXX format';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $totalInvalid = 0;

        $checks = [
            ['Customers', 'phone', Customer::query()->whereNotNull('phone')->where('phone', '!=', '')],
            ['Customers', 'next_of_kin_phone', Customer::query()->whereNotNull('next_of_kin_phone')->where('next_of_kin_phone', '!=', '')],
            ['Loans', 'disbursement_phone_number', Loan::query()->whereNotNull('disbursement_phone_number')->where('disbursement_phone_number', '!=', '')],
            ['Repayments', 'phone_number', Repayment::query()->whereNotNull('phone_number')->where('phone_number', '!=', '')],
        ];

        if (Schema::hasTable('customer_registration_requests')) {
            $checks[] = ['Registration requests', 'phone', CustomerRegistrationRequest::query()->whereNotNull('phone')->where('phone', '!=', '')];
        }

        if (Schema::hasTable('wallets')) {
            $checks[] = ['Wallets', 'wallet_number', Wallet::query()->whereNotNull('wallet_number')->where('wallet_number', '!=', '')];
        }

        foreach ($checks as [$label, $column, $query]) {
            $invalid = (clone $query)->get()->filter(
                fn ($row) => ! PhoneNumberFormatter::isValid((string) $row->{$column})
            );

            $count = $invalid->count();
            $totalInvalid += $count;

            $this->line('');
            $this->info("{$label} → {$column}: {$count} invalid");

            foreach ($invalid->take($limit) as $row) {
                $this->line(sprintf(
                    '  id=%s value=%s',
                    $row->getKey(),
                    $row->{$column}
                ));
            }

            if ($count > $limit) {
                $this->line('  … '.($count - $limit).' more');
            }
        }

        $this->newLine();
        if ($totalInvalid === 0) {
            $this->info('All audited phone fields match the Zambian mobile format.');

            return self::SUCCESS;
        }

        $this->warn("Total invalid records: {$totalInvalid}. No data was modified.");

        return self::FAILURE;
    }
}
