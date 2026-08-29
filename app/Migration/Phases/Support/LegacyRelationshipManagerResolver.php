<?php

namespace App\Migration\Phases\Support;

/**
 * Mirrors legacy RelationshipManagerResolverService rules for migration.
 */
class LegacyRelationshipManagerResolver
{
    /**
     * @param  array<string, mixed>  $legacyCustomer
     * @param  array<string, mixed>|null  $legacyClient
     */
    public function resolveLegacyRelationshipManagerId(array $legacyCustomer, ?array $legacyClient): ?int
    {
        if (! $legacyClient) {
            return $this->positiveInt($legacyCustomer['relationship_manager_id'] ?? null);
        }

        if (($legacyClient['product_type'] ?? null) === 'salary_based') {
            return $this->positiveInt($legacyClient['relationship_manager'] ?? null);
        }

        $customerRm = $this->positiveInt($legacyCustomer['relationship_manager_id'] ?? null);
        if ($customerRm) {
            return $customerRm;
        }

        return $this->positiveInt($legacyClient['relationship_manager'] ?? null);
    }

    private function positiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }
}
