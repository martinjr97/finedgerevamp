<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Bank;
use App\Models\Company;
use App\Models\Creditor;
use App\Models\CreditorConversion;
use App\Models\ExpenseCategory;
use App\Models\FinancialTransaction;
use App\Models\IncomeCategory;
use App\Services\CreditorBalanceService;
use Database\Seeders\FinancialCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CreditorRepaymentTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(array $permissions): Admin
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Creditor Co '.$suffix,
            'slug' => 'creditor-co-'.$suffix,
            'code' => 'CR'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Creditor',
            'last_name' => 'Admin',
            'email' => 'creditor-admin-'.$suffix.'@example.com',
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

    public function test_creditor_loan_repayment_expense_reduces_creditor_balance(): void
    {
        $this->seed(FinancialCategorySeeder::class);

        $admin = $this->makeAdmin(['financial-transactions.create']);
        $bank = Bank::create([
            'name' => 'Creditor Bank',
            'account_number' => 'CRB-'.Str::lower(Str::random(4)),
            'account_name' => 'Creditor Co',
            'bank_name' => 'Test Bank',
            'opening_balance' => 50000,
            'current_balance' => 50000,
            'is_active' => true,
        ]);

        $creditor = Creditor::create([
            'name' => 'Starlabs Lending',
            'amount' => 10000,
            'is_active' => true,
        ]);

        $category = ExpenseCategory::query()
            ->where('code', CreditorBalanceService::CREDITOR_LOAN_REPAYMENT_CODE)
            ->firstOrFail();

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.financial-transactions.expense.store'), [
                'transaction_date' => now()->toDateString(),
                'category' => $category->code,
                'description' => 'Partial creditor repayment',
                'amount' => 2500,
                'creditor_id' => $creditor->id,
                'source_type' => 'bank',
                'source_id' => $bank->id,
            ]);

        $response->assertRedirect(route('admin.financial-transactions.index'));

        $creditor->refresh();
        $bank->refresh();

        $this->assertSame(7500.0, (float) $creditor->amount);
        $this->assertSame(47500.0, (float) $bank->current_balance);

        $this->assertDatabaseHas('financial_transactions', [
            'type' => 'expense',
            'creditor_id' => $creditor->id,
            'amount' => 2500,
        ]);
    }

    public function test_creditor_loan_repayment_requires_creditor_selection(): void
    {
        $this->seed(FinancialCategorySeeder::class);

        $admin = $this->makeAdmin(['financial-transactions.create']);
        $bank = Bank::create([
            'name' => 'Creditor Bank 2',
            'account_number' => 'CRB2-'.Str::lower(Str::random(4)),
            'account_name' => 'Creditor Co',
            'bank_name' => 'Test Bank',
            'opening_balance' => 10000,
            'current_balance' => 10000,
            'is_active' => true,
        ]);

        $category = ExpenseCategory::query()
            ->where('code', CreditorBalanceService::CREDITOR_LOAN_REPAYMENT_CODE)
            ->firstOrFail();

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.financial-transactions.expense.store'), [
                'transaction_date' => now()->toDateString(),
                'category' => $category->code,
                'description' => 'Missing creditor',
                'amount' => 500,
                'source_type' => 'bank',
                'source_id' => $bank->id,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_deleting_creditor_repayment_expense_restores_creditor_balance(): void
    {
        $this->seed(FinancialCategorySeeder::class);

        $admin = $this->makeAdmin(['financial-transactions.create', 'financial-transactions.delete']);
        $bank = Bank::create([
            'name' => 'Creditor Bank 3',
            'account_number' => 'CRB3-'.Str::lower(Str::random(4)),
            'account_name' => 'Creditor Co',
            'bank_name' => 'Test Bank',
            'opening_balance' => 20000,
            'current_balance' => 20000,
            'is_active' => true,
        ]);

        $creditor = Creditor::create([
            'name' => 'Legacy Lender',
            'amount' => 8000,
            'is_active' => true,
        ]);

        $category = ExpenseCategory::query()
            ->where('code', CreditorBalanceService::CREDITOR_LOAN_REPAYMENT_CODE)
            ->firstOrFail();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.financial-transactions.expense.store'), [
                'transaction_date' => now()->toDateString(),
                'category' => $category->code,
                'description' => 'Repayment to reverse',
                'amount' => 2000,
                'creditor_id' => $creditor->id,
                'source_type' => 'bank',
                'source_id' => $bank->id,
            ]);

        $transaction = FinancialTransaction::query()->where('creditor_id', $creditor->id)->firstOrFail();
        $creditor->refresh();
        $this->assertSame(6000.0, (float) $creditor->amount);

        $this->actingAs($admin, 'admin')
            ->delete(route('admin.financial-transactions.destroy', $transaction));

        $creditor->refresh();
        $this->assertSame(8000.0, (float) $creditor->amount);
    }

    public function test_creditor_conversion_reduces_balance_and_credits_bank(): void
    {
        $this->seed(FinancialCategorySeeder::class);

        $admin = $this->makeAdmin(['creditors.view', 'creditors.update']);
        $bank = Bank::create([
            'name' => 'Conversion Bank',
            'account_number' => 'CVB-'.Str::lower(Str::random(4)),
            'account_name' => 'Creditor Co',
            'bank_name' => 'Test Bank',
            'opening_balance' => 1000,
            'current_balance' => 1000,
            'is_active' => true,
        ]);

        $creditor = Creditor::create([
            'name' => 'Convertible Creditor',
            'amount' => 5000,
            'is_active' => true,
        ]);

        IncomeCategory::query()->where('code', 'creditor_conversion')->firstOrFail();

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.creditors.convert', $creditor), [
                'amount' => 1500,
                'destination_type' => 'bank',
                'destination_id' => $bank->id,
                'notes' => 'Equity conversion',
            ]);

        $response->assertRedirect(route('admin.creditors.show', $creditor));

        $creditor->refresh();
        $bank->refresh();

        $this->assertSame(3500.0, (float) $creditor->amount);
        $this->assertSame(2500.0, (float) $bank->current_balance);
        $this->assertSame(1, CreditorConversion::query()->where('creditor_id', $creditor->id)->count());
    }

    public function test_expense_create_from_creditor_prefills_category_and_creditor(): void
    {
        $this->seed(FinancialCategorySeeder::class);

        $admin = $this->makeAdmin(['financial-transactions.create', 'creditors.view']);
        $creditor = Creditor::create([
            'name' => 'Prefill Creditor',
            'amount' => 3000,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.financial-transactions.expense.create', [
                'creditor_id' => $creditor->id,
            ]));

        $response->assertOk();
        $response->assertSee('Prefill Creditor');
        $response->assertSee('Creditor Loan Repayment');
        $response->assertSee('Creditor loan repayment for Prefill Creditor');
        $response->assertSee('Back to Creditor');
    }

    public function test_creditor_payment_redirects_back_to_creditor_show(): void
    {
        $this->seed(FinancialCategorySeeder::class);

        $admin = $this->makeAdmin(['financial-transactions.create', 'creditors.view']);
        $bank = Bank::create([
            'name' => 'Return Bank',
            'account_number' => 'RTB-'.Str::lower(Str::random(4)),
            'account_name' => 'Creditor Co',
            'bank_name' => 'Test Bank',
            'opening_balance' => 10000,
            'current_balance' => 10000,
            'is_active' => true,
        ]);

        $creditor = Creditor::create([
            'name' => 'Return Creditor',
            'amount' => 2000,
            'is_active' => true,
        ]);

        $category = ExpenseCategory::query()
            ->where('code', CreditorBalanceService::CREDITOR_LOAN_REPAYMENT_CODE)
            ->firstOrFail();

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.financial-transactions.expense.store'), [
                'transaction_date' => now()->toDateString(),
                'category' => $category->code,
                'description' => 'Payment with return',
                'amount' => 500,
                'creditor_id' => $creditor->id,
                'return_creditor_id' => $creditor->id,
                'source_type' => 'bank',
                'source_id' => $bank->id,
            ]);

        $response->assertRedirect(route('admin.creditors.show', $creditor));
        $creditor->refresh();
        $this->assertSame(1500.0, (float) $creditor->amount);
    }
}
