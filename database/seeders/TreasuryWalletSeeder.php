<?php

namespace Database\Seeders;

use App\Models\Wallet;
use Illuminate\Database\Seeder;

class TreasuryWalletSeeder extends Seeder
{
    public function run(): void
    {
        Wallet::updateOrCreate(
            ['wallet_number' => 'CGRATE-TREASURY'],
            [
                'name' => 'cGrate Treasury Wallet',
                'provider' => 'other',
                'currency' => 'ZMW',
                'opening_balance' => 0,
                'current_balance' => 0,
                'is_active' => true,
                'notes' => 'Treasury wallet for cGrate gateway collections and disbursements.',
            ],
        );

        Wallet::updateOrCreate(
            ['wallet_number' => 'KAZANG-TREASURY'],
            [
                'name' => 'Kazang Treasury Wallet',
                'provider' => 'other',
                'currency' => 'ZMW',
                'opening_balance' => 0,
                'current_balance' => 0,
                'is_active' => true,
                'notes' => 'Treasury wallet for Kazang gateway collections and disbursements.',
            ],
        );
    }
}
