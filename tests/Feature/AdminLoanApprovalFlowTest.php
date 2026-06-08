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

class AdminLoanApprovalFlowTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdminWithLoanApprovePermission(): Admin
    {
        $suffix = Str::lower(Str::random(6));

        $company = Company::create([
            'name' => 'Loan Approval Co '.$suffix,
            'slug' => 'loan-approval-co-'.$suffix,
            'code' => 'LAC'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Approver',
            'last_name' => 'Admin',
            'email' => 'loan-approver-'.$suffix.'@example.com',
            'password' => 'password',
            'is_active' => true,
            'approval_status' => 'approved',
        ]);

        Permission::firstOrCreate(['name' => 'loans.approve', 'guard_name' => 'admin']);
        $admin->givePermissionTo('loans.approve');

        return $admin;
    }

    public function test_approving_from_loan_show_redirects_back_to_that_loan_when_requested(): void
    {
        $admin = $this->makeAdminWithLoanApprovePermission();

        $product = LoanProduct::create([
            'company_id' => $admin->company_id,
            'name' => 'Approval Product',
            'code' => 'APR-'.Str::upper(Str::random(5)),
            'category' => 'character',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'company_id' => $admin->company_id,
            'loan_product_id' => $product->id,
            'first_name' => 'Loan',
            'last_name' => 'Applicant',
            'email' => 'loan-applicant-'.Str::lower(Str::random(6)).'@example.com',
            'phone' => '260955'.random_int(100000, 999999),
            'password' => '1234',
            'tpin' => (string) random_int(10000000, 99999999),
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $loan = Loan::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'loan_number' => 'LN-'.Str::upper(Str::random(10)),
            'principal_amount' => 5000,
            'processing_fee' => 100,
            'interest_accrued' => 200,
            'total_amount' => 5300,
            'outstanding_balance' => 5300,
            'tenure_months' => 3,
            'loan_start_date' => now()->toDateString(),
            'loan_end_date' => now()->addMonths(3)->toDateString(),
            'accrual_type' => 'daily',
            'status' => 'pending_approval',
            'disbursement_status' => 'pending',
        ]);

        $response = $this->actingAs($admin, 'admin')->post(
            route('admin.approvals.loans.approve', $loan),
            [
                'redirect_to_loan' => '1',
                'notes' => 'Approved from loan detail page',
            ]
        );

        $response->assertRedirect(route('admin.loans.show', $loan));

        $loan->refresh();
        $this->assertSame('approved', $loan->status);
        $this->assertSame($admin->id, $loan->approved_by);
        $this->assertSame('Approved from loan detail page', $loan->approval_notes);
        $this->assertNotNull($loan->approved_at);
    }
}
