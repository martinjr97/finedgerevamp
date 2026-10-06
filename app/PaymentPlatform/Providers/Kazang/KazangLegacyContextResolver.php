<?php

namespace App\PaymentPlatform\Providers\Kazang;

use App\Migration\Phases\MigrationEntityMapRepository;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

final class KazangLegacyContextResolver
{
    /**
     * @return array{client_id: int, user_id: int}
     */
    public function resolveForCustomer(Customer $customer): array
    {
        $customer->loadMissing('company');

        $metadata = (array) ($customer->metadata ?? []);
        $legacyUserId = (int) ($metadata['legacy_user_id'] ?? 0);
        $legacyClientId = (int) ($metadata['legacy_client_id'] ?? 0);

        if ($legacyUserId <= 0) {
            $legacyUserId = (int) ($this->findLegacyUserIdByTarget($customer->id) ?? 0);
        }

        if ($legacyClientId <= 0 && $customer->company_id) {
            $companySettings = (array) ($customer->company?->settings ?? []);
            $legacyClientId = (int) ($companySettings['legacy_client_id'] ?? 0);

            if ($legacyClientId <= 0) {
                $legacyClientId = $this->findLegacyClientIdByCompanyTarget((int) $customer->company_id);
            }
        }

        if ($legacyUserId <= 0 || $legacyClientId <= 0) {
            throw new KazangException('Legacy Kazang context could not be resolved for this customer.');
        }

        return [
            'client_id' => $legacyClientId,
            'user_id' => $legacyUserId,
        ];
    }

    private function findLegacyUserIdByTarget(int $customerId): ?int
    {
        $legacyIdentifier = DB::table('migration_entity_maps')
            ->where('entity_type', MigrationEntityMapRepository::TYPE_CUSTOMER)
            ->where('target_type', Customer::class)
            ->where('target_id', $customerId)
            ->where('superseded_at', null)
            ->value('legacy_identifier');

        return $legacyIdentifier !== null ? (int) $legacyIdentifier : null;
    }

    private function findLegacyClientIdByCompanyTarget(int $companyId): int
    {
        $legacyIdentifier = DB::table('migration_entity_maps')
            ->where('entity_type', MigrationEntityMapRepository::TYPE_COMPANY)
            ->where('target_id', $companyId)
            ->where('superseded_at', null)
            ->value('legacy_identifier');

        return $legacyIdentifier !== null ? (int) $legacyIdentifier : 0;
    }
}
