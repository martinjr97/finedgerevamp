<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Company;
use App\Models\ExpenseCategory;
use App\Models\FinancialTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class FinancialTransactionIndexFilterTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): Admin
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Txn Filter Co '.$suffix,
            'slug' => 'txn-filter-co-'.$suffix,
            'code' => 'TF'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Txn',
            'last_name' => 'Admin',
            'email' => 'txn-filter-'.$suffix.'@example.com',
            'password' => 'password',
            'is_active' => true,
            'approval_status' => 'approved',
            'must_change_password' => false,
        ]);

        Permission::firstOrCreate(['name' => 'financial-transactions.view', 'guard_name' => 'admin']);
        $admin->givePermissionTo('financial-transactions.view');

        return $admin;
    }

    public function test_expense_list_hides_loan_take_out_by_default(): void
    {
        $admin = $this->makeAdmin();

        $loanTakeout = ExpenseCategory::create([
            'name' => 'Loan Take Out',
            'code' => 'loan_take_out',
            'is_active' => true,
        ]);

        $utilities = ExpenseCategory::create([
            'name' => 'Utilities',
            'code' => 'utilities',
            'is_active' => true,
        ]);

        FinancialTransaction::create([
            'transaction_number' => 'EXP-LOAN-1',
            'transaction_date' => now()->toDateString(),
            'type' => 'expense',
            'category' => $loanTakeout->code,
            'expense_category_id' => $loanTakeout->id,
            'description' => 'Loan disbursement',
            'amount' => 5000,
            'source_type' => 'bank',
            'source_id' => 1,
        ]);

        FinancialTransaction::create([
            'transaction_number' => 'EXP-UTIL-1',
            'transaction_date' => now()->toDateString(),
            'type' => 'expense',
            'category' => $utilities->code,
            'expense_category_id' => $utilities->id,
            'description' => 'Power bill',
            'amount' => 200,
            'source_type' => 'bank',
            'source_id' => 1,
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.financial-transactions.index', ['type' => 'expense']));

        $response->assertOk();
        $response->assertSee('Power bill');
        $response->assertDontSee('Loan disbursement');
    }

    public function test_expense_list_can_show_loan_take_out_when_checkbox_unchecked(): void
    {
        $admin = $this->makeAdmin();

        $loanTakeout = ExpenseCategory::create([
            'name' => 'Loan Take Out',
            'code' => 'loan_take_out',
            'is_active' => true,
        ]);

        FinancialTransaction::create([
            'transaction_number' => 'EXP-LOAN-2',
            'transaction_date' => now()->toDateString(),
            'type' => 'expense',
            'category' => $loanTakeout->code,
            'expense_category_id' => $loanTakeout->id,
            'description' => 'Visible loan disbursement',
            'amount' => 9000,
            'source_type' => 'bank',
            'source_id' => 1,
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.financial-transactions.index', [
                'type' => 'expense',
                'exclude_loan_takeouts' => '0',
            ]));

        $response->assertOk();
        $response->assertSee('Visible loan disbursement');
    }
}
