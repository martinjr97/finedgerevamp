<?php

namespace App\Services;

use App\Models\CashRegister;

class CashRegisterService
{
    public function defaultRegister(): CashRegister
    {
        return CashRegister::query()->firstOrCreate(
            ['code' => CashRegister::DEFAULT_CODE],
            [
                'name' => 'Cash on Hand',
                'currency' => 'ZMW',
                'opening_balance' => 0,
                'current_balance' => 0,
                'is_active' => true,
            ]
        );
    }

    public function resolveRegisterId(?int $cashRegisterId): int
    {
        if ($cashRegisterId) {
            return (int) CashRegister::query()
                ->where('is_active', true)
                ->findOrFail($cashRegisterId)
                ->id;
        }

        return (int) $this->defaultRegister()->id;
    }
}
