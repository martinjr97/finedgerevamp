<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Company;
use App\Models\Customer;
use App\Models\LoanProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class GovernmentKycDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private function adminWithKycPermission(): Admin
    {
        $suffix = Str::lower(Str::random(5));
        $company = Company::create([
            'name' => 'KYC Co '.$suffix,
            'slug' => 'kyc-'.$suffix,
            'code' => 'KC'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);
        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'KYC',
            'last_name' => 'Admin',
            'email' => 'kyc-'.$suffix.'@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);
        Permission::firstOrCreate(['name' => 'kyc.create', 'guard_name' => 'admin']);
        $admin->givePermissionTo('kyc.create');

        return $admin;
    }

    private function governmentCustomer(LoanProduct $product): Customer
    {
        return Customer::create([
            'loan_product_id' => $product->id,
            'first_name' => 'Government',
            'last_name' => 'Employee',
            'email' => 'gov-kyc-'.Str::random(5).'@example.com',
            'password' => '1234',
            'national_id_type' => 'nrc',
            'national_id' => '123456/78/1',
            'employee_number' => 'EMP-001',
            'status' => 'pending',
            'kyc_status' => 'unverified',
        ]);
    }

    public function test_government_kyc_requires_bank_statement_and_payslip(): void
    {
        Storage::fake('public');
        config(['approval.customers.create' => false]);

        $admin = $this->adminWithKycPermission();
        $product = LoanProduct::create([
            'company_id' => $admin->company_id,
            'name' => 'Government Loan',
            'code' => 'GOV-KYC-'.Str::upper(Str::random(3)),
            'category' => 'government',
            'is_active' => true,
        ]);
        $customer = $this->governmentCustomer($product);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.customers.kyc.store', $customer), [
                'document_type' => 'nrc',
                'front_image' => UploadedFile::fake()->image('front.jpg'),
            ])
            ->assertSessionHasErrors(['bank_statement', 'payslip']);
    }

    public function test_government_kyc_accepts_bank_statement_and_payslip(): void
    {
        Storage::fake('public');
        config(['approval.customers.create' => false]);

        $admin = $this->adminWithKycPermission();
        $product = LoanProduct::create([
            'company_id' => $admin->company_id,
            'name' => 'Government Loan',
            'code' => 'GOV-KYC-'.Str::upper(Str::random(3)),
            'category' => 'government',
            'is_active' => true,
        ]);
        $customer = $this->governmentCustomer($product);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.customers.kyc.store', $customer), [
                'document_type' => 'nrc',
                'front_image' => UploadedFile::fake()->image('front.jpg'),
                'bank_statement' => UploadedFile::fake()->create('statement.pdf', 100, 'application/pdf'),
                'payslip' => UploadedFile::fake()->create('payslip.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(route('admin.customers.show', $customer));

        $this->assertDatabaseHas('kyc_documents', [
            'customer_id' => $customer->id,
            'status' => 'verified',
        ]);

        $customer->refresh();
        $this->assertNotNull($customer->latestKycDocument?->bank_statement_path);
        $this->assertNotNull($customer->latestKycDocument?->payslip_path);
    }

    public function test_non_government_kyc_does_not_require_bank_statement_or_payslip(): void
    {
        Storage::fake('public');
        config(['approval.customers.create' => false]);

        $admin = $this->adminWithKycPermission();
        $product = LoanProduct::create([
            'company_id' => $admin->company_id,
            'name' => 'Character Loan',
            'code' => 'CHAR-KYC-'.Str::upper(Str::random(3)),
            'category' => 'character',
            'is_active' => true,
        ]);
        $customer = Customer::create([
            'loan_product_id' => $product->id,
            'first_name' => 'Character',
            'last_name' => 'Borrower',
            'email' => 'char-kyc-'.Str::random(5).'@example.com',
            'password' => '1234',
            'national_id_type' => 'nrc',
            'national_id' => '987654/32/1',
            'status' => 'pending',
            'kyc_status' => 'unverified',
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.customers.kyc.store', $customer), [
                'document_type' => 'nrc',
                'front_image' => UploadedFile::fake()->image('front.jpg'),
            ])
            ->assertRedirect(route('admin.customers.show', $customer))
            ->assertSessionHasNoErrors();
    }

    public function test_kyc_rejects_documents_larger_than_fifteen_megabytes(): void
    {
        Storage::fake('public');
        config(['approval.customers.create' => false]);

        $admin = $this->adminWithKycPermission();
        $product = LoanProduct::create([
            'company_id' => $admin->company_id,
            'name' => 'Government Loan',
            'code' => 'GOV-SZ-'.Str::upper(Str::random(3)),
            'category' => 'government',
            'is_active' => true,
        ]);
        $customer = $this->governmentCustomer($product);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.customers.kyc.store', $customer), [
                'document_type' => 'nrc',
                'front_image' => UploadedFile::fake()->image('front.jpg'),
                'bank_statement' => UploadedFile::fake()->create('statement.pdf', 15361, 'application/pdf'),
                'payslip' => UploadedFile::fake()->create('payslip.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors(['bank_statement']);
    }
}
