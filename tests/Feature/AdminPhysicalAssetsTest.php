<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Asset;
use App\Models\Company;
use App\Models\FinancialTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AdminPhysicalAssetsTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(array $permissions): Admin
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Assets Co '.$suffix,
            'slug' => 'assets-co-'.$suffix,
            'code' => 'AC'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Asset',
            'last_name' => 'Admin',
            'email' => 'asset-admin-'.$suffix.'@example.com',
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

    public function test_admin_can_create_physical_asset_and_see_it_on_balance_sheet(): void
    {
        $admin = $this->makeAdmin([
            'assets.view',
            'assets.create',
            'financial-statements.view',
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.assets.store'), [
                'asset_type' => 'Furniture',
                'name' => 'Office Desk',
                'value' => 1500.50,
                'acquisition_date' => now()->toDateString(),
                'description' => 'Main office desk',
                'is_active' => 1,
            ])
            ->assertRedirect(route('admin.assets.index'));

        $this->assertDatabaseHas('assets', [
            'name' => 'Office Desk',
            'asset_type' => 'Furniture',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.financial-statements.balance-sheet', [
                'as_of_date' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Physical Assets')
            ->assertSee('Office Desk')
            ->assertSee('1,500.50');
    }

    public function test_income_statement_excludes_shareholder_contribution(): void
    {
        $admin = $this->makeAdmin(['financial-statements.view']);

        FinancialTransaction::create([
            'transaction_number' => FinancialTransaction::generateTransactionNumber('income'),
            'type' => 'income',
            'category' => 'shareholder_contribution',
            'amount' => 5000,
            'transaction_date' => now()->toDateString(),
            'description' => 'Shareholder funds',
            'created_by' => $admin->id,
        ]);

        FinancialTransaction::create([
            'transaction_number' => FinancialTransaction::generateTransactionNumber('income'),
            'type' => 'income',
            'category' => 'other_income',
            'amount' => 250,
            'transaction_date' => now()->toDateString(),
            'description' => 'Misc income',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.financial-statements.income-statement', [
                'start_date' => now()->startOfMonth()->toDateString(),
                'end_date' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Other Income')
            ->assertDontSee('Shareholder Contribution');
    }

    public function test_assets_index_requires_permission(): void
    {
        $admin = $this->makeAdmin([]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.assets.index'))
            ->assertForbidden();
    }
}
