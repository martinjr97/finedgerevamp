<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Channel;
use App\Models\Company;
use App\Models\CompanyRelationshipManagerHistory;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Loan;
use App\Models\LoanPaymentSchedule;
use App\Models\LoanProduct;
use App\Support\PortfolioLoanSnapshot;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CompanyRelationshipManagerTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(string $suffix): Company
    {
        return Company::create([
            'name' => 'Company '.$suffix,
            'slug' => 'company-'.$suffix,
            'code' => 'CO'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);
    }

    private function makeAdminWithPermissions(array $permissions, ?int $companyId = null): Admin
    {
        $suffix = Str::lower(Str::random(6));
        $company = $companyId
            ? Company::find($companyId)
            : $this->makeCompany('admin-'.$suffix);

        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Company',
            'last_name' => 'Admin',
            'email' => 'company-admin-'.$suffix.'@example.com',
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

    private function makeRelationshipManager(Company $company, string $suffix): Admin
    {
        return Admin::create([
            'company_id' => $company->id,
            'first_name' => 'RM',
            'last_name' => $suffix,
            'email' => 'rm-'.$suffix.'@example.com',
            'password' => 'password',
            'is_active' => true,
            'is_relationship_manager' => true,
            'approval_status' => 'approved',
            'must_change_password' => false,
        ]);
    }

    public function test_company_show_allows_changing_relationship_manager_and_records_history(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = $this->makeCompany($suffix);
        $firstManager = $this->makeRelationshipManager($company, $suffix.'a');
        $secondManager = $this->makeRelationshipManager($company, $suffix.'b');
        $admin = $this->makeAdminWithPermissions(['companies.view', 'companies.update']);

        $company->update(['relationship_manager_id' => $firstManager->id]);
        CompanyRelationshipManagerHistory::create([
            'company_id' => $company->id,
            'relationship_manager_id' => $firstManager->id,
            'started_at' => now()->subMonth(),
            'change_reason' => 'Initial assignment',
            'changed_by' => $admin->id,
        ]);

        $this->actingAs($admin, 'admin')
            ->from(route('admin.companies.show', $company))
            ->put(route('admin.companies.update-relationship-manager', $company), [
                'relationship_manager_id' => $secondManager->id,
                'change_reason' => 'Portfolio reassignment',
            ])
            ->assertRedirect(route('admin.companies.show', $company))
            ->assertSessionHas('status');

        $company->refresh();
        $this->assertSame($secondManager->id, $company->relationship_manager_id);

        $this->assertDatabaseHas('company_relationship_manager_histories', [
            'company_id' => $company->id,
            'relationship_manager_id' => $firstManager->id,
            'change_reason' => 'Initial assignment',
        ]);

        $this->assertDatabaseHas('company_relationship_manager_histories', [
            'company_id' => $company->id,
            'relationship_manager_id' => $secondManager->id,
            'change_reason' => 'Portfolio reassignment',
            'changed_by' => $admin->id,
        ]);

        $closedHistory = CompanyRelationshipManagerHistory::query()
            ->where('company_id', $company->id)
            ->where('relationship_manager_id', $firstManager->id)
            ->first();

        $this->assertNotNull($closedHistory?->ended_at);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.companies.show', $company))
            ->assertStatus(200)
            ->assertSee('View relationship manager history')
            ->assertSee($firstManager->full_name)
            ->assertSee($secondManager->full_name)
            ->assertSee('Portfolio reassignment');
    }

    public function test_changing_company_relationship_manager_requires_reason_when_one_is_assigned(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = $this->makeCompany($suffix);
        $manager = $this->makeRelationshipManager($company, $suffix);
        $admin = $this->makeAdminWithPermissions(['companies.view', 'companies.update']);

        $company->update(['relationship_manager_id' => $manager->id]);

        $this->actingAs($admin, 'admin')
            ->from(route('admin.companies.show', $company))
            ->put(route('admin.companies.update-relationship-manager', $company), [
                'relationship_manager_id' => '',
            ])
            ->assertRedirect(route('admin.companies.show', $company))
            ->assertSessionHasErrors('change_reason');
    }

    public function test_arrears_report_can_be_filtered_by_company_id(): void
    {
        $suffix = Str::lower(Str::random(6));
        $companyA = $this->makeCompany($suffix.'a');
        $companyB = $this->makeCompany($suffix.'b');
        $productA = LoanProduct::create([
            'company_id' => $companyA->id,
            'name' => 'Product A '.$suffix,
            'code' => 'PA-'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);
        $productB = LoanProduct::create([
            'company_id' => $companyB->id,
            'name' => 'Product B '.$suffix,
            'code' => 'PB-'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);
        $channel = Channel::create([
            'name' => 'Arrears Channel '.$suffix,
            'code' => 'ACH-'.$suffix,
            'can_disburse' => true,
            'can_repay' => true,
            'is_active' => true,
        ]);

        foreach ([$companyA, $companyB] as $index => $company) {
            $product = $index === 0 ? $productA : $productB;
            $customer = Customer::create([
                'company_id' => $company->id,
                'loan_product_id' => $product->id,
                'first_name' => 'Customer',
                'last_name' => strtoupper($suffix).$index,
                'email' => 'arrears-'.$suffix.$index.'@example.com',
                'phone' => '260967'.random_int(100000, 999999),
                'password' => '1234',
                'status' => 'active',
                'approval_status' => 'approved',
                'must_change_pin' => false,
            ]);

            $loan = Loan::create([
                'customer_id' => $customer->id,
                'loan_product_id' => $product->id,
                'channel_id' => $channel->id,
                'loan_number' => 'AR-'.$suffix.'-'.$index,
                'principal_amount' => 1000,
                'processing_fee' => 0,
                'daily_rate' => 0.001,
                'total_amount' => 1000,
                'amount_paid' => 0,
                'outstanding_balance' => 1000,
                'tenure_months' => 2,
                'loan_start_date' => now()->subMonth()->toDateString(),
                'loan_end_date' => now()->addMonth()->toDateString(),
                'first_payment_date' => now()->subDays(10)->toDateString(),
                'last_payment_date' => now()->addDays(20)->toDateString(),
                'accrual_type' => 'daily',
                'status' => 'active',
                'disbursement_status' => 'completed',
                'disbursed_at' => now()->subMonth(),
            ]);

            LoanPaymentSchedule::create([
                'loan_id' => $loan->id,
                'period_number' => 1,
                'due_date' => Carbon::today()->subDays(7)->toDateString(),
                'expected_amount' => 500,
                'amount_paid' => 0,
                'remaining_amount' => 500,
                'status' => 'overdue',
                'days_overdue' => 7,
            ]);
        }

        $admin = $this->makeAdminWithPermissions(['reports.view'], $companyA->id);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.reports.arrears', ['company_id' => $companyA->id]))
            ->assertStatus(200)
            ->assertSee('Showing overdue loans for')
            ->assertSee($companyA->name)
            ->assertViewHas('arrearsSummary', fn (array $summary) => $summary['total_loans'] === 1 && $summary['total_overdue_amount'] === 500.0)
            ->assertViewHas('arrearsData', function ($paginator) use ($suffix) {
                return $paginator->count() === 1
                    && $paginator->first()['loan']->loan_number === 'AR-'.$suffix.'-0';
            });
    }

    public function test_customer_group_show_includes_loan_portfolio_snapshot(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = $this->makeCompany($suffix);
        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Group Product '.$suffix,
            'code' => 'GP-'.$suffix,
            'category' => 'group_loans',
            'is_active' => true,
        ]);
        $group = CustomerGroup::create([
            'loan_product_id' => $product->id,
            'name' => 'Group '.$suffix,
            'code' => 'GRP-'.$suffix,
            'risk_level' => 'medium',
            'is_active' => true,
        ]);
        $channel = Channel::create([
            'name' => 'Group Snapshot Channel '.$suffix,
            'code' => 'GSC-'.$suffix,
            'can_disburse' => true,
            'can_repay' => true,
            'is_active' => true,
        ]);
        $customer = Customer::create([
            'company_id' => $company->id,
            'customer_group_id' => $group->id,
            'loan_product_id' => $product->id,
            'first_name' => 'Group',
            'last_name' => 'Member',
            'email' => 'group-member-'.$suffix.'@example.com',
            'phone' => '260968'.random_int(100000, 999999),
            'password' => '1234',
            'status' => 'active',
            'approval_status' => 'approved',
            'must_change_pin' => false,
        ]);
        $admin = $this->makeAdminWithPermissions(['loan-products.view']);

        $loan = Loan::create([
            'customer_id' => $customer->id,
            'customer_group_id' => $group->id,
            'loan_product_id' => $product->id,
            'channel_id' => $channel->id,
            'loan_number' => 'GR-'.$suffix.'-1',
            'principal_amount' => 1000,
            'processing_fee' => 0,
            'daily_rate' => 0.001,
            'total_amount' => 1000,
            'amount_paid' => 100,
            'outstanding_balance' => 900,
            'tenure_months' => 2,
            'loan_start_date' => now()->subMonth()->toDateString(),
            'loan_end_date' => now()->addMonth()->toDateString(),
            'first_payment_date' => now()->subDays(10)->toDateString(),
            'last_payment_date' => now()->addDays(20)->toDateString(),
            'accrual_type' => 'daily',
            'status' => 'active',
            'disbursement_status' => 'completed',
            'disbursed_at' => now()->subMonth(),
        ]);

        LoanPaymentSchedule::create([
            'loan_id' => $loan->id,
            'period_number' => 1,
            'due_date' => Carbon::today()->subDays(3)->toDateString(),
            'expected_amount' => 250,
            'amount_paid' => 0,
            'remaining_amount' => 250,
            'status' => 'overdue',
            'days_overdue' => 3,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.customer-groups.show', $group))
            ->assertStatus(200)
            ->assertSee('Snapshot')
            ->assertSee('Active Loans')
            ->assertSee('Total Outstanding Balance')
            ->assertSee('Overdue Exposure')
            ->assertSee('ZMW 900.00')
            ->assertSee('ZMW 250.00')
            ->assertSee('Customers in this Group')
            ->assertDontSee('View relationship manager history')
            ->assertSee('admin-data-table', false);
    }

    public function test_customer_group_snapshot_counts_loans_by_customer_membership_not_loan_group_id(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = $this->makeCompany($suffix);
        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Group Product '.$suffix,
            'code' => 'GP2-'.$suffix,
            'category' => 'group_loans',
            'is_active' => true,
        ]);
        $group = CustomerGroup::create([
            'loan_product_id' => $product->id,
            'name' => 'Group '.$suffix,
            'code' => 'GRP2-'.$suffix,
            'risk_level' => 'medium',
            'is_active' => true,
        ]);
        $channel = Channel::create([
            'name' => 'Group Snapshot Channel '.$suffix,
            'code' => 'GSC2-'.$suffix,
            'can_disburse' => true,
            'can_repay' => true,
            'is_active' => true,
        ]);
        $customer = Customer::create([
            'company_id' => $company->id,
            'customer_group_id' => $group->id,
            'loan_product_id' => $product->id,
            'first_name' => 'Legacy',
            'last_name' => 'Member',
            'email' => 'legacy-group-member-'.$suffix.'@example.com',
            'phone' => '260967'.random_int(100000, 999999),
            'password' => '1234',
            'status' => 'active',
            'approval_status' => 'approved',
            'must_change_pin' => false,
        ]);
        $admin = $this->makeAdminWithPermissions(['loan-products.view']);

        Loan::create([
            'customer_id' => $customer->id,
            'customer_group_id' => null,
            'loan_product_id' => $product->id,
            'channel_id' => $channel->id,
            'loan_number' => 'LEG-GR-'.$suffix.'-1',
            'principal_amount' => 5000,
            'processing_fee' => 0,
            'daily_rate' => 0.001,
            'total_amount' => 5000,
            'amount_paid' => 0,
            'outstanding_balance' => 5000,
            'tenure_months' => 6,
            'loan_start_date' => now()->subMonth()->toDateString(),
            'loan_end_date' => now()->addMonths(5)->toDateString(),
            'first_payment_date' => now()->addDays(5)->toDateString(),
            'last_payment_date' => now()->addMonths(5)->toDateString(),
            'accrual_type' => 'daily',
            'status' => 'active',
            'disbursement_status' => 'completed',
            'disbursed_at' => now()->subMonth(),
        ]);

        $snapshot = PortfolioLoanSnapshot::forCustomerGroup($group);

        $this->assertSame(1, $snapshot['active_loans_count']);
        $this->assertSame(5000.0, $snapshot['total_outstanding_balance']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.customer-groups.show', $group))
            ->assertStatus(200)
            ->assertSee('ZMW 5,000.00');
    }
}
