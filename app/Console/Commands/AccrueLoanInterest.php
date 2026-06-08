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
    protected $signature = 'loans:accrue-interest {--date=}';

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
        $date = $this->option('date') 
            ? Carbon::parse($this->option('date')) 
            : Carbon::today();

        $this->info("Processing interest accrual for date: {$date->format('Y-m-d')}");

        // Get all active loans with daily accrual type
        $loans = Loan::where('accrual_type', 'daily')
            ->where('status', 'active')
            ->whereDate('loan_start_date', '<=', $date)
            ->whereDate('loan_end_date', '>=', $date)
            ->get();

        $this->info("Found {$loans->count()} active loans with daily accrual type");

        $processed = 0;
        $skipped = 0;

        foreach ($loans as $loan) {
            try {
                // Check if accrual already exists for this date
                $existingAccrual = $loan->accruals()
                    ->whereDate('accrual_date', $date)
                    ->first();

                if ($existingAccrual) {
                    $this->warn("Loan {$loan->loan_number} already has accrual for {$date->format('Y-m-d')}");
                    $skipped++;
                    continue;
                }

                // Accrue interest for this date
                $loan->accrueInterestForDate($date);
                $processed++;

                $this->info("✓ Processed loan {$loan->loan_number}");
            } catch (\Exception $e) {
                $this->error("✗ Failed to process loan {$loan->loan_number}: {$e->getMessage()}");
            }
        }

        $this->info("\nCompleted: {$processed} loans processed, {$skipped} skipped");
        
        return Command::SUCCESS;
    }
}
