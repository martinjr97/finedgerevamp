<?php

namespace Database\Seeders;

use App\Models\Channel;
use Illuminate\Database\Seeder;

class ChannelSeeder extends Seeder
{
    public function run(): void
    {
        $channels = [
            [
                'name' => 'MTN Money',
                'code' => 'MTN_MONEY',
                'type' => Channel::TYPE_MOBILE_WALLET,
                'description' => 'MTN Mobile Money service for loan repayments and disbursements',
                'can_disburse' => true,
                'can_repay' => true,
                'is_repayment_integrated' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Airtel Money',
                'code' => 'AIRTEL_MONEY',
                'type' => Channel::TYPE_MOBILE_WALLET,
                'description' => 'Airtel Money mobile money service for loan repayments and disbursements',
                'can_disburse' => true,
                'can_repay' => true,
                'is_repayment_integrated' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Zamtel Money',
                'code' => 'ZAMTEL_MONEY',
                'type' => Channel::TYPE_MOBILE_WALLET,
                'description' => 'Zamtel Money mobile money service for loan repayments and disbursements',
                'can_disburse' => true,
                'can_repay' => true,
                'is_repayment_integrated' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Bank Transfer',
                'code' => 'BANK_TRANSFER',
                'type' => Channel::TYPE_BANK,
                'description' => 'Bank transfer for loan disbursements and repayments',
                'can_disburse' => true,
                'can_repay' => true,
                'is_repayment_integrated' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Cash',
                'code' => 'CASH',
                'type' => Channel::TYPE_CASH,
                'description' => 'Physical cash transactions for loan repayments and disbursements',
                'can_disburse' => false,
                'can_repay' => true,
                'is_repayment_integrated' => false,
                'is_active' => true,
            ],
        ];

        foreach ($channels as $channelData) {
            Channel::updateOrCreate(
                ['code' => $channelData['code']],
                $channelData
            );
        }
    }
}
