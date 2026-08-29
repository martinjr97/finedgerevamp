<?php

namespace App\Migration\Phases\Support;

use App\Migration\LegacyConnection;
use App\Migration\Phases\MigrationEntityMapRepository;
use App\Models\Customer;

class CustomerIdentityResolver
{
    public function __construct(
        private readonly MigrationEntityMapRepository $maps,
    ) {}

    /**
     * Apply approved identity resolutions to migration_entity_maps (target DB only).
     *
     * @return array<string, mixed>
     */
    public function applyApprovedResolutions(?int $migrationRunId = null): array
    {
        $applied = [];

        foreach (CustomerIdentityResolutionRegistry::approved() as $nrc => $resolution) {
            $primaryId = (int) $resolution['primary_legacy_user_id'];
            $targetId = (int) ($resolution['target_customer_id'] ?? 0);

            if ($targetId > 0) {
                $this->maps->store(
                    MigrationEntityMapRepository::TYPE_CUSTOMER,
                    (string) $primaryId,
                    Customer::class,
                    $targetId,
                    'identity_resolution_primary',
                    'HIGH',
                    null,
                    $migrationRunId,
                    [
                        'identity_resolution' => $resolution['classification'],
                        'nrc' => $nrc,
                        'role' => 'primary',
                    ]
                );
            }

            foreach ($resolution['alias_legacy_user_ids'] ?? [] as $aliasId) {
                if ($targetId > 0) {
                    $this->maps->annotateMap(
                        MigrationEntityMapRepository::TYPE_CUSTOMER,
                        (string) $aliasId,
                        [
                            'identity_resolution' => $resolution['classification'],
                            'nrc' => $nrc,
                            'role' => 'alias',
                            'primary_legacy_user_id' => $primaryId,
                            'approved_reason' => $resolution['reason'],
                        ],
                        'identity_resolution_alias',
                        $targetId
                    );

                    if (! $this->maps->find(MigrationEntityMapRepository::TYPE_CUSTOMER, (string) $aliasId)) {
                        $this->maps->store(
                            MigrationEntityMapRepository::TYPE_CUSTOMER,
                            (string) $aliasId,
                            Customer::class,
                            $targetId,
                            'identity_resolution_alias',
                            'HIGH',
                            null,
                            $migrationRunId,
                            [
                                'identity_resolution' => $resolution['classification'],
                                'nrc' => $nrc,
                                'role' => 'alias',
                                'primary_legacy_user_id' => $primaryId,
                            ]
                        );
                    }
                }

                $applied[] = ['nrc' => $nrc, 'alias_legacy_user_id' => $aliasId, 'target_customer_id' => $targetId ?: null];
            }

            $applied[] = ['nrc' => $nrc, 'primary_legacy_user_id' => $primaryId, 'target_customer_id' => $targetId ?: null];
        }

        $this->markObsoleteCompanyMaps();

        return ['applied' => $applied, 'duplicate_groups_resolved' => count(CustomerIdentityResolutionRegistry::approved())];
    }

    public function markObsoleteCompanyMaps(): void
    {
        foreach (['36', '8', '6', '7', '2'] as $legacyClientId) {
            $this->maps->annotateMap(
                MigrationEntityMapRepository::TYPE_COMPANY,
                $legacyClientId,
                [
                    'superseded' => true,
                    'status' => 'OBSOLETE_IGNORED',
                    'reason' => match ($legacyClientId) {
                        '36' => 'Marketeer product placeholder (Marketize Loans) — must not become target company.',
                        '8' => 'Government product placeholder (GRZ) — must not become target company.',
                        '6', '7' => 'Character product/agent placeholder — must not become target company.',
                        '2' => 'Character/agent loan bucket (Vendor) — must not become target company.',
                        default => 'Obsolete pilot company map.',
                    },
                ],
                'superseded_product_placeholder'
            );
        }
    }

    public function duplicateGroupsResolved(): bool
    {
        LegacyConnection::configureFromLegacyEnvFile();
        $legacy = LegacyConnection::connection();

        $groups = $legacy->table('customers')
            ->whereNotNull('nrc')
            ->where('nrc', '!=', '')
            ->get()
            ->groupBy('nrc')
            ->filter(fn ($g) => $g->count() > 1);

        foreach ($groups->keys() as $nrc) {
            if (! CustomerIdentityResolutionRegistry::forNrc((string) $nrc)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Resolve target customer id for a legacy user, including alias lookups mid-run.
     */
    public function resolveTargetForUser(int $legacyUserId, array $runCache = []): ?int
    {
        $existing = $this->maps->targetId(MigrationEntityMapRepository::TYPE_CUSTOMER, (string) $legacyUserId);
        if ($existing) {
            return $existing;
        }

        if (isset($runCache[$legacyUserId]) && (int) $runCache[$legacyUserId] > 0) {
            return (int) $runCache[$legacyUserId];
        }

        $resolution = CustomerIdentityResolutionRegistry::forUser($legacyUserId);
        if (! $resolution) {
            return null;
        }

        if (CustomerIdentityResolutionRegistry::isAlias($legacyUserId)) {
            $primaryId = (int) $resolution['primary_legacy_user_id'];
            if (isset($runCache[$primaryId]) && (int) $runCache[$primaryId] > 0) {
                return (int) $runCache[$primaryId];
            }
            if ($resolution['target_customer_id']) {
                return (int) $resolution['target_customer_id'];
            }

            return $this->maps->targetId(MigrationEntityMapRepository::TYPE_CUSTOMER, (string) $primaryId);
        }

        if ($resolution['target_customer_id']) {
            return (int) $resolution['target_customer_id'];
        }

        return null;
    }

    public function isApprovedAliasMap(object $map): bool
    {
        $metadata = json_decode($map->metadata ?? '{}', true) ?: [];

        return ($map->mapping_method ?? '') === 'identity_resolution_alias'
            || (($metadata['identity_resolution'] ?? '') === CustomerIdentityResolutionRegistry::CLASS_SAME_PERSON_MAP_ONE
                && ($metadata['role'] ?? '') === 'alias');
    }
}
