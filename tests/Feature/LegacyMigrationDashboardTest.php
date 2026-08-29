<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Company;
use App\Support\PermissionMatrix;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LegacyMigrationDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        config(['migration-dashboard.enabled' => true]);
    }

    private function company(): Company
    {
        $suffix = Str::lower(Str::random(5));

        return Company::create([
            'name' => 'Migration Co '.$suffix,
            'slug' => 'migration-'.$suffix,
            'code' => 'MC'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);
    }

    private function adminWithRole(Company $company, string $role): Admin
    {
        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Migration',
            'last_name' => 'Viewer',
            'email' => 'migration-'.Str::random(5).'@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);

        Role::findOrCreate($role, 'admin');
        $admin->assignRole($role);

        return $admin;
    }

    private function seedMigrationRun(): int
    {
        return DB::table('migration_runs')->insertGetId([
            'name' => 'test-run',
            'phase' => 'm2-customers',
            'scope' => 'pilot',
            'status' => 'completed',
            'summary' => json_encode([
                'promote' => false,
                'read' => 10,
                'created' => 2,
                'matched_existing' => 3,
            ]),
            'run_uuid' => (string) Str::uuid(),
            'started_at' => now(),
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_feature_flag_disables_dashboard_routes(): void
    {
        config(['migration-dashboard.enabled' => false]);
        $admin = $this->adminWithRole($this->company(), PermissionMatrix::SUPER_ADMIN_ROLE);

        $this->actingAs($admin, 'admin')
            ->get(route('legacy.migration-dashboard.index'))
            ->assertNotFound();
    }

    public function test_unauthorized_admin_cannot_access_dashboard(): void
    {
        $admin = $this->adminWithRole($this->company(), 'auditor');

        $this->actingAs($admin, 'admin')
            ->get(route('legacy.migration-dashboard.index'))
            ->assertForbidden();
    }

    public function test_authorized_migration_admin_can_access_dashboard_home(): void
    {
        $admin = $this->adminWithRole($this->company(), PermissionMatrix::SUPER_ADMIN_ROLE);

        $this->actingAs($admin, 'admin')
            ->get(route('legacy.migration-dashboard.index'))
            ->assertOk()
            ->assertSee('TEMPORARY LEGACY MIGRATION TOOL')
            ->assertSee('Migration Phase Progress')
            ->assertDontSee('DB_PASSWORD')
            ->assertDontSee('LEGACY_DB_PASSWORD');
    }

    public function test_migration_view_permission_grants_access(): void
    {
        $company = $this->company();
        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Migration',
            'last_name' => 'Only',
            'email' => 'migration-only-'.Str::random(5).'@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);

        Permission::findOrCreate('migration.view', 'admin');
        $admin->givePermissionTo('migration.view');

        $this->actingAs($admin, 'admin')
            ->get(route('legacy.migration-dashboard.index'))
            ->assertOk();
    }

    public function test_migration_run_listing_and_detail(): void
    {
        $admin = $this->adminWithRole($this->company(), PermissionMatrix::SUPER_ADMIN_ROLE);
        $runId = $this->seedMigrationRun();

        $this->actingAs($admin, 'admin')
            ->get(route('legacy.migration-dashboard.runs.index'))
            ->assertOk()
            ->assertSee('m2-customers');

        $this->actingAs($admin, 'admin')
            ->get(route('legacy.migration-dashboard.runs.show', $runId))
            ->assertOk()
            ->assertSee('Run metadata');
    }

    public function test_customer_listing_and_detail_with_identity_aliases(): void
    {
        $admin = $this->adminWithRole($this->company(), PermissionMatrix::SUPER_ADMIN_ROLE);
        $runId = $this->seedMigrationRun();

        DB::table('migration_customers')->insert([
            'migration_run_id' => $runId,
            'legacy_user_id' => 14,
            'legacy_customer_id' => 100,
            'migration_status' => 'matched_existing',
            'confidence' => 'HIGH',
            'raw_context' => json_encode(['national_id' => '123456/78/1']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('legacy.migration-dashboard.customers.index'))
            ->assertOk()
            ->assertSee('14');

        $this->actingAs($admin, 'admin')
            ->get(route('legacy.migration-dashboard.customers.show', 14))
            ->assertOk()
            ->assertSee('Legacy User 14');

        $this->actingAs($admin, 'admin')
            ->get(route('legacy.migration-dashboard.identity.index'))
            ->assertOk();
    }

    public function test_loan_and_repayment_pages_with_filters(): void
    {
        $admin = $this->adminWithRole($this->company(), PermissionMatrix::SUPER_ADMIN_ROLE);
        $runId = $this->seedMigrationRun();

        DB::table('migration_loans')->insert([
            'migration_run_id' => $runId,
            'legacy_loan_id' => 9001,
            'legacy_user_id' => 14,
            'migration_status' => 'manual_review',
            'confidence' => 'LOW',
            'exception' => 'BALANCE_VARIANCE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('migration_repayments')->insert([
            'migration_run_id' => $runId,
            'legacy_repayment_id' => 5001,
            'legacy_user_id' => 14,
            'attribution_class' => 'C_AMBIGUOUS',
            'repayment_amount' => 1000,
            'migration_status' => 'manual_review',
            'exception' => 'AMBIGUOUS_MOU_REPAYMENT',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('legacy.migration-dashboard.loans.index', ['migration_status' => 'manual_review']))
            ->assertOk()
            ->assertSee('9001');

        $this->actingAs($admin, 'admin')
            ->get(route('legacy.migration-dashboard.loans.show', 9001))
            ->assertOk()
            ->assertSee('Manual review required');

        $this->actingAs($admin, 'admin')
            ->get(route('legacy.migration-dashboard.repayments.index', ['classification' => 'C_AMBIGUOUS']))
            ->assertOk()
            ->assertSee('5001')
            ->assertSee('D_MANUAL breakdown');
    }

    public function test_exceptions_and_reconciliation_pages_load(): void
    {
        $admin = $this->adminWithRole($this->company(), PermissionMatrix::SUPER_ADMIN_ROLE);

        $this->actingAs($admin, 'admin')
            ->get(route('legacy.migration-dashboard.exceptions.index'))
            ->assertOk();

        $this->actingAs($admin, 'admin')
            ->get(route('legacy.migration-dashboard.reconciliation.index'))
            ->assertOk()
            ->assertSee('Legacy Outstanding');
    }

    public function test_mappings_and_company_pages_load(): void
    {
        $admin = $this->adminWithRole($this->company(), PermissionMatrix::SUPER_ADMIN_ROLE);

        $this->actingAs($admin, 'admin')
            ->get(route('legacy.migration-dashboard.mappings.index'))
            ->assertOk()
            ->assertSee('Treasury bank and wallet provider records');

        $this->actingAs($admin, 'admin')
            ->get(route('legacy.migration-dashboard.companies.index'))
            ->assertOk();

        $this->actingAs($admin, 'admin')
            ->get(route('legacy.migration-dashboard.marketeers.index'))
            ->assertOk()
            ->assertSee('MARK-001');
    }

    public function test_pagination_on_customer_listing(): void
    {
        $admin = $this->adminWithRole($this->company(), PermissionMatrix::SUPER_ADMIN_ROLE);
        $runId = $this->seedMigrationRun();

        for ($i = 1; $i <= 30; $i++) {
            DB::table('migration_customers')->insert([
                'migration_run_id' => $runId,
                'legacy_user_id' => 1000 + $i,
                'migration_status' => 'would_create',
                'confidence' => 'HIGH',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $response = $this->actingAs($admin, 'admin')
            ->get(route('legacy.migration-dashboard.customers.index'));

        $response->assertOk();
        $this->assertStringContainsString('pagination', strtolower($response->getContent()));
    }

    public function test_get_routes_do_not_write_migration_data(): void
    {
        $admin = $this->adminWithRole($this->company(), PermissionMatrix::SUPER_ADMIN_ROLE);
        $runCountBefore = DB::table('migration_runs')->count();

        $this->actingAs($admin, 'admin')->get(route('legacy.migration-dashboard.index'));
        $this->actingAs($admin, 'admin')->get(route('legacy.migration-dashboard.runs.index'));
        $this->actingAs($admin, 'admin')->get(route('legacy.migration-dashboard.customers.index'));

        $this->assertSame($runCountBefore, DB::table('migration_runs')->count());
    }
}
