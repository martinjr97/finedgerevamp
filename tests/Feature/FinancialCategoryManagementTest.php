<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Company;
use App\Models\ExpenseCategory;
use App\Models\IncomeCategory;
use Database\Seeders\FinancialCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class FinancialCategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(array $permissions): Admin
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Fin Cat Co '.$suffix,
            'slug' => 'fin-cat-co-'.$suffix,
            'code' => 'FC'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Finance',
            'last_name' => 'Admin',
            'email' => 'fin-cat-'.$suffix.'@example.com',
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

    public function test_financial_transactions_index_links_to_category_management(): void
    {
        $this->seed(FinancialCategorySeeder::class);
        $admin = $this->makeAdmin(['financial-transactions.view', 'financial-categories.view']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.financial-transactions.index'))
            ->assertOk()
            ->assertSee('Manage Categories')
            ->assertSee(route('admin.financial-categories.index'), false);
    }

    public function test_admin_can_create_expense_and_income_categories(): void
    {
        $this->seed(FinancialCategorySeeder::class);
        $admin = $this->makeAdmin([
            'financial-categories.view',
            'financial-categories.create',
            'financial-categories.update',
        ]);

        $suffix = Str::lower(Str::random(5));

        $this->actingAs($admin, 'admin')
            ->post(route('admin.financial-categories.expense.store'), [
                'name' => 'Transport '.$suffix,
                'description' => 'Fuel and travel',
                'sort_order' => 10,
                'is_active' => 1,
            ])
            ->assertRedirect(route('admin.financial-categories.index'));

        $expenseCategory = ExpenseCategory::query()->where('name', 'Transport '.$suffix)->first();
        $this->assertNotNull($expenseCategory);
        $this->assertTrue($expenseCategory->subcategories()->where('name', 'Unclassified')->exists());

        $this->actingAs($admin, 'admin')
            ->post(route('admin.financial-categories.income.store'), [
                'name' => 'Consulting '.$suffix,
                'code' => 'CONS_'.$suffix,
                'sort_order' => 5,
                'is_active' => 1,
            ])
            ->assertRedirect(route('admin.financial-categories.index'));

        $this->assertTrue(IncomeCategory::query()->where('code', 'CONS_'.$suffix)->exists());
    }

    public function test_admin_can_create_expense_subcategory_from_category_index(): void
    {
        $this->seed(FinancialCategorySeeder::class);
        $admin = $this->makeAdmin([
            'financial-categories.view',
            'financial-categories.create',
        ]);

        $category = ExpenseCategory::query()->where('code', 'operational')->firstOrFail();
        $suffix = Str::lower(Str::random(5));

        $this->actingAs($admin, 'admin')
            ->get(route('admin.financial-categories.index'))
            ->assertOk()
            ->assertSee(route('admin.financial-categories.expense.subcategory.create', $category), false);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.financial-categories.expense.subcategory.store', $category), [
                'name' => 'Office supplies '.$suffix,
                'description' => 'Stationery and consumables',
                'is_active' => 1,
            ])
            ->assertRedirect(route('admin.financial-categories.expense.edit', $category));

        $this->assertTrue(
            $category->subcategories()->where('name', 'Office supplies '.$suffix)->exists()
        );
    }
}
