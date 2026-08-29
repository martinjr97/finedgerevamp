<?php

namespace App\Migration\Phases\Support;

class MarketeerClassifier
{
    /**
     * Authoritative legacy Marketeer identification.
     *
     * Primary: customers.is_marketize_customer = true
     * Secondary: clients.product_type = marketize_based
     */
    public function isMarketeerCustomer(array $legacyCustomer, ?array $legacyClient = null): bool
    {
        if ((bool) ($legacyCustomer['is_marketize_customer'] ?? false)) {
            return true;
        }

        return ($legacyClient['product_type'] ?? null) === 'marketize_based';
    }

    /**
     * Legacy client exists only to bucket Marketeer product — not a real employer.
     *
     * @param  array<string, mixed>  $legacyClient
     */
    public function isMarketeerPlaceholderClient(array $legacyClient): bool
    {
        if (($legacyClient['product_type'] ?? null) !== 'marketize_based') {
            return false;
        }

        $name = strtolower(trim((string) ($legacyClient['company_name'] ?? '')));

        return str_contains($name, 'marketize')
            || str_contains($name, 'marketeer')
            || preg_match('/^mkt-\d+/i', (string) ($legacyClient['reg_number'] ?? '')) === 1;
    }

    public function legacyMarketId(array $legacyCustomer): ?int
    {
        $id = (int) ($legacyCustomer['market_id'] ?? 0);

        return $id > 0 ? $id : null;
    }
}
