<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Company;
use App\Models\Creditor;
use App\Models\FinancialTransaction;
use Database\Seeders\FinancialCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CreditorReportTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(array $permissions): Admin
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Creditor Report Co '.$suffix,
            'slug' => 'creditor-report-co-'.$suffix,
            'code' => 'CR'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Creditor',
            'last_name' => 'Reporter',
            'email' => 'creditor-report-'.$suffix.'@example.com',
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

    public function test_creditors_report_shows_summary_and_period_payments(): void
    {
        $this->seed(FinancialCategorySeeder::class);
        $admin = $this->makeAdmin(['reports.view']);

        $creditor = Creditor::create([
            'name' => 'Supplier Alpha',
            'amount' => 5000,
            'is_active' => true,
        ]);

        FinancialTransaction::create([
            'transaction_number' => 'EXP-CR-1',
            'transaction_date' => now()->startOfMonth()->addDays(2)->toDateString(),
            'type' => 'expense',
            'category' => 'creditor_loan_repayment',
            'description' => 'Partial repayment',
            'amount' => 1500,
            'source_type' => 'wallet',
            'source_id' => 1,
            'creditor_id' => $creditor->id,
            'created_by' => $admin->id,
        ]);

        $from = now()->startOfMonth()->toDateString();
        $to = now()->toDateString();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.reports.creditors', ['date_from' => $from, 'date_to' => $to]))
            ->assertOk()
            ->assertSee('Creditors Report', false)
            ->assertSee('Supplier Alpha', false)
            ->assertSee('ZMW 5,000.00', false)
            ->assertSee('ZMW 1,500.00', false);
    }

    public function test_creditors_report_excel_export_requires_reports_view(): void
    {
        $admin = $this->makeAdmin(['reports.view']);

        Creditor::create([
            'name' => 'Export Creditor',
            'amount' => 100,
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.reports.creditors.export'))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_reports_index_links_to_creditors_report(): void
    {
        $admin = $this->makeAdmin(['reports.view']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertSee('Creditors Report', false)
            ->assertSee(route('admin.reports.creditors'), false);
    }
}
