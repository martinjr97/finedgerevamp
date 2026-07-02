<?php

namespace Database\Seeders;

use App\Models\PaymentGateway;
use App\PaymentPlatform\Enums\PaymentGatewayStatus;
use App\PaymentPlatform\Enums\PaymentGatewayType;
use App\PaymentPlatform\Providers\CGrate\CGratePaymentGateway;
use Illuminate\Database\Seeder;

class CGratePaymentGatewaySeeder extends Seeder
{
    public function run(): void
    {
        PaymentGateway::updateOrCreate(
            ['code' => 'cgrate'],
            [
                'name' => 'cGrate',
                'provider_class' => CGratePaymentGateway::class,
                'type' => PaymentGatewayType::Both,
                'status' => PaymentGatewayStatus::Inactive,
                'priority' => 10,
                'is_default' => true,
                'supports_collections' => true,
                'supports_disbursements' => true,
                'supports_mobile_money' => true,
                'supports_bank' => true,
                'supports_callbacks' => true,
                'supports_polling' => true,
                'financial_account_type' => null,
                'financial_account_id' => null,
                'config' => [],
                'metadata' => [
                    'description' => 'cGrate mobile money and bank disbursements via SOAP API',
                ],
            ]
        );
    }
}
