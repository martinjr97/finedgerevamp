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
use App\Models\Repayment;
use App\Models\Wallet;
use App\PaymentPlatform\Enums\FinancialAccountType;
use App\PaymentPlatform\Enums\GatewayAttemptStatus;
use App\PaymentPlatform\Enums\GatewayDirection;
use App\PaymentPlatform\Enums\GatewayPaymentMethod;
use App\PaymentPlatform\Enums\GatewayRouteKey;
use App\PaymentPlatform\Enums\PaymentGatewayStatus;
use App\PaymentPlatform\Jobs\QueryGatewayAttemptStatusJob;
use App\PaymentPlatform\Services\GatewayIntegrationService;
use App\Support\RepaymentRecoveryMethod;
use Database\Seeders\CGratePaymentGatewaySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\EnablesPaymentGatewayRoutes;
use Tests\TestCase;

class RepaymentShowGatewayCollectionTest extends TestCase
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
            'cgrate.username' => 'test-cgrate-user',
            'cgrate.password' => 'test-cgrate-password',
        ]);
    }

    public function test_gateway_processing_show_page_displays_gateway_panel_and_recheck_action(): void
    {
        $context = $this->makeGatewayRepaymentContext();
        $admin = $this->makeAdmin(['repayments.view', 'repayments.process']);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.repayments.show', $context['repayment']));

        $response->assertOk();
        $response->assertSee('Gateway collection in progress', false);
        $response->assertSee('Recheck Gateway Status', false);
        $response->assertSee($context['attempt']->internal_reference, false);
        $response->assertSee('Manual Reconciliation', false);
        $response->assertDontSee('Processing Confirmation', false);
    }

    public function test_manual_pending_show_page_displays_approval_panel_without_gateway_panel(): void
    {
        $context = $this->makeManualPendingRepaymentContext();
        $admin = $this->makeAdmin(['repayments.view', 'repayments.approve', 'repayments.reject']);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.repayments.show', $context['repayment']));

        $response->assertOk();
        $response->assertSee('Manual Repayment Approval', false);
        $response->assertSee('Approve Manual Repayment', false);
        $response->assertDontSee('Gateway collection in progress', false);
        $response->assertDontSee('Recheck Gateway Status', false);
    }

    public function test_recheck_pending_does_not_finalize_repayment_or_ledger(): void
    {
        $context = $this->makeGatewayRepaymentContext();
        $admin = $this->makeAdmin(['repayments.view', 'repayments.process']);

        $this->fakeCGrateQuerySoap([
            'queryCustomerPayment' => $this->soapQueryBody(responseCode: 206, message: 'Pending', paymentId: null),
        ]);

        $walletBalanceBefore = (float) $context['wallet']->current_balance;
        $loanPaidBefore = (float) $context['loan']->amount_paid;

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.repayments.gateway-recheck', $context['repayment']));

        $context['repayment']->refresh();
        $context['attempt']->refresh();
        $context['loan']->refresh();
        $context['wallet']->refresh();

        $response->assertRedirect(route('admin.repayments.show', $context['repayment']));
        $response->assertSessionHas('status');
        $response->assertSessionHas('gateway_recheck_result');
        Http::assertSentCount(1);
        $this->assertSame('processing', $context['repayment']->status);
        $this->assertSame(GatewayAttemptStatus::Pending, $context['attempt']->status);
        $this->assertSame($loanPaidBefore, (float) $context['loan']->amount_paid);
        $this->assertSame($walletBalanceBefore, (float) $context['wallet']->current_balance);
        $this->assertDatabaseCount('loan_repayments', 0);
    }

    public function test_recheck_confirmed_shows_apply_synchronization_without_auto_finalizing(): void
    {
        $context = $this->makeGatewayRepaymentContext();
        $admin = $this->makeAdmin(['repayments.view', 'repayments.process']);

        $this->fakeCGrateQuerySoap([
            'queryCustomerPayment' => $this->soapQueryBody(responseCode: 207, message: 'Approved', paymentId: 'TXN-RECHECK-001'),
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.repayments.gateway-recheck', $context['repayment']));

        $context['repayment']->refresh();
        $context['attempt']->refresh();
        $context['loan']->refresh();
        $context['wallet']->refresh();

        $response->assertRedirect(route('admin.repayments.show', $context['repayment']));
        $response->assertSessionHas('gateway_recheck_result');
        Http::assertSentCount(1);
        $this->assertSame('processing', $context['repayment']->status);
        $this->assertSame(GatewayAttemptStatus::Pending, $context['attempt']->status);
        $this->assertDatabaseCount('loan_repayments', 0);
        $response->assertSessionHas('gateway_recheck_result', function (array $result): bool {
            $this->assertTrue($result['show_apply_synchronization']);

            return true;
        });
    }

    public function test_apply_gateway_synchronization_completes_repayment_and_credits_wallet_once(): void
    {
        $context = $this->makeGatewayRepaymentContext();
        $admin = $this->makeAdmin(['repayments.view', 'repayments.process']);

        $this->fakeCGrateQuerySoap([
            'queryCustomerPayment' => $this->soapQueryBody(responseCode: 207, message: 'Approved', paymentId: 'TXN-APPLY-001'),
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.repayments.gateway-recheck.apply', $context['repayment']), [
                'note' => 'Verified with provider dashboard.',
            ])
            ->assertRedirect(route('admin.repayments.show', $context['repayment']))
            ->assertSessionHas('status');

        $context['repayment']->refresh();
        $context['wallet']->refresh();
        $context['loan']->refresh();

        $this->assertSame('completed', $context['repayment']->status);
        $this->assertSame(1000.0, (float) $context['wallet']->current_balance);
        $this->assertSame(1000.0, (float) $context['loan']->amount_paid);
        $this->assertDatabaseCount('loan_repayments', 1);
    }

    public function test_apply_gateway_synchronization_is_idempotent(): void
    {
        $context = $this->makeGatewayRepaymentContext();
        $admin = $this->makeAdmin(['repayments.view', 'repayments.process']);

        $this->fakeCGrateQuerySoap([
            'queryCustomerPayment' => $this->soapQueryBody(responseCode: 207, message: 'Approved', paymentId: 'TXN-IDEM-001'),
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.repayments.gateway-recheck.apply', $context['repayment']), ['note' => 'First apply.']);

        $context['repayment']->refresh();
        $loanCount = $context['repayment']->loanRepayments()->count();
        $walletBalance = (float) $context['wallet']->fresh()->current_balance;

        $this->actingAs($admin, 'admin')
            ->post(route('admin.repayments.gateway-recheck.apply', $context['repayment']), ['note' => 'Second apply.'])
            ->assertSessionHas('warning');

        $this->assertSame($loanCount, $context['repayment']->fresh()->loanRepayments()->count());
        $this->assertSame($walletBalance, (float) $context['wallet']->fresh()->current_balance);
    }

    public function test_gateway_confirmed_and_already_completed_recheck_shows_no_apply_action(): void
    {
        $context = $this->makeGatewayRepaymentContext();
        $context['attempt']->update([
            'status' => GatewayAttemptStatus::Confirmed,
            'confirmed_at' => now(),
        ]);
        app(GatewayIntegrationService::class)->finalizeConfirmedAttempt($context['attempt']->fresh());

        $admin = $this->makeAdmin(['repayments.view', 'repayments.process']);

        $this->fakeCGrateQuerySoap([
            'queryCustomerPayment' => $this->soapQueryBody(responseCode: 207, message: 'Approved', paymentId: 'TXN-DONE'),
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.repayments.gateway-recheck', $context['repayment']));

        $response->assertSessionHas('gateway_recheck_result');
        $response->assertSessionHas('gateway_recheck_result', function (array $result): bool {
            $this->assertSame('confirmed_synchronized', $result['outcome']);
            $this->assertFalse($result['show_apply_synchronization']);

            return true;
        });
    }

    public function test_recheck_gateway_failed_allows_mark_failed_without_loan_update(): void
    {
        $context = $this->makeGatewayRepaymentContext();
        $admin = $this->makeAdmin(['repayments.view', 'repayments.process']);

        $this->fakeCGrateQuerySoap([
            'queryCustomerPayment' => $this->soapQueryBody(responseCode: 208, message: 'Rejected', paymentId: null),
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.repayments.gateway-recheck', $context['repayment']))
            ->assertSessionHas('gateway_recheck_result');

        $this->assertSame('processing', $context['repayment']->fresh()->status);
        $this->assertDatabaseCount('loan_repayments', 0);

        $this->fakeCGrateQuerySoap([
            'queryCustomerPayment' => $this->soapQueryBody(responseCode: 208, message: 'Rejected', paymentId: null),
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.repayments.gateway-recheck.mark-failed', $context['repayment']), [
                'note' => 'Customer rejected prompt.',
            ])
            ->assertRedirect(route('admin.repayments.show', $context['repayment']));

        $this->assertSame('failed', $context['repayment']->fresh()->status);
        $this->assertDatabaseCount('loan_repayments', 0);
    }

    public function test_polling_job_still_expires_overdue_attempts(): void
    {
        config(['cgrate.payment_expiry_minutes' => 10]);

        $context = $this->makeGatewayRepaymentContext();
        $context['attempt']->update([
            'initiated_at' => now()->subMinutes(11),
            'next_query_at' => now()->subSecond(),
        ]);

        QueryGatewayAttemptStatusJob::dispatchSync($context['attempt']->id);

        $this->assertSame('failed', $context['repayment']->fresh()->status);
        $this->assertSame(GatewayAttemptStatus::Expired, $context['attempt']->fresh()->status);
    }

    public function test_unauthorized_user_cannot_apply_gateway_sync_or_recheck(): void
    {
        $context = $this->makeGatewayRepaymentContext();
        $viewer = $this->makeAdmin(['repayments.view']);

        $this->actingAs($viewer, 'admin')
            ->post(route('admin.repayments.gateway-recheck.apply', $context['repayment']), ['note' => 'Nope'])
            ->assertForbidden();

        $this->actingAs($viewer, 'admin')
            ->post(route('admin.repayments.gateway-recheck.mark-failed', $context['repayment']), ['note' => 'Nope'])
            ->assertForbidden();
    }

    public function test_view_only_admin_can_recheck_but_not_apply(): void
    {
        $context = $this->makeGatewayRepaymentContext();
        $viewer = $this->makeAdmin(['repayments.view']);

        $this->fakeCGrateQuerySoap([
            'queryCustomerPayment' => $this->soapQueryBody(responseCode: 207, message: 'Approved', paymentId: 'TXN-VIEW'),
        ]);

        $this->actingAs($viewer, 'admin')
            ->post(route('admin.repayments.gateway-recheck', $context['repayment']))
            ->assertRedirect();

        $this->assertSame('processing', $context['repayment']->fresh()->status);
    }

    public function test_poll_interval_and_expiry_config_are_reflected_in_attempt_scheduling(): void
    {
        config([
            'cgrate.poll_interval_seconds' => 30,
            'cgrate.payment_expiry_minutes' => 10,
        ]);

        $context = $this->makeGatewayRepaymentContext();
        $context['attempt']->update([
            'next_query_at' => now()->subSecond(),
        ]);

        $this->fakeCGrateQuerySoap([
            'queryCustomerPayment' => $this->soapQueryBody(responseCode: 206, message: 'Pending', paymentId: null),
        ]);

        QueryGatewayAttemptStatusJob::dispatchSync($context['attempt']->id);

        $context['attempt']->refresh();
        $showState = app(\App\Services\Repayments\RepaymentGatewayShowStateService::class)
            ->forRepayment($context['repayment']->fresh());

        $this->assertNotNull($context['attempt']->next_query_at);
        $this->assertTrue($context['attempt']->next_query_at->greaterThan(now()->addSeconds(25)));
        $this->assertNotNull($showState->expiresAt);
        $this->assertTrue($showState->expiresAt->equalTo(
            $context['attempt']->initiated_at->copy()->addMinutes(10)
        ));
    }

    public function test_terminal_confirmed_recheck_does_not_double_apply_loans_or_wallet(): void
    {
        $context = $this->makeGatewayRepaymentContext();
        $context['attempt']->update([
            'status' => GatewayAttemptStatus::Confirmed,
            'confirmed_at' => now(),
            'provider_transaction_id' => 'TXN-ALREADY',
            'next_query_at' => now()->subSecond(),
        ]);

        app(GatewayIntegrationService::class)->finalizeConfirmedAttempt($context['attempt']->fresh());

        $context['repayment']->refresh();
        $context['wallet']->refresh();
        $loanRepaymentCount = $context['repayment']->loanRepayments()->count();
        $walletBalance = (float) $context['wallet']->current_balance;

        $admin = $this->makeAdmin(['repayments.view', 'repayments.process']);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.repayments.gateway-recheck', $context['repayment']))
            ->assertRedirect(route('admin.repayments.show', $context['repayment']));

        $context['repayment']->refresh();
        $context['wallet']->refresh();

        $this->assertSame('completed', $context['repayment']->status);
        $this->assertSame($loanRepaymentCount, $context['repayment']->loanRepayments()->count());
        $this->assertSame($walletBalance, (float) $context['wallet']->current_balance);
    }

    public function test_apply_gateway_confirmation_action_finalizes_confirmed_reconciliation_case(): void
    {
        $context = $this->makeGatewayRepaymentContext();
        $context['attempt']->update([
            'status' => GatewayAttemptStatus::Confirmed,
            'confirmed_at' => now(),
            'provider_transaction_id' => 'TXN-RECON',
        ]);

        $context['repayment']->update([
            'status' => 'processing',
            'status_message' => 'Payment confirmed by gateway but requires finance reconciliation (no linked account).',
            'metadata' => array_merge($context['repayment']->metadata ?? [], [
                'requires_finance_reconciliation' => true,
            ]),
        ]);

        $admin = $this->makeAdmin(['repayments.view', 'repayments.process']);

        $this->fakeCGrateQuerySoap([
            'queryCustomerPayment' => $this->soapQueryBody(responseCode: 207, message: 'Approved', paymentId: 'TXN-RECON'),
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.repayments.gateway-recheck.apply', $context['repayment']), [
                'note' => 'Finance account linked and verified.',
            ]);

        $context['repayment']->refresh();
        $context['wallet']->refresh();

        $response->assertRedirect(route('admin.repayments.show', $context['repayment']));
        $response->assertSessionHas('status');
        $this->assertSame('completed', $context['repayment']->status);
        $this->assertSame(1000.0, (float) $context['wallet']->current_balance);
    }

    public function test_manual_repayment_approval_requires_approve_permission(): void
    {
        $context = $this->makeManualPendingRepaymentContext();
        $viewer = $this->makeAdmin(['repayments.view']);

        $this->actingAs($viewer, 'admin')
            ->get(route('admin.repayments.show', $context['repayment']))
            ->assertOk()
            ->assertDontSee('Approve Manual Repayment', false);

        $this->actingAs($viewer, 'admin')
            ->post(route('admin.repayments.approve', $context['repayment']), [
                'channel_id' => $context['repayment']->channel_id,
            ])
            ->assertForbidden();
    }

    public function test_manual_pending_show_page_hides_inline_processing_confirmation_form(): void
    {
        $context = $this->makeManualPendingRepaymentContext();
        $admin = $this->makeAdmin(['repayments.view', 'repayments.approve', 'repayments.reject']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.repayments.show', $context['repayment']))
            ->assertOk()
            ->assertDontSee('name="provider_status"', false)
            ->assertDontSee('Processing Confirmation', false);
    }

    public function test_failed_gateway_repayment_show_page_offers_retry_collection_action(): void
    {
        $context = $this->makeGatewayRepaymentContext();
        $context['attempt']->update([
            'status' => GatewayAttemptStatus::Expired,
            'expired_at' => now(),
        ]);
        $context['repayment']->update([
            'status' => 'failed',
            'status_message' => 'Payment window expired.',
        ]);

        $admin = $this->makeAdmin(['repayments.view', 'repayments.process']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.repayments.show', $context['repayment']))
            ->assertOk()
            ->assertSee('Gateway collection failed or expired', false)
            ->assertSee('Retry Collection', false);
    }

    /**
     * @return array{company: Company, channel: Channel, customer: Customer, loan: Loan, repayment: Repayment, attempt: PaymentGatewayAttempt, wallet: Wallet}
     */
    private function makeGatewayRepaymentContext(): array
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Show GW Co '.$suffix,
            'slug' => 'show-gw-'.$suffix,
            'code' => 'SG'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);
        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Show GW Product',
            'code' => 'SGP-'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);
        $channel = Channel::create([
            'name' => 'Mobile '.$suffix,
            'code' => 'MB-'.$suffix,
            'type' => Channel::TYPE_MOBILE_WALLET,
            'can_disburse' => true,
            'can_repay' => true,
            'is_repayment_integrated' => true,
            'is_active' => true,
        ]);
        $customer = Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $product->id,
            'first_name' => 'Gateway',
            'last_name' => 'Borrower',
            'email' => 'gw-borrower-'.$suffix.'@example.com',
            'phone' => '260978232334',
            'password' => '1234',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);
        $loan = Loan::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'channel_id' => $channel->id,
            'loan_number' => 'LN-'.$suffix,
            'principal_amount' => 1000,
            'processing_fee' => 0,
            'interest_accrued' => 0,
            'total_amount' => 1000,
            'outstanding_balance' => 1000,
            'tenure_months' => 3,
            'loan_start_date' => now()->toDateString(),
            'loan_end_date' => now()->addMonths(3)->toDateString(),
            'accrual_type' => 'daily',
            'status' => 'active',
        ]);

        $wallet = Wallet::create([
            'name' => 'cGrate Wallet',
            'wallet_number' => '260955'.random_int(100000, 999999),
            'provider' => 'other',
            'currency' => 'ZMW',
            'opening_balance' => 0,
            'current_balance' => 0,
            'is_active' => true,
        ]);

        $gateway = PaymentGateway::query()->where('code', 'cgrate')->firstOrFail();
        $gateway->update([
            'status' => PaymentGatewayStatus::Active,
            'financial_account_type' => FinancialAccountType::Wallet,
            'financial_account_id' => $wallet->id,
        ]);
        $this->enablePaymentGatewayRoute(GatewayRouteKey::WalletCollection, $gateway->id);

        $repayment = Repayment::create([
            'customer_id' => $customer->id,
            'channel_id' => $channel->id,
            'repayment_number' => Repayment::generateRepaymentNumber(),
            'total_amount' => 1000,
            'recovery_method' => RepaymentRecoveryMethod::NORMAL,
            'phone_number' => $customer->phone,
            'status' => 'processing',
            'status_message' => 'Gateway collection in progress.',
            'metadata' => [
                'repayment_type' => 'full',
                'submission_mode' => 'gateway_collection',
            ],
        ]);

        $attempt = PaymentGatewayAttempt::create([
            'payment_gateway_id' => $gateway->id,
            'direction' => GatewayDirection::Collection,
            'purpose' => \App\PaymentPlatform\Enums\GatewayAttemptPurpose::LoanRepayment,
            'attemptable_type' => Repayment::class,
            'attemptable_id' => $repayment->id,
            'internal_reference' => 'FINEDGE-SHOW-'.$suffix,
            'provider_reference' => 'FINEDGE-SHOW-'.$suffix,
            'payment_method' => GatewayPaymentMethod::MobileMoney,
            'amount' => 1000,
            'currency' => 'ZMW',
            'customer_phone' => $customer->phone,
            'status' => GatewayAttemptStatus::Pending,
            'initiated_at' => now(),
            'next_query_at' => now()->subSecond(),
        ]);

        $repayment->update(['payment_gateway_attempt_id' => $attempt->id]);

        return compact('company', 'channel', 'customer', 'loan', 'repayment', 'attempt', 'wallet');
    }

    /**
     * @return array{repayment: Repayment}
     */
    private function makeManualPendingRepaymentContext(): array
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Manual Co '.$suffix,
            'slug' => 'manual-'.$suffix,
            'code' => 'M'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);
        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Manual Product',
            'code' => 'MP-'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);
        $channel = Channel::create([
            'name' => 'Cash '.$suffix,
            'code' => 'CS-'.$suffix,
            'type' => Channel::TYPE_CASH,
            'can_disburse' => true,
            'can_repay' => true,
            'is_active' => true,
        ]);
        $customer = Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $product->id,
            'first_name' => 'Manual',
            'last_name' => 'Borrower',
            'email' => 'manual-'.$suffix.'@example.com',
            'phone' => '260977111222',
            'password' => '1234',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $repayment = Repayment::create([
            'customer_id' => $customer->id,
            'channel_id' => $channel->id,
            'repayment_number' => Repayment::generateRepaymentNumber(),
            'total_amount' => 500,
            'recovery_method' => RepaymentRecoveryMethod::NORMAL,
            'phone_number' => $customer->phone,
            'status' => 'pending',
            'status_message' => 'Repayment submitted and awaiting approval.',
            'metadata' => [
                'repayment_type' => 'full',
                'submission_mode' => 'manual',
            ],
        ]);

        return compact('repayment');
    }

    /**
     * @param  array<string, string>  $responses
     */
    private function fakeCGrateQuerySoap(array $responses): void
    {
        Http::fake(function ($request) use ($responses) {
            $body = $request->body();
            foreach ($responses as $operation => $response) {
                if (str_contains($body, $operation)) {
                    return Http::response($response, 200);
                }
            }

            return Http::response($this->soapQueryBody(206, 'Pending', null), 200);
        });
    }

    private function soapQueryBody(int $responseCode, string $message, ?string $paymentId): string
    {
        $paymentXml = $paymentId !== null
            ? '<paymentID>'.htmlspecialchars($paymentId, ENT_XML1).'</paymentID>'
            : '';

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">'
            .'<soapenv:Body>'
            .'<queryCustomerPaymentResponse>'
            .'<return>'
            .'<responseCode>'.$responseCode.'</responseCode>'
            .'<responseMessage>'.htmlspecialchars($message, ENT_XML1).'</responseMessage>'
            .$paymentXml
            .'</return>'
            .'</queryCustomerPaymentResponse>'
            .'</soapenv:Body>'
            .'</soapenv:Envelope>';
    }

    /**
     * @param  list<string>  $permissions
     */
    private function makeAdmin(array $permissions): Admin
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Admin Co '.$suffix,
            'slug' => 'admin-'.$suffix,
            'code' => 'A'.$suffix,
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
            'must_change_password' => false,
        ]);

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'admin']);
        }
        $admin->givePermissionTo($permissions);

        return $admin;
    }
}
