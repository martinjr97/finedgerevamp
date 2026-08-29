<?php

namespace App\Support;

use App\Models\Company;
use Illuminate\Support\Facades\Cache;

class OperatorCompany
{
    public static function resolve(): ?Company
    {
        return Cache::remember('operator_company', 300, function () {
            return Company::query()
                ->where('type', 'operator')
                ->orderByDesc('is_primary')
                ->orderBy('id')
                ->first()
                ?? Company::query()
                    ->where('is_primary', true)
                    ->orderBy('id')
                    ->first();
        });
    }

    public static function id(): ?int
    {
        return self::resolve()?->id;
    }

    public static function forgetCache(): void
    {
        Cache::forget('operator_company');
    }
}
