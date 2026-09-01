<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Branch;
use App\Models\Channel;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanPaymentSchedule;
use App\Models\LoanProduct;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MissedPaymentsReportTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): Admin
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Missed Payments Co '.$suffix,
            'slug' => 'missed-co-'.$suffix,
            'code' => 'MP'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Missed',
            'last_name' => 'Admin',
            'email' => 'missed-'.$suffix.'@example.com',
            'password' => 'password',
            'is_active' => true,
            'approval_status' => 'approved',
            'must_change_password' => false,
        ]);

        Permission::firstOrCreate(['name' => 'loans.view', 'guard_name' => 'admin']);
        Permission::firstOrCreate(['name' => 'loans.export', 'guard_name' => 'admin']);
        $admin->givePermissionTo(['loans.view', 'loans.export']);

        return $admin;
    }

    private function makeLoanWithSchedule(
        Company $company,
        Carbon $dueDate,
        float $remaining,
        ?int $relationshipManagerId = null
    ): Loan {
        $suffix = Str::lower(Str::random(4));
        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Character '.$suffix,
            'code' => 'CHR-'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);

        if ($relationshipManagerId) {
            $company->update(['relationship_manager_id' => $relationshipManagerId]);
        }

        $customer = Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $product->id,
            'first_name' => 'Borrower',
            'last_name' => $suffix,
            'email' => 'borrower-'.$suffix.'@example.com',
            'phone' => '260955'.random_int(100000, 999999),
            'password' => '1234',
            'status' => 'active',
        ]);

        $channel = Channel::create([
            'name' => 'Channel '.$suffix,
            'code' => 'CH-'.$suffix,
            'can_disburse' => true,
            'can_repay' => true,
            'is_active' => true,
        ]);

        $loan = Loan::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'channel_id' => $channel->id,
            'loan_number' => 'MP-'.$suffix,
            'principal_amount' => 1000,
            'processing_fee' => 0,
            'total_amount' => 1000,
            'amount_paid' => 1000 - $remaining,
            'outstanding_balance' => $remaining,
            'tenure_months' => 2,
            'loan_start_date' => now()->subMonths(2)->toDateString(),
            'loan_end_date' => now()->addMonth()->toDateString(),
            'first_payment_date' => $dueDate->toDateString(),
            'last_payment_date' => now()->addMonth()->toDateString(),
            'accrual_type' => 'daily',
            'status' => 'active',
            'disbursement_status' => 'completed',
            'disbursed_at' => now()->subMonths(2),
        ]);

        LoanPaymentSchedule::create([
            'loan_id' => $loan->id,
            'period_number' => 1,
            'due_date' => $dueDate->toDateString(),
            'expected_amount' => $remaining,
            'amount_paid' => 0,
            'remaining_amount' => $remaining,
            'status' => 'overdue',
            'days_overdue' => max(0, $dueDate->diffInDays(Carbon::today())),
        ]);

        return $loan;
    }

    public function test_missed_payments_excludes_today_and_includes_unsettled_installments_in_window(): void
    {
        Carbon::setTestNow('2026-09-01 12:00:00');

        $admin = $this->makeAdmin();
        $company = Company::find($admin->company_id);

        $included = $this->makeLoanWithSchedule($company, Carbon::today()->subDays(5), 250.00);
        $dueToday = $this->makeLoanWithSchedule($company, Carbon::today(), 400.00);
        $tooOld = $this->makeLoanWithSchedule($company, Carbon::today()->subDays(20), 300.00);
        $settled = $this->makeLoanWithSchedule($company, Carbon::today()->subDays(3), 0.00);
        LoanPaymentSchedule::where('loan_id', $settled->id)->update(['remaining_amount' => 0, 'status' => 'paid']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loans.missed-payments'))
            ->assertOk()
            ->assertSee($included->loan_number)
            ->assertDontSee($dueToday->loan_number)
            ->assertDontSee($tooOld->loan_number)
            ->assertDontSee($settled->loan_number)
            ->assertSee('Due Last 14 Days');

        Carbon::setTestNow();
    }

    public function test_missed_payments_can_be_filtered_by_relationship_manager(): void
    {
        Carbon::setTestNow('2026-09-01 12:00:00');

        $suffix = Str::lower(Str::random(6));
        $companyA = Company::create([
            'name' => 'RM Filter Co A '.$suffix,
            'slug' => 'rm-filter-a-'.$suffix,
            'code' => 'RFA'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
            'relationship_manager_id' => null,
        ]);
        $companyB = Company::create([
            'name' => 'RM Filter Co B '.$suffix,
            'slug' => 'rm-filter-b-'.$suffix,
            'code' => 'RFB'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
            'relationship_manager_id' => null,
        ]);

        $rmA = Admin::create([
            'company_id' => $companyA->id,
            'first_name' => 'Alice',
            'last_name' => 'Manager',
            'email' => 'alice-'.$suffix.'@example.com',
            'password' => 'password',
            'is_active' => true,
            'is_relationship_manager' => true,
            'approval_status' => 'approved',
            'must_change_password' => false,
        ]);

        $rmB = Admin::create([
            'company_id' => $companyB->id,
            'first_name' => 'Bob',
            'last_name' => 'Manager',
            'email' => 'bob-'.$suffix.'@example.com',
            'password' => 'password',
            'is_active' => true,
            'is_relationship_manager' => true,
            'approval_status' => 'approved',
            'must_change_password' => false,
        ]);

        $admin = Admin::create([
            'company_id' => $companyA->id,
            'first_name' => 'Viewer',
            'last_name' => 'Admin',
            'email' => 'viewer-'.$suffix.'@example.com',
            'password' => 'password',
            'is_active' => true,
            'approval_status' => 'approved',
            'must_change_password' => false,
        ]);
        Permission::firstOrCreate(['name' => 'loans.view', 'guard_name' => 'admin']);
        $admin->givePermissionTo('loans.view');
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => \App\Support\PermissionMatrix::SUPER_ADMIN_ROLE, 'guard_name' => 'admin']);
        $admin->assignRole(\App\Support\PermissionMatrix::SUPER_ADMIN_ROLE);

        $loanA = $this->makeLoanWithSchedule($companyA, Carbon::today()->subDays(4), 500.00, $rmA->id);
        $loanB = $this->makeLoanWithSchedule($companyB, Carbon::today()->subDays(4), 600.00, $rmB->id);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loans.missed-payments', ['relationship_manager_id' => $rmA->id]))
            ->assertOk()
            ->assertSee($loanA->loan_number)
            ->assertDontSee($loanB->loan_number);

        Carbon::setTestNow();
    }

    public function test_missed_payments_export_downloads_excel(): void
    {
        Carbon::setTestNow('2026-09-01 12:00:00');

        $admin = $this->makeAdmin();
        $company = Company::find($admin->company_id);
        $this->makeLoanWithSchedule($company, Carbon::today()->subDays(2), 150.00);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.loans.missed-payments.export'));

        $response->assertOk();
        $this->assertStringContainsString(
            'missed-payments-',
            (string) $response->headers->get('content-disposition')
        );

        Carbon::setTestNow();
    }
}
