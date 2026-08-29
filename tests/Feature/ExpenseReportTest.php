<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Bank;
use App\Models\Company;
use App\Models\Employee;
use App\Models\ExpenseCategory;
use App\Models\FinancialTransaction;
use Database\Seeders\FinancialCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ExpenseReportTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(array $permissions): Admin
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Expense Report Co '.$suffix,
            'slug' => 'expense-report-co-'.$suffix,
            'code' => 'ER'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Report',
            'last_name' => 'Admin',
            'email' => 'expense-report-'.$suffix.'@example.com',
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

    public function test_expenses_report_shows_category_breakdown_and_receiver_insights(): void
    {
        $this->seed(FinancialCategorySeeder::class);
        $admin = $this->makeAdmin(['reports.view', 'financial-transactions.create']);

        $category = ExpenseCategory::query()->where('code', 'utilities')->firstOrFail();
        $bank = Bank::create([
            'name' => 'Expense Report Bank',
            'account_number' => 'ERB-'.Str::lower(Str::random(4)),
            'account_name' => 'Expense Report Co',
            'bank_name' => 'Test Bank',
            'opening_balance' => 100000,
            'current_balance' => 100000,
            'is_active' => true,
        ]);
        $employee = Employee::create([
            'employee_number' => 'EMP-'.Str::upper(Str::random(4)),
            'first_name' => 'Jane',
            'last_name' => 'Receiver',
            'is_active' => true,
        ]);

        FinancialTransaction::create([
            'transaction_number' => 'EXP-TEST-1',
            'transaction_date' => now()->toDateString(),
            'type' => 'expense',
            'category' => $category->code,
            'expense_category_id' => $category->id,
            'description' => 'Office power bill',
            'receiver_name' => 'ZESCO',
            'amount' => 1500,
            'source_type' => 'bank',
            'source_id' => $bank->id,
            'created_by' => $admin->id,
        ]);

        FinancialTransaction::create([
            'transaction_number' => 'EXP-TEST-2',
            'transaction_date' => now()->toDateString(),
            'type' => 'expense',
            'category' => $category->code,
            'expense_category_id' => $category->id,
            'description' => 'Staff travel',
            'receiver_name' => 'Jane Receiver',
            'employee_id' => $employee->id,
            'amount' => 500,
            'source_type' => 'bank',
            'source_id' => $bank->id,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.reports.expenses'))
            ->assertOk()
            ->assertSee('Expenses Report')
            ->assertSee('Spending by category')
            ->assertSee('Utilities')
            ->assertSee('ZESCO')
            ->assertSee('ZMW 2,000.00');
    }

    public function test_store_expense_saves_receiver_name_and_employee_link(): void
    {
        $this->seed(FinancialCategorySeeder::class);
        $admin = $this->makeAdmin(['financial-transactions.create']);

        $bank = Bank::create([
            'name' => 'Expense Store Bank',
            'account_number' => 'ESB-'.Str::lower(Str::random(4)),
            'account_name' => 'Expense Store Co',
            'bank_name' => 'Test Bank',
            'opening_balance' => 5000,
            'current_balance' => 5000,
            'is_active' => true,
        ]);
        $employee = Employee::create([
            'employee_number' => 'EMP-'.Str::upper(Str::random(4)),
            'first_name' => 'John',
            'last_name' => 'Payee',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.financial-transactions.expense.store'), [
                'transaction_date' => now()->toDateString(),
                'category' => 'operational',
                'description' => 'Field allowance',
                'employee_id' => $employee->id,
                'receiver_name' => 'John Payee',
                'amount' => 250,
                'source_type' => 'bank',
                'source_id' => $bank->id,
            ])
            ->assertRedirect(route('admin.financial-transactions.index'));

        $this->assertDatabaseHas('financial_transactions', [
            'description' => 'Field allowance',
            'receiver_name' => 'John Payee',
            'employee_id' => $employee->id,
            'type' => 'expense',
        ]);
    }
}
