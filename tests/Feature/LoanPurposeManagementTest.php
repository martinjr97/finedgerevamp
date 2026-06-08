<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Company;
use App\Models\LoanPurpose;
use Database\Seeders\LoanPurposeSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class LoanPurposeManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, LoanPurposeSeeder::class]);
    }

    private function adminWithPermissions(array $permissions): Admin
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Purpose Co '.$suffix,
            'slug' => 'purpose-co-'.$suffix,
            'code' => 'PC'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Purpose',
            'last_name' => 'Admin',
            'email' => 'purpose-'.$suffix.'@example.com',
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

    public function test_admin_can_view_loan_purposes_index(): void
    {
        $admin = $this->adminWithPermissions(['loan-purposes.view']);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-purposes.index'));

        $response->assertOk();
        $response->assertSee('Personal Use', false);
        $response->assertSee('School Fees', false);
    }

    public function test_admin_can_create_loan_purpose(): void
    {
        $admin = $this->adminWithPermissions(['loan-purposes.create']);

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-purposes.store'), [
                'name' => 'Wedding Expenses',
                'description' => 'Funding wedding costs',
                'sort_order' => 20,
                'is_active' => 1,
            ]);

        $response->assertRedirect(route('admin.loan-purposes.index'));
        $this->assertDatabaseHas('loan_purposes', [
            'name' => 'Wedding Expenses',
            'sort_order' => 20,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_delete_unused_loan_purpose(): void
    {
        $admin = $this->adminWithPermissions(['loan-purposes.delete']);
        $purpose = LoanPurpose::create([
            'name' => 'Temporary Purpose',
            'sort_order' => 99,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->delete(route('admin.loan-purposes.destroy', $purpose));

        $response->assertRedirect(route('admin.loan-purposes.index'));
        $this->assertSoftDeleted('loan_purposes', ['id' => $purpose->id]);
    }

}
