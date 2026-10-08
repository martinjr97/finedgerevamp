<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanArrearsAccrual;
use App\Models\LoanPaymentSchedule;
use App\Models\LoanProduct;
use App\Models\LoanRepayment;
use App\Models\Repayment;
use App\Services\LoanRepaymentLedgerService;
use App\Services\LoanRepaymentRefundService;
use App\Services\Loans\LoanArrearsAccrualService;
use App\Services\Loans\LoanArrearsSummaryService;
use App\Services\LoanSettlementService;
use App\Services\RepaymentProcessingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LoanArrearsProductionHardeningTest extends TestCase
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
            'name' => 'Hard Co '.$suffix,
            'slug' => 'hard-'.$suffix,
            'code' => 'HC'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);
        $loanProduct = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Hard Product',
            'code' => 'HP-'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);
        $customer = Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $loanProduct->id,
            'first_name' => 'Hard',
            'last_name' => 'Customer',
            'email' => 'hard-'.$suffix.'@example.com',
            'phone' => '260966'.random_int(100000, 999999),
            'password' => '1234',
            'status' => 'active',
            'approval_status' => 'approved',
            'must_change_pin' => false,
        ]);

        $channel = Channel::create([
            'name' => 'Hard Channel '.$suffix,
            'code' => 'HCH-'.$suffix,
            'can_disburse' => true,
            'can_repay' => true,
            'is_active' => true,
        ]);

        $total = $amountEach * count($dueDates);
        $loan = Loan::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $loanProduct->id,
            'channel_id' => $channel->id,
            'loan_number' => Loan::generateLoanNumber($loanProduct),
            'principal_amount' => $total,
            'processing_fee' => 0,
            'total_amount' => $total,
            'amount_paid' => 0,
            'outstanding_balance' => $total,
            'tenure_months' => count($dueDates),
            'loan_start_date' => '2026-01-01',
            'loan_end_date' => end($dueDates),
            'first_payment_date' => $dueDates[0],
            'last_payment_date' => end($dueDates),
            'accrual_type' => 'daily',
            'status' => 'active',
            'disbursement_status' => 'completed',
            'disbursed_at' => Carbon::parse('2026-01-01'),
            'arrear_rate' => $arrearRate,
        ]);

        foreach ($dueDates as $index => $dueDate) {
            LoanPaymentSchedule::create([
                'loan_id' => $loan->id,
                'period_number' => $index + 1,
                'due_date' => $dueDate,
                'expected_amount' => $amountEach,
                'amount_paid' => 0,
                'remaining_amount' => $amountEach,
                'status' => 'overdue',
                'days_overdue' => 10,
            ]);
        }

        return $loan->fresh(['paymentSchedules', 'customer']);
    }

    private function postPartialPayment(Loan $loan, float $amount, string $effectiveDate): LoanRepayment
    {
        $repayment = Repayment::create([
            'customer_id' => $loan->customer_id,
            'channel_id' => null,
            'repayment_number' => 'RP-'.Str::random(8),
            'total_amount' => $amount,
            'status' => 'completed',
            'processed_at' => Carbon::parse($effectiveDate.' 15:00:00'),
            'metadata' => [
                'repayment_type' => 'partial',
                'loan_id' => $loan->id,
                'effective_date' => $effectiveDate,
            ],
        ]);

        app(RepaymentProcessingService::class)->applyRepaymentToLoans(
            $repayment,
            $loan->customer,
            'partial',
            $loan->id,
            $amount,
            'hardening test'
        );

        return LoanRepayment::query()->where('repayment_id', $repayment->id)->firstOrFail();
    }

    public function test_full_refund_reverses_schedule_and_arrears_inversely(): void
    {
        $loan = $this->makeLoanWithSchedules(['2026-10-30']);
        app(LoanArrearsAccrualService::class)->accrueLoan(
            $loan,
            Carbon::parse('2026-11-09'),
            null,
            Carbon::parse('2026-01-01'),
            false
        );

        $payment = $this->postPartialPayment($loan, 600.0, '2026-11-10');
        $loan->refresh();
        $schedule = $loan->paymentSchedules->first();

        $this->assertEqualsWithDelta(500.0, (float) $schedule->remaining_amount, 0.01);
        $this->assertEqualsWithDelta(0.0, app(LoanArrearsSummaryService::class)->outstandingArrearsInterest($loan), 0.01);

        app(LoanRepaymentRefundService::class)->applyRefund(
            $loan,
            $payment,
            600.0,
            'Full reversal test'
        );

        $loan->refresh();
        $schedule->refresh();

        $this->assertEqualsWithDelta(1000.0, (float) $schedule->remaining_amount, 0.01);
        $this->assertEqualsWithDelta(100.0, app(LoanArrearsSummaryService::class)->outstandingArrearsInterest($loan), 0.01);
    }

    public function test_partial_refund_restores_proportional_schedule_not_gross_amount(): void
    {
        $loan = $this->makeLoanWithSchedules(['2026-10-30']);
        app(LoanArrearsAccrualService::class)->accrueLoan(
            $loan,
            Carbon::parse('2026-11-09'),
            null,
            Carbon::parse('2026-01-01'),
            false
        );

        $payment = $this->postPartialPayment($loan, 600.0, '2026-11-10');
        $loan->refresh();
        $this->assertEqualsWithDelta(500.0, (float) $loan->paymentSchedules->first()->remaining_amount, 0.01);

        app(LoanRepaymentRefundService::class)->applyRefund(
            $loan,
            $payment,
            300.0,
            'Partial refund'
        );

        $loan->refresh();
        $schedule = $loan->paymentSchedules->first();

        $this->assertEqualsWithDelta(750.0, (float) $schedule->remaining_amount, 0.01);
        $this->assertEqualsWithDelta(50.0, app(LoanArrearsSummaryService::class)->outstandingArrearsInterest($loan), 0.01);
    }

    public function test_repayment_posting_persists_canonical_effective_date(): void
    {
        $loan = $this->makeLoanWithSchedules(['2026-10-30']);
        $payment = $this->postPartialPayment($loan, 100.0, '2026-11-05');

        $this->assertSame('2026-11-05', $payment->fresh()->effective_date->toDateString());
        $this->assertTrue($payment->effectiveDate()->equalTo(Carbon::parse('2026-11-05')));
    }

    public function test_same_day_payment_reconciles_accrual_after_early_cron(): void
    {
        $loan = $this->makeLoanWithSchedules(['2026-10-30']);
        $schedule = $loan->paymentSchedules->first();

        LoanArrearsAccrual::create([
            'loan_id' => $loan->id,
            'loan_payment_schedule_id' => $schedule->id,
            'accrual_date' => '2026-11-05',
            'arrear_rate' => 0.01,
            'opening_overdue_amount' => 1000,
            'arrears_charge' => 10,
            'days_overdue_on_date' => 6,
            'metadata' => ['daily_factor' => '0.01'],
        ]);
        $loan->update(['arrears_last_accrual_date' => '2026-11-05']);

        $row = LoanArrearsAccrual::query()->whereDate('accrual_date', '2026-11-05')->firstOrFail();
        $this->assertEqualsWithDelta(1000.0, (float) $row->opening_overdue_amount, 0.01);
        $this->assertEqualsWithDelta(10.0, (float) $row->arrears_charge, 0.01);

        $this->postPartialPayment($loan, 600.0, '2026-11-05');

        $row->refresh();
        $schedule->refresh();
        $this->assertEqualsWithDelta((float) $schedule->remaining_amount, (float) $row->opening_overdue_amount, 0.01);
        $this->assertLessThan(10.0, (float) $row->arrears_charge);
        $this->assertGreaterThan(3.0, (float) $row->arrears_charge);
        $this->assertSame(1, LoanArrearsAccrual::query()->whereDate('accrual_date', '2026-11-05')->count());
        $this->assertSame('payment_posted_same_day', data_get($row->metadata, 'adjustment_reason'));
    }

    public function test_settlement_clears_contractual_and_arrears_without_double_count(): void
    {
        $loan = $this->makeLoanWithSchedules(['2026-10-30']);
        $loan->update([
            'loan_end_date' => '2026-12-31',
            'last_payment_date' => '2026-12-31',
        ]);

        app(LoanArrearsAccrualService::class)->accrueLoan(
            $loan,
            Carbon::parse('2026-11-09'),
            null,
            Carbon::parse('2026-01-01'),
            false
        );

        app(LoanRepaymentLedgerService::class)->syncLoanLedger($loan->fresh());
        $loan->refresh();

        $this->assertEqualsWithDelta(100.0, app(LoanArrearsSummaryService::class)->outstandingArrearsInterest($loan), 0.01);
        $this->assertEqualsWithDelta(1100.0, (float) $loan->outstanding_balance, 0.01);

        $settlementDate = '2026-11-10';
        app(LoanSettlementService::class)->applySettlement($loan, [
            'amount' => 1100.0,
            'settlement_date' => $settlementDate,
        ]);

        $loan->refresh();
        $this->assertEqualsWithDelta(0.0, (float) $loan->paymentSchedules->sum('remaining_amount'), 0.01);
        $this->assertEqualsWithDelta(0.0, app(LoanArrearsSummaryService::class)->outstandingArrearsInterest($loan), 0.01);
        $this->assertContains($loan->status, ['settled', 'completed']);
    }

    public function test_npl_loan_accepts_payment_without_resuming_arrears_accrual(): void
    {
        $loan = $this->makeLoanWithSchedules(['2026-01-01']);
        $service = app(LoanArrearsAccrualService::class);
        $cutoff = $service->resolveNplCutoffDate($loan);

        $loan->update([
            'performance_status' => 'npl',
            'npl_at' => $cutoff->copy()->addDay()->toDateString(),
        ]);

        $service->accrueLoan($loan, $cutoff->copy()->addDays(30), null, Carbon::parse('2026-01-01'), false);
        $countBefore = LoanArrearsAccrual::query()->where('loan_id', $loan->id)->count();

        $this->postPartialPayment($loan, 500.0, $cutoff->copy()->addDays(15)->toDateString());

        $service->accrueLoan($loan, $cutoff->copy()->addDays(45), null, Carbon::parse('2026-01-01'), false);
        $this->assertSame($countBefore, LoanArrearsAccrual::query()->where('loan_id', $loan->id)->count());
        $loan->refresh();
        $this->assertSame('npl', $loan->performance_status);
    }

    public function test_production_command_fails_without_engine_effective_date(): void
    {
        config(['arrears.engine_effective_date' => null]);
        app()->detectEnvironment(fn () => 'production');

        $this->artisan('loans:accrue-arrears')
            ->assertFailed();
    }

    public function test_migrated_replay_uses_schedule_applied_amount(): void
    {
        $repayment = new LoanRepayment([
            'amount' => 600,
            'principal_amount' => 500,
            'interest_amount' => 0,
            'processing_fee_amount' => 0,
            'arrears_interest_amount' => 100,
            'transaction_type' => LoanRepayment::TRANSACTION_TYPE_PAYMENT,
        ]);

        $this->assertEqualsWithDelta(500.0, $repayment->scheduleAppliedAmount(), 0.01);
    }
}
