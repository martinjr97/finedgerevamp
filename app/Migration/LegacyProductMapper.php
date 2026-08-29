<?php

namespace App\Migration;

class LegacyProductMapper
{
    /**
     * @param  array<string, mixed>  $loan
     * @param  array<string, mixed>|null  $client
     * @return array{code: string, category: string, reason: string}
     */
    public function mapLoanProduct(array $loan, ?array $client = null): array
    {
        $productType = $client['product_type'] ?? null;

        if ((bool) ($loan['gvnt_loan'] ?? false)) {
            return ['code' => 'GOV-001', 'category' => 'government', 'reason' => 'gvnt_loan flag'];
        }

        if ((bool) ($loan['salary_based'] ?? false) || $productType === 'salary_based') {
            return ['code' => 'MOU-001', 'category' => 'mou', 'reason' => 'salary_based flag or client product_type'];
        }

        if ($productType === 'marketize_based') {
            return ['code' => 'MARK-001', 'category' => 'marketeer', 'reason' => 'marketize_based client product_type'];
        }

        if ($productType === 'character_based') {
            return ['code' => 'CHAR-001', 'category' => 'character', 'reason' => 'character_based client product_type'];
        }

        return ['code' => 'CHAR-001', 'category' => 'character', 'reason' => 'default fixed-rate fallback'];
    }
}
