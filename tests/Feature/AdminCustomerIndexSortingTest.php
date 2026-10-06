<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AdminCustomerIndexSortingTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): Admin
    {
        $suffix = Str::lower(Str::random(6));

        $company = Company::create([
            'name' => 'Sort Co '.$suffix,
            'slug' => 'sort-co-'.$suffix,
            'code' => 'SC'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Sort',
            'last_name' => 'Admin',
            'email' => 'sort-'.$suffix.'@example.com',
            'password' => 'password',
            'is_active' => true,
            'approval_status' => 'approved',
            'must_change_password' => false,
        ]);

        Permission::firstOrCreate(['name' => 'customers.view', 'guard_name' => 'admin']);
        $admin->givePermissionTo('customers.view');

        return $admin;
    }

    private function makeProduct(Admin $admin, string $name): LoanProduct
    {
        return LoanProduct::create([
            'company_id' => $admin->company_id,
            'name' => $name,
            'code' => Str::upper(Str::substr($name, 0, 3)).'-'.Str::lower(Str::random(4)),
            'category' => 'character',
            'is_active' => true,
        ]);
    }

    private function makeCustomer(LoanProduct $product, string $firstName, string $lastName, string $status = 'active'): Customer
    {
        $suffix = Str::lower(Str::random(6));

        return Customer::create([
            'company_id' => $product->company_id,
            'loan_product_id' => $product->id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $suffix.'@example.com',
            'phone' => '260955'.random_int(100000, 999999),
            'password' => '1234',
            'status' => $status,
            'approval_status' => 'approved',
            'must_change_pin' => false,
        ]);
    }

    public function test_customers_index_can_sort_by_name_ascending(): void
    {
        $admin = $this->makeAdmin();
        $product = $this->makeProduct($admin, 'Alpha Product');

        $zebra = $this->makeCustomer($product, 'Zara', 'Zulu');
        $alpha = $this->makeCustomer($product, 'Amy', 'Alpha');

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.customers.index', ['sort' => 'name', 'direction' => 'asc']));

        $response->assertOk();

        $names = collect($response->viewData('customers')->items())
            ->map(fn (Customer $customer) => $customer->full_name)
            ->values()
            ->all();

        $this->assertSame(['Amy Alpha', 'Zara Zulu'], $names);
        $this->assertTrue($response->viewData('customers')->contains($alpha));
        $this->assertTrue($response->viewData('customers')->contains($zebra));
    }

    public function test_customers_index_can_sort_by_product_name(): void
    {
        $admin = $this->makeAdmin();
        $zebraProduct = $this->makeProduct($admin, 'Zebra Loans');
        $alphaProduct = $this->makeProduct($admin, 'Alpha Loans');

        $this->makeCustomer($zebraProduct, 'One', 'Customer');
        $this->makeCustomer($alphaProduct, 'Two', 'Customer');

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.customers.index', ['sort' => 'product', 'direction' => 'asc']));

        $response->assertOk();

        $productNames = collect($response->viewData('customers')->items())
            ->map(fn (Customer $customer) => $customer->loanProduct->name)
            ->values()
            ->all();

        $this->assertSame(['Alpha Loans', 'Zebra Loans'], $productNames);
    }

    public function test_customers_index_can_sort_by_status(): void
    {
        $admin = $this->makeAdmin();
        $product = $this->makeProduct($admin, 'Status Product');

        $this->makeCustomer($product, 'Active', 'User', 'active');
        $this->makeCustomer($product, 'Pending', 'User', 'pending');

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.customers.index', ['sort' => 'status', 'direction' => 'asc']));

        $response->assertOk();

        $statuses = collect($response->viewData('customers')->items())
            ->map(fn (Customer $customer) => $customer->status)
            ->values()
            ->all();

        // MySQL enum order follows column definition: pending, active, suspended, closed.
        $this->assertSame(['pending', 'active'], $statuses);
    }

    public function test_customers_index_can_sort_by_outstanding_balance(): void
    {
        $admin = $this->makeAdmin();
        $product = $this->makeProduct($admin, 'Balance Product');

        $lowBalanceCustomer = $this->makeCustomer($product, 'Low', 'Balance');
        $highBalanceCustomer = $this->makeCustomer($product, 'High', 'Balance');

        $this->makeLoan($lowBalanceCustomer, $product, [
            'loan_number' => 'LN-LOW-'.Str::upper(Str::random(4)),
            'principal_amount' => 1000,
            'total_amount' => 1000,
            'outstanding_balance' => 100,
            'status' => 'active',
            'disbursement_status' => 'completed',
            'disbursed_at' => now(),
        ]);

        $this->makeLoan($highBalanceCustomer, $product, [
            'loan_number' => 'LN-HIGH-'.Str::upper(Str::random(4)),
            'principal_amount' => 5000,
            'total_amount' => 5000,
            'outstanding_balance' => 5000,
            'status' => 'active',
            'disbursement_status' => 'completed',
            'disbursed_at' => now(),
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.customers.index', ['sort' => 'balance', 'direction' => 'desc']));

        $response->assertOk();

        $customerIds = collect($response->viewData('customers')->items())
            ->map(fn (Customer $customer) => $customer->id)
            ->values()
            ->all();

        $this->assertSame([$highBalanceCustomer->id, $lowBalanceCustomer->id], $customerIds);
    }

    private function makeLoan(Customer $customer, LoanProduct $product, array $overrides = []): Loan
    {
        return Loan::create(array_merge([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'loan_number' => 'LN-'.Str::upper(Str::random(10)),
            'principal_amount' => 5000,
            'processing_fee' => 250,
            'interest_accrued' => 300,
            'total_amount' => 5550,
            'amount_paid' => 0,
            'outstanding_balance' => 5550,
            'tenure_months' => 6,
            'loan_start_date' => now()->toDateString(),
            'loan_end_date' => now()->addMonths(6)->toDateString(),
            'accrual_type' => 'daily',
            'status' => 'approved',
            'disbursement_status' => 'pending',
        ], $overrides));
    }
}
