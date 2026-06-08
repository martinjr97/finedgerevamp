<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Models\Repayment;
use App\Models\LoanRepayment;
use App\Models\Channel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BackfillRepaymentRecords extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'loans:backfill-repayments {loan_id? : Specific loan ID to backfill, or all loans if not provided}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill repayment records for loans that were paid before the repayments table existed';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $loanId = $this->argument('loan_id');
        
        // Get loans with payments but no repayment records
        $query = Loan::where('amount_paid', '>', 0)
            ->whereDoesntHave('loanRepayments');
        
        if ($loanId) {
            $query->where('id', $loanId);
            $this->info("Backfilling repayment records for loan ID: {$loanId}");
        } else {
            $this->info("Backfilling repayment records for all loans with payments but no repayment records...");
        }
        
        $loans = $query->with(['customer', 'channel'])->get();
        
        if ($loans->isEmpty()) {
            $this->info("No loans found that need repayment records backfilled.");
            return 0;
        }
        
        $this->info("Found {$loans->count()} loan(s) to process.");
        
        $bar = $this->output->createProgressBar($loans->count());
        $bar->start();
        
        $processed = 0;
        $errors = 0;
        
        foreach ($loans as $loan) {
            try {
                DB::beginTransaction();
                
                // Get default repayment channel or use loan's channel if it supports repayment
                $channel = null;
                if ($loan->channel && $loan->channel->can_repay) {
                    $channel = $loan->channel;
                } else {
                    $channel = Channel::where('is_active', true)->where('can_repay', true)->first();
                }
                
                if (!$channel) {
                    throw new \Exception('No repayment channel available. Please configure channels first.');
                }
                
                // Create a repayment record for the existing payment
                $repayment = Repayment::create([
                    'customer_id' => $loan->customer_id,
                    'channel_id' => $channel->id,
                    'repayment_number' => Repayment::generateRepaymentNumber(),
                    'total_amount' => $loan->amount_paid,
                    'phone_number' => $loan->disbursement_phone_number ?? $loan->customer->phone,
                    'status' => 'completed',
                    'processed_at' => $loan->loan_settled_date 
                        ? Carbon::parse($loan->loan_settled_date) 
                        : ($loan->updated_at ?? now()),
                    'metadata' => [
                        'backfilled' => true,
                        'backfilled_at' => now()->toIso8601String(),
                        'original_loan_settled_date' => $loan->loan_settled_date?->toDateString(),
                    ],
                ]);
                
                // Use the Loan model's helper method to calculate repayment allocation
                // This ensures principal + interest + processing_fee = paymentAmount
                $paymentAmount = $loan->amount_paid;
                $allocation = $loan->calculateRepaymentAllocation($paymentAmount);
                
                $principalPaid = $allocation['principal_amount'];
                $interestPaid = $allocation['interest_amount'];
                $processingFeePaid = $allocation['processing_fee_amount'];
                
                // Verify the allocation sums correctly (should always be true)
                $totalAllocated = $principalPaid + $interestPaid + $processingFeePaid;
                if (abs($totalAllocated - $paymentAmount) > 0.01) {
                    // If there's a rounding discrepancy, adjust principal
                    $principalPaid += ($paymentAmount - $totalAllocated);
                    $principalPaid = max(0, $principalPaid);
                }
                
                // Get balance before payment (estimated)
                $outstandingBefore = $loan->outstanding_balance + $paymentAmount;
                $outstandingAfter = $loan->outstanding_balance;
                
                // Create loan repayment record
                LoanRepayment::create([
                    'repayment_id' => $repayment->id,
                    'loan_id' => $loan->id,
                    'amount' => $paymentAmount,
                    'principal_amount' => round($principalPaid, 2),
                    'interest_amount' => round($interestPaid, 2),
                    'processing_fee_amount' => round($processingFeePaid, 2),
                    'outstanding_balance_before' => $outstandingBefore,
                    'outstanding_balance_after' => $outstandingAfter,
                    'notes' => 'Backfilled repayment record - estimated splits based on loan structure',
                ]);
                
                // Update loan status to 'settled' if fully paid
                if ($loan->outstanding_balance <= 0 && in_array($loan->status, ['completed', 'active', 'approved'])) {
                    $loan->update(['status' => 'settled']);
                }
                
                DB::commit();
                $processed++;
                
            } catch (\Exception $e) {
                DB::rollBack();
                $errors++;
                $this->error("\nError processing loan {$loan->loan_number}: " . $e->getMessage());
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine(2);
        
        $this->info("✓ Successfully processed: {$processed} loan(s)");
        if ($errors > 0) {
            $this->warn("✗ Errors encountered: {$errors} loan(s)");
        }
        
        return 0;
    }
}
