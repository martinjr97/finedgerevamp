<?php

namespace App\Console\Commands;

use App\Models\Admin;
use Database\Seeders\PermissionSeeder;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SyncSuperAdminPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permissions:sync-super-admin';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync all permissions to super-admin role and clear cache';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Syncing permissions to super-admin role...');

        // Clear permission cache
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Get or create super-admin role
        $superAdminRole = Role::firstOrCreate(
            ['name' => PermissionSeeder::SUPER_ADMIN_ROLE, 'guard_name' => 'admin']
        );

        // Get all permissions
        $allPermissions = Permission::where('guard_name', 'admin')->get();

        if ($allPermissions->isEmpty()) {
            $this->warn('No permissions found. Running PermissionSeeder...');
            $this->call('db:seed', ['--class' => PermissionSeeder::class]);
            $allPermissions = Permission::where('guard_name', 'admin')->get();
        }

        // Sync all permissions to super-admin role
        $superAdminRole->syncPermissions($allPermissions);

        $this->info("✓ Synced {$allPermissions->count()} permissions to super-admin role");

        // Update all super-admin users
        $superAdmins = Admin::role(PermissionSeeder::SUPER_ADMIN_ROLE)->get();
        $this->info("✓ Found {$superAdmins->count()} super-admin user(s)");

        // Clear cache again
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->info('✓ Permission cache cleared');
        $this->newLine();
        $this->info('Super-admin permissions synced successfully!');
        $this->info('Note: Users may need to log out and log back in to see changes.');

        return Command::SUCCESS;
    }
}
