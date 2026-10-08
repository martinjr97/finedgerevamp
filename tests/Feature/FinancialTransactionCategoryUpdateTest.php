<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Company;
use App\Models\ExpenseCategory;
use App\Models\ExpenseSubcategory;
use App\Models\FinancialTransaction;
use App\Models\IncomeCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class FinancialTransactionCategoryUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(array $permissions = []): Admin
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Txn Category Co '.$suffix,
            'slug' => 'txn-category-co-'.$suffix,
            'code' => 'TC'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Category',
            'last_name' => 'Admin',
            'email' => 'txn-category-'.$suffix.'@example.com',
            'password' => 'password',
            'is_active' => true,
            'approval_status' => 'approved',
            'must_change_password' => false,
        ]);

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'admin']);
            $admin->givePermissionTo($permission);
        }

        return $admin;
    }

    public function test_update_category_requires_permission(): void
    {
        $admin = $this->makeAdmin(['financial-transactions.view']);

        $transaction = FinancialTransaction::create([
            'transaction_number' => 'EXP-TEST-0001',
            'transaction_date' => now()->toDateString(),
            'type' => 'expense',
            'category' => 'office_supplies',
            'description' => 'Test expense',
            'amount' => 100,
            'source_type' => 'wallet',
            'source_id' => 1,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.financial-transactions.category.update', $transaction), [
                'category' => 'office_supplies',
            ])
            ->assertForbidden();
    }

    public function test_expense_category_and_subcategory_can_be_updated(): void
    {
        $admin = $this->makeAdmin(['financial-transactions.view', 'financial-transactions.update-category']);

        $fromCategory = ExpenseCategory::create([
            'name' => 'Office Supplies',
            'code' => 'office_supplies',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $toCategory = ExpenseCategory::create([
            'name' => 'Travel',
            'code' => 'travel',
            'is_active' => true,
            'sort_order' => 2,
        ]);
        $subcategory = ExpenseSubcategory::create([
            'expense_category_id' => $toCategory->id,
            'name' => 'Fuel',
            'is_active' => true,
        ]);

        $transaction = FinancialTransaction::create([
            'transaction_number' => 'EXP-TEST-0002',
            'transaction_date' => now()->toDateString(),
            'type' => 'expense',
            'category' => $fromCategory->code,
            'expense_category_id' => $fromCategory->id,
            'description' => 'Test expense',
            'amount' => 50,
            'source_type' => 'wallet',
            'source_id' => 1,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.financial-transactions.category.update', $transaction), [
                'category' => $toCategory->code,
                'expense_subcategory_id' => $subcategory->id,
            ])
            ->assertRedirect(route('admin.financial-transactions.show', $transaction));

        $transaction->refresh();
        $this->assertSame($toCategory->code, $transaction->category);
        $this->assertSame($toCategory->id, $transaction->expense_category_id);
        $this->assertSame($subcategory->id, $transaction->expense_subcategory_id);
    }

    public function test_income_category_can_be_updated(): void
    {
        $admin = $this->makeAdmin(['financial-transactions.view', 'financial-transactions.update-category']);

        $fromCategory = IncomeCategory::create([
            'name' => 'Interest Income',
            'code' => 'interest_income',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $toCategory = IncomeCategory::create([
            'name' => 'Fee Income',
            'code' => 'fee_income',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $transaction = FinancialTransaction::create([
            'transaction_number' => 'INC-TEST-0001',
            'transaction_date' => now()->toDateString(),
            'type' => 'income',
            'category' => $fromCategory->code,
            'income_category_id' => $fromCategory->id,
            'description' => 'Test income',
            'amount' => 200,
            'destination_type' => 'wallet',
            'destination_id' => 1,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.financial-transactions.category.update', $transaction), [
                'category' => $toCategory->code,
            ])
            ->assertRedirect(route('admin.financial-transactions.show', $transaction));

        $transaction->refresh();
        $this->assertSame($toCategory->code, $transaction->category);
        $this->assertSame($toCategory->id, $transaction->income_category_id);
    }

    public function test_show_page_includes_edit_category_button_with_permission(): void
    {
        $admin = $this->makeAdmin(['financial-transactions.view', 'financial-transactions.update-category']);

        ExpenseCategory::create([
            'name' => 'Misc',
            'code' => 'misc_expense',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $transaction = FinancialTransaction::create([
            'transaction_number' => 'EXP-TEST-0003',
            'transaction_date' => now()->toDateString(),
            'type' => 'expense',
            'category' => 'misc_expense',
            'description' => 'Test expense',
            'amount' => 10,
            'source_type' => 'wallet',
            'source_id' => 1,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.financial-transactions.show', $transaction))
            ->assertOk()
            ->assertSee('Edit category', false);
    }
}
