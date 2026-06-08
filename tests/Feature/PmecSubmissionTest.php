<?php

namespace Tests\Feature;

use App\Exports\PmecSubmissionExport;
use App\Models\Admin;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Loan;
use App\Models\LoanPaymentSchedule;
use App\Models\LoanProduct;
use App\Models\PmecSubmission;
use App\Models\PmecSubmissionItem;
use App\Services\PmecSubmissionService;
use App\Support\PmecDateFormatter;
use App\Support\PmecSubmissionDefaults;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PmecSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private PmecSubmissionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PmecSubmissionService::class);
        foreach (['view', 'create', 'export', 'mark_failed'] as $action) {
            Permission::firstOrCreate(['name' => "pmec_submissions.{$action}", 'guard_name' => 'admin']);
        }
    }

    public function test_begda_is_first_day_of_loan_start_month(): void
    {
        $begda = PmecDateFormatter::begdaFromLoanStart(Carbon::parse('2026-05-05'));

        $this->assertSame('01.05.2026', PmecDateFormatter::format($begda));
    }

    public function test_endda_is_last_day_of_loan_end_month(): void
    {
        $endda = PmecDateFormatter::enddaFromLoanEnd(Carbon::parse('2026-05-15'));

        $this->assertSame('31.05.2026', PmecDateFormatter::format($endda));

        $juneEnd = PmecDateFormatter::enddaFromLoanEnd(Carbon::parse('2026-06-01'));
        $this->assertSame('30.06.2026', PmecDateFormatter::format($juneEnd));
    }

    public function test_endda_handles_february_leap_and_non_leap_years(): void
    {
        $leap = PmecDateFormatter::enddaFromLoanEnd(Carbon::parse('2024-02-10'));
        $this->assertSame('29.02.2024', PmecDateFormatter::format($leap));

        $nonLeap = PmecDateFormatter::enddaFromLoanEnd(Carbon::parse('2025-02-10'));
        $this->assertSame('28.02.2025', PmecDateFormatter::format($nonLeap));
    }

    public function test_new_loans_mode_excludes_successfully_submitted_loans(): void
    {
        $context = $this->governmentContext();
        $submittedLoan = $this->createGovernmentLoan($context, withEmployee: true);
        $newLoan = $this->createGovernmentLoan($context, withEmployee: true);

        $this->seedSubmittedItem($submittedLoan, $context['admin']);

        $rows = $this->service->buildPreviewRows(
            $context['product'],
            now()->format('Y-m'),
            PmecSubmissionDefaults::MODE_NEW_LOANS,
        );

        $loanIds = $rows->pluck('loan_id')->all();
        $this->assertNotContains($submittedLoan->id, $loanIds);
        $this->assertContains($newLoan->id, $loanIds);
    }

    public function test_failed_submissions_can_be_included_again(): void
    {
        $context = $this->governmentContext();
        $loan = $this->createGovernmentLoan($context, withEmployee: true);

        PmecSubmissionItem::query()->create([
            'pmec_submission_id' => $this->createSubmission($context)->id,
            'loan_id' => $loan->id,
            'customer_id' => $loan->customer_id,
            'pernr' => '12345',
            'nrc' => '111111/11/1',
            'first_name' => 'Test',
            'surname' => 'User',
            'begda' => now()->startOfMonth(),
            'endda' => now()->endOfMonth(),
            'betrg' => 500,
            'status' => PmecSubmissionDefaults::ITEM_STATUS_FAILED,
        ]);

        $rows = $this->service->buildPreviewRows(
            $context['product'],
            now()->format('Y-m'),
            PmecSubmissionDefaults::MODE_FAILED_MISSED,
        );

        $this->assertContains($loan->id, $rows->pluck('loan_id')->all());
    }

    public function test_manual_mode_exports_only_selected_loans(): void
    {
        $context = $this->governmentContext();
        $loanA = $this->createGovernmentLoan($context, withEmployee: true);
        $loanB = $this->createGovernmentLoan($context, withEmployee: true);

        $rows = $this->service->buildPreviewRows(
            $context['product'],
            now()->format('Y-m'),
            PmecSubmissionDefaults::MODE_MANUAL,
            manualLoanIds: [$loanA->id],
        );

        $this->assertCount(1, $rows);
        $this->assertSame($loanA->id, $rows->first()['loan_id']);
        $this->assertNotContains($loanB->id, $rows->pluck('loan_id')->all());
    }

    public function test_customer_group_filter_limits_preview_rows(): void
    {
        $context = $this->governmentContext();
        $otherGroup = CustomerGroup::create([
            'loan_product_id' => $context['product']->id,
            'name' => 'Other Group',
            'code' => 'OG-'.Str::random(4),
            'is_active' => true,
        ]);

        $loanInDefault = $this->createGovernmentLoan($context, withEmployee: true);
        $customerOther = Customer::create([
            'loan_product_id' => $context['product']->id,
            'customer_group_id' => $otherGroup->id,
            'first_name' => 'Other',
            'last_name' => 'Group',
            'email' => 'other-'.Str::random(5).'@example.com',
            'phone' => '260955'.random_int(100000, 999999),
            'password' => '1234',
            'employee_number' => '99999',
            'national_id' => '222222/22/2',
            'status' => 'active',
        ]);
        $loanOther = $this->createGovernmentLoan($context, withEmployee: true, customer: $customerOther);

        $rows = $this->service->buildPreviewRows(
            $context['product'],
            now()->format('Y-m'),
            PmecSubmissionDefaults::MODE_NEW_LOANS,
            customerGroupIds: [$context['group']->id],
        );

        $ids = $rows->pluck('loan_id')->all();
        $this->assertContains($loanInDefault->id, $ids);
        $this->assertNotContains($loanOther->id, $ids);
    }

    public function test_non_government_loans_are_excluded(): void
    {
        $context = $this->governmentContext();
        $charProduct = LoanProduct::create([
            'company_id' => $context['company']->id,
            'name' => 'Character',
            'code' => 'CHR-'.Str::random(4),
            'category' => 'character',
            'is_active' => true,
        ]);

        $charGroup = CustomerGroup::create([
            'loan_product_id' => $charProduct->id,
            'name' => 'Char Group',
            'code' => 'CG-'.Str::random(4),
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'loan_product_id' => $charProduct->id,
            'customer_group_id' => $charGroup->id,
            'first_name' => 'Char',
            'last_name' => 'Borrower',
            'email' => 'char-'.Str::random(5).'@example.com',
            'phone' => '260955'.random_int(100000, 999999),
            'password' => '1234',
            'employee_number' => '55555',
            'national_id' => '333333/33/3',
            'status' => 'active',
        ]);

        Loan::create([
            'loan_product_id' => $charProduct->id,
            'customer_id' => $customer->id,
            'customer_group_id' => $charGroup->id,
            'loan_number' => 'LN-CHAR-'.Str::upper(Str::random(6)),
            'status' => 'active',
            'disbursement_status' => 'completed',
            'principal_amount' => 5000,
            'processing_fee' => 0,
            'total_amount' => 5000,
            'tenure_months' => 3,
            'loan_start_date' => now()->startOfMonth(),
            'loan_end_date' => now()->addMonths(2)->endOfMonth(),
            'first_payment_date' => now()->addMonth(),
            'outstanding_balance' => 5000,
            'accrual_type' => 'daily',
        ]);

        $rows = $this->service->buildPreviewRows(
            $context['product'],
            now()->format('Y-m'),
            PmecSubmissionDefaults::MODE_NEW_LOANS,
        );

        $this->assertTrue(
            $rows->every(fn (array $row) => Loan::find($row['loan_id'])->loan_product_id === $context['product']->id)
        );
    }

    public function test_missing_pernr_blocks_export_when_not_excluding_invalid(): void
    {
        $context = $this->governmentContext();
        $admin = $context['admin'];
        $admin->givePermissionTo([
            'pmec_submissions.view',
            'pmec_submissions.create',
            'pmec_submissions.export',
        ]);

        $loan = $this->createGovernmentLoan($context, withEmployee: false);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.pmec-submissions.generate'), [
                'loan_product_id' => $context['product']->id,
                'submission_month' => now()->format('Y-m'),
                'mode' => PmecSubmissionDefaults::MODE_NEW_LOANS,
                'loan_ids' => [$loan->id],
                'exclude_invalid' => false,
            ])
            ->assertSessionHasErrors('export');
    }

    public function test_excel_columns_match_pmec_template_and_total_row_sums_betrg(): void
    {
        $items = collect([
            (object) [
                'pernr' => '10001',
                'lgart' => '8000',
                'endda' => Carbon::parse('2026-07-31'),
                'begda' => Carbon::parse('2026-05-01'),
                'betrg' => 1500.50,
                'emfsl' => 'F021',
                'zlsch' => 'E',
                'nrc' => '123456/78/1',
                'first_name' => 'John',
                'surname' => 'Banda',
            ],
            (object) [
                'pernr' => '10002',
                'lgart' => '8000',
                'endda' => Carbon::parse('2026-08-31'),
                'begda' => Carbon::parse('2026-05-01'),
                'betrg' => 2499.50,
                'emfsl' => 'F021',
                'zlsch' => 'E',
                'nrc' => '987654/32/1',
                'first_name' => 'Mary',
                'surname' => 'Phiri',
            ],
        ]);

        $rows = $this->service->excelRows($items);
        $total = (float) $items->sum('betrg');

        Excel::store(new PmecSubmissionExport($rows, $total), 'test-pmec-export.xlsx', 'local');
        $stored = storage_path('app/private/test-pmec-export.xlsx');
        if (! is_file($stored)) {
            $stored = storage_path('app/test-pmec-export.xlsx');
        }

        $spreadsheet = IOFactory::load($stored);
        $sheet = $spreadsheet->getActiveSheet();

        $this->assertSame(
            PmecSubmissionDefaults::excelHeadings(),
            [
                $sheet->getCell('A1')->getValue(),
                $sheet->getCell('B1')->getValue(),
                $sheet->getCell('C1')->getValue(),
                $sheet->getCell('D1')->getValue(),
                $sheet->getCell('E1')->getValue(),
                $sheet->getCell('F1')->getValue(),
                $sheet->getCell('G1')->getValue(),
                $sheet->getCell('H1')->getValue(),
                $sheet->getCell('I1')->getValue(),
                $sheet->getCell('J1')->getValue(),
            ]
        );

        $this->assertSame('10001', (string) $sheet->getCell('A2')->getFormattedValue());
        $this->assertSame('8000', (string) $sheet->getCell('B2')->getFormattedValue());
        $this->assertSame('31.07.2026', $sheet->getCell('C2')->getValue());
        $this->assertSame('01.05.2026', $sheet->getCell('D2')->getValue());
        $this->assertEqualsWithDelta(1500.50, (float) $sheet->getCell('E2')->getValue(), 0.01);
        $this->assertSame('F021', $sheet->getCell('F2')->getValue());
        $this->assertSame('E', $sheet->getCell('G2')->getValue());
        $this->assertSame('123456/78/1', $sheet->getCell('H2')->getValue());
        $this->assertSame('John', $sheet->getCell('I2')->getValue());
        $this->assertSame('Banda', $sheet->getCell('J2')->getValue());

        $lastRow = 4;
        $this->assertSame('Total', $sheet->getCell('A'.$lastRow)->getValue());
        $this->assertEqualsWithDelta(4000.0, (float) $sheet->getCell('E'.$lastRow)->getValue(), 0.01);

        @unlink($stored);
    }

    public function test_generate_creates_submission_and_downloadable_file(): void
    {
        $context = $this->governmentContext();
        $admin = $context['admin'];
        $admin->givePermissionTo([
            'pmec_submissions.view',
            'pmec_submissions.create',
            'pmec_submissions.export',
        ]);

        $loan = $this->createGovernmentLoan($context, withEmployee: true);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.pmec-submissions.generate'), [
                'loan_product_id' => $context['product']->id,
                'submission_month' => '2026-06',
                'mode' => PmecSubmissionDefaults::MODE_NEW_LOANS,
                'loan_ids' => [$loan->id],
            ])
            ->assertRedirect();

        $submission = PmecSubmission::query()->latest('id')->first();
        $this->assertNotNull($submission);
        $this->assertSame('generated', $submission->status);
        $this->assertCount(1, $submission->items);
        $this->assertStringContainsString('PMEC_SUBMISSION_F021_2026_06.xlsx', $submission->file_path);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.pmec-submissions.download', $submission))
            ->assertOk();
    }

    public function test_build_loan_payload_uses_customer_and_schedule_fields(): void
    {
        $context = $this->governmentContext();
        $loan = $this->createGovernmentLoan($context, withEmployee: true);

        $payload = $this->service->buildLoanPayload($loan->fresh(['customer', 'paymentSchedules']));

        $this->assertNotEmpty($payload['pernr']);
        $this->assertNotEmpty($payload['nrc']);
        $this->assertSame('Gov', $payload['first_name']);
        $this->assertSame('Worker', $payload['surname']);
        $this->assertEqualsWithDelta(4426.67, (float) $payload['betrg'], 0.01);
        $this->assertSame('01.05.2026', $payload['begda_formatted']);
        $this->assertSame('31.07.2026', $payload['endda_formatted']);
    }

    /**
     * @return array<string, mixed>
     */
    private function governmentContext(): array
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'PMEC Co '.$suffix,
            'slug' => 'pmec-'.$suffix,
            'code' => 'PC'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'PMEC',
            'last_name' => 'Admin',
            'email' => 'pmec-'.$suffix.'@example.com',
            'password' => 'password',
            'is_active' => true,
            'approval_status' => 'approved',
        ]);

        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Government',
            'code' => 'GOV-'.$suffix,
            'category' => 'government',
            'is_active' => true,
        ]);

        $group = CustomerGroup::create([
            'loan_product_id' => $product->id,
            'name' => 'PMEC Group',
            'code' => 'PG-'.$suffix,
            'is_active' => true,
        ]);

        return compact('company', 'admin', 'product', 'group');
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function createGovernmentLoan(array $context, bool $withEmployee = true, ?Customer $customer = null): Loan
    {
        if (! $customer) {
            $customer = Customer::create([
                'loan_product_id' => $context['product']->id,
                'customer_group_id' => $context['group']->id,
                'first_name' => 'Gov',
                'last_name' => 'Worker',
                'email' => 'gov-'.Str::random(6).'@example.com',
                'phone' => '260955'.random_int(100000, 999999),
                'password' => '1234',
                'employee_number' => $withEmployee ? 'EMP-'.Str::upper(Str::random(5)) : null,
                'national_id' => $withEmployee ? random_int(100000, 999999).'/78/1' : null,
                'status' => 'active',
            ]);
        }

        $loan = Loan::create([
            'loan_product_id' => $context['product']->id,
            'customer_id' => $customer->id,
            'customer_group_id' => $customer->customer_group_id,
            'loan_number' => 'LN-'.Str::upper(Str::random(8)),
            'status' => 'active',
            'disbursement_status' => 'completed',
            'principal_amount' => 10000,
            'processing_fee' => 500,
            'total_amount' => 13280,
            'tenure_months' => 3,
            'loan_start_date' => '2026-05-05',
            'loan_end_date' => '2026-07-28',
            'first_payment_date' => '2026-06-28',
            'last_payment_date' => '2026-07-28',
            'outstanding_balance' => 13280,
            'accrual_type' => 'daily',
        ]);

        foreach ([1, 2, 3] as $period) {
            LoanPaymentSchedule::create([
                'loan_id' => $loan->id,
                'period_number' => $period,
                'due_date' => Carbon::parse('2026-05-28')->addMonths($period - 1),
                'expected_amount' => 4426.67,
                'principal_component' => 3333.33,
                'interest_component' => 926.67,
                'fee_component' => 166.67,
                'amount_paid' => 0,
                'remaining_amount' => 4426.67,
                'status' => 'upcoming',
                'days_overdue' => 0,
            ]);
        }

        return $loan;
    }

    private function createSubmission(array $context): PmecSubmission
    {
        return PmecSubmission::query()->create([
            'batch_number' => 'PMEC-TEST-'.Str::random(4),
            'loan_product_id' => $context['product']->id,
            'submission_month' => now()->format('Y-m'),
            'mode' => PmecSubmissionDefaults::MODE_NEW_LOANS,
            'status' => PmecSubmissionDefaults::SUBMISSION_STATUS_GENERATED,
            'generated_by' => $context['admin']->id,
            'generated_at' => now(),
        ]);
    }

    private function seedSubmittedItem(Loan $loan, Admin $admin): void
    {
        $submission = PmecSubmission::query()->create([
            'batch_number' => 'PMEC-PRIOR-'.Str::random(4),
            'loan_product_id' => $loan->loan_product_id,
            'submission_month' => now()->subMonth()->format('Y-m'),
            'mode' => PmecSubmissionDefaults::MODE_NEW_LOANS,
            'status' => PmecSubmissionDefaults::SUBMISSION_STATUS_SUBMITTED,
            'generated_by' => $admin->id,
            'generated_at' => now()->subMonth(),
        ]);

        PmecSubmissionItem::query()->create([
            'pmec_submission_id' => $submission->id,
            'loan_id' => $loan->id,
            'customer_id' => $loan->customer_id,
            'pernr' => '11111',
            'nrc' => '123456/78/1',
            'first_name' => 'Gov',
            'surname' => 'Worker',
            'begda' => now()->startOfMonth(),
            'endda' => now()->endOfMonth(),
            'betrg' => 100,
            'status' => PmecSubmissionDefaults::ITEM_STATUS_SUBMITTED,
        ]);
    }
}
