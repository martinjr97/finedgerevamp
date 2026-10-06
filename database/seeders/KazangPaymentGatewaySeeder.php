<?php

namespace Database\Seeders;

use App\Models\PaymentGateway;
use App\Models\Wallet;
use App\PaymentPlatform\Enums\FinancialAccountType;
use App\PaymentPlatform\Enums\PaymentGatewayStatus;
use App\PaymentPlatform\Enums\PaymentGatewayType;
use App\PaymentPlatform\Providers\Kazang\KazangPaymentGateway;
use Illuminate\Database\Seeder;

class KazangPaymentGatewaySeeder extends Seeder
{
    public function run(): void
    {
        $wallet = Wallet::query()->where('wallet_number', 'KAZANG-TREASURY')->first();

        PaymentGateway::updateOrCreate(
            ['code' => 'kazang'],
            [
                'name' => 'Kazang',
                'provider_class' => KazangPaymentGateway::class,
                'type' => PaymentGatewayType::Both,
                'status' => PaymentGatewayStatus::Inactive,
                'priority' => 20,
                'is_default' => false,
                'supports_collections' => true,
                'supports_disbursements' => true,
                'supports_mobile_money' => true,
                'supports_bank' => false,
                'supports_callbacks' => true,
                'supports_polling' => false,
                'financial_account_type' => $wallet ? FinancialAccountType::Wallet : null,
                'financial_account_id' => $wallet?->id,
                'config' => [],
                'metadata' => [
                    'description' => 'Kazang mobile money collections via RabbitMQ and direct XML-RPC disbursements.',
                ],
            ],
        );
    }
}
