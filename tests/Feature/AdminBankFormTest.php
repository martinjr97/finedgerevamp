<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Bank;
use App\Models\Company;
use App\Models\FinancialInstitution;
use App\Models\FinancialInstitutionBranch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AdminBankFormTest extends TestCase
{
    use RefreshDatabase;

    private function adminWithPermissions(array $permissions): Admin
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Bank Admin Co '.$suffix,
            'slug' => 'bank-admin-co-'.$suffix,
            'code' => 'BK'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Treasury',
            'last_name' => 'Admin',
            'email' => 'bank-admin-'.$suffix.'@example.com',
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

    public function test_create_page_shows_institution_and_branch_dropdowns_with_guidance(): void
    {
        $admin = $this->adminWithPermissions(['banks.create']);

        $institution = FinancialInstitution::create([
            'name' => 'Zanaco',
            'code' => 'ZAN',
            'is_active' => true,
        ]);

        FinancialInstitutionBranch::create([
            'financial_institution_id' => $institution->id,
            'name' => 'Cairo Road',
            'code' => 'CR',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.banks.create'));

        $response->assertOk();
        $response->assertSee('Account label', false);
        $response->assertSee('placeholder="e.g. Main Operations — Zanaco"', false);
        $response->assertSee('placeholder="e.g. Havencrest Finance Limited"', false);
        $response->assertSee('id="bank_financial_institution"', false);
        $response->assertSee('id="bank_branch"', false);
        $response->assertSee('data-bank-institution-select', false);
        $response->assertSee('Zanaco (ZAN)', false);
        $response->assertSee('Cairo Road', false);
    }

    public function test_admin_can_create_bank_using_institution_and_branch_dropdowns(): void
    {
        $admin = $this->adminWithPermissions(['banks.create']);

        $institution = FinancialInstitution::create([
            'name' => 'Stanbic Bank',
            'code' => 'STAN',
            'is_active' => true,
        ]);

        FinancialInstitutionBranch::create([
            'financial_institution_id' => $institution->id,
            'name' => 'Acacia Park',
            'code' => 'AP',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin, 'admin')->post(route('admin.banks.store'), [
            'name' => 'Disbursements — Stanbic',
            'account_number' => '9988776655',
            'account_name' => 'Havencrest Finance Limited',
            'bank_name' => 'Stanbic Bank',
            'branch' => 'Acacia Park',
            'currency' => 'ZMW',
            'opening_balance' => 10000,
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.banks.index'));
        $response->assertSessionHas('status', 'Bank created successfully.');

        $bank = Bank::query()->where('account_number', '9988776655')->first();

        $this->assertNotNull($bank);
        $this->assertSame('Disbursements — Stanbic', $bank->name);
        $this->assertSame('Havencrest Finance Limited', $bank->account_name);
        $this->assertSame('Stanbic Bank', $bank->bank_name);
        $this->assertSame('Acacia Park', $bank->branch);
    }
}
