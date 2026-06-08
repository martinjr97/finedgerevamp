<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Support\CreditScoreService;
use Illuminate\Console\Command;

class CalculateCreditScores extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'credit-scores:calculate {--customer-id= : Calculate for specific customer ID} {--all : Calculate for all customers}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate and update credit scores for customers';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $customerId = $this->option('customer-id');
        $all = $this->option('all');

        if ($customerId) {
            $customer = Customer::find($customerId);
            if (!$customer) {
                $this->error("Customer with ID {$customerId} not found.");
                return 1;
            }

            $this->info("Calculating credit score for customer: {$customer->full_name} (ID: {$customer->id})...");
            CreditScoreService::updateCreditScore($customer);
            $this->info("Credit score updated: {$customer->credit_score}");
            return 0;
        }

        if (!$all) {
            $this->error("Please specify --customer-id=ID or --all to calculate scores.");
            return 1;
        }

        $this->info("Calculating credit scores for all customers...");
        
        $customers = Customer::where('status', 'active')->get();
        $total = $customers->count();
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $updated = 0;
        foreach ($customers as $customer) {
            try {
                CreditScoreService::updateCreditScore($customer);
                $updated++;
            } catch (\Exception $e) {
                $this->newLine();
                $this->error("Failed to calculate score for customer {$customer->id}: " . $e->getMessage());
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Completed! Updated credit scores for {$updated}/{$total} customers.");
        
        return 0;
    }
}
