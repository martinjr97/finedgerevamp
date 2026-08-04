<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Company;
use App\Models\FinancialInstitution;
use App\Models\FinancialInstitutionBranch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AdminFinancialInstitutionManagementTest extends TestCase
{
    use RefreshDatabase;

    private function adminWithPermissions(array $permissions): Admin
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'FI Admin Co '.$suffix,
            'slug' => 'fi-admin-co-'.$suffix,
            'code' => 'FI'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Bank',
            'last_name' => 'Admin',
            'email' => 'fi-admin-'.$suffix.'@example.com',
            'password' => 'password',
            'is_active' => true,
            'approval_status' => 'approved',
        ]);

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'admin']);
        }

        $admin->givePermissionTo($permissions);

        return $admin;
    }

    public function test_admin_can_list_financial_institutions(): void
    {
        $admin = $this->adminWithPermissions(['financial-institutions.view']);

        FinancialInstitution::create([
            'name' => 'Test Bank',
            'code' => 'TEST_BANK',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.financial-institutions.index'));

        $response->assertOk();
        $response->assertSee('Financial Institutions');
        $response->assertSee('Test Bank');
        $response->assertDontSee('name="ids[]"', false);
        $response->assertDontSee('Activate selected', false);
    }

    public function test_index_shows_bulk_controls_when_user_can_update(): void
    {
        $admin = $this->adminWithPermissions([
            'financial-institutions.view',
            'financial-institutions.update',
        ]);

        FinancialInstitution::create([
            'name' => 'Selectable Bank',
            'code' => 'SEL',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.financial-institutions.index'))
            ->assertOk()
            ->assertSee('Activate selected', false)
            ->assertSee('Deactivate selected', false)
            ->assertSee('name="ids[]"', false)
            ->assertSee('id="select_all_institutions"', false);
    }

    public function test_admin_can_create_institution_and_add_branch(): void
    {
        $admin = $this->adminWithPermissions([
            'financial-institutions.view',
            'financial-institutions.create',
            'financial-institutions.update',
        ]);

        $createResponse = $this->actingAs($admin, 'admin')->post(route('admin.financial-institutions.store'), [
            'name' => 'New Zambia Bank',
            'code' => 'NZB',
            'is_active' => '1',
        ]);

        $institution = FinancialInstitution::where('code', 'NZB')->first();
        $this->assertNotNull($institution);
        $createResponse->assertRedirect(route('admin.financial-institutions.branches', $institution));

        $branchResponse = $this->actingAs($admin, 'admin')->post(
            route('admin.financial-institutions.branches.store', $institution),
            [
                'name' => 'Lusaka Centre',
                'code' => 'LUS',
                'sort_code' => '001',
                'is_active' => '1',
            ]
        );

        $branchResponse->assertRedirect(route('admin.financial-institutions.branches', $institution));
        $this->assertDatabaseHas('financial_institution_branches', [
            'financial_institution_id' => $institution->id,
            'name' => 'Lusaka Centre',
            'code' => 'LUS',
        ]);
    }

    public function test_admin_can_update_institution_and_branch(): void
    {
        $admin = $this->adminWithPermissions([
            'financial-institutions.view',
            'financial-institutions.update',
        ]);

        $institution = FinancialInstitution::create([
            'name' => 'Old Bank',
            'code' => 'OLD',
            'is_active' => true,
        ]);

        $branch = FinancialInstitutionBranch::create([
            'financial_institution_id' => $institution->id,
            'name' => 'Main Branch',
            'code' => 'MAIN',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.financial-institutions.update', $institution), [
                'name' => 'Renamed Bank',
                'code' => 'OLD',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.financial-institutions.edit', $institution));

        $this->actingAs($admin, 'admin')
            ->put(route('admin.financial-institutions.branches.update', [$institution, $branch]), [
                'name' => 'Kitwe Branch',
                'code' => 'KIT',
                'sort_code' => '002',
                'is_active' => '0',
            ])
            ->assertRedirect(route('admin.financial-institutions.branches', $institution));

        $institution->refresh();
        $branch->refresh();

        $this->assertSame('Renamed Bank', $institution->name);
        $this->assertSame('Kitwe Branch', $branch->name);
        $this->assertFalse($branch->is_active);
    }

    public function test_branch_edit_is_scoped_to_institution(): void
    {
        $admin = $this->adminWithPermissions(['financial-institutions.update']);

        $institutionA = FinancialInstitution::create(['name' => 'Bank A', 'code' => 'A', 'is_active' => true]);
        $institutionB = FinancialInstitution::create(['name' => 'Bank B', 'code' => 'B', 'is_active' => true]);
        $branchOnB = FinancialInstitutionBranch::create([
            'financial_institution_id' => $institutionB->id,
            'name' => 'Branch B',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.financial-institutions.branches.edit', [$institutionA, $branchOnB]))
            ->assertNotFound();
    }

    public function test_admin_can_bulk_activate_and_deactivate_institutions(): void
    {
        $admin = $this->adminWithPermissions([
            'financial-institutions.view',
            'financial-institutions.update',
        ]);

        $active = FinancialInstitution::create([
            'name' => 'Active Bank',
            'code' => 'ACT',
            'is_active' => true,
        ]);
        $inactive = FinancialInstitution::create([
            'name' => 'Inactive Bank',
            'code' => 'INACT',
            'is_active' => false,
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.financial-institutions.bulk-status'), [
                'ids' => [$active->id, $inactive->id],
                'action' => 'deactivate',
            ])
            ->assertRedirect(route('admin.financial-institutions.index'))
            ->assertSessionHas('status');

        $this->assertFalse($active->fresh()->is_active);
        $this->assertFalse($inactive->fresh()->is_active);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.financial-institutions.bulk-status'), [
                'ids' => [$active->id, $inactive->id],
                'action' => 'activate',
            ])
            ->assertRedirect(route('admin.financial-institutions.index'));

        $this->assertTrue($active->fresh()->is_active);
        $this->assertTrue($inactive->fresh()->is_active);
    }

    public function test_bulk_status_requires_update_permission(): void
    {
        $admin = $this->adminWithPermissions(['financial-institutions.view']);

        $institution = FinancialInstitution::create([
            'name' => 'Permission Bank',
            'code' => 'PERM',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.financial-institutions.bulk-status'), [
                'ids' => [$institution->id],
                'action' => 'deactivate',
            ])
            ->assertForbidden();

        $this->assertTrue($institution->fresh()->is_active);
    }

    public function test_guest_cannot_access_financial_institutions(): void
    {
        $this->get(route('admin.financial-institutions.index'))->assertRedirect();
    }
}
