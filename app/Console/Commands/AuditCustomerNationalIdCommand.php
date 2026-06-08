<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\CustomerRegistrationRequest;
use App\Rules\ZambianNrcNumber;
use App\Support\NationalIdRules;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class AuditCustomerNationalIdCommand extends Command
{
    protected $signature = 'customers:audit-national-id {--limit=20 : Max sample rows to print per issue}';

    protected $description = 'Report customers and registration requests with missing or invalid national ID data';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $issues = 0;

        $issues += $this->auditCustomers($limit);
        if (Schema::hasTable('customer_registration_requests')) {
            $issues += $this->auditRegistrationRequests($limit);
        }

        $tpinMissing = Customer::query()
            ->where(fn ($q) => $q->whereNull('tpin')->orWhere('tpin', ''))
            ->count();

        $this->newLine();
        $this->line("TPIN missing (informational, not an error): {$tpinMissing}");

        if ($issues === 0) {
            $this->info('No national ID data issues found.');

            return self::SUCCESS;
        }

        $this->warn("Total issue categories with findings: {$issues}. No data was modified.");

        return self::FAILURE;
    }

    private function auditCustomers(int $limit): int
    {
        $issues = 0;

        $missingId = Customer::query()
            ->where(fn ($q) => $q->whereNull('national_id')->orWhere('national_id', ''))
            ->get();

        $issues += $this->report('Customers missing national_id', $missingId, $limit, fn ($row) => "id={$row->id}");

        $missingType = Customer::query()
            ->whereNotNull('national_id')
            ->where('national_id', '!=', '')
            ->where(fn ($q) => $q->whereNull('national_id_type')->orWhere('national_id_type', ''))
            ->get();

        $issues += $this->report('Customers missing national_id_type', $missingType, $limit, fn ($row) => "id={$row->id} national_id={$row->national_id}");

        $invalidNrc = Customer::query()
            ->where('national_id_type', NationalIdRules::TYPE_NRC)
            ->whereNotNull('national_id')
            ->where('national_id', '!=', '')
            ->get()
            ->filter(fn ($row) => ! ZambianNrcNumber::isValid($row->national_id));

        $issues += $this->report('Customers with invalid NRC format (type=nrc)', $invalidNrc, $limit, fn ($row) => "id={$row->id} value={$row->national_id}");

        return $issues > 0 ? 1 : 0;
    }

    private function auditRegistrationRequests(int $limit): int
    {
        $issues = 0;

        $missingId = CustomerRegistrationRequest::query()
            ->where(fn ($q) => $q->whereNull('national_id')->orWhere('national_id', ''))
            ->get();

        $issues += $this->report('Registration requests missing national_id', $missingId, $limit, fn ($row) => "id={$row->id} ref={$row->reference}");

        $missingType = CustomerRegistrationRequest::query()
            ->whereNotNull('national_id')
            ->where('national_id', '!=', '')
            ->where(fn ($q) => $q->whereNull('national_id_type')->orWhere('national_id_type', ''))
            ->get();

        $issues += $this->report('Registration requests missing national_id_type', $missingType, $limit, fn ($row) => "id={$row->id} ref={$row->reference}");

        return $issues > 0 ? 1 : 0;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>  $rows
     */
    private function report(string $label, $rows, int $limit, callable $formatter): int
    {
        $count = $rows->count();
        $this->line('');
        $this->info("{$label}: {$count}");

        foreach ($rows->take($limit) as $row) {
            $this->line('  '.$formatter($row));
        }

        if ($count > $limit) {
            $this->line('  … '.($count - $limit).' more');
        }

        return $count > 0 ? 1 : 0;
    }
}
