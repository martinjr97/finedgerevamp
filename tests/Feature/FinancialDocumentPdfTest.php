<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Channel;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\LoanRate;
use App\Models\LoanRateType;
use App\Services\LoanPricingService;
use App\Support\Pdf\FinancialDocumentBranding;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class FinancialDocumentPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_admin_can_download_repayment_schedule_pdf(): void
    {
        $loan = $this->createScheduledLoan(tenureMonths: 3);
        $admin = $this->makeAdmin(['loans.view']);
        $paidBefore = (float) $loan->amount_paid;
        $outstandingBefore = (float) $loan->outstanding_balance;
        $scheduleCountBefore = $loan->paymentSchedules()->count();

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.loans.schedule-pdf', $loan));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');

        $disposition = (string) $response->headers->get('content-disposition');
        $this->assertStringContainsString('repayment-schedule-', $disposition);
        $this->assertStringContainsString($loan->loan_number, $disposition);
        $this->assertStringContainsString('.pdf', $disposition);

        $this->assertGreaterThan(1000, strlen((string) $response->getContent()));

        $loan->refresh();
        $this->assertSame($paidBefore, (float) $loan->amount_paid);
        $this->assertSame($outstandingBefore, (float) $loan->outstanding_balance);
        $this->assertSame($scheduleCountBefore, $loan->paymentSchedules()->count());
    }

    public function test_schedule_pdf_uses_authoritative_plan_totals_and_omits_default_interest_when_absent(): void
    {
        $loan = $this->createScheduledLoan(tenureMonths: 3);
        $schedule = $loan->getRepaymentSchedule();
        $plan = $loan->getSchedulePlan();

        $this->assertEqualsWithDelta(
            (float) $plan['schedule_total'],
            (float) collect($schedule)->sum('expected_amount'),
            0.05
        );

        $html = $this->renderScheduleHtml($loan);

        $this->assertStringContainsString('Loan Repayment Schedule', $html);
        $this->assertStringContainsString($loan->loan_number, $html);
        $this->assertStringContainsString('Schedule Borrower', $html);
        $this->assertStringContainsString(FinancialDocumentBranding::formatMoney($plan['principal']), $html);
        $this->assertStringContainsString(FinancialDocumentBranding::formatMoney($plan['schedule_total']), $html);
        $this->assertStringContainsString(FinancialDocumentBranding::formatMoney((float) collect($schedule)->sum('expected_amount')), $html);
        $this->assertStringContainsString(FinancialDocumentBranding::formatMoney((float) collect($schedule)->sum('amount_paid')), $html);
        $this->assertStringContainsString(FinancialDocumentBranding::formatMoney((float) collect($schedule)->sum('remaining_amount')), $html);
        $this->assertStringNotContainsString('Default interest / penalties', $html);
        $this->assertStringNotContainsString('+260 000 000 000', $html);
        $this->assertStringNotContainsString('Customer Support Office', $html);
    }

    public function test_schedule_pdf_requires_loans_view_permission(): void
    {
        $loan = $this->createScheduledLoan(tenureMonths: 1);
        $admin = $this->makeAdmin([]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loans.schedule-pdf', $loan))
            ->assertForbidden();
    }

    public function test_multi_page_schedule_pdf_renders_without_exception(): void
    {
        $loan = $this->createScheduledLoan(tenureMonths: 12);
        $admin = $this->makeAdmin(['loans.view']);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.loans.schedule-pdf', $loan));

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, $this->countPdfPages((string) $response->getContent()));
    }

    private function renderScheduleHtml(Loan $loan): string
    {
        $company = $loan->customer->company;
        $branding = FinancialDocumentBranding::resolve($company);

        return view('pdf.loan-repayment-schedule', [
            'loan' => $loan->fresh(['customer.company', 'loanProduct', 'paymentSchedules']),
            'repaymentSchedule' => $loan->getRepaymentSchedule(),
            'defaultInterestEntries' => collect(),
            'defaultInterestTotal' => 0.0,
            'company' => $company,
            'branding' => $branding,
        ])->render();
    }

    private function countPdfPages(string $pdfBinary): int
    {
        preg_match_all('/\/Type\s*\/Page\b/', $pdfBinary, $matches);

        return max(1, count($matches[0]));
    }

    /**
     * @param  list<string>  $permissions
     */
    private function makeAdmin(array $permissions): Admin
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'PDF Co '.$suffix,
            'slug' => 'pdf-co-'.$suffix,
            'code' => 'PDF'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Pdf',
            'last_name' => 'Tester',
            'email' => 'pdf-admin-'.$suffix.'@example.com',
            'password' => 'password',
            'is_active' => true,
            'approval_status' => 'approved',
            'must_change_password' => false,
        ]);

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'admin']);
        }

        if ($permissions !== []) {
            $admin->givePermissionTo($permissions);
        }

        return $admin;
    }

    private function createScheduledLoan(int $tenureMonths, string $status = 'active'): Loan
    {
        $suffix = Str::lower(Str::random(6));

        $company = Company::create([
            'name' => 'Schedule Co '.$suffix,
            'slug' => 'schedule-co-'.$suffix,
            'code' => 'SC'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Schedule Product',
            'code' => 'SP-'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);

        $rateType = LoanRateType::create([
            'loan_product_id' => $product->id,
            'name' => 'Rate '.$suffix,
            'code' => 'RT-'.$suffix,
            'accrual_period' => 'daily',
            'interest_behavior' => LoanRateType::INTEREST_BEHAVIOR_UPFRONT_FLAT,
            'rate_input_mode' => LoanRateType::RATE_INPUT_TERM_PERCENTAGE,
            'is_active' => true,
        ]);

        $loanRate = LoanRate::create([
            'loan_rate_type_id' => $rateType->id,
            'tenure_months' => $tenureMonths,
            'processing_fee_percentage' => 5,
            'term_interest_percentage' => 27.8,
            'arrear_rate' => 0.01,
            'is_active' => true,
        ]);

        $group = CustomerGroup::create([
            'loan_product_id' => $product->id,
            'loan_rate_type_id' => $rateType->id,
            'name' => 'Group '.$suffix,
            'code' => 'GRP-'.$suffix,
            'risk_level' => 'medium',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $product->id,
            'customer_group_id' => $group->id,
            'first_name' => 'Schedule',
            'last_name' => 'Borrower',
            'email' => 'schedule-'.$suffix.'@example.com',
            'phone' => '260955'.random_int(100000, 999999),
            'password' => '1234',
            'tpin' => (string) random_int(10000000, 99999999),
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $channel = Channel::create([
            'name' => 'Channel '.$suffix,
            'code' => 'CH-'.$suffix,
            'can_disburse' => true,
            'can_repay' => true,
            'is_active' => true,
        ]);

        $loanStartDate = Carbon::parse('2026-01-01');
        $loanEndDate = $loanStartDate->copy()->addMonths($tenureMonths);
        $days = $loanStartDate->diffInDays($loanEndDate);

        $pricing = app(LoanPricingService::class);
        $quote = $pricing->quoteLoan([
            'principal' => 10000,
            'tenure_months' => $tenureMonths,
            'start_date' => $loanStartDate->toDateString(),
            'term_days' => $days,
            'loan_rate' => $loanRate,
            'loan_rate_type' => $rateType,
            'loan_product' => $product,
        ]);

        $financials = $pricing->buildLoanFinancialSnapshot($quote);
        $pricingMeta = $financials['pricing_metadata'] ?? [];
        unset($financials['pricing_metadata']);

        $loan = Loan::create(array_merge([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'customer_group_id' => $group->id,
            'loan_rate_id' => $loanRate->id,
            'channel_id' => $channel->id,
            'loan_number' => 'LN-PDF-'.$suffix,
            'principal_amount' => 10000,
            'tenure_months' => $tenureMonths,
            'loan_start_date' => $loanStartDate,
            'loan_end_date' => $loanEndDate,
            'first_payment_date' => $loanStartDate->copy()->addMonth(),
            'last_payment_date' => $loanEndDate,
            'amount_paid' => 0,
            'status' => $status,
            'disbursement_status' => 'completed',
            'disbursed_at' => $loanStartDate,
            'disbursement_phone_number' => $customer->phone,
            'metadata' => $pricingMeta,
        ], $financials));

        $loan->createPaymentSchedule();

        return $loan->fresh(['customer.company', 'loanProduct', 'paymentSchedules']);
    }
}
