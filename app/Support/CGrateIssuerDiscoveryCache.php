<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class CGrateIssuerDiscoveryCache
{
    private const CACHE_KEY = 'payment_gateway.cgrate.discovered_issuers';

    /**
     * @param  list<string>  $issuers
     * @param  array<string, mixed>  $raw
     */
    public function store(array $issuers, array $raw = []): void
    {
        Cache::put(self::CACHE_KEY, [
            'issuers' => array_values($issuers),
            'raw' => $raw,
            'discovered_at' => now()->toIso8601String(),
        ], now()->addDays(7));
    }

    /**
     * @return array{issuers: list<string>, raw: array<string, mixed>, discovered_at: ?string}|null
     */
    public function latest(): ?array
    {
        /** @var array{issuers?: list<string>, raw?: array<string, mixed>, discovered_at?: string}|null $payload */
        $payload = Cache::get(self::CACHE_KEY);

        if (! is_array($payload) || empty($payload['issuers'])) {
            return null;
        }

        return [
            'issuers' => array_values($payload['issuers']),
            'raw' => is_array($payload['raw'] ?? null) ? $payload['raw'] : [],
            'discovered_at' => $payload['discovered_at'] ?? null,
        ];
    }

    public function clear(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
