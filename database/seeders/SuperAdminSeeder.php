<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Company;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $operator = Company::firstWhere('slug', 'main-operator')
            ?? Company::first();

        if (! $operator) {
            $this->call(CompanySeeder::class);
            $operator = Company::firstWhere('slug', 'main-operator');
        }

        $domain = config('app.email_domain');

        $superAdmins = [
            [
                'email' => "superadmin@{$domain}",
                'first_name' => 'LMS',
                'last_name' => 'Super Admin',
                'phone' => '+26070000000',
            ],
            [
                'email' => "information-specialist@{$domain}",
                'first_name' => 'Information',
                'last_name' => 'Specialist',
                'phone' => '+26070000002',
            ],
        ];

        $admins = [];
        foreach ($superAdmins as $superAdmin) {
            $admins[] = Admin::updateOrCreate(
                ['email' => $superAdmin['email']],
                [
                    'company_id' => optional($operator)->id,
                    'first_name' => $superAdmin['first_name'],
                    'last_name' => $superAdmin['last_name'],
                    'phone' => $superAdmin['phone'],
                    'password' => Hash::make('ChangeMe123!'),
                    'is_active' => true,
                    'is_relationship_manager' => true,
                    'must_change_password' => false,
                    'email_verified_at' => now(),
                    'preferences' => [
                        'theme' => 'light',
                    ],
                ]
            );
        }

        // Ensure PermissionSeeder has been run first
        if (!Role::where('name', PermissionSeeder::SUPER_ADMIN_ROLE)->where('guard_name', 'admin')->exists()) {
            $this->call(PermissionSeeder::class);
        }

        $role = Role::firstOrCreate(
            ['name' => PermissionSeeder::SUPER_ADMIN_ROLE, 'guard_name' => 'admin']
        );
        
        // Sync all permissions to super-admin role (in case PermissionSeeder was run before)
        $allPermissions = \Spatie\Permission\Models\Permission::where('guard_name', 'admin')->get();
        $role->syncPermissions($allPermissions);
        
        foreach ($admins as $admin) {
            $admin->syncRoles($role);
        }
        
        // Clear permission cache
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}


