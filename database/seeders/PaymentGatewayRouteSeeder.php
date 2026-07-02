<?php

namespace Database\Seeders;

use App\PaymentPlatform\Services\PaymentGatewayRouteProvisioner;
use Illuminate\Database\Seeder;

class PaymentGatewayRouteSeeder extends Seeder
{
    public function run(): void
    {
        app(PaymentGatewayRouteProvisioner::class)->sync();
    }
}
