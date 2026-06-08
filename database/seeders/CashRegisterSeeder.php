<?php

namespace Database\Seeders;

use App\Services\CashRegisterService;
use Illuminate\Database\Seeder;

class CashRegisterSeeder extends Seeder
{
    public function run(): void
    {
        app(CashRegisterService::class)->defaultRegister();
    }
}
