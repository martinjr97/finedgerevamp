<?php

namespace App\Migration\Phases;

use App\Migration\Phases\Support\LegacyClientClassifier;
use App\Models\CustomerGroup;
use App\Models\LoanProduct;

class ProductCustomerGroupReferenceMigrator
{
    public const GOV_DEFAULT_GROUP_CODE = 'GOV-DEFAULT';

    public function __construct(
        private readonly MigrationEntityMapRepository $maps,
        private readonly LegacyClientClassifier $clientClassifier,
    ) {}

    /**
     * @param  array<string, mixed>  $stats
     */
    public function migrate($legacy, int $runId, bool $promote, array &$stats): void
    {
        $stats['customer_groups'] ??= [
            'GOVERNMENT_MATCHED' => 0,
            'GOVERNMENT_CREATED' => 0,
            'GOVERNMENT_WOULD_CREATE' => 0,
            'GOVERNMENT_MANUAL_REVIEW' => 0,
            'CHARACTER_MATCHED' => 0,
            'CHARACTER_CREATED' => 0,
            'CHARACTER_WOULD_CREATE' => 0,
            'CHARACTER_MANUAL_REVIEW' => 0,
        ];

        $this->migrateGovernmentDefaultGroup($runId, $promote, $stats);
        $this->migrateCharacterClientGroups($legacy, $runId, $promote, $stats);
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function migrateGovernmentDefaultGroup(int $runId, bool $promote, array &$stats): void
    {
        $mapKey = self::GOV_DEFAULT_GROUP_CODE;
        if ($this->maps->find(MigrationEntityMapRepository::TYPE_CUSTOMER_GROUP, $mapKey)) {
            $stats['customer_groups']['GOVERNMENT_MATCHED']++;

            return;
        }

        $product = LoanProduct::query()->where('code', 'GOV-001')->first();
        if (! $product) {
            $stats['customer_groups']['GOVERNMENT_MANUAL_REVIEW']++;

            return;
        }

        $existing = CustomerGroup::query()->where('code', self::GOV_DEFAULT_GROUP_CODE)->first();
        if ($existing) {
            if ($promote && $existing->name !== 'Default Group') {
                $existing->update(['name' => 'Default Group']);
            }
            $stats['customer_groups']['GOVERNMENT_MATCHED']++;
            $this->maps->store(
                MigrationEntityMapRepository::TYPE_CUSTOMER_GROUP,
                $mapKey,
                CustomerGroup::class,
                $existing->id,
                'existing_target',
                'HIGH',
                null,
                $runId
            );

            return;
        }

        if (! $promote) {
            $stats['customer_groups']['GOVERNMENT_WOULD_CREATE']++;

            return;
        }

        $group = CustomerGroup::create([
            'loan_product_id' => $product->id,
            'name' => 'Default Group',
            'code' => self::GOV_DEFAULT_GROUP_CODE,
            'description' => 'Default customer group for migrated legacy government customers.',
            'risk_level' => 'medium',
            'max_loan_amount' => $product->max_amount,
            'max_loan_tenure_months' => $product->tenure_months,
            'is_active' => true,
            'allow_multiple_loans' => false,
        ]);

        $stats['customer_groups']['GOVERNMENT_CREATED']++;
        $this->maps->store(
            MigrationEntityMapRepository::TYPE_CUSTOMER_GROUP,
            $mapKey,
            CustomerGroup::class,
            $group->id,
            'created',
            'HIGH',
            null,
            $runId
        );
        $this->maps->trackCreated($runId, CustomerGroup::class, $group->id);
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function migrateCharacterClientGroups($legacy, int $runId, bool $promote, array &$stats): void
    {
        $product = LoanProduct::query()->where('code', 'CHAR-001')->first();
        if (! $product) {
            $stats['customer_groups']['CHARACTER_MANUAL_REVIEW']++;

            return;
        }

        $clients = $legacy->table('clients')->get();
        $allLoans = $legacy->table('loans')->get()->groupBy('client_id');

        foreach ($clients as $client) {
            $clientArr = (array) $client;
            $legacyClientId = (int) $clientArr['id'];
            $customerCount = (int) $legacy->table('customers')->where('client_id', $legacyClientId)->count();
            $clientLoans = $allLoans->get($legacyClientId, collect());
            $classification = $this->clientClassifier->classify($clientArr, $clientLoans, $customerCount);

            if ($classification !== LegacyClientClassifier::CHARACTER_PRODUCT_PLACEHOLDER) {
                continue;
            }

            $mapKey = $this->characterGroupMapKey($legacyClientId);
            if ($this->maps->find(MigrationEntityMapRepository::TYPE_CUSTOMER_GROUP, $mapKey)) {
                $stats['customer_groups']['CHARACTER_MATCHED']++;

                continue;
            }

            $groupName = trim((string) ($clientArr['company_name'] ?? 'Character Group '.$legacyClientId));
            $existing = CustomerGroup::query()
                ->where('loan_product_id', $product->id)
                ->where('name', $groupName)
                ->first();

            if ($existing) {
                $stats['customer_groups']['CHARACTER_MATCHED']++;
                $this->maps->store(
                    MigrationEntityMapRepository::TYPE_CUSTOMER_GROUP,
                    $mapKey,
                    CustomerGroup::class,
                    $existing->id,
                    'existing_target',
                    'HIGH',
                    (string) $legacyClientId,
                    $runId
                );

                continue;
            }

            if (! $promote) {
                $stats['customer_groups']['CHARACTER_WOULD_CREATE']++;

                continue;
            }

            $group = CustomerGroup::create([
                'loan_product_id' => $product->id,
                'name' => $groupName,
                'code' => 'LEG-CHAR-'.$legacyClientId,
                'description' => 'Migrated legacy character/agent bucket: '.$groupName,
                'risk_level' => 'medium',
                'max_loan_amount' => $product->max_amount,
                'max_loan_tenure_months' => $product->tenure_months,
                'is_active' => true,
                'allow_multiple_loans' => false,
            ]);

            $stats['customer_groups']['CHARACTER_CREATED']++;
            $this->maps->store(
                MigrationEntityMapRepository::TYPE_CUSTOMER_GROUP,
                $mapKey,
                CustomerGroup::class,
                $group->id,
                'created',
                'HIGH',
                (string) $legacyClientId,
                $runId
            );
            $this->maps->trackCreated($runId, CustomerGroup::class, $group->id);
        }
    }

    public function characterGroupMapKey(int $legacyClientId): string
    {
        return 'LEG-CHAR-'.$legacyClientId;
    }

    public function governmentDefaultGroupId(): ?int
    {
        return $this->maps->targetId(
            MigrationEntityMapRepository::TYPE_CUSTOMER_GROUP,
            self::GOV_DEFAULT_GROUP_CODE
        );
    }

    public function characterGroupIdForLegacyClient(int $legacyClientId): ?int
    {
        return $this->maps->targetId(
            MigrationEntityMapRepository::TYPE_CUSTOMER_GROUP,
            $this->characterGroupMapKey($legacyClientId)
        );
    }

    public function marketeerGroupId(): ?int
    {
        return $this->maps->targetId(
            MigrationEntityMapRepository::TYPE_CUSTOMER_GROUP,
            MarketeerReferenceMigrator::DEFAULT_GROUP_CODE
        );
    }
}
