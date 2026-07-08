<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Channel;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\PaymentGateway;
use App\Models\PaymentGatewayAttempt;
use App\PaymentPlatform\Enums\GatewayAttemptPurpose;
use App\PaymentPlatform\Enums\GatewayAttemptStatus;
use App\PaymentPlatform\Enums\GatewayDirection;
use App\PaymentPlatform\Enums\GatewayPaymentMethod;
use App\PaymentPlatform\Enums\PaymentGatewayStatus;
use App\PaymentPlatform\Enums\PaymentGatewayType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AdminLoanCancellationTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_pending_disbursement_loan_can_be_cancelled(): void
    {
        $admin = $this->makeAdmin(['loans.cancel']);
        $loan = $this->makeLoan('approved', 'pending');

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loans.cancel', $loan), [
                'notes' => 'Customer withdrew before disbursement',
            ])
            ->assertRedirect(route('admin.loans.show', $loan))
            ->assertSessionHas('status');

        $loan->refresh();
        $this->assertSame('cancelled', $loan->status);
        $this->assertSame('Customer withdrew before disbursement', $loan->approval_notes);
        $this->assertSame('pending', $loan->disbursement_status);
        $this->assertSame($admin->id, data_get($loan->metadata, 'cancellation.cancelled_by'));
    }

    public function test_approved_failed_disbursement_loan_can_be_cancelled(): void
    {
        $admin = $this->makeAdmin(['loans.cancel']);
        $loan = $this->makeLoan('approved', 'failed');

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loans.cancel', $loan))
            ->assertRedirect(route('admin.loans.show', $loan))
            ->assertSessionHas('status');

        $this->assertSame('cancelled', $loan->fresh()->status);
    }

    public function test_cancel_is_blocked_when_disbursement_is_processing(): void
    {
        $admin = $this->makeAdmin(['loans.cancel']);
        $loan = $this->makeLoan('approved', 'processing');

        $this->actingAs($admin, 'admin')
            ->from(route('admin.loans.show', $loan))
            ->post(route('admin.loans.cancel', $loan))
            ->assertRedirect(route('admin.loans.show', $loan))
            ->assertSessionHasErrors('loan');

        $this->assertSame('approved', $loan->fresh()->status);
    }

    public function test_cancel_is_blocked_when_loan_is_already_disbursed(): void
    {
        $admin = $this->makeAdmin(['loans.cancel']);
        $loan = $this->makeLoan('active', 'completed');

        $this->actingAs($admin, 'admin')
            ->from(route('admin.loans.show', $loan))
            ->post(route('admin.loans.cancel', $loan))
            ->assertRedirect(route('admin.loans.show', $loan))
            ->assertSessionHasErrors('loan');

        $this->assertSame('active', $loan->fresh()->status);
    }

    public function test_cancel_is_blocked_when_gateway_attempt_is_active(): void
    {
        $admin = $this->makeAdmin(['loans.cancel']);
        $loan = $this->makeLoan('approved', 'pending');
        $gateway = PaymentGateway::create([
            'name' => 'Test Gateway',
            'code' => 'test-gw-'.Str::lower(Str::random(4)),
            'provider_class' => 'App\\PaymentPlatform\\Providers\\CGrate\\CGratePaymentGateway',
            'type' => PaymentGatewayType::Both,
            'status' => PaymentGatewayStatus::Active,
            'supports_collections' => false,
            'supports_disbursements' => true,
            'supports_mobile_money' => true,
            'supports_bank' => false,
        ]);

        PaymentGatewayAttempt::create([
            'payment_gateway_id' => $gateway->id,
            'attemptable_type' => Loan::class,
            'attemptable_id' => $loan->id,
            'direction' => GatewayDirection::Disbursement,
            'purpose' => GatewayAttemptPurpose::LoanDisbursement,
            'status' => GatewayAttemptStatus::Pending,
            'payment_method' => GatewayPaymentMethod::MobileMoney,
            'amount' => $loan->principal_amount,
            'currency' => 'ZMW',
            'internal_reference' => 'FINEDGE-OUT-'.$loan->id.'-1-TESTREF01',
            'initiated_at' => now(),
        ]);

        $this->actingAs($admin, 'admin')
            ->from(route('admin.loans.show', $loan))
            ->post(route('admin.loans.cancel', $loan))
            ->assertRedirect(route('admin.loans.show', $loan))
            ->assertSessionHasErrors('loan');

        $this->assertSame('approved', $loan->fresh()->status);
    }

    public function test_cancel_requires_permission(): void
    {
        $admin = $this->makeAdmin([]);
        $loan = $this->makeLoan('approved', 'pending');

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loans.cancel', $loan))
            ->assertForbidden();
    }

    public function test_loan_show_page_includes_cancel_action_for_approved_undisbursed_loan(): void
    {
        $admin = $this->makeAdmin(['loans.view', 'loans.cancel']);
        $loan = $this->makeLoan('approved', 'pending');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loans.show', $loan))
            ->assertOk()
            ->assertSee('Cancel Loan')
            ->assertSee('cancelLoanForm', false);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function makeAdmin(array $permissions): Admin
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Cancel Co '.$suffix,
            'slug' => 'cancel-co-'.$suffix,
            'code' => 'CC'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Cancel',
            'last_name' => 'Admin',
            'email' => 'cancel-admin-'.$suffix.'@example.com',
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

    private function makeLoan(string $status, string $disbursementStatus): Loan
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Loan Co '.$suffix,
            'slug' => 'loan-co-'.$suffix,
            'code' => 'LC'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);
        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Product '.$suffix,
            'code' => 'P-'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);
        $channel = Channel::create([
            'name' => 'Channel '.$suffix,
            'code' => 'CH-'.$suffix,
            'type' => Channel::TYPE_MOBILE_WALLET,
            'can_disburse' => true,
            'can_repay' => true,
            'is_active' => true,
        ]);
        $customer = Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $product->id,
            'first_name' => 'Borrower',
            'last_name' => 'Test',
            'email' => 'borrower-'.$suffix.'@example.com',
            'phone' => '260955'.random_int(100000, 999999),
            'password' => '1234',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        return Loan::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'channel_id' => $channel->id,
            'loan_number' => 'LN-CANCEL-'.Str::upper($suffix),
            'principal_amount' => 5000,
            'processing_fee' => 0,
            'interest_accrued' => 0,
            'total_amount' => 5000,
            'outstanding_balance' => 5000,
            'tenure_months' => 3,
            'loan_start_date' => now()->toDateString(),
            'loan_end_date' => now()->addMonths(3)->toDateString(),
            'accrual_type' => 'daily',
            'status' => $status,
            'disbursement_status' => $disbursementStatus,
            'disbursement_channel_type' => Channel::TYPE_MOBILE_WALLET,
            'disbursement_phone_number' => '260955'.random_int(100000, 999999),
        ]);
    }
}
