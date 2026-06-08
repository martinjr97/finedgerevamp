<?php

namespace Database\Seeders;

use App\Models\WalletProvider;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class WalletProviderSeeder extends Seeder
{
    public function run(): void
    {
        $providers = [
            ['name' => 'Airtel Money', 'code' => 'AIRTEL_MONEY'],
            ['name' => 'MTN Money', 'code' => 'MTN_MONEY'],
            ['name' => 'Zamtel Money', 'code' => 'ZAMTEL_MONEY'],
            ['name' => 'Zed Mobile Money', 'code' => 'ZED_MOBILE_MONEY'],
        ];

        foreach ($providers as $provider) {
            $name = Str::upper($provider['name']);
            $code = $provider['code'];

            $existing = WalletProvider::query()
                ->where('code', $code)
                ->orWhere('name', $name)
                ->first();

            if (! $existing) {
                WalletProvider::create([
                    'name' => $name,
                    'code' => $code,
                    'is_active' => true,
                ]);
                continue;
            }

            $existing->update([
                'name' => $name,
                'code' => $code,
                'is_active' => true,
            ]);
        }
    }
}
