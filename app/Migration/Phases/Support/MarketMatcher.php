<?php

namespace App\Migration\Phases\Support;

use App\Models\Market;

class MarketMatcher
{
    /**
     * @param  array<string, mixed>  $legacyMarket
     * @return array{market: ?Market, method: ?string, confidence: string}
     */
    public function matchExisting(array $legacyMarket): array
    {
        $legacyId = (int) ($legacyMarket['id'] ?? 0);

        $byCode = Market::query()->where('code', $this->legacyCode($legacyId))->first();
        if ($byCode) {
            return ['market' => $byCode, 'method' => 'legacy_code', 'confidence' => 'HIGH'];
        }

        $name = $this->normalizeName((string) ($legacyMarket['name'] ?? ''));
        if ($name !== '') {
            $candidates = Market::query()->get()->filter(
                fn (Market $m) => $this->normalizeName($m->name) === $name
            );
            if ($candidates->count() === 1) {
                return ['market' => $candidates->first(), 'method' => 'normalized_name', 'confidence' => 'HIGH'];
            }
        }

        return ['market' => null, 'method' => null, 'confidence' => 'LOW'];
    }

    public function legacyCode(int $legacyMarketId): string
    {
        return 'MRKT-LEG-'.$legacyMarketId;
    }

    public function normalizeName(string $name): string
    {
        $name = strtolower(trim($name));
        $name = preg_replace('/\s+/', ' ', $name) ?? $name;
        $name = preg_replace('/[^a-z0-9 ]/', '', $name) ?? $name;

        return trim($name);
    }
}
