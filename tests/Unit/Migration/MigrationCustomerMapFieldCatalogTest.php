<?php

namespace Tests\Unit\Migration;

use App\Migration\Dashboard\MigrationCustomerMapFieldCatalog;
use App\Models\Company;
use App\Models\Customer;
use App\Models\LoanProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MigrationCustomerMapFieldCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_comparison_rows_highlight_differences_and_suggest_legacy_for_gaps(): void
    {
        $company = Company::create([
            'name' => 'Catalog Co',
            'slug' => 'catalog-co',
            'code' => 'CAT',
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Catalog Product',
            'code' => 'CAT-'.Str::lower(Str::random(4)),
            'category' => 'character',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $product->id,
            'first_name' => 'Martin',
            'last_name' => 'Mwale',
            'email' => 'martin@example.com',
            'phone' => '260977000001',
            'password' => '1234',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $legacy = [
            'first_name' => 'Martin',
            'last_name' => 'Mwale',
            'email' => 'martin@example.com',
            'phone' => '260977123456',
            'employee_number' => 'EMP-10',
        ];

        $rows = MigrationCustomerMapFieldCatalog::comparisonRows($legacy, $customer);
        $phone = collect($rows)->firstWhere('key', 'phone');
        $employee = collect($rows)->firstWhere('key', 'employee_number');

        $this->assertTrue($phone['differs']);
        $this->assertTrue($phone['suggest_legacy']);
        $this->assertTrue($employee['suggest_legacy']);
        $this->assertSame('260977000001', $phone['final']);
    }
}
