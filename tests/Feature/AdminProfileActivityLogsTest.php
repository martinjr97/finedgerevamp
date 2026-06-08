<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminProfileActivityLogsTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(string $suffix): Company
    {
        return Company::create([
            'name' => 'Profile Co '.$suffix,
            'slug' => 'profile-co-'.$suffix,
            'code' => 'PC'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);
    }

    private function makeAdmin(string $suffix): Admin
    {
        $company = $this->makeCompany($suffix);

        return Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Profile',
            'last_name' => 'Admin',
            'email' => 'profile-admin-'.$suffix.'@example.com',
            'password' => 'password',
            'is_active' => true,
            'approval_status' => 'approved',
            'must_change_password' => false,
        ]);
    }

    public function test_profile_activity_section_uses_audit_logs_and_excludes_login_timestamp_updates(): void
    {
        $suffix = Str::lower(Str::random(6));
        $admin = $this->makeAdmin($suffix);

        AuditLog::create([
            'event' => 'updated',
            'auditable_type' => Admin::class,
            'auditable_id' => (string) $admin->id,
            'old_values' => ['phone' => null],
            'new_values' => ['phone' => '260977000111'],
            'changed_fields' => ['phone'],
            'actor_type' => Admin::class,
            'actor_id' => (string) $admin->id,
            'actor_name' => $admin->full_name,
            'actor_guard' => 'admin',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'url' => 'http://localhost/admin/profile',
            'http_method' => 'PATCH',
            'metadata' => ['route_name' => 'admin.profile.update-name'],
        ]);

        AuditLog::create([
            'event' => 'updated',
            'auditable_type' => Admin::class,
            'auditable_id' => (string) $admin->id,
            'old_values' => ['last_login_at' => null, 'last_login_ip' => null],
            'new_values' => ['last_login_at' => now()->toIso8601String(), 'last_login_ip' => '127.0.0.1'],
            'changed_fields' => ['last_login_at', 'last_login_ip'],
            'actor_type' => Admin::class,
            'actor_id' => (string) $admin->id,
            'actor_name' => $admin->full_name,
            'actor_guard' => 'admin',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'url' => 'http://localhost/admin/login',
            'http_method' => 'POST',
            'metadata' => ['route_name' => 'admin.login.store'],
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.profile.show'));

        $response->assertStatus(200);
        $response->assertSee('Updated Admin');
        $response->assertSee('Updated fields: phone');
        $response->assertDontSee('Updated fields: last login at, last login ip');
    }
}

