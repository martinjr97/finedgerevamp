<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanArrearsAccrual;
use App\Models\LoanPaymentSchedule;
use App\Models\LoanProduct;
use App\Models\LoanRepayment;
use App\Models\Repayment;
use App\Services\Loans\LoanArrearsAccrualService;
use App\Services\Loans\LoanArrearsSummaryService;
use App\Services\LoanSettlementService;
use App\Services\RepaymentProcessingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LoanArrearsAccrualTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'arrears.engine_effective_date' => '2026-01-01',
            'arrears.npl_days_after_final_due' => 90,
            'arrears.timezone' => 'Africa/Lusaka',
        ]);
    }

    private function makeLoanWithSchedules(array $dueDates, float $amountEach = 1000.0, float $arrearRate = 0.01): Loan
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Arrears Co '.$suffix,
            'slug' => 'arrears-'.$suffix,
            'code' => 'AR'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Character',
            'code' => 'CHR-'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $product->id,
            'first_name' => 'Borrower',
            'last_name' => $suffix,
            'email' => 'arrears-'.$suffix.'@example.com',
            'phone' => '260955'.random_int(100000, 999999),
            'password' => '1234',
            'status' => 'active',
        ]);

        $firstDue = Carbon::parse($dueDates[0]);
        $lastDue = Carbon::parse($dueDates[count($dueDates) - 1]);

        $loan = Loan::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'loan_number' => 'AR-'.$suffix,
            'principal_amount' => $amountEach * count($dueDates),
            'processing_fee' => 0,
            'total_amount' => $amountEach * count($dueDates),
            'amount_paid' => 0,
            'outstanding_balance' => $amountEach * count($dueDates),
            'tenure_months' => count($dueDates),
            'loan_start_date' => $firstDue->copy()->subMonth()->toDateString(),
            'loan_end_date' => $lastDue->copy()->addMonths(1)->toDateString(),
            'first_payment_date' => $firstDue->toDateString(),
            'last_payment_date' => $lastDue->toDateString(),
            'accrual_type' => 'at_beginning',
            'interest_behavior' => Loan::INTEREST_BEHAVIOR_UPFRONT_FLAT,
            'arrear_rate' => $arrearRate,
            'status' => 'active',
            'disbursement_status' => 'completed',
            'disbursed_at' => now(),
        ]);

        foreach ($dueDates as $index => $due) {
            LoanPaymentSchedule::create([
                'loan_id' => $loan->id,
                'period_number' => $index + 1,
                'due_date' => $due,
                'expected_amount' => $amountEach,
                'principal_component' => $amountEach,
                'interest_component' => 0,
                'fee_component' => 0,
                'amount_paid' => 0,
                'remaining_amount' => $amountEach,
                'status' => 'upcoming',
                'days_overdue' => 0,
            ]);
        }

        return $loan->fresh(['paymentSchedules']);
    }

    public function test_first_missed_installment_starts_accrual_day_after_due(): void
    {
        $loan = $this->makeLoanWithSchedules(['2026-10-30']);
        $service = app(LoanArrearsAccrualService::class);

        $service->accrueLoan($loan, Carbon::parse('2026-10-30'), null, Carbon::parse('2026-01-01'), false);
        $this->assertSame(0, LoanArrearsAccrual::query()->count());

        $service->accrueLoan($loan, Carbon::parse('2026-10-31'), null, Carbon::parse('2026-01-01'), false);
        $this->assertSame(1, LoanArrearsAccrual::query()->count());
        $this->assertEqualsWithDelta(10.0, (float) LoanArrearsAccrual::first()->arrears_charge, 0.01);
    }

    public function test_multiple_missed_installments_accrue_independently(): void
    {
        $loan = $this->makeLoanWithSchedules(['2026-10-30', '2026-11-30', '2026-12-30']);
        $service = app(LoanArrearsAccrualService::class);

        $service->accrueLoan($loan, Carbon::parse('2026-10-31'), null, Carbon::parse('2026-01-01'), false);
        $this->assertEqualsWithDelta(10.0, (float) LoanArrearsAccrual::sum('arrears_charge'), 0.01);

        LoanArrearsAccrual::query()->delete();
        $loan->update(['arrears_last_accrual_date' => null]);

        $service->accrueLoan($loan, Carbon::parse('2026-12-01'), null, Carbon::parse('2026-01-01'), false);
        $dayRows = LoanArrearsAccrual::query()->whereDate('accrual_date', '2026-12-01')->get();
        $this->assertCount(2, $dayRows);
        $this->assertEqualsWithDelta(20.0, (float) $dayRows->sum('arrears_charge'), 0.01);

        LoanArrearsAccrual::query()->delete();
        $loan->update(['arrears_last_accrual_date' => null]);

        $service->accrueLoan($loan, Carbon::parse('2026-12-31'), null, Carbon::parse('2026-01-01'), false);
        $dayRows = LoanArrearsAccrual::query()->whereDate('accrual_date', '2026-12-31')->get();
        $this->assertCount(3, $dayRows);
        $this->assertEqualsWithDelta(30.0, (float) $dayRows->sum('arrears_charge'), 0.01);
    }

    public function test_partial_repayment_reduces_future_accrual_base(): void
    {
        $loan = $this->makeLoanWithSchedules(['2026-10-30']);
        $schedule = $loan->paymentSchedules->first();
        $service = app(LoanArrearsAccrualService::class);

        $service->accrueLoan($loan, Carbon::parse('2026-11-04'), null, Carbon::parse('2026-01-01'), false);

        $schedule->update([
            'amount_paid' => 600,
            'remaining_amount' => 400,
            'status' => 'partial',
        ]);

        $repayment = Repayment::create([
            'customer_id' => $loan->customer_id,
            'channel_id' => null,
            'repayment_number' => 'RP-'.Str::random(8),
            'total_amount' => 600,
            'status' => 'completed',
            'processed_at' => Carbon::parse('2026-11-05 10:00:00'),
        ]);

        LoanRepayment::create([
            'repayment_id' => $repayment->id,
            'loan_id' => $loan->id,
            'transaction_type' => LoanRepayment::TRANSACTION_TYPE_PAYMENT,
            'amount' => 600,
            'principal_amount' => 600,
            'interest_amount' => 0,
            'processing_fee_amount' => 0,
            'arrears_interest_amount' => 0,
            'outstanding_balance_before' => 1000,
            'outstanding_balance_after' => 400,
            'metadata' => ['effective_date' => '2026-11-05'],
        ]);

        $loan->update(['arrears_last_accrual_date' => '2026-11-04']);
        $service->accrueLoan($loan, Carbon::parse('2026-11-05'), null, Carbon::parse('2026-01-01'), false);

        $row = LoanArrearsAccrual::query()->whereDate('accrual_date', '2026-11-05')->first();
        $this->assertNotNull($row);
        $this->assertEqualsWithDelta(400.0, (float) $row->opening_overdue_amount, 0.01);
        $this->assertEqualsWithDelta(4.0, (float) $row->arrears_charge, 0.01);
    }

    public function test_fully_paid_installment_stops_accrual(): void
    {
        $loan = $this->makeLoanWithSchedules(['2026-10-30']);
        $schedule = $loan->paymentSchedules->first();
        $schedule->update([
            'amount_paid' => 1000,
            'remaining_amount' => 0,
            'status' => 'paid',
        ]);

        $repayment = Repayment::create([
            'customer_id' => $loan->customer_id,
            'channel_id' => null,
            'repayment_number' => 'RP-'.Str::random(8),
            'total_amount' => 1000,
            'status' => 'completed',
            'processed_at' => Carbon::parse('2026-10-30 12:00:00'),
        ]);

        LoanRepayment::create([
            'repayment_id' => $repayment->id,
            'loan_id' => $loan->id,
            'transaction_type' => LoanRepayment::TRANSACTION_TYPE_PAYMENT,
            'amount' => 1000,
            'principal_amount' => 1000,
            'interest_amount' => 0,
            'processing_fee_amount' => 0,
            'arrears_interest_amount' => 0,
            'outstanding_balance_before' => 1000,
            'outstanding_balance_after' => 0,
            'metadata' => ['effective_date' => '2026-10-30'],
        ]);

        app(LoanArrearsAccrualService::class)->accrueLoan(
            $loan,
            Carbon::parse('2026-11-05'),
            null,
            Carbon::parse('2026-01-01'),
            false
        );

        $this->assertSame(0, LoanArrearsAccrual::query()->count());
    }

    public function test_historical_rate_snapshot_on_loan_not_product(): void
    {
        $loan = $this->makeLoanWithSchedules(['2026-10-30'], 1000, 0.02);
        $loan->update(['arrear_rate' => 0.02]);

        app(LoanArrearsAccrualService::class)->accrueLoan(
            $loan->fresh(),
            Carbon::parse('2026-10-31'),
            null,
            Carbon::parse('2026-01-01'),
            false
        );

        $row = LoanArrearsAccrual::first();
        $this->assertEqualsWithDelta(0.02, (float) $row->arrear_rate, 0.00001);
        $this->assertEqualsWithDelta(20.0, (float) $row->arrears_charge, 0.01);

        $newLoan = $this->makeLoanWithSchedules(['2026-10-30'], 1000, 0.05);
        $newLoan->update(['arrear_rate' => 0.05]);
        LoanArrearsAccrual::query()->delete();

        app(LoanArrearsAccrualService::class)->accrueLoan(
            $newLoan->fresh(),
            Carbon::parse('2026-10-31'),
            null,
            Carbon::parse('2026-01-01'),
            false
        );

        $this->assertEqualsWithDelta(50.0, (float) LoanArrearsAccrual::first()->arrears_charge, 0.01);
    }

    public function test_npl_cutoff_uses_ninety_calendar_days_not_three_months(): void
    {
        $loan = $this->makeLoanWithSchedules(['2026-01-31']);
        $service = app(LoanArrearsAccrualService::class);
        $cutoff = $service->resolveNplCutoffDate($loan);

        $this->assertSame('2026-05-01', $cutoff->toDateString());

        $service->accrueLoan($loan, $cutoff->copy(), null, Carbon::parse('2026-01-01'), false);
        $this->assertSame(1, LoanArrearsAccrual::query()->whereDate('accrual_date', $cutoff->toDateString())->count());

        $loan->update(['arrears_last_accrual_date' => $cutoff->toDateString()]);
        LoanArrearsAccrual::query()->whereDate('accrual_date', '>', $cutoff->toDateString())->delete();

        $service->accrueLoan($loan, $cutoff->copy()->addDay(), null, Carbon::parse('2026-01-01'), false);
        $this->assertSame(0, LoanArrearsAccrual::query()->whereDate('accrual_date', $cutoff->copy()->addDay()->toDateString())->count());
    }

    public function test_npl_classification_after_cutoff_with_unpaid_balance(): void
    {
        $loan = $this->makeLoanWithSchedules(['2026-10-30']);
        app(LoanArrearsAccrualService::class)->accrueLoan(
            $loan,
            Carbon::parse('2027-04-01'),
            null,
            Carbon::parse('2026-01-01'),
            false
        );

        $loan->refresh();
        $this->assertSame('npl', $loan->performance_status);
        $this->assertNotNull($loan->npl_at);
    }

    public function test_settled_loan_does_not_accrue(): void
    {
        $loan = $this->makeLoanWithSchedules(['2026-10-30']);
        foreach ($loan->paymentSchedules as $schedule) {
            $schedule->update(['amount_paid' => $schedule->expected_amount, 'remaining_amount' => 0, 'status' => 'paid']);
        }
        $loan->update(['status' => 'settled', 'outstanding_balance' => 0]);

        app(LoanArrearsAccrualService::class)->accruePortfolio(Carbon::parse('2026-11-05'), null, $loan->id, false);
        $this->assertSame(0, LoanArrearsAccrual::where('loan_id', $loan->id)->count());
    }

    public function test_scheduler_catch_up_processes_missing_dates_once(): void
    {
        $loan = $this->makeLoanWithSchedules(['2026-10-30']);
        $loan->update(['arrears_last_accrual_date' => '2026-11-02']);

        $this->artisan('loans:accrue-arrears', [
            '--from' => '2026-11-03',
            '--to' => '2026-11-06',
            '--loan-id' => $loan->id,
        ])->assertSuccessful();

        $this->assertSame(4, LoanArrearsAccrual::where('loan_id', $loan->id)->count());

        $this->artisan('loans:accrue-arrears', [
            '--from' => '2026-11-03',
            '--to' => '2026-11-06',
            '--loan-id' => $loan->id,
        ])->assertSuccessful();

        $this->assertSame(4, LoanArrearsAccrual::where('loan_id', $loan->id)->count());
    }

    public function test_duplicate_cron_does_not_duplicate_accruals(): void
    {
        $loan = $this->makeLoanWithSchedules(['2026-10-30']);

        $this->artisan('loans:accrue-arrears', [
            '--date' => '2026-10-31',
            '--loan-id' => $loan->id,
        ])->assertSuccessful();

        $this->artisan('loans:accrue-arrears', [
            '--date' => '2026-10-31',
            '--loan-id' => $loan->id,
        ])->assertSuccessful();

        $this->assertSame(1, LoanArrearsAccrual::where('loan_id', $loan->id)->count());
    }

    public function test_statement_aggregates_match_daily_ledger_total(): void
    {
        $loan = $this->makeLoanWithSchedules(['2026-10-30']);
        app(LoanArrearsAccrualService::class)->accrueLoan(
            $loan,
            Carbon::parse('2026-11-04'),
            null,
            Carbon::parse('2026-01-01'),
            false
        );

        $summary = app(LoanArrearsSummaryService::class);
        $ledgerTotal = (float) LoanArrearsAccrual::where('loan_id', $loan->id)->sum('arrears_charge');
        $segments = $summary->statementSegments($loan->fresh());

        $this->assertEqualsWithDelta($ledgerTotal, array_sum(array_column($segments, 'arrears_interest')), 0.01);
    }

    public function test_payment_allocation_includes_arrears_interest_bucket(): void
    {
        $loan = $this->makeLoanWithSchedules(['2026-10-30']);
        app(LoanArrearsAccrualService::class)->accrueLoan(
            $loan,
            Carbon::parse('2026-11-04'),
            null,
            Carbon::parse('2026-01-01'),
            false
        );

        $allocation = $loan->fresh()->calculateRepaymentAllocation(50.0);
        $this->assertGreaterThan(0, $allocation['arrears_interest_amount']);
        $this->assertEqualsWithDelta(
            50.0,
            $allocation['arrears_interest_amount']
                + $allocation['principal_amount']
                + $allocation['interest_amount']
                + $allocation['processing_fee_amount'],
            0.02
        );
    }

    public function test_settlement_quote_includes_outstanding_arrears(): void
    {
        $loan = $this->makeLoanWithSchedules(['2026-10-30']);
        app(LoanArrearsAccrualService::class)->accrueLoan(
            $loan,
            Carbon::parse('2026-11-04'),
            null,
            Carbon::parse('2026-01-01'),
            false
        );

        $quote = app(LoanSettlementService::class)->quoteSettlement($loan, '2026-11-04');
        $this->assertGreaterThan(0, (float) $quote['arrears_interest_outstanding']);
        $this->assertGreaterThan((float) $quote['principal_remaining'], (float) $quote['payoff_amount']);
    }

    public function test_no_compounding_on_previous_arrears_charges(): void
    {
        $loan = $this->makeLoanWithSchedules(['2026-10-30']);
        $service = app(LoanArrearsAccrualService::class);
        $service->accrueLoan($loan, Carbon::parse('2026-11-01'), null, Carbon::parse('2026-01-01'), false);

        $rows = LoanArrearsAccrual::orderBy('accrual_date')->get();
        $this->assertCount(2, $rows);
        $this->assertEqualsWithDelta(10.0, (float) $rows[0]->arrears_charge, 0.01);
        $this->assertEqualsWithDelta(10.0, (float) $rows[1]->arrears_charge, 0.01);
        $this->assertEqualsWithDelta(1000.0, (float) $rows[1]->opening_overdue_amount, 0.01);
    }

    public function test_engine_effective_date_blocks_historical_back_charge(): void
    {
        config(['arrears.engine_effective_date' => '2026-11-10']);
        $loan = $this->makeLoanWithSchedules(['2026-10-30']);

        $this->artisan('loans:accrue-arrears', [
            '--to' => '2026-11-15',
            '--loan-id' => $loan->id,
        ])->assertSuccessful();

        $this->assertSame(
            6,
            LoanArrearsAccrual::where('loan_id', $loan->id)->count()
        );
        $this->assertSame(
            0,
            LoanArrearsAccrual::where('loan_id', $loan->id)->whereDate('accrual_date', '<', '2026-11-10')->count()
        );
    }

    public function test_k3000_example_daily_charges_on_combined_bases(): void
    {
        $loan = $this->makeLoanWithSchedules(['2026-10-30', '2026-11-30', '2026-12-30'], 1000, 0.01);
        $service = app(LoanArrearsAccrualService::class);

        $service->accrueLoan($loan, Carbon::parse('2026-10-31'), null, Carbon::parse('2026-01-01'), false);
        $this->assertEqualsWithDelta(10.0, (float) LoanArrearsAccrual::whereDate('accrual_date', '2026-10-31')->sum('arrears_charge'), 0.01);

        $loan->update(['arrears_last_accrual_date' => null]);
        LoanArrearsAccrual::query()->delete();

        $service->accrueLoan($loan, Carbon::parse('2026-12-01'), null, Carbon::parse('2026-01-01'), false);
        $this->assertEqualsWithDelta(20.0, (float) LoanArrearsAccrual::whereDate('accrual_date', '2026-12-01')->sum('arrears_charge'), 0.01);

        $loan->update(['arrears_last_accrual_date' => null]);
        LoanArrearsAccrual::query()->delete();

        $service->accrueLoan($loan, Carbon::parse('2026-12-31'), null, Carbon::parse('2026-01-01'), false);
        $this->assertEqualsWithDelta(30.0, (float) LoanArrearsAccrual::whereDate('accrual_date', '2026-12-31')->sum('arrears_charge'), 0.01);
    }

    public function test_example_b_payment_allocates_arrears_before_schedule_without_double_counting(): void
    {
        $loan = $this->makeLoanWithSchedules(['2026-10-30']);
        app(LoanArrearsAccrualService::class)->accrueLoan(
            $loan,
            Carbon::parse('2026-11-09'),
            null,
            Carbon::parse('2026-01-01'),
            false
        );

        $this->assertEqualsWithDelta(100.0, app(LoanArrearsSummaryService::class)->outstandingArrearsInterest($loan->fresh()), 0.01);

        $repayment = Repayment::create([
            'customer_id' => $loan->customer_id,
            'channel_id' => null,
            'repayment_number' => 'RP-'.Str::random(8),
            'total_amount' => 600,
            'status' => 'completed',
            'processed_at' => Carbon::parse('2026-11-10 12:00:00'),
            'metadata' => ['effective_date' => '2026-11-10'],
        ]);

        app(RepaymentProcessingService::class)->applyRepaymentToLoans(
            $repayment,
            $loan->customer,
            'partial',
            $loan->id,
            600.0,
            'test'
        );

        $loan->refresh();
        $schedule = $loan->paymentSchedules->first();

        $this->assertEqualsWithDelta(500.0, (float) $schedule->remaining_amount, 0.01);
        $this->assertEqualsWithDelta(0.0, app(LoanArrearsSummaryService::class)->outstandingArrearsInterest($loan), 0.01);

        $loanRepayment = LoanRepayment::where('loan_id', $loan->id)->first();
        $this->assertEqualsWithDelta(100.0, (float) $loanRepayment->arrears_interest_amount, 0.01);
        $this->assertEqualsWithDelta(500.0, $loanRepayment->scheduleAppliedAmount(), 0.01);

        $loan->update(['arrears_last_accrual_date' => '2026-11-09']);
        app(LoanArrearsAccrualService::class)->accrueLoan(
            $loan,
            Carbon::parse('2026-11-10'),
            null,
            Carbon::parse('2026-01-01'),
            false
        );

        $row = LoanArrearsAccrual::query()->whereDate('accrual_date', '2026-11-10')->first();
        $this->assertEqualsWithDelta(500.0, (float) $row->opening_overdue_amount, 0.01);
    }

    public function test_example_c_small_payment_hits_arrears_only_and_leaves_installment_unchanged(): void
    {
        $loan = $this->makeLoanWithSchedules(['2026-10-30']);
        app(LoanArrearsAccrualService::class)->accrueLoan(
            $loan,
            Carbon::parse('2026-11-19'),
            null,
            Carbon::parse('2026-01-01'),
            false
        );

        $this->assertEqualsWithDelta(200.0, app(LoanArrearsSummaryService::class)->outstandingArrearsInterest($loan->fresh()), 0.01);

        $repayment = Repayment::create([
            'customer_id' => $loan->customer_id,
            'channel_id' => null,
            'repayment_number' => 'RP-'.Str::random(8),
            'total_amount' => 100,
            'status' => 'completed',
            'processed_at' => now(),
        ]);

        app(RepaymentProcessingService::class)->applyRepaymentToLoans(
            $repayment,
            $loan->customer,
            'partial',
            $loan->id,
            100.0,
            'test'
        );

        $loan->refresh();
        $this->assertEqualsWithDelta(1000.0, (float) $loan->paymentSchedules->first()->remaining_amount, 0.01);
        $this->assertEqualsWithDelta(100.0, app(LoanArrearsSummaryService::class)->outstandingArrearsInterest($loan), 0.01);
    }

    public function test_same_day_effective_payment_reduces_that_days_accrual_base(): void
    {
        $loan = $this->makeLoanWithSchedules(['2026-10-30']);
        app(LoanArrearsAccrualService::class)->accrueLoan(
            $loan,
            Carbon::parse('2026-11-04'),
            null,
            Carbon::parse('2026-01-01'),
            false
        );

        $repayment = Repayment::create([
            'customer_id' => $loan->customer_id,
            'channel_id' => null,
            'repayment_number' => 'RP-'.Str::random(8),
            'total_amount' => 600,
            'status' => 'completed',
            'processed_at' => Carbon::parse('2026-11-05 08:00:00'),
        ]);

        LoanRepayment::create([
            'repayment_id' => $repayment->id,
            'loan_id' => $loan->id,
            'transaction_type' => LoanRepayment::TRANSACTION_TYPE_PAYMENT,
            'amount' => 600,
            'principal_amount' => 600,
            'interest_amount' => 0,
            'processing_fee_amount' => 0,
            'arrears_interest_amount' => 0,
            'outstanding_balance_before' => 1000,
            'outstanding_balance_after' => 400,
            'metadata' => ['effective_date' => '2026-11-05'],
        ]);

        $loan->paymentSchedules->first()->update([
            'amount_paid' => 600,
            'remaining_amount' => 400,
        ]);

        $loan->update(['arrears_last_accrual_date' => '2026-11-04']);

        $nov4 = LoanArrearsAccrual::whereDate('accrual_date', '2026-11-04')->first();
        $this->assertEqualsWithDelta(1000.0, (float) $nov4->opening_overdue_amount, 0.01);

        app(LoanArrearsAccrualService::class)->accrueLoan(
            $loan,
            Carbon::parse('2026-11-05'),
            null,
            Carbon::parse('2026-01-01'),
            false
        );

        $nov5 = LoanArrearsAccrual::whereDate('accrual_date', '2026-11-05')->first();
        $this->assertEqualsWithDelta(400.0, (float) $nov5->opening_overdue_amount, 0.01);
    }

    public function test_december_schedule_cutoff_is_march_thirtieth_seven(): void
    {
        $loan = $this->makeLoanWithSchedules(['2026-10-30', '2026-11-30', '2026-12-30']);
        $cutoff = app(LoanArrearsAccrualService::class)->resolveNplCutoffDate($loan);
        $this->assertSame('2027-03-30', $cutoff->toDateString());

        app(LoanArrearsAccrualService::class)->accrueLoan(
            $loan,
            $cutoff->copy(),
            null,
            Carbon::parse('2026-01-01'),
            false
        );
        $this->assertTrue(
            LoanArrearsAccrual::whereDate('accrual_date', $cutoff->toDateString())->exists()
        );

        $loan->update(['arrears_last_accrual_date' => $cutoff->toDateString()]);
        app(LoanArrearsAccrualService::class)->accrueLoan(
            $loan,
            $cutoff->copy()->addDay(),
            null,
            Carbon::parse('2026-01-01'),
            false
        );
        $this->assertFalse(
            LoanArrearsAccrual::whereDate('accrual_date', $cutoff->copy()->addDay()->toDateString())->exists()
        );
    }

    public function test_each_installment_accrual_day_count_through_shared_cutoff(): void
    {
        $loan = $this->makeLoanWithSchedules(['2026-10-30', '2026-11-30', '2026-12-30']);
        $cutoff = app(LoanArrearsAccrualService::class)->resolveNplCutoffDate($loan);

        app(LoanArrearsAccrualService::class)->accrueLoan(
            $loan,
            $cutoff->copy(),
            Carbon::parse('2026-10-31'),
            Carbon::parse('2026-01-01'),
            false
        );

        $schedules = $loan->paymentSchedules()->orderBy('due_date')->get();
        $expectedStarts = [
            '2026-10-31',
            '2026-12-01',
            '2026-12-31',
        ];

        foreach ($schedules as $index => $schedule) {
            $count = LoanArrearsAccrual::where('loan_payment_schedule_id', $schedule->id)->count();
            $start = Carbon::parse($expectedStarts[$index]);
            $expectedDays = $start->diffInDays($cutoff) + 1;
            $this->assertSame(
                (int) $expectedDays,
                $count,
                "Unexpected accrual day count for installment {$schedule->period_number}"
            );
        }
    }

    public function test_npl_does_not_clear_balances_or_close_loan(): void
    {
        $loan = $this->makeLoanWithSchedules(['2026-12-30']);
        app(LoanArrearsAccrualService::class)->accrueLoan(
            $loan,
            Carbon::parse('2027-04-01'),
            null,
            Carbon::parse('2026-01-01'),
            false
        );

        $loan->refresh();
        $this->assertSame('npl', $loan->performance_status);
        $this->assertSame('active', $loan->status);
        $this->assertGreaterThan(0, (float) $loan->paymentSchedules->first()->remaining_amount);
        $this->assertGreaterThan(0, app(LoanArrearsSummaryService::class)->outstandingArrearsInterest($loan));
    }
}
