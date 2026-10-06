<?php

namespace App\Console\Commands;

use App\Models\Loan;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AccrueLoanInterest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'loans:accrue-interest
        {--date= : Single accrual date (Y-m-d)}
        {--loan-id= : Limit to one loan}
        {--from= : Catch-up start date (Y-m-d, inclusive)}
        {--to= : Catch-up end date (Y-m-d, inclusive; defaults to yesterday)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Accrue daily interest for loans with daily accrual type';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('from')) {
            return $this->handleCatchUpRange();
        }

        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : Carbon::today();

        $loans = $this->eligibleLoans($date);
        $this->info("Processing interest accrual for date: {$date->format('Y-m-d')}");
        $this->info("Found {$loans->count()} active loans with daily accrual type");

        [$processed, $skipped] = $this->accrueLoansForDate($loans, $date);

        $this->info("\nCompleted: {$processed} loans processed, {$skipped} skipped");

        return Command::SUCCESS;
    }

    private function handleCatchUpRange(): int
    {
        $from = Carbon::parse($this->option('from'))->startOfDay();
        $to = $this->option('to')
            ? Carbon::parse($this->option('to'))->startOfDay()
            : Carbon::yesterday()->startOfDay();

        if ($from->gt($to)) {
            $this->error('--from must be on or before --to');

            return Command::FAILURE;
        }

        $totalProcessed = 0;
        $totalSkipped = 0;

        for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
            $loans = $this->eligibleLoans($date);
            [$processed, $skipped] = $this->accrueLoansForDate($loans, $date, quiet: true);
            $totalProcessed += $processed;
            $totalSkipped += $skipped;
        }

        $this->info("Catch-up complete from {$from->toDateString()} to {$to->toDateString()}: {$totalProcessed} accruals created, {$totalSkipped} skipped.");

        return Command::SUCCESS;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Loan>
     */
    private function eligibleLoans(Carbon $date)
    {
        $query = Loan::query()
            ->where('accrual_type', 'daily')
            ->where('status', 'active')
            ->whereDate('loan_start_date', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('loan_end_date')->orWhereDate('loan_end_date', '>=', $date);
            });

        if ($this->option('loan-id')) {
            $query->where('id', (int) $this->option('loan-id'));
        }

        return $query->get();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Loan>  $loans
     * @return array{0: int, 1: int}
     */
    private function accrueLoansForDate($loans, Carbon $date, bool $quiet = false): array
    {
        $processed = 0;
        $skipped = 0;

        foreach ($loans as $loan) {
            try {
                $existingAccrual = $loan->accruals()
                    ->whereDate('accrual_date', $date)
                    ->first();

                if ($existingAccrual) {
                    $skipped++;

                    continue;
                }

                $loan->accrueInterestForDate($date);
                $processed++;

                if (! $quiet) {
                    $this->info("✓ Processed loan {$loan->loan_number}");
                }
            } catch (\Exception $e) {
                $this->error("✗ Failed to process loan {$loan->loan_number}: {$e->getMessage()}");
            }
        }

        return [$processed, $skipped];
    }
}
