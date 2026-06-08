<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Channel;
use App\Models\Communication;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AdminLoanPaymentDetailsChangeTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdminWithPermissions(array $permissions): Admin
    {
        $suffix = Str::lower(Str::random(6));

        $company = Company::create([
            'name' => 'Loan Payment Change Co '.$suffix,
            'slug' => 'loan-payment-change-co-'.$suffix,
            'code' => 'LPC'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Payment',
            'last_name' => 'Admin',
            'email' => 'payment-admin-'.$suffix.'@example.com',
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

    /**
     * @param  array<int, string>  $permissions
     * @return array{admin: Admin, customer: Customer, loan: Loan, channel: Channel, alternateChannel: Channel}
     */
    private function createLoanContext(array $permissions, string $loanStatus = 'pending_approval', string $disbursementStatus = 'pending'): array
    {
        $admin = $this->makeAdminWithPermissions($permissions);

        $product = LoanProduct::create([
            'company_id' => $admin->company_id,
            'name' => 'Payment Change Product',
            'code' => 'PCP-'.Str::upper(Str::random(4)),
            'category' => 'character',
            'is_active' => true,
        ]);

        $channel = Channel::create([
            'name' => 'MTN MoMo',
            'code' => 'MTN-'.Str::upper(Str::random(4)),
            'type' => Channel::TYPE_MOBILE_WALLET,
            'can_disburse' => true,
            'can_repay' => true,
            'is_active' => true,
        ]);

        $alternateChannel = Channel::create([
            'name' => 'Airtel Money',
            'code' => 'ATL-'.Str::upper(Str::random(4)),
            'type' => Channel::TYPE_MOBILE_WALLET,
            'can_disburse' => true,
            'can_repay' => true,
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'company_id' => $admin->company_id,
            'loan_product_id' => $product->id,
            'first_name' => 'Loan',
            'last_name' => 'Customer',
            'email' => 'loan-customer-'.Str::lower(Str::random(6)).'@example.com',
            'phone' => '260955'.random_int(100000, 999999),
            'password' => '1234',
            'tpin' => (string) random_int(10000000, 99999999),
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $loan = Loan::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'channel_id' => $channel->id,
            'loan_number' => 'LN-'.Str::upper(Str::random(10)),
            'principal_amount' => 5000,
            'processing_fee' => 100,
            'interest_accrued' => 200,
            'total_amount' => 5300,
            'outstanding_balance' => 5300,
            'tenure_months' => 3,
            'loan_start_date' => now()->toDateString(),
            'loan_end_date' => now()->addMonths(3)->toDateString(),
            'accrual_type' => 'daily',
            'status' => $loanStatus,
            'disbursement_status' => $disbursementStatus,
            'disbursement_phone_number' => '260955000111',
        ]);

        return compact('admin', 'customer', 'loan', 'channel', 'alternateChannel');
    }

    public function test_admin_with_permission_can_change_payment_details_before_approval_via_dedicated_endpoint(): void
    {
        Mail::fake();

        $context = $this->createLoanContext(['loans.approve', 'loans.update-payment-details']);
        $admin = $context['admin'];
        $loan = $context['loan'];
        $alternateChannel = $context['alternateChannel'];

        $this->actingAs($admin, 'admin')->post(route('admin.loans.payment-details', $loan), [
            'form_action' => 'payment-details',
            'channel_id' => $alternateChannel->id,
            'disbursement_phone_number' => '260977777777',
            'payment_change_reason' => 'Customer requested a different payout wallet.',
        ])->assertRedirect(route('admin.loans.show', $loan));

        $response = $this->actingAs($admin, 'admin')->post(route('admin.approvals.loans.approve', $loan), [
            'redirect_to_loan' => '1',
            'notes' => 'Approved after payout detail verification.',
        ]);

        $response->assertRedirect(route('admin.loans.show', $loan));

        $loan->refresh();

        $this->assertSame('approved', $loan->status);
        $this->assertSame($alternateChannel->id, $loan->channel_id);
        $this->assertSame('260977777777', $loan->disbursement_phone_number);
        $this->assertSame('Approved after payout detail verification.', $loan->approval_notes);

        $trail = collect(data_get($loan->metadata, 'payment_details_change_trail', []));
        $this->assertCount(1, $trail);
        $this->assertSame('approval', data_get($trail->first(), 'stage'));
        $this->assertSame('Customer requested a different payout wallet.', data_get($trail->first(), 'reason'));

        $notifications = Communication::query()->get()->filter(function (Communication $communication) use ($loan): bool {
            return data_get($communication->metadata, 'notification_type') === 'loan_payment_details_changed'
                && (int) data_get($communication->metadata, 'loan_id') === $loan->id;
        });

        $this->assertCount(2, $notifications);
    }

    public function test_approve_endpoint_ignores_payment_detail_fields_without_update_permission(): void
    {
        $context = $this->createLoanContext(['loans.approve']);
        $admin = $context['admin'];
        $loan = $context['loan'];
        $channel = $context['channel'];
        $alternateChannel = $context['alternateChannel'];

        $response = $this->actingAs($admin, 'admin')->post(route('admin.approvals.loans.approve', $loan), [
            'redirect_to_loan' => '1',
            'channel_id' => $alternateChannel->id,
            'disbursement_phone_number' => '260977777777',
            'payment_change_reason' => 'Attempted unauthorized change.',
        ]);

        $response->assertRedirect(route('admin.loans.show', $loan));

        $loan->refresh();

        $this->assertSame('approved', $loan->status);
        $this->assertSame($channel->id, $loan->channel_id);
        $this->assertSame('260955000111', $loan->disbursement_phone_number);
        $this->assertEmpty(data_get($loan->metadata, 'payment_details_change_trail', []));
    }

    public function test_reason_is_required_when_payment_details_change_before_approval(): void
    {
        $context = $this->createLoanContext(['loans.approve', 'loans.update-payment-details']);
        $admin = $context['admin'];
        $loan = $context['loan'];
        $channel = $context['channel'];
        $alternateChannel = $context['alternateChannel'];

        $response = $this->from(route('admin.loans.show', $loan))
            ->actingAs($admin, 'admin')
            ->post(route('admin.loans.payment-details', $loan), [
                'form_action' => 'payment-details',
                'channel_id' => $alternateChannel->id,
                'disbursement_phone_number' => '260977777777',
            ]);

        $response->assertRedirect(route('admin.loans.show', $loan));
        $response->assertSessionHasErrors('payment_change_reason');

        $loan->refresh();

        $this->assertSame('pending_approval', $loan->status);
        $this->assertSame($channel->id, $loan->channel_id);
        $this->assertSame('260955000111', $loan->disbursement_phone_number);
    }

    public function test_pending_approval_loan_show_includes_change_payment_details_in_approve_modal(): void
    {
        $context = $this->createLoanContext(['loans.approve', 'loans.update-payment-details']);
        $admin = $context['admin'];
        $loan = $context['loan'];

        $response = $this->actingAs($admin, 'admin')->get(route('admin.loans.show', $loan));

        $response->assertOk();
        $response->assertSee('openPaymentDetailsFromApprove()', false);
        $response->assertSee('Change Payment Details', false);
        $response->assertDontSee('name="channel_id" required', false);
    }

    public function test_permissioned_admin_sees_standalone_payment_details_action_on_editable_loan(): void
    {
        $context = $this->createLoanContext(['loans.update-payment-details'], 'approved', 'pending');
        $admin = $context['admin'];
        $loan = $context['loan'];

        $response = $this->actingAs($admin, 'admin')->get(route('admin.loans.show', $loan));

        $response->assertOk();
        $response->assertSee('id="changePaymentDetailsButton"', false);
        $response->assertSee(route('admin.loans.payment-details', $loan), false);
    }

    public function test_approved_loan_show_includes_change_payment_details_in_disburse_modal(): void
    {
        config(['app.disbursement_type' => 'manual']);

        $context = $this->createLoanContext(['loans.disburse', 'loans.update-payment-details'], 'approved', 'pending');
        $admin = $context['admin'];
        $loan = $context['loan'];

        $response = $this->actingAs($admin, 'admin')->get(route('admin.loans.show', $loan));

        $response->assertOk();
        $response->assertSee('openPaymentDetailsFromDisburse()', false);
        $response->assertSee('id="disbursementModal"', false);
        $response->assertSee('Change Payment Details', false);

        $content = $response->getContent();
        $disburseModalStart = strpos($content, 'id="disbursementModal"');
        $this->assertNotFalse($disburseModalStart);
        $disburseFormEnd = strpos($content, 'id="confirmDisbursementButton"', $disburseModalStart);
        $this->assertNotFalse($disburseFormEnd);
        $disburseFormEnd = strpos($content, '</form>', $disburseFormEnd);
        $this->assertNotFalse($disburseFormEnd);
        $disburseSection = substr($content, $disburseModalStart, $disburseFormEnd - $disburseModalStart);

        $this->assertStringNotContainsString('name="channel_id"', $disburseSection);
        $this->assertStringNotContainsString('name="payment_change_reason"', $disburseSection);
    }

    public function test_admin_with_permission_can_change_payment_details_from_standalone_action(): void
    {
        Mail::fake();

        $context = $this->createLoanContext(['loans.update-payment-details'], 'approved', 'pending');
        $admin = $context['admin'];
        $loan = $context['loan'];
        $alternateChannel = $context['alternateChannel'];

        $response = $this->actingAs($admin, 'admin')->post(route('admin.loans.payment-details', $loan), [
            'form_action' => 'payment-details',
            'channel_id' => $alternateChannel->id,
            'disbursement_phone_number' => '260966666666',
            'payment_change_reason' => 'Customer requested a different payout wallet before disbursement.',
        ]);

        $response->assertRedirect(route('admin.loans.show', $loan));
        $response->assertSessionHas('status', 'Payment details updated successfully.');

        $loan->refresh();

        $this->assertSame('approved', $loan->status);
        $this->assertSame('pending', $loan->disbursement_status);
        $this->assertSame($alternateChannel->id, $loan->channel_id);
        $this->assertSame('260966666666', $loan->disbursement_phone_number);

        $trail = collect(data_get($loan->metadata, 'payment_details_change_trail', []));
        $this->assertCount(1, $trail);
        $this->assertSame('disbursement', data_get($trail->first(), 'stage'));
        $this->assertSame('Customer requested a different payout wallet before disbursement.', data_get($trail->first(), 'reason'));

        $audit = AuditLog::query()
            ->where('event', 'payment_details_changed')
            ->where('auditable_type', Loan::class)
            ->where('auditable_id', (string) $loan->id)
            ->first();

        $this->assertNotNull($audit);

        $notifications = Communication::query()->get()->filter(function (Communication $communication) use ($loan): bool {
            return data_get($communication->metadata, 'notification_type') === 'loan_payment_details_changed'
                && (int) data_get($communication->metadata, 'loan_id') === $loan->id;
        });

        $this->assertCount(2, $notifications);
    }

    public function test_admin_with_permission_can_change_payment_details_during_disbursement(): void
    {
        Mail::fake();
        config(['app.disbursement_type' => 'manual']);

        $context = $this->createLoanContext(['loans.disburse', 'loans.update-payment-details'], 'approved', 'pending');
        $admin = $context['admin'];
        $loan = $context['loan'];
        $alternateChannel = $context['alternateChannel'];

        $wallet = Wallet::create([
            'name' => 'Operations Wallet',
            'wallet_number' => '260970000001',
            'provider' => 'mtn',
            'opening_balance' => 500000,
            'current_balance' => 500000,
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')->post(route('admin.loans.payment-details', $loan), [
            'form_action' => 'payment-details',
            'channel_id' => $alternateChannel->id,
            'disbursement_phone_number' => '260966666666',
            'payment_change_reason' => 'Customer corrected the target payout account.',
        ])->assertRedirect(route('admin.loans.show', $loan));

        $response = $this->actingAs($admin, 'admin')->post(route('admin.loans.disburse', $loan), [
            'form_action' => 'disburse',
            'source_type' => 'wallet',
            'source_id' => $wallet->id,
            'reference_number' => 'DISB-REF-001',
            'disbursement_date' => now()->toDateString(),
            'description' => 'Manual disbursement after payment detail correction.',
        ]);

        $response->assertRedirect(route('admin.loans.show', $loan));

        $loan->refresh();
        $wallet->refresh();

        $this->assertSame('active', $loan->status);
        $this->assertSame('completed', $loan->disbursement_status);
        $this->assertSame($alternateChannel->id, $loan->channel_id);
        $this->assertSame('260966666666', $loan->disbursement_phone_number);
        $this->assertSame('wallet', $loan->disbursed_via_type);
        $this->assertSame(495000.0, (float) $wallet->current_balance);

        $trail = collect(data_get($loan->metadata, 'payment_details_change_trail', []));
        $this->assertCount(1, $trail);
        $this->assertSame('disbursement', data_get($trail->first(), 'stage'));
        $this->assertSame('Customer corrected the target payout account.', data_get($trail->first(), 'reason'));

        $paymentChangeNotifications = Communication::query()->get()->filter(function (Communication $communication) use ($loan): bool {
            return data_get($communication->metadata, 'notification_type') === 'loan_payment_details_changed'
                && (int) data_get($communication->metadata, 'loan_id') === $loan->id;
        });

        $disbursementNotifications = Communication::query()->get()->filter(function (Communication $communication) use ($loan): bool {
            return data_get($communication->metadata, 'notification_type') === 'loan_disbursed'
                && (int) data_get($communication->metadata, 'loan_id') === $loan->id;
        });

        $this->assertCount(2, $paymentChangeNotifications);
        $this->assertCount(2, $disbursementNotifications);
    }
}
