<?php

namespace Tests\Feature;

use App\Migration\ParallelRun\LegacyLoanPollService;
use App\Migration\ParallelRun\MigrationLoanInboxRepository;
use App\Migration\ParallelRun\MigrationRepaymentInboxRepository;
use App\Migration\Phases\MigrationEntityMapRepository;
use App\Models\Admin;
use App\Models\Company;
use App\Support\PermissionMatrix;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ParallelRunMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        config([
            'migration-dashboard.enabled' => true,
            'legacy-parallel-run.enabled' => true,
        ]);
    }

    private function superAdmin(): Admin
    {
        $suffix = Str::lower(Str::random(5));
        $company = Company::create([
            'name' => 'Parallel Co '.$suffix,
            'slug' => 'parallel-'.$suffix,
            'code' => 'PC'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Parallel',
            'last_name' => 'Admin',
            'email' => 'parallel-'.Str::random(5).'@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);

        Role::findOrCreate(PermissionMatrix::SUPER_ADMIN_ROLE, 'admin');
        $admin->assignRole(PermissionMatrix::SUPER_ADMIN_ROLE);

        return $admin;
    }

    public function test_loan_inbox_repository_upsert_and_count(): void
    {
        $repo = app(MigrationLoanInboxRepository::class);

        $repo->upsertDetected(9001, 500, [
            'id' => 9001,
            'user_id' => 500,
            'loan_amount' => 1000,
            'status_code' => '301',
        ]);

        $this->assertSame(1, $repo->countPending());

        $repo->markImported(9001, 1, 42, 'test');
        $this->assertSame(0, $repo->countPending());
    }

    public function test_repayment_inbox_repository_tracks_pending(): void
    {
        $repo = app(MigrationRepaymentInboxRepository::class);

        $repo->upsertDetected(7001, 500, [
            'id' => 7001,
            'user_id' => 500,
            'repayment_amount' => 250,
            'status_code' => 215,
        ]);

        $this->assertSame(1, $repo->countPending());
    }

    public function test_pending_loans_dashboard_page_loads(): void
    {
        $repo = app(MigrationLoanInboxRepository::class);
        $repo->upsertDetected(9002, 501, [
            'id' => 9002,
            'user_id' => 501,
            'loan_amount' => 500,
        ]);

        $this->actingAs($this->superAdmin(), 'admin')
            ->get(route('legacy.migration-dashboard.loans.pending'))
            ->assertOk()
            ->assertSee('9002');
    }

    public function test_pending_loan_review_page_shows_import_comparison(): void
    {
        $repo = app(MigrationLoanInboxRepository::class);
        $repo->upsertDetected(9010, 510, [
            'id' => 9010,
            'user_id' => 510,
            'status_code' => '301',
            'obtained_amount' => 5000,
            'loan_amount' => 6390,
            'repaid_amount' => 0,
            'payment_period' => 1,
            'created_at' => '2026-10-01 09:15:00',
            'due_date' => '2026-11-01',
            'salary_based' => 0,
            'gvnt_loan' => 0,
        ]);

        $this->actingAs($this->superAdmin(), 'admin')
            ->get(route('legacy.migration-dashboard.loans.pending.show', 9010))
            ->assertOk()
            ->assertSee('Field comparison')
            ->assertSee('Legacy (source)')
            ->assertSee('Revamp (after import)')
            ->assertSee('LEG-9010');
    }

    public function test_migration_home_shows_treasury_cutover_section(): void
    {
        $this->actingAs($this->superAdmin(), 'admin')
            ->get(route('legacy.migration-dashboard.index'))
            ->assertOk()
            ->assertSee('Treasury cutover balances')
            ->assertSee('Set current balances from opening');
    }

    public function test_treasury_cutover_sync_updates_current_balance(): void
    {
        \App\Models\Wallet::create([
            'name' => 'Dashboard Cutover Wallet',
            'wallet_number' => 'W-CUTOVER-1',
            'opening_balance' => 12000,
            'current_balance' => 0,
            'is_active' => true,
        ]);

        $this->actingAs($this->superAdmin(), 'admin')
            ->post(route('legacy.migration-dashboard.treasury.sync-current-balances'))
            ->assertRedirect(route('legacy.migration-dashboard.index'))
            ->assertSessionHas('status');

        $wallet = \App\Models\Wallet::query()->where('wallet_number', 'W-CUTOVER-1')->first();
        $this->assertEqualsWithDelta(12000.0, (float) $wallet->current_balance, 0.01);
    }

    public function test_migration_home_shows_parallel_run_alert(): void
    {
        app(MigrationLoanInboxRepository::class)->upsertDetected(9003, 502, [
            'id' => 9003,
            'user_id' => 502,
            'loan_amount' => 800,
        ]);

        $this->actingAs($this->superAdmin(), 'admin')
            ->get(route('legacy.migration-dashboard.index'))
            ->assertOk()
            ->assertSee('Parallel-run sync queue');
    }

    public function test_loan_poll_uses_legacy_identifier_column_on_entity_maps(): void
    {
        config([
            'legacy-parallel-run.loan_polling_enabled' => true,
            'legacy-parallel-run.loan_watermark_id' => 999999,
        ]);

        DB::table('migration_entity_maps')->insert([
            'entity_type' => MigrationEntityMapRepository::TYPE_LOAN,
            'legacy_identifier' => '888001',
            'target_type' => \App\Models\Loan::class,
            'target_id' => 1,
            'mapping_method' => 'test',
            'mapping_confidence' => 'HIGH',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('migration:poll-legacy-loans')->assertSuccessful();

        $stats = app(LegacyLoanPollService::class)->poll();
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('detected', $stats);
        $this->assertArrayHasKey('skipped_mapped', $stats);
    }

    public function test_parallel_run_playbook_artisan_commands_run(): void
    {
        config([
            'legacy-parallel-run.loan_polling_enabled' => true,
            'legacy-parallel-run.repayment_polling_enabled' => true,
            'legacy-parallel-run.loan_watermark_id' => 999999,
        ]);

        $this->artisan('migration:poll-legacy-loans')->assertSuccessful();
        $this->artisan('migration:poll-legacy-repayments', ['--no-sync' => true])->assertSuccessful();
        $this->artisan('migration:sync-legacy-repayments')->assertSuccessful();
        $this->artisan('migration:poll-legacy-expenses', ['--no-sync' => true])->assertSuccessful();
        $this->artisan('loans:refresh-schedule-aging')->assertSuccessful();
        $this->artisan('loans:accrue-interest', [
            '--from' => '2026-10-01',
            '--to' => '2026-10-05',
        ])->assertSuccessful();
    }
}
