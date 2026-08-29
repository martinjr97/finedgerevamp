<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Asset;
use App\Models\Company;
use App\Models\Employee;
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

    public function test_admin_can_add_employee_and_assign_as_asset_owner(): void
    {
        $admin = $this->makeAdmin([
            'assets.view',
            'assets.create',
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.employees.store'), [
                'first_name' => 'Jane',
                'last_name' => 'Mwale',
                'employee_number' => 'EMP-001',
                'department' => 'Finance',
                'is_active' => 1,
            ])
            ->assertRedirect(route('admin.employees.index'));

        $employee = Employee::query()->where('employee_number', 'EMP-001')->firstOrFail();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.assets.store'), [
                'asset_type' => 'Equipment',
                'name' => 'Company Laptop',
                'value' => 8000,
                'employee_id' => $employee->id,
                'is_active' => 1,
            ])
            ->assertRedirect(route('admin.assets.index'));

        $this->assertDatabaseHas('assets', [
            'name' => 'Company Laptop',
            'employee_id' => $employee->id,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.assets.index'))
            ->assertOk()
            ->assertSee('Jane Mwale')
            ->assertSee('Company Laptop');
    }

    public function test_admin_can_transfer_asset_between_employees_with_trail(): void
    {
        $admin = $this->makeAdmin([
            'assets.view',
            'assets.create',
            'assets.update',
        ]);

        $fromEmployee = Employee::create([
            'first_name' => 'Alice',
            'last_name' => 'Banda',
            'employee_number' => 'EMP-A',
            'is_active' => true,
        ]);

        $toEmployee = Employee::create([
            'first_name' => 'Bob',
            'last_name' => 'Zulu',
            'employee_number' => 'EMP-B',
            'is_active' => true,
        ]);

        $asset = Asset::create([
            'asset_type' => 'Equipment',
            'name' => 'Projector',
            'value' => 5000,
            'employee_id' => $fromEmployee->id,
            'is_active' => true,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $this->actingAs($admin, 'admin')
            ->from(route('admin.assets.show', $asset))
            ->post(route('admin.assets.transfer', $asset), [
                'to_employee_id' => $toEmployee->id,
                'reason' => 'Alice moved to another department',
            ])
            ->assertRedirect(route('admin.assets.show', $asset));

        $asset->refresh();

        $this->assertSame($toEmployee->id, $asset->employee_id);
        $this->assertDatabaseHas('asset_transfers', [
            'asset_id' => $asset->id,
            'from_employee_id' => $fromEmployee->id,
            'to_employee_id' => $toEmployee->id,
            'reason' => 'Alice moved to another department',
            'transferred_by' => $admin->id,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.assets.show', $asset))
            ->assertOk()
            ->assertSee('Transfer History')
            ->assertSee('Alice Banda')
            ->assertSee('Bob Zulu')
            ->assertSee('Alice moved to another department');
    }
}
