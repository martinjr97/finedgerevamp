<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Loan;
use App\Models\LoanPaymentSchedule;
use App\Models\LoanProduct;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RiskHeatmapReportTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdminWithPermissions(array $permissions): Admin
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Risk Co '.$suffix,
            'slug' => 'risk-co-'.$suffix,
            'code' => 'RK'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Risk',
            'last_name' => 'Viewer',
            'email' => 'risk-viewer-'.$suffix.'@example.com',
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

    public function test_risk_heatmap_default_rate_includes_par90_loans(): void
    {
        Carbon::setTestNow('2026-08-31 12:00:00');

        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Heatmap Co '.$suffix,
            'slug' => 'heatmap-co-'.$suffix,
            'code' => 'HM'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);
        $branch = Branch::create([
            'name' => 'Heatmap Branch '.$suffix,
            'code' => 'HB-'.$suffix,
            'is_active' => true,
        ]);
        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Heatmap Product '.$suffix,
            'code' => 'HP-'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);
        $relationshipManager = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Heatmap',
            'last_name' => 'Manager',
            'email' => 'heatmap-rm-'.$suffix.'@example.com',
            'password' => 'password',
            'is_active' => true,
            'is_relationship_manager' => true,
            'approval_status' => 'approved',
            'must_change_password' => false,
        ]);
        $group = CustomerGroup::create([
            'loan_product_id' => $product->id,
            'branch_id' => $branch->id,
            'relationship_manager_id' => $relationshipManager->id,
            'name' => 'Heatmap Group '.$suffix,
            'code' => 'HG-'.$suffix,
            'risk_level' => 'medium',
            'is_active' => true,
        ]);
        $customer = Customer::create([
            'company_id' => $company->id,
            'customer_group_id' => $group->id,
            'loan_product_id' => $product->id,
            'first_name' => 'Heatmap',
            'last_name' => 'Borrower',
            'email' => 'heatmap-borrower-'.$suffix.'@example.com',
            'phone' => '260966'.random_int(100000, 999999),
            'password' => '1234',
            'status' => 'active',
            'approval_status' => 'approved',
            'must_change_pin' => false,
        ]);
        $loan = Loan::create([
            'customer_id' => $customer->id,
            'customer_group_id' => $group->id,
            'loan_product_id' => $product->id,
            'loan_number' => 'HM-'.$suffix,
            'principal_amount' => 1000,
            'processing_fee' => 0,
            'total_amount' => 1000,
            'amount_paid' => 0,
            'outstanding_balance' => 1000,
            'tenure_months' => 1,
            'loan_start_date' => now()->subMonths(4)->toDateString(),
            'loan_end_date' => now()->addMonth()->toDateString(),
            'first_payment_date' => now()->subMonths(3)->toDateString(),
            'last_payment_date' => now()->addMonth()->toDateString(),
            'accrual_type' => 'daily',
            'status' => 'active',
            'disbursement_status' => 'completed',
            'disbursed_at' => now()->subMonths(4),
        ]);
        LoanPaymentSchedule::create([
            'loan_id' => $loan->id,
            'period_number' => 1,
            'due_date' => now()->subDays(95)->toDateString(),
            'expected_amount' => 1000,
            'amount_paid' => 0,
            'remaining_amount' => 1000,
            'status' => 'overdue',
            'days_overdue' => 95,
        ]);

        $admin = $this->makeAdminWithPermissions(['reports.view']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.reports.risk-heatmap'))
            ->assertStatus(200)
            ->assertViewHas('loanOfficerRisk', function ($rows) use ($relationshipManager) {
                return $rows->contains(function (array $row) use ($relationshipManager) {
                    return $row['officer']->id === $relationshipManager->id
                        && $row['total_loans'] === 1
                        && abs($row['default_rate'] - 100.0) < 0.01;
                });
            });

        Carbon::setTestNow();
    }

    public function test_risk_heatmap_export_excel_downloads_for_officers_dataset(): void
    {
        Carbon::setTestNow('2026-08-31 12:00:00');

        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Export Co '.$suffix,
            'slug' => 'export-co-'.$suffix,
            'code' => 'EX'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);
        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Export Product '.$suffix,
            'code' => 'EP-'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);
        $branch = Branch::create([
            'name' => 'Export Branch '.$suffix,
            'code' => 'EB-'.$suffix,
            'is_active' => true,
        ]);
        $relationshipManager = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Export',
            'last_name' => 'Manager',
            'email' => 'export-rm-'.$suffix.'@example.com',
            'password' => 'password',
            'is_active' => true,
            'is_relationship_manager' => true,
            'approval_status' => 'approved',
            'must_change_password' => false,
        ]);
        $group = CustomerGroup::create([
            'loan_product_id' => $product->id,
            'branch_id' => $branch->id,
            'relationship_manager_id' => $relationshipManager->id,
            'name' => 'Export Group '.$suffix,
            'code' => 'EG-'.$suffix,
            'risk_level' => 'medium',
            'is_active' => true,
        ]);
        $customer = Customer::create([
            'company_id' => $company->id,
            'customer_group_id' => $group->id,
            'branch_id' => $branch->id,
            'loan_product_id' => $product->id,
            'first_name' => 'Export',
            'last_name' => 'Borrower',
            'email' => 'export-borrower-'.$suffix.'@example.com',
            'phone' => '260966'.random_int(100000, 999999),
            'password' => '1234',
            'status' => 'active',
            'approval_status' => 'approved',
            'must_change_pin' => false,
        ]);
        Loan::create([
            'customer_id' => $customer->id,
            'customer_group_id' => $group->id,
            'loan_product_id' => $product->id,
            'loan_number' => 'EX-'.$suffix,
            'principal_amount' => 1000,
            'processing_fee' => 0,
            'total_amount' => 1000,
            'amount_paid' => 0,
            'outstanding_balance' => 1000,
            'tenure_months' => 1,
            'loan_start_date' => now()->subMonths(4)->toDateString(),
            'loan_end_date' => now()->addMonth()->toDateString(),
            'first_payment_date' => now()->subMonths(3)->toDateString(),
            'last_payment_date' => now()->addMonth()->toDateString(),
            'accrual_type' => 'daily',
            'status' => 'active',
            'disbursement_status' => 'completed',
            'disbursed_at' => now()->subMonths(4),
        ]);

        $admin = $this->makeAdminWithPermissions(['reports.view']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.reports.risk-heatmap.export', ['format' => 'excel', 'dataset' => 'officers']))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        Carbon::setTestNow();
    }
}
