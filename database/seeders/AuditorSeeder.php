<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Company;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AuditorSeeder extends Seeder
{
    public function run(): void
    {
        $operator = Company::firstWhere('slug', 'main-operator')
            ?? Company::first();

        $domain = config('app.email_domain');


        if (! $operator) {
            $this->call(CompanySeeder::class);
            $operator = Company::firstWhere('slug', 'main-operator');
        }

        $admin = Admin::updateOrCreate(
            ['email' => "auditor@{$domain}"],
            [
                'company_id' => optional($operator)->id,
                'first_name' => 'Havencrest',
                'last_name' => 'Auditor',
                'phone' => '+260799999998',
                'password' => Hash::make('ChangeMe123!'),
                'is_active' => true,
                'is_relationship_manager' => false,
                'must_change_password' => false,
                'email_verified_at' => now(),
                'preferences' => [
                    'theme' => 'light',
                ],
            ]
        );

        $role = Role::firstOrCreate(
            ['name' => 'auditor', 'guard_name' => 'admin']
        );
        $admin->syncRoles($role);
    }
}

