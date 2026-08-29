<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\LoanProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RelationshipManagerCustomerBranchSyncTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(string $suffix): Company
    {
        return Company::create([
            'name' => 'Company '.$suffix,
            'slug' => 'company-'.$suffix,
            'code' => 'CO'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);
    }

    private function makeBranch(string $suffix): Branch
    {
        return Branch::create([
            'name' => 'Branch '.$suffix,
            'code' => 'BR-'.$suffix,
            'is_active' => true,
        ]);
    }

    private function makeAdminWithPermissions(array $permissions): Admin
    {
        $suffix = Str::lower(Str::random(6));
        $company = $this->makeCompany('admin-'.$suffix);

        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Sync',
            'last_name' => 'Admin',
            'email' => 'sync-admin-'.$suffix.'@example.com',
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

    public function test_company_relationship_manager_update_assigns_branch_to_customers_without_one(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = $this->makeCompany($suffix);
        $branch = $this->makeBranch($suffix);
        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Product '.$suffix,
            'code' => 'PR-'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);
        $relationshipManager = Admin::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'first_name' => 'RM',
            'last_name' => $suffix,
            'email' => 'rm-'.$suffix.'@example.com',
            'password' => 'password',
            'is_active' => true,
            'is_relationship_manager' => true,
            'approval_status' => 'approved',
            'must_change_password' => false,
        ]);
        $admin = $this->makeAdminWithPermissions(['companies.update']);

        $customerWithoutBranch = Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $product->id,
            'first_name' => 'No',
            'last_name' => 'Branch',
            'email' => 'no-branch-'.$suffix.'@example.com',
            'phone' => '260966'.random_int(100000, 999999),
            'password' => '1234',
            'status' => 'active',
            'approval_status' => 'approved',
            'must_change_pin' => false,
        ]);

        $existingBranch = $this->makeBranch($suffix.'x');
        $customerWithBranch = Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $product->id,
            'branch_id' => $existingBranch->id,
            'first_name' => 'Has',
            'last_name' => 'Branch',
            'email' => 'has-branch-'.$suffix.'@example.com',
            'phone' => '260967'.random_int(100000, 999999),
            'password' => '1234',
            'status' => 'active',
            'approval_status' => 'approved',
            'must_change_pin' => false,
        ]);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.companies.update-relationship-manager', $company), [
                'relationship_manager_id' => $relationshipManager->id,
            ])
            ->assertRedirect(route('admin.companies.show', $company));

        $this->assertSame($branch->id, $customerWithoutBranch->fresh()->branch_id);
        $this->assertSame($existingBranch->id, $customerWithBranch->fresh()->branch_id);
    }

    public function test_customer_group_relationship_manager_update_assigns_branch_to_customers_without_one(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = $this->makeCompany($suffix);
        $branch = $this->makeBranch($suffix);
        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Group Product '.$suffix,
            'code' => 'GP-'.$suffix,
            'category' => 'group_loans',
            'is_active' => true,
        ]);
        $group = CustomerGroup::create([
            'loan_product_id' => $product->id,
            'branch_id' => $branch->id,
            'name' => 'Group '.$suffix,
            'code' => 'GRP-'.$suffix,
            'risk_level' => 'medium',
            'is_active' => true,
        ]);
        $relationshipManager = Admin::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'first_name' => 'GroupRM',
            'last_name' => $suffix,
            'email' => 'group-rm-'.$suffix.'@example.com',
            'password' => 'password',
            'is_active' => true,
            'is_relationship_manager' => true,
            'approval_status' => 'approved',
            'must_change_password' => false,
        ]);
        $admin = $this->makeAdminWithPermissions(['loan-products.view']);

        $customerWithoutBranch = Customer::create([
            'company_id' => $company->id,
            'customer_group_id' => $group->id,
            'loan_product_id' => $product->id,
            'first_name' => 'Group',
            'last_name' => 'NoBranch',
            'email' => 'group-no-branch-'.$suffix.'@example.com',
            'phone' => '260968'.random_int(100000, 999999),
            'password' => '1234',
            'status' => 'active',
            'approval_status' => 'approved',
            'must_change_pin' => false,
        ]);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.customer-groups.update-relationship-manager', $group), [
                'relationship_manager_id' => $relationshipManager->id,
            ])
            ->assertRedirect(route('admin.customer-groups.show', $group));

        $this->assertSame($branch->id, $customerWithoutBranch->fresh()->branch_id);
    }
}
