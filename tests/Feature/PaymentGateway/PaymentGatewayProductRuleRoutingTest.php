<?php

namespace Tests\Feature\PaymentGateway;

use App\Models\Channel;
use App\Models\Company;
use App\Models\LoanProduct;
use App\Models\PaymentGateway;
use App\Models\PaymentGatewayProductRule;
use App\Models\Wallet;
use App\PaymentPlatform\Enums\FinancialAccountType;
use App\PaymentPlatform\Enums\GatewayDirection;
use App\PaymentPlatform\Enums\GatewayPaymentMethod;
use App\PaymentPlatform\Enums\GatewayRouteKey;
use App\PaymentPlatform\Enums\PaymentGatewayStatus;
use App\PaymentPlatform\Enums\PaymentGatewayType;
use App\PaymentPlatform\Providers\Kazang\KazangPaymentGateway;
use App\PaymentPlatform\Services\GatewaySelectionService;
use App\PaymentPlatform\Services\PaymentGatewayRouteService;
use Database\Seeders\CGratePaymentGatewaySeeder;
use Database\Seeders\KazangPaymentGatewaySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\EnablesPaymentGatewayRoutes;
use Tests\TestCase;

class PaymentGatewayProductRuleRoutingTest extends TestCase
{
    use EnablesPaymentGatewayRoutes;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cgrate.enabled' => true,
            'kazang.enabled' => true,
        ]);

        $this->seed(CGratePaymentGatewaySeeder::class);
        $this->seed(KazangPaymentGatewaySeeder::class);
        $this->seedPaymentGatewayRoutes();
    }

    public function test_product_rule_overrides_global_wallet_collection_gateway(): void
    {
        $wallet = Wallet::create([
            'name' => 'Treasury Wallet',
            'wallet_number' => 'TEST-TREASURY',
            'provider' => 'other',
            'currency' => 'ZMW',
            'opening_balance' => 1000,
            'current_balance' => 1000,
            'is_active' => true,
        ]);
        $cgrate = $this->activateGateway('cgrate', $wallet);
        $kazang = $this->activateGateway('kazang', $wallet);

        $this->enablePaymentGatewayRoute(GatewayRouteKey::WalletCollection, $cgrate->id);

        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Rule Co '.$suffix,
            'slug' => 'rule-'.$suffix,
            'code' => 'R'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);
        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Rule Product',
            'code' => 'RP-'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);

        PaymentGatewayProductRule::create([
            'loan_product_id' => $product->id,
            'direction' => GatewayDirection::Collection,
            'payment_method' => GatewayPaymentMethod::MobileMoney,
            'payment_gateway_id' => $kazang->id,
            'enabled' => true,
            'auto_process' => true,
            'priority' => 10,
        ]);

        $channel = Channel::create([
            'name' => 'Mobile Wallet',
            'code' => 'MM-'.$suffix,
            'type' => Channel::TYPE_MOBILE_WALLET,
            'can_disburse' => true,
            'can_repay' => true,
            'is_repayment_integrated' => true,
            'is_active' => true,
        ]);
        $resolution = app(PaymentGatewayRouteService::class)->resolveRouteForCollection(
            $channel,
            $product->id,
        );

        $this->assertTrue($resolution->available);
        $this->assertSame($kazang->id, $resolution->gateway?->id);

        $selected = app(GatewaySelectionService::class)->selectForCollection($channel, loanProductId: $product->id);
        $this->assertSame($kazang->id, $selected?->id);
    }

    private function activateGateway(string $code, Wallet $wallet): PaymentGateway
    {
        $gateway = PaymentGateway::query()->where('code', $code)->firstOrFail();
        $gateway->update([
            'status' => PaymentGatewayStatus::Active,
            'financial_account_type' => FinancialAccountType::Wallet,
            'financial_account_id' => $wallet->id,
            'provider_class' => $code === 'kazang'
                ? KazangPaymentGateway::class
                : $gateway->provider_class,
            'type' => PaymentGatewayType::Both,
            'supports_collections' => true,
            'supports_disbursements' => true,
            'supports_mobile_money' => true,
            'supports_bank' => $code === 'cgrate',
            'supports_callbacks' => true,
            'supports_polling' => $code === 'cgrate',
        ]);

        return $gateway->fresh();
    }
}
