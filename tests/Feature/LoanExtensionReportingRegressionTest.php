<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Channel;
use App\Models\Company;
use App\Models\Customer;
use App\Models\GeneralSetting;
use App\Models\Loan;
use App\Models\LoanPaymentSchedule;
use App\Models\LoanProduct;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class LoanExtensionReportingRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(string $suffix): Company
    {
        return Company::create([
            'name' => 'Regression Co '.$suffix,
            'slug' => 'regression-co-'.$suffix,
            'code' => 'RC'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);
    }

    private function makeLoanProduct(Company $company, string $suffix): LoanProduct
    {
        return LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Regression Product '.$suffix,
            'code' => 'RP-'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);
    }

    private function makeChannel(string $suffix): Channel
    {
        return Channel::create([
            'name' => 'Regression Channel '.$suffix,
            'code' => 'RCH-'.$suffix,
            'can_disburse' => true,
            'can_repay' => true,
            'is_active' => true,
        ]);
    }

    private function makeCustomer(Company $company, LoanProduct $loanProduct, string $suffix): Customer
    {
        return Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $loanProduct->id,
            'first_name' => 'Regression',
            'last_name' => 'Customer',
            'email' => 'reg-customer-'.$suffix.'@example.com',
            'phone' => '260966'.random_int(100000, 999999),
            'password' => '1234',
            'status' => 'active',
            'approval_status' => 'approved',
            'must_change_pin' => false,
        ]);
    }

    private function makeLoan(Customer $customer, LoanProduct $loanProduct, Channel $channel, string $suffix): Loan
    {
        return Loan::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $loanProduct->id,
            'channel_id' => $channel->id,
            'loan_number' => 'RG-'.$suffix.'-'.Str::upper(Str::random(8)),
            'principal_amount' => 1000,
            'processing_fee' => 0,
            'daily_rate' => 0.001,
            'total_amount' => 1000,
            'amount_paid' => 0,
            'outstanding_balance' => 1000,
            'tenure_months' => 2,
            'loan_start_date' => now()->subMonth()->toDateString(),
            'loan_end_date' => now()->addMonth()->toDateString(),
            'first_payment_date' => now()->addDays(5)->toDateString(),
            'last_payment_date' => now()->addDays(35)->toDateString(),
            'accrual_type' => 'daily',
            'status' => 'active',
            'disbursement_status' => 'completed',
            'disbursed_at' => now()->subMonth(),
        ]);
    }

    private function makeSchedule(
        Loan $loan,
        int $periodNumber,
        Carbon $dueDate,
        float $expected,
        float $amountPaid,
        float $remaining,
        string $status,
        bool $isRestructured,
        int $daysOverdue
    ): LoanPaymentSchedule {
        return LoanPaymentSchedule::withoutGlobalScope('non_restructured')->create([
            'loan_id' => $loan->id,
            'period_number' => $periodNumber,
            'due_date' => $dueDate->toDateString(),
            'expected_amount' => $expected,
            'amount_paid' => $amountPaid,
            'remaining_amount' => $remaining,
            'status' => $status,
            'days_overdue' => $daysOverdue,
            'is_restructured' => $isRestructured,
            'restructured_at' => $isRestructured ? now() : null,
        ]);
    }

    private function makeAdminWithPermissions(array $permissions): Admin
    {
        $suffix = Str::lower(Str::random(6));
        $company = $this->makeCompany('admin-'.$suffix);

        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Report',
            'last_name' => 'Admin',
            'email' => 'report-admin-'.$suffix.'@example.com',
            'password' => 'password',
            'is_active' => true,
            'approval_status' => 'approved',
            'must_change_password' => false,
        ]);

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'admin']);
        }
        $admin->givePermissionTo($permissions);

        return $admin;
    }

    public function test_par_status_ignores_restructured_installments(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = $this->makeCompany($suffix);
        $product = $this->makeLoanProduct($company, $suffix);
        $channel = $this->makeChannel($suffix);
        $customer = $this->makeCustomer($company, $product, $suffix);

        $loan = $this->makeLoan($customer, $product, $channel, $suffix);

        $this->makeSchedule(
            $loan,
            1,
            Carbon::today()->subDays(95),
            500,
            0,
            500,
            'overdue',
            true,
            95
        );
        $this->makeSchedule(
            $loan,
            2,
            Carbon::today()->addDays(10),
            500,
            0,
            500,
            'upcoming',
            false,
            0
        );

        $this->assertNull($loan->fresh()->getPARStatus());
        $this->assertSame(0.0, (float) $loan->fresh()->getOverdueAmount());

        $loan2 = $this->makeLoan($customer, $product, $channel, $suffix.'b');
        $this->makeSchedule(
            $loan2,
            1,
            Carbon::today()->subDays(40),
            500,
            0,
            500,
            'overdue',
            false,
            40
        );

        $this->assertSame('PAR30', $loan2->fresh()->getPARStatus());
    }

    public function test_arrears_exports_ignore_restructured_installments_and_keep_par_counts_correct(): void
    {
        Excel::fake();
        Excel::matchByRegex();

        $suffix = Str::lower(Str::random(6));
        $company = $this->makeCompany($suffix);
        $product = $this->makeLoanProduct($company, $suffix);
        $channel = $this->makeChannel($suffix);
        $customer = $this->makeCustomer($company, $product, $suffix);
        $admin = $this->makeAdminWithPermissions(['reports.view']);

        $loanOnlyRestructured = $this->makeLoan($customer, $product, $channel, $suffix.'a');
        $this->makeSchedule(
            $loanOnlyRestructured,
            1,
            Carbon::today()->subDays(95),
            500,
            0,
            500,
            'overdue',
            true,
            95
        );

        $loanWithActiveOverdue = $this->makeLoan($customer, $product, $channel, $suffix.'b');
        $this->makeSchedule(
            $loanWithActiveOverdue,
            1,
            Carbon::today()->subDays(45),
            500,
            0,
            500,
            'overdue',
            false,
            45
        );

        $this->actingAs($admin, 'admin')
            ->get(route('admin.reports.arrears.export'))
            ->assertStatus(200);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.reports.arrears.export-summary'))
            ->assertStatus(200);

        Excel::assertDownloaded('/^arrears_report_.*\.xlsx$/', function ($export) use ($loanOnlyRestructured, $loanWithActiveOverdue) {
            $rows = $export->collection();
            $loanNumbers = $rows
                ->map(fn ($row) => $row[0] ?? null)
                ->filter()
                ->values()
                ->all();

            return in_array($loanWithActiveOverdue->loan_number, $loanNumbers, true)
                && !in_array($loanOnlyRestructured->loan_number, $loanNumbers, true);
        });

        Excel::assertDownloaded('/^arrears_summary_.*\.xlsx$/', function ($export) {
            $rows = $export->collection()->map(fn ($row) => array_values((array) $row));
            $summary = [];

            foreach ($rows as $row) {
                if (isset($row[0], $row[1]) && is_string($row[0])) {
                    $summary[trim($row[0])] = (string) $row[1];
                }
            }

            return ($summary['Total Overdue Loans'] ?? null) === '1'
                && ($summary['PAR30'] ?? null) === '1'
                && ($summary['PAR60'] ?? null) === '0'
                && ($summary['PAR90'] ?? null) === '0';
        });
    }

    public function test_report_excel_exports_render_without_breaking_after_loan_extension_changes(): void
    {
        Excel::fake();
        Excel::matchByRegex();

        $suffix = Str::lower(Str::random(6));
        $company = $this->makeCompany($suffix);
        $product = $this->makeLoanProduct($company, $suffix);
        $channel = $this->makeChannel($suffix);
        $customer = $this->makeCustomer($company, $product, $suffix);
        $admin = $this->makeAdminWithPermissions(['reports.view']);

        $loan = $this->makeLoan($customer, $product, $channel, $suffix.'x');
        $this->makeSchedule(
            $loan,
            1,
            Carbon::today()->subDays(10),
            500,
            0,
            500,
            'overdue',
            false,
            10
        );

        $routes = [
            route('admin.reports.disbursements.export'),
            route('admin.reports.disbursements.export-summary'),
            route('admin.reports.collections.export'),
            route('admin.reports.collections.export-summary'),
            route('admin.reports.collection-split.export'),
            route('admin.reports.loan-book.export'),
            route('admin.reports.loan-book.export-summary'),
            route('admin.reports.loan-performance.export'),
            route('admin.reports.relationship-manager.export', ['format' => 'excel']),
        ];

        foreach ($routes as $url) {
            $this->actingAs($admin, 'admin')
                ->get($url)
                ->assertStatus(200);
        }

        Excel::assertDownloaded('/^disbursements_report_.*\.xlsx$/');
        Excel::assertDownloaded('/^disbursements_summary_.*\.xlsx$/');
        Excel::assertDownloaded('/^collections_report_.*\.xlsx$/');
        Excel::assertDownloaded('/^collections_summary_.*\.xlsx$/');
        Excel::assertDownloaded('/^collection_split_report_.*\.xlsx$/');
        Excel::assertDownloaded('/^loan_book_report_.*\.xlsx$/');
        Excel::assertDownloaded('/^loan_book_summary_.*\.xlsx$/');
        Excel::assertDownloaded('/^loan_performance_report_.*\.xlsx$/');
        Excel::assertDownloaded('/^relationship_manager_report_.*\.xlsx$/');
    }

    public function test_reminder_command_sends_missed_reminders_for_active_non_restructured_schedules_only(): void
    {
        Mail::fake();

        GeneralSetting::create([
            'allow_customer_registration' => false,
            'public_registration_product_ids' => null,
            'public_registration_group_ids' => null,
            'repayment_reminders_enabled' => true,
            'remind_1_week_before' => false,
            'remind_2_days_before' => false,
            'remind_1_day_before' => false,
            'missed_payment_reminder_count' => 1,
        ]);

        $suffix = Str::lower(Str::random(6));
        $company = $this->makeCompany($suffix);
        $product = $this->makeLoanProduct($company, $suffix);
        $channel = $this->makeChannel($suffix);
        $customer = $this->makeCustomer($company, $product, $suffix);
        $loan = $this->makeLoan($customer, $product, $channel, $suffix);

        $eligibleSchedule = $this->makeSchedule(
            $loan,
            1,
            Carbon::today()->subDays(2),
            500,
            0,
            500,
            'overdue',
            false,
            2
        );

        $restructuredSchedule = $this->makeSchedule(
            $loan,
            2,
            Carbon::today()->subDays(2),
            500,
            0,
            500,
            'overdue',
            true,
            2
        );

        $this->artisan('repayments:send-reminders')
            ->assertExitCode(0);

        $this->assertDatabaseHas('repayment_reminder_logs', [
            'loan_payment_schedule_id' => $eligibleSchedule->id,
            'customer_id' => $customer->id,
            'reminder_type' => 'missed_1',
        ]);

        $this->assertDatabaseMissing('repayment_reminder_logs', [
            'loan_payment_schedule_id' => $restructuredSchedule->id,
            'customer_id' => $customer->id,
            'reminder_type' => 'missed_1',
        ]);
    }
}

