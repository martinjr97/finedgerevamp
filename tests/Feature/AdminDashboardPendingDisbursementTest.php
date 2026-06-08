<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AdminDashboardPendingDisbursementTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdminContext(array $permissions = []): array
    {
        $suffix = Str::lower(Str::random(6));

        $company = Company::create([
            'name' => 'Dashboard Queue Co '.$suffix,
            'slug' => 'dashboard-queue-co-'.$suffix,
            'code' => 'DQC-'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Dashboard',
            'last_name' => 'Admin',
            'email' => 'dashboard-admin-'.$suffix.'@example.com',
            'password' => 'password',
            'is_active' => true,
            'approval_status' => 'approved',
            'must_change_password' => false,
        ]);

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'admin']);
        }
        if (!empty($permissions)) {
            $admin->givePermissionTo($permissions);
        }

        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Dashboard Product',
            'code' => 'DB-'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $product->id,
            'first_name' => 'Queue',
            'last_name' => 'Customer',
            'email' => 'queue-customer-'.$suffix.'@example.com',
            'phone' => '26097'.random_int(100000, 999999),
            'password' => '1234',
            'tpin' => (string) random_int(10000000, 99999999),
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        return compact('admin', 'company', 'product', 'customer');
    }

    private function makeLoan(Customer $customer, LoanProduct $product, array $overrides = []): Loan
    {
        return Loan::create(array_merge([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'loan_number' => 'LN-'.Str::upper(Str::random(10)),
            'principal_amount' => 3000,
            'processing_fee' => 150,
            'interest_accrued' => 200,
            'total_amount' => 3350,
            'amount_paid' => 0,
            'outstanding_balance' => 3350,
            'tenure_months' => 3,
            'loan_start_date' => now()->toDateString(),
            'loan_end_date' => now()->addMonths(3)->toDateString(),
            'accrual_type' => 'daily',
            'status' => 'approved',
            'disbursement_status' => 'pending',
        ], $overrides));
    }

    public function test_dashboard_shows_pending_disbursement_queue_with_overdue_priority(): void
    {
        $context = $this->makeAdminContext(['loans.disburse']);

        $overdueLoan = $this->makeLoan(
            $context['customer'],
            $context['product'],
            [
                'loan_number' => 'LN-OVERDUE-001',
                'loan_start_date' => now()->subDays(3)->toDateString(),
                'loan_end_date' => now()->addMonths(3)->toDateString(),
            ]
        );

        $upcomingLoan = $this->makeLoan(
            $context['customer'],
            $context['product'],
            [
                'loan_number' => 'LN-UPCOMING-001',
                'loan_start_date' => now()->addDay()->toDateString(),
                'loan_end_date' => now()->addMonths(3)->toDateString(),
            ]
        );

        $notPendingDisbursement = $this->makeLoan(
            $context['customer'],
            $context['product'],
            [
                'loan_number' => 'LN-COMPLETED-001',
                'status' => 'active',
                'disbursement_status' => 'completed',
            ]
        );

        $response = $this->actingAs($context['admin'], 'admin')
            ->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Pending Disbursement Queue');
        $response->assertViewHas('pendingDisbursementCount', 2);
        $response->assertViewHas('overduePendingDisbursementCount', 1);
        $response->assertSee($overdueLoan->loan_number);
        $response->assertSee($upcomingLoan->loan_number);
        $response->assertDontSee($notPendingDisbursement->loan_number);
        $response->assertSee('3 days late');
    }

    public function test_dashboard_hides_pending_disbursement_queue_when_no_pending_loans(): void
    {
        $context = $this->makeAdminContext(['loans.disburse']);

        $this->makeLoan(
            $context['customer'],
            $context['product'],
            [
                'loan_number' => 'LN-COMPLETED-ONLY',
                'status' => 'active',
                'disbursement_status' => 'completed',
            ]
        );

        $response = $this->actingAs($context['admin'], 'admin')
            ->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertDontSee('Pending Disbursement Queue');
        $response->assertViewHas('pendingDisbursementCount', 0);
    }

    public function test_dashboard_hides_pending_disbursement_queue_without_disburse_permission(): void
    {
        $context = $this->makeAdminContext();

        $this->makeLoan($context['customer'], $context['product'], [
            'loan_number' => 'LN-PENDING-NO-PERM',
        ]);

        $response = $this->actingAs($context['admin'], 'admin')
            ->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertDontSee('Pending Disbursement Queue');
    }

    public function test_dashboard_hides_pending_approvals_card_without_approvals_permission(): void
    {
        $context = $this->makeAdminContext();

        $response = $this->actingAs($context['admin'], 'admin')
            ->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertDontSee('Pending Approvals');
    }

    public function test_dashboard_shows_pending_approvals_card_with_approvals_permission(): void
    {
        $context = $this->makeAdminContext(['approvals.view']);

        $response = $this->actingAs($context['admin'], 'admin')
            ->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Pending Approvals');
    }

    public function test_dashboard_total_outstanding_excludes_approved_pending_disbursement(): void
    {
        $context = $this->makeAdminContext(['loans.view']);

        $this->makeLoan(
            $context['customer'],
            $context['product'],
            [
                'loan_number' => 'LN-APPROVED-PENDING',
                'status' => 'approved',
                'disbursement_status' => 'pending',
                'outstanding_balance' => 10000,
            ]
        );

        $this->makeLoan(
            $context['customer'],
            $context['product'],
            [
                'loan_number' => 'LN-ACTIVE-DISBURSED',
                'status' => 'active',
                'disbursement_status' => 'completed',
                'disbursed_at' => now(),
                'outstanding_balance' => 2500,
            ]
        );

        $response = $this->actingAs($context['admin'], 'admin')
            ->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertViewHas('overallStats', function (array $stats): bool {
            return (float) $stats['total_outstanding'] === 2500.0
                && (int) $stats['active_loans'] === 1;
        });
        $response->assertSee('2,500.00');
        $response->assertDontSee('10,000.00');
    }

    public function test_loans_index_can_filter_by_disbursement_status(): void
    {
        $context = $this->makeAdminContext();

        $pendingLoan = $this->makeLoan(
            $context['customer'],
            $context['product'],
            ['loan_number' => 'LN-PENDING-DISB-001', 'disbursement_status' => 'pending']
        );

        $completedLoan = $this->makeLoan(
            $context['customer'],
            $context['product'],
            ['loan_number' => 'LN-COMPLETED-DISB-001', 'disbursement_status' => 'completed']
        );

        $response = $this->actingAs($context['admin'], 'admin')
            ->get(route('admin.loans.index', ['disbursement_status' => 'pending']));

        $response->assertOk();
        $response->assertSee($pendingLoan->loan_number);
        $response->assertDontSee($completedLoan->loan_number);
    }
}
