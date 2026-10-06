<?php

namespace Database\Seeders;

use App\Models\LoanProduct;
use App\Models\PaymentGateway;
use App\Models\PaymentGatewayProductRule;
use App\PaymentPlatform\Enums\GatewayDirection;
use App\PaymentPlatform\Enums\GatewayPaymentMethod;
use Illuminate\Database\Seeder;

class PaymentGatewayProductRuleSeeder extends Seeder
{
    public function run(): void
    {
        $cgrate = PaymentGateway::query()->where('code', 'cgrate')->first();

        if (! $cgrate) {
            return;
        }

        $combinations = [
            [GatewayDirection::Collection, GatewayPaymentMethod::MobileMoney],
            [GatewayDirection::Collection, GatewayPaymentMethod::Bank],
            [GatewayDirection::Disbursement, GatewayPaymentMethod::MobileMoney],
            [GatewayDirection::Disbursement, GatewayPaymentMethod::Bank],
        ];

        LoanProduct::query()->orderBy('id')->each(function (LoanProduct $product) use ($cgrate, $combinations): void {
            foreach ($combinations as [$direction, $paymentMethod]) {
                PaymentGatewayProductRule::updateOrCreate(
                    [
                        'loan_product_id' => $product->id,
                        'direction' => $direction->value,
                        'payment_method' => $paymentMethod->value,
                    ],
                    [
                        'payment_gateway_id' => $cgrate->id,
                        'enabled' => true,
                        'auto_process' => true,
                        'priority' => 10,
                        'notes' => 'Default cGrate routing for all products.',
                    ],
                );
            }
        });
    }
}
