<?php

namespace Tests\Feature\PaymentGateway;

use App\Models\Admin;
use App\Models\Channel;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\PaymentGateway;
use App\Models\Wallet;
use App\PaymentPlatform\Enums\FinancialAccountType;
use App\PaymentPlatform\Enums\GatewayRouteKey;
use App\PaymentPlatform\Enums\PaymentGatewayStatus;
use App\Services\Loans\AutomaticLoanDisbursementService;
use Database\Seeders\CGratePaymentGatewaySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\EnablesPaymentGatewayRoutes;
use Tests\TestCase;

class AdminLoanApprovalModalAutoDisbursementTest extends TestCase
{
    use EnablesPaymentGatewayRoutes;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CGratePaymentGatewaySeeder::class);
        $this->seedPaymentGatewayRoutes();
        config([
            'cgrate.enabled' => true,
            'cgrate.username' => 'test-user',
            'cgrate.password' => 'test-pass',
        ]);
    }

    public function test_loan_show_with_auto_disbursement_ready_displays_approval_modal_note(): void
    {
        $wallet = $this->activateGatewayWallet(20000, autoProcess: true);
        $context = $this->makePendingLoanContext(Channel::TYPE_MOBILE_WALLET, 'MTN_MONEY');
        $admin = $this->makeAdmin(['loans.show', 'loans.approve']);
        $gateway = PaymentGateway::query()->where('code', 'cgrate')->firstOrFail();

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.loans.show', $context['loan']));

        $response->assertOk();
        $response->assertSee('Automatic gateway disbursement is ready', false);
        $response->assertSee($gateway->name, false);
        $response->assertSee('Wallet &amp; Mobile Money Disbursements', false);
        $response->assertSee($wallet->name, false);
        $response->assertSee('Ready', false);
        $response->assertSee('Funds will only be deducted after the payout is confirmed', false);
        $response->assertViewHas('approvalAutoDisbursementPreview', fn ($preview) => $preview->autoDisbursementReady === true);
    }

    public function test_loan_show_with_auto_disbursement_off_displays_manual_note(): void
    {
        $context = $this->makePendingLoanContext(Channel::TYPE_MOBILE_WALLET, 'MTN_MONEY');
        $admin = $this->makeAdmin(['loans.show', 'loans.approve']);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.loans.show', $context['loan']));

        $response->assertOk();
        $response->assertSee('Manual disbursement will be required after approval', false);
        $response->assertDontSee('Automatic gateway disbursement is ready', false);
        $response->assertViewHas('approvalAutoDisbursementPreview', fn ($preview) => $preview->autoDisbursementApplicable === false);
    }

    public function test_loan_show_with_auto_disbursement_configured_but_missing_wallet_displays_warning(): void
    {
        $gateway = PaymentGateway::query()->where('code', 'cgrate')->firstOrFail();
        $gateway->update(['status' => PaymentGatewayStatus::Active]);
        $this->enablePaymentGatewayRoute(GatewayRouteKey::WalletDisbursement, $gateway->id, autoProcess: true);

        $context = $this->makePendingLoanContext(Channel::TYPE_MOBILE_WALLET, 'MTN_MONEY');
        $admin = $this->makeAdmin(['loans.show', 'loans.approve']);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.loans.show', $context['loan']));

        $response->assertOk();
        $response->assertSee('Automatic disbursement is configured but not ready', false);
        $response->assertSee('manual disbursement will be required', false);
        $response->assertSee('Missing Linked Account', false);
        $response->assertViewHas('approvalAutoDisbursementPreview', fn ($preview) => $preview->autoDisbursementApplicable === true
            && $preview->autoDisbursementReady === false);
    }

    public function test_modal_does_not_expose_credentials(): void
    {
        $this->activateGatewayWallet(20000, autoProcess: true);
        $context = $this->makePendingLoanContext(Channel::TYPE_MOBILE_WALLET, 'MTN_MONEY');
        $admin = $this->makeAdmin(['loans.show', 'loans.approve']);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.loans.show', $context['loan']));

        $response->assertOk();
        $response->assertDontSee('test-pass', false);
        $response->assertDontSee('CGRATE_PASSWORD', false);
        $response->assertDontSee('M554T67u', false);
    }

    public function test_loan_show_with_auto_disbursement_ready_and_low_balance_shows_balance_warning(): void
    {
        $this->activateGatewayWallet(1000, autoProcess: true);
        $context = $this->makePendingLoanContext(Channel::TYPE_MOBILE_WALLET, 'MTN_MONEY');
        $admin = $this->makeAdmin(['loans.show', 'loans.approve']);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.loans.show', $context['loan']));

        $response->assertOk();
        $response->assertSee('Automatic gateway disbursement is ready', false);
        $response->assertSee('System balance', false);
        $response->assertViewHas('approvalAutoDisbursementPreview', fn ($preview) => $preview->autoDisbursementReady === true
            && filled($preview->balanceWarning));
    }

    public function test_dashboard_shows_balance_alert_for_auto_disbursement_routes(): void
    {
        $this->activateGatewayWallet(0, autoProcess: true);
        $admin = $this->makeAdmin(['wallets.view']);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Gateway Disbursement Balance Notice', false);
        $response->assertSee('Automatic gateway disbursement is enabled', false);
    }

    public function test_dashboard_hides_balance_alert_without_wallet_or_bank_permission(): void
    {
        $this->activateGatewayWallet(0, autoProcess: true);
        $admin = $this->makeAdmin(['loans.view']);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertDontSee('Gateway Disbursement Balance Notice', false);
    }

    public function test_preview_service_returns_ready_state_without_initiating_disbursement(): void
    {
        $this->activateGatewayWallet(20000, autoProcess: true);
        $context = $this->makePendingLoanContext(Channel::TYPE_MOBILE_WALLET, 'MTN_MONEY');

        $preview = app(AutomaticLoanDisbursementService::class)->previewForApproval($context['loan']);

        $this->assertTrue($preview->autoDisbursementApplicable);
        $this->assertTrue($preview->autoDisbursementReady);
        $this->assertSame('Ready', $preview->statusLabel);
        $this->assertDatabaseCount('payment_gateway_attempts', 0);
    }

    /**
     * @return array{company: Company, channel: Channel, customer: Customer, loan: Loan}
     */
    private function makePendingLoanContext(string $channelType, ?string $channelCode = null): array
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Modal Disb '.$suffix,
            'slug' => 'modal-disb-'.$suffix,
            'code' => 'MD'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);
        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Product',
            'code' => 'P-'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);
        $channel = Channel::create([
            'name' => 'Channel '.$suffix,
            'code' => $channelCode ?? 'CH-'.$suffix,
            'type' => $channelType,
            'can_disburse' => true,
            'can_repay' => true,
            'is_active' => true,
        ]);
        $customer = Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $product->id,
            'first_name' => 'Test',
            'last_name' => 'Borrower',
            'email' => 'bor-'.$suffix.'@example.com',
            'phone' => '260970000000',
            'password' => '1234',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $loanData = [
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'channel_id' => $channel->id,
            'loan_number' => 'LN-'.$suffix,
            'principal_amount' => 5000,
            'processing_fee' => 0,
            'interest_accrued' => 0,
            'total_amount' => 5000,
            'outstanding_balance' => 5000,
            'tenure_months' => 3,
            'loan_start_date' => now()->toDateString(),
            'loan_end_date' => now()->addMonths(3)->toDateString(),
            'accrual_type' => 'daily',
            'status' => 'pending_approval',
            'disbursement_status' => 'pending',
            'disbursement_channel_type' => $channelType,
        ];

        if ($channelType === Channel::TYPE_MOBILE_WALLET) {
            $loanData['disbursement_phone_number'] = '260970000000';
        }

        $loan = Loan::create($loanData);

        return compact('company', 'channel', 'customer', 'loan');
    }

    private function activateGatewayWallet(float $balance, bool $autoProcess = false): Wallet
    {
        $wallet = Wallet::create([
            'name' => 'Treasury Wallet',
            'wallet_number' => '260955'.random_int(100000, 999999),
            'provider' => 'other',
            'currency' => 'ZMW',
            'opening_balance' => $balance,
            'current_balance' => $balance,
            'is_active' => true,
        ]);

        $gateway = PaymentGateway::query()->where('code', 'cgrate')->firstOrFail();
        $gateway->update([
            'status' => PaymentGatewayStatus::Active,
            'financial_account_type' => FinancialAccountType::Wallet,
            'financial_account_id' => $wallet->id,
        ]);

        $this->enablePaymentGatewayRoute(GatewayRouteKey::WalletDisbursement, $gateway->id, autoProcess: $autoProcess);

        return $wallet;
    }

    /**
     * @param  list<string>  $permissions
     */
    private function makeAdmin(array $permissions): Admin
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Admin Co '.$suffix,
            'slug' => 'admin-co-'.$suffix,
            'code' => 'AC'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin-'.$suffix.'@example.com',
            'password' => 'password',
            'is_active' => true,
            'approval_status' => 'approved',
        ]);

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'admin']);
            $admin->givePermissionTo($permission);
        }

        return $admin;
    }
}
