<?php

namespace Tests\Feature\PaymentGateway;

use App\Models\Company;
use App\Models\Customer;
use App\Models\LoanProduct;
use App\Models\PaymentGateway;
use App\Models\PaymentGatewayAttempt;
use App\Models\Repayment;
use Illuminate\Support\Str;
use App\PaymentPlatform\Enums\GatewayAttemptPurpose;
use App\PaymentPlatform\Enums\GatewayAttemptStatus;
use App\PaymentPlatform\Enums\GatewayDirection;
use App\PaymentPlatform\Enums\GatewayPaymentMethod;
use App\PaymentPlatform\Enums\PaymentGatewayStatus;
use Database\Seeders\KazangPaymentGatewaySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KazangCallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'kazang.enabled' => true,
            'kazang.callback.username' => 'callback-user',
            'kazang.callback.password' => 'callback-pass',
            'kazang.callback.success_status' => '301',
        ]);

        $this->seed(KazangPaymentGatewaySeeder::class);
    }

    public function test_success_callback_confirms_gateway_attempt(): void
    {
        $context = $this->makePendingKazangAttempt();

        $response = $this->postJson('/api/repayment/kazang/callback', [
            'transaction_id' => $context['repayment']->id,
            'transaction_status' => '301',
            'supplier_transaction_id' => 'KZ-123',
            'response_message' => 'Success',
        ], [
            'username' => 'callback-user',
            'password' => 'callback-pass',
        ]);

        $response->assertOk()
            ->assertJson([
                'status_code' => '202',
                'status_message' => 'transaction updated as success',
            ]);

        $this->assertSame(
            GatewayAttemptStatus::Confirmed,
            $context['attempt']->fresh()->status,
        );
    }

    public function test_invalid_credentials_are_rejected_with_legacy_response(): void
    {
        $response = $this->postJson('/api/repayment/kazang/callback', [
            'transaction_id' => 1,
            'transaction_status' => '301',
        ], [
            'username' => 'wrong',
            'password' => 'wrong',
        ]);

        $response->assertOk()
            ->assertJson([
                'status_code' => '285',
                'status_message' => 'Invalid Credentials',
            ]);
    }

    /**
     * @return array{repayment: Repayment, attempt: PaymentGatewayAttempt}
     */
    private function makePendingKazangAttempt(): array
    {
        $gateway = PaymentGateway::query()->where('code', 'kazang')->firstOrFail();
        $gateway->update(['status' => PaymentGatewayStatus::Active]);

        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Kazang Co '.$suffix,
            'slug' => 'kazang-'.$suffix,
            'code' => 'KZ'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);
        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Kazang Product',
            'code' => 'KZP-'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);
        $customer = Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $product->id,
            'first_name' => 'Kazang',
            'last_name' => 'Customer',
            'email' => 'kazang-'.$suffix.'@example.com',
            'phone' => '260971234567',
            'password' => '1234',
            'status' => 'active',
            'approval_status' => 'approved',
            'metadata' => [
                'legacy_user_id' => 501,
                'legacy_client_id' => 36,
            ],
        ]);

        $repayment = Repayment::create([
            'customer_id' => $customer->id,
            'repayment_number' => Repayment::generateRepaymentNumber(),
            'total_amount' => 50,
            'status' => 'processing',
            'phone_number' => '260971234567',
            'metadata' => ['repayment_type' => 'full'],
        ]);

        $attempt = PaymentGatewayAttempt::create([
            'payment_gateway_id' => $gateway->id,
            'direction' => GatewayDirection::Collection,
            'purpose' => GatewayAttemptPurpose::LoanRepayment,
            'attemptable_type' => Repayment::class,
            'attemptable_id' => $repayment->id,
            'internal_reference' => 'KAZ-TEST-1',
            'provider_reference' => 'KAZ-TEST-1',
            'payment_method' => GatewayPaymentMethod::MobileMoney,
            'amount' => 50,
            'currency' => 'ZMW',
            'customer_phone' => '260971234567',
            'status' => GatewayAttemptStatus::Pending,
        ]);

        $repayment->update(['payment_gateway_attempt_id' => $attempt->id]);

        return compact('repayment', 'attempt');
    }
}
