<?php

namespace Tests\Feature\Sms;

use App\Models\Admin;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SmsOperationsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_sms_operations_page(): void
    {
        $admin = $this->makeAdmin(['sms-operations.view']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.sms-operations.index'))
            ->assertOk()
            ->assertSee('SMS Operations')
            ->assertSee('SMS Templates')
            ->assertDontSee('ZAMTEL_SMS_API_KEY');
    }

    public function test_admin_can_view_sms_templates_index(): void
    {
        $this->seed(\Database\Seeders\SmsTemplateSeeder::class);
        $admin = $this->makeAdmin(['sms-operations.view']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.sms-templates.index'))
            ->assertOk()
            ->assertSee('customer_approved');
    }

    public function test_admin_without_permission_cannot_view_sms_operations(): void
    {
        $admin = $this->makeAdmin(['settings.view']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.sms-operations.index'))
            ->assertForbidden();
    }

    /**
     * @param  list<string>  $permissions
     */
    private function makeAdmin(array $permissions): Admin
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Admin Co '.$suffix,
            'slug' => 'admin-'.$suffix,
            'code' => 'A'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin-'.$suffix.'@example.com',
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
}
