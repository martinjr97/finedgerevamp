<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Channel;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\FinancialInstitution;
use App\Models\FinancialInstitutionBranch;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\LoanRate;
use App\Models\LoanRateType;
use App\Models\Wallet;
use App\Services\DisbursementDestinationService;
use Database\Seeders\FinancialInstitutionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DisbursementDestinationPhaseFTest extends TestCase
{
    use RefreshDatabase;

    private function adminWithPermissions(array $permissions): Admin
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Phase F Co '.$suffix,
            'slug' => 'phase-f-co-'.$suffix,
            'code' => 'PFC'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Phase',
            'last_name' => 'F',
            'email' => 'phase-f-'.$suffix.'@example.com',
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

    private function channel(string $type, string $suffix): Channel
    {
        return Channel::create([
            'name' => ucfirst(str_replace('_', ' ', $type)).' '.$suffix,
            'code' => strtoupper($type).'_'.$suffix,
            'type' => $type,
            'can_disburse' => true,
            'can_repay' => true,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeLoan(Company $company, Channel $channel, array $overrides = []): Loan
    {
        $suffix = Str::lower(Str::random(6));

        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Phase F Product',
            'code' => 'PF-'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);

        $rateType = LoanRateType::create([
            'loan_product_id' => $product->id,
            'name' => 'Rate',
            'code' => 'RT-'.$suffix,
            'accrual_period' => 'daily',
            'is_active' => true,
        ]);

        LoanRate::create([
            'loan_rate_type_id' => $rateType->id,
            'tenure_months' => 3,
            'processing_fee_percentage' => 5,
            'daily_rate' => 0.03,
            'arrear_rate' => 0.03,
            'is_active' => true,
        ]);

        $group = CustomerGroup::create([
            'loan_product_id' => $product->id,
            'loan_rate_type_id' => $rateType->id,
            'name' => 'Group '.$suffix,
            'code' => 'G-'.$suffix,
            'risk_level' => 'medium',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $product->id,
            'customer_group_id' => $group->id,
            'first_name' => 'Borrower',
            'last_name' => 'F',
            'email' => 'borrower-f-'.$suffix.'@example.com',
            'phone' => '2609'.random_int(10000000, 99999999),
            'password' => '1234',
            'tpin' => (string) random_int(10000000, 99999999),
            'maximum_loan_take' => 50000,
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        return Loan::create(array_merge([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'customer_group_id' => $group->id,
            'channel_id' => $channel->id,
            'loan_number' => 'LN-'.Str::upper(Str::random(8)),
            'principal_amount' => 5000,
            'processing_fee' => 0,
            'interest_accrued' => 0,
            'total_amount' => 5000,
            'outstanding_balance' => 5000,
            'tenure_months' => 3,
            'loan_start_date' => now()->toDateString(),
            'loan_end_date' => now()->addMonths(3)->toDateString(),
            'accrual_type' => 'daily',
            'status' => 'active',
            'disbursement_status' => 'completed',
            'disbursed_at' => now(),
        ], $overrides));
    }

    /**
     * @return array<string, string>
     */
    private function firstExportRowMap(object $export): array
    {
        $headings = $export->headings();
        $values = $export->collection()->first();

        return array_combine($headings, $values);
    }

    public function test_loan_export_includes_channel_type_and_destination_summary(): void
    {
        Excel::fake();
        Excel::matchByRegex();

        $admin = $this->adminWithPermissions(['loans.export']);
        $channel = $this->channel(Channel::TYPE_MOBILE_WALLET, 'EXP');
        $loan = $this->makeLoan($admin->company, $channel, [
            'disbursement_channel_type' => Channel::TYPE_MOBILE_WALLET,
            'disbursement_phone_number' => '260978232334',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loans.export'))
            ->assertOk();

        Excel::assertDownloaded('/^loans-export-.*\.xlsx$/', function ($export) use ($loan) {
            $row = $this->firstExportRowMap($export);

            return ($row['Channel Type'] ?? null) === 'Mobile Money'
                && str_contains((string) ($row['Disbursement Destination'] ?? ''), '260978232334')
                && ($row['Loan Number'] ?? null) === $loan->loan_number;
        });
    }

    public function test_disbursement_report_export_includes_destination_summary(): void
    {
        Excel::fake();
        Excel::matchByRegex();

        $admin = $this->adminWithPermissions(['reports.view']);
        $channel = $this->channel(Channel::TYPE_CASH, 'DISB');
        $loan = $this->makeLoan($admin->company, $channel, [
            'disbursement_channel_type' => Channel::TYPE_CASH,
            'disbursement_notes' => 'Collect at branch counter',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.reports.disbursements.export'))
            ->assertOk();

        Excel::assertDownloaded('/^disbursements_report_.*\.xlsx$/', function ($export) use ($loan) {
            $row = $this->firstExportRowMap($export);

            return ($row['Channel Type'] ?? null) === 'Cash'
                && str_contains((string) ($row['Disbursement Destination'] ?? ''), 'Collect at branch counter')
                && ($row['Loan Number'] ?? null) === $loan->loan_number;
        });

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.reports.disbursements'));

        $response->assertOk();
        $response->assertSee('Collect at branch counter', false);
        $response->assertSee('Cash', false);
    }

    public function test_bank_destination_export_uses_masked_account_number(): void
    {
        Excel::fake();
        Excel::matchByRegex();

        $this->seed(FinancialInstitutionSeeder::class);
        $institution = FinancialInstitution::where('code', 'ZANACO')->firstOrFail();
        $branch = $institution->branches()->where('name', 'Main Branch')->firstOrFail();

        $admin = $this->adminWithPermissions(['loans.export']);
        $channel = $this->channel(Channel::TYPE_BANK, 'BANK');
        $loan = $this->makeLoan($admin->company, $channel, [
            'disbursement_channel_type' => Channel::TYPE_BANK,
            'disbursement_financial_institution_id' => $institution->id,
            'disbursement_financial_institution_branch_id' => $branch->id,
            'disbursement_account_holder_name' => 'Jane Banda',
            'disbursement_account_number' => '1234567890',
            'disbursement_destination_snapshot' => [
                'channel_type' => Channel::TYPE_BANK,
                'financial_institution_name' => $institution->name,
                'branch_name' => $branch->name,
                'account_holder_name' => 'Jane Banda',
                'masked_account_number' => DisbursementDestinationService::maskAccountNumber('1234567890'),
            ],
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loans.export'))
            ->assertOk();

        Excel::assertDownloaded('/^loans-export-.*\.xlsx$/', function ($export) {
            $row = $this->firstExportRowMap($export);
            $masked = (string) ($row['Masked Account Number'] ?? '');

            return str_contains($masked, '7890')
                && ! str_contains($masked, '1234567890')
                && ($row['Channel Type'] ?? null) === 'Bank Transfer';
        });
    }

    public function test_cash_destination_export_does_not_require_mobile_wallet_number(): void
    {
        Excel::fake();
        Excel::matchByRegex();

        $admin = $this->adminWithPermissions(['loans.export']);
        $channel = $this->channel(Channel::TYPE_CASH, 'CASH');
        $this->makeLoan($admin->company, $channel, [
            'disbursement_channel_type' => Channel::TYPE_CASH,
            'disbursement_notes' => 'Counter pickup',
            'disbursement_phone_number' => null,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loans.export'))
            ->assertOk();

        Excel::assertDownloaded('/^loans-export-.*\.xlsx$/', function ($export) {
            $row = $this->firstExportRowMap($export);

            return ($row['Channel Type'] ?? null) === 'Cash'
                && ($row['Mobile Wallet Number'] ?? '') === ''
                && str_contains((string) ($row['Cash Notes'] ?? ''), 'Counter pickup');
        });
    }

    public function test_legacy_phone_only_loan_exports_safely(): void
    {
        Excel::fake();
        Excel::matchByRegex();

        $admin = $this->adminWithPermissions(['loans.export']);
        $channel = $this->channel(Channel::TYPE_MOBILE_WALLET, 'LEG');
        $loan = $this->makeLoan($admin->company, $channel, [
            'disbursement_channel_type' => null,
            'disbursement_phone_number' => '260955000111',
        ]);

        $this->assertStringContainsString('260955000111', $loan->disbursementDestinationSummary());

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loans.export'))
            ->assertOk();

        Excel::assertDownloaded('/^loans-export-.*\.xlsx$/', function ($export) {
            $row = $this->firstExportRowMap($export);

            return ($row['Mobile Wallet Number'] ?? null) === '260955000111'
                && ($row['Channel Type'] ?? null) === 'Mobile Money';
        });
    }

    public function test_customer_repayment_select_channel_does_not_mislead_cash_phone_copy(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Repay Co '.$suffix,
            'slug' => 'repay-co-'.$suffix,
            'code' => 'RC'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Repay Product',
            'code' => 'RP-'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $product->id,
            'first_name' => 'Repay',
            'last_name' => 'Customer',
            'email' => 'repay-'.$suffix.'@example.com',
            'phone' => '2609'.random_int(10000000, 99999999),
            'password' => '1234',
            'tpin' => (string) random_int(10000000, 99999999),
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $walletChannel = $this->channel(Channel::TYPE_MOBILE_WALLET, 'RW');
        $cashChannel = $this->channel(Channel::TYPE_CASH, 'RC');

        $this->makeLoan($company, $walletChannel, [
            'customer_id' => $customer->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($customer, 'customer')
            ->withSession([
                'repayment.type' => 'full',
                'repayment.loan_id' => null,
                'repayment.amount' => null,
            ])
            ->get(route('customer.repayments.select-channel'));

        $response->assertOk();
        $response->assertSee('Mobile money number (optional)', false);
        $response->assertSee('data-channel-type="cash"', false);
        $response->assertSee('repaymentPhoneSection', false);
        $response->assertDontSee('Mobile money number (required)', false);
    }

    public function test_api_loan_show_includes_channel_type_and_destination_without_raw_account(): void
    {
        $this->seed(FinancialInstitutionSeeder::class);
        $institution = FinancialInstitution::where('code', 'ZANACO')->firstOrFail();
        $branch = $institution->branches()->where('name', 'Main Branch')->firstOrFail();

        $admin = $this->adminWithPermissions(['loans.view']);
        $channel = $this->channel(Channel::TYPE_BANK, 'API');
        $loan = $this->makeLoan($admin->company, $channel, [
            'disbursement_channel_type' => Channel::TYPE_BANK,
            'disbursement_financial_institution_id' => $institution->id,
            'disbursement_financial_institution_branch_id' => $branch->id,
            'disbursement_account_holder_name' => 'Jane Banda',
            'disbursement_account_number' => '1234567890',
        ]);

        $token = $admin->createToken('phase-f-api')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson(route('api.v1.admin.loans.show', $loan));

        $response->assertOk();
        $response->assertJsonPath('data.channel.type', Channel::TYPE_BANK);
        $response->assertJsonPath('data.channel.type_label', 'Bank Transfer');
        $response->assertJsonPath('data.disbursement_destination.channel_type', Channel::TYPE_BANK);
        $response->assertJsonPath('data.disbursement_destination.channel_type_label', 'Bank Transfer');
        $this->assertStringContainsString(
            '7890',
            (string) $response->json('data.disbursement_destination.destination_summary')
        );
        $this->assertStringNotContainsString(
            '1234567890',
            $response->getContent()
        );
    }

    public function test_admin_loan_show_keeps_treasury_source_separate_from_customer_destination(): void
    {
        config(['app.disbursement_type' => 'manual']);

        $admin = $this->adminWithPermissions(['loans.view', 'loans.disburse']);
        $channel = $this->channel(Channel::TYPE_MOBILE_WALLET, 'SHOW');
        $loan = $this->makeLoan($admin->company, $channel, [
            'status' => 'approved',
            'disbursement_status' => 'pending',
            'disbursement_channel_type' => Channel::TYPE_MOBILE_WALLET,
            'disbursement_phone_number' => '260978232334',
        ]);

        $treasuryWallet = Wallet::create([
            'name' => 'Treasury Wallet',
            'wallet_number' => '260955111222',
            'current_balance' => 50000,
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')->post(route('admin.loans.disburse', $loan), [
            'source_type' => 'wallet',
            'source_id' => $treasuryWallet->id,
            'reference_number' => 'DISB-F-001',
            'disbursement_date' => now()->toDateString(),
            'description' => 'Manual disbursement',
        ]);

        $loan->refresh();

        $response = $this->actingAs($admin, 'admin')->get(route('admin.loans.show', $loan));

        $response->assertOk();
        $response->assertSee('Destination Summary', false);
        $response->assertSee('260978232334', false);
        $response->assertSee('Disbursed From (treasury)', false);
        $response->assertSee('Wallet', false);
    }
}
