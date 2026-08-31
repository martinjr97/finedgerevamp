<?php

namespace App\Migration\Phases;

use Illuminate\Support\Facades\DB;

class MigrationEntityMapRepository
{
    public const TYPE_COMPANY = 'company';

    public const TYPE_CUSTOMER = 'customer';

    public const TYPE_LOAN = 'loan';

    public const TYPE_REPAYMENT = 'repayment';

    public const TYPE_BANK = 'bank';

    public const TYPE_FINANCIAL_INSTITUTION = 'financial_institution';

    public const TYPE_WALLET_PROVIDER = 'wallet_provider';

    public const TYPE_PRODUCT = 'product';

    public const TYPE_MARKET = 'market';

    public const TYPE_CUSTOMER_GROUP = 'customer_group';

    public const TYPE_BRANCH = 'branch';

    public const TYPE_RELATIONSHIP_MANAGER = 'relationship_manager';

    public const TYPE_EXPENSE_CATEGORY = 'expense_category';

    public const TYPE_EXPENSE_SUBCATEGORY = 'expense_subcategory';

    public const TYPE_INCOME_CATEGORY = 'income_category';

    public const TYPE_FINANCIAL_TRANSACTION = 'financial_transaction';

    public const TYPE_TREASURY_WALLET = 'treasury_wallet';

    public function find(string $entityType, string $legacyIdentifier, ?string $legacySecondary = null): ?object
    {
        $query = DB::table('migration_entity_maps')
            ->where('entity_type', $entityType)
            ->where('legacy_identifier', $legacyIdentifier);

        if ($legacySecondary !== null) {
            return $query->where('legacy_secondary', $legacySecondary)->first();
        }

        return $query
            ->orderByRaw('CASE WHEN legacy_secondary IS NULL THEN 0 ELSE 1 END')
            ->orderByRaw("CASE WHEN mapping_method LIKE 'identity_resolution%' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->first();
    }

    public function targetId(string $entityType, string $legacyIdentifier, ?string $legacySecondary = null): ?int
    {
        $row = $this->find($entityType, $legacyIdentifier, $legacySecondary);

        return $row ? (int) $row->target_id : null;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function store(
        string $entityType,
        string $legacyIdentifier,
        string $targetType,
        int $targetId,
        string $mappingMethod,
        string $confidence = 'HIGH',
        ?string $legacySecondary = null,
        ?int $migrationRunId = null,
        array $metadata = [],
    ): void {
        $existing = $this->find($entityType, $legacyIdentifier, $legacySecondary);
        if ($existing) {
            return;
        }

        DB::table('migration_entity_maps')->insert([
            'entity_type' => $entityType,
            'legacy_identifier' => $legacyIdentifier,
            'legacy_secondary' => $legacySecondary,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'mapping_method' => $mappingMethod,
            'mapping_confidence' => $confidence,
            'migration_run_id' => $migrationRunId,
            'metadata' => $metadata === [] ? null : json_encode($metadata),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function storeOrUpdate(
        string $entityType,
        string $legacyIdentifier,
        string $targetType,
        int $targetId,
        string $mappingMethod,
        string $confidence = 'HIGH',
        ?string $legacySecondary = null,
        ?int $migrationRunId = null,
        array $metadata = [],
    ): void {
        $existing = $this->find($entityType, $legacyIdentifier, $legacySecondary);
        if ($existing) {
            $merged = array_merge(json_decode($existing->metadata ?? '{}', true) ?: [], $metadata);

            DB::table('migration_entity_maps')
                ->where('id', $existing->id)
                ->update([
                    'target_type' => $targetType,
                    'target_id' => $targetId,
                    'mapping_method' => $mappingMethod,
                    'mapping_confidence' => $confidence,
                    'migration_run_id' => $migrationRunId ?? $existing->migration_run_id,
                    'metadata' => $merged === [] ? null : json_encode($merged),
                    'updated_at' => now(),
                ]);

            return;
        }

        $this->store(
            $entityType,
            $legacyIdentifier,
            $targetType,
            $targetId,
            $mappingMethod,
            $confidence,
            $legacySecondary,
            $migrationRunId,
            $metadata,
        );
    }

    public function trackCreated(int $migrationRunId, string $recordType, int $recordId): void
    {
        DB::table('migration_created_records')->insert([
            'migration_run_id' => $migrationRunId,
            'record_type' => $recordType,
            'record_id' => $recordId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Update metadata/method on an existing map without changing target_id.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function annotateMap(
        string $entityType,
        string $legacyIdentifier,
        array $metadata,
        ?string $mappingMethod = null,
        ?int $forceTargetId = null,
        ?string $legacySecondary = null,
    ): void {
        $existing = $this->find($entityType, $legacyIdentifier, $legacySecondary);
        if (! $existing) {
            return;
        }

        $merged = array_merge(json_decode($existing->metadata ?? '{}', true) ?: [], $metadata);

        $update = [
            'metadata' => json_encode($merged),
            'mapping_confidence' => 'HIGH',
            'updated_at' => now(),
        ];

        if ($mappingMethod !== null) {
            $update['mapping_method'] = $mappingMethod;
        }

        if ($forceTargetId !== null) {
            $update['target_id'] = $forceTargetId;
        }

        DB::table('migration_entity_maps')
            ->where('id', $existing->id)
            ->update($update);
    }

    public function isSuperseded(string $entityType, string $legacyIdentifier): bool
    {
        $existing = $this->find($entityType, $legacyIdentifier);
        if (! $existing) {
            return false;
        }

        $metadata = json_decode($existing->metadata ?? '{}', true) ?: [];

        return (bool) ($metadata['superseded'] ?? false);
    }

    /**
     * @return list<object>
     */
    public function createdByRun(int $migrationRunId, ?string $recordType = null): array
    {
        return DB::table('migration_created_records')
            ->where('migration_run_id', $migrationRunId)
            ->when($recordType, fn ($q) => $q->where('record_type', $recordType))
            ->get()
            ->all();
    }
}
