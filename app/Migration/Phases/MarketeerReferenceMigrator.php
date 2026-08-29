<?php

namespace App\Migration\Phases;

use App\Migration\Phases\Support\MarketMatcher;
use App\Models\CustomerGroup;
use App\Models\District;
use App\Models\LoanProduct;
use App\Models\Market;
use App\Models\Province;
use Illuminate\Support\Facades\DB;

class MarketeerReferenceMigrator
{
    public const DEFAULT_GROUP_CODE = 'MRKT-LEGACY';

    public function __construct(
        private readonly MigrationEntityMapRepository $maps,
        private readonly MarketMatcher $marketMatcher,
    ) {}

    /**
     * @param  array<string, mixed>  $stats
     */
    public function migrate($legacy, int $runId, bool $promote, array &$stats): void
    {
        $this->migrateDefaultGroup($runId, $promote, $stats);
        $this->migrateMarkets($legacy, $runId, $promote, $stats);
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function migrateDefaultGroup(int $runId, bool $promote, array &$stats): void
    {
        $existingMap = $this->maps->find(
            MigrationEntityMapRepository::TYPE_CUSTOMER_GROUP,
            self::DEFAULT_GROUP_CODE
        );

        if ($existingMap) {
            $stats['marketeer']['groups']['MATCHED_EXISTING']++;

            return;
        }

        $existing = CustomerGroup::query()->where('code', self::DEFAULT_GROUP_CODE)->first();
        if ($existing) {
            $stats['marketeer']['groups']['MATCHED_EXISTING']++;
            $this->maps->store(
                MigrationEntityMapRepository::TYPE_CUSTOMER_GROUP,
                self::DEFAULT_GROUP_CODE,
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
            $stats['marketeer']['groups']['WOULD_CREATE']++;

            return;
        }

        $product = LoanProduct::query()->where('code', 'MARK-001')->first();
        if (! $product) {
            $stats['marketeer']['groups']['MANUAL_REVIEW']++;

            return;
        }

        $group = CustomerGroup::create([
            'loan_product_id' => $product->id,
            'name' => 'Legacy Marketeer Markets',
            'code' => self::DEFAULT_GROUP_CODE,
            'description' => 'Default customer group for migrated legacy marketize/marketeer traders.',
            'risk_level' => 'medium',
            'max_loan_amount' => (int) $product->max_amount,
            'max_loan_tenure_months' => $product->tenure_months,
            'is_active' => true,
            'allow_multiple_loans' => false,
        ]);

        $stats['marketeer']['groups']['CREATED']++;
        $this->maps->store(
            MigrationEntityMapRepository::TYPE_CUSTOMER_GROUP,
            self::DEFAULT_GROUP_CODE,
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
    private function migrateMarkets($legacy, int $runId, bool $promote, array &$stats): void
    {
        foreach ($legacy->table('markets')->get() as $row) {
            $legacyMarket = (array) $row;
            $legacyId = (int) $legacyMarket['id'];
            $customerCount = (int) $legacy->table('customers')->where('market_id', $legacyId)->count();

            if ($customerCount === 0) {
                $stats['marketeer']['markets']['SKIP_UNUSED']++;
                $this->stageMarket($runId, $legacyId, null, 'skipped_unused');

                continue;
            }

            $existingMap = $this->maps->find(MigrationEntityMapRepository::TYPE_MARKET, (string) $legacyId);
            if ($existingMap) {
                $stats['marketeer']['markets']['MATCHED_EXISTING']++;
                $this->stageMarket($runId, $legacyId, (int) $existingMap->target_id, 'matched_existing');

                continue;
            }

            $match = $this->marketMatcher->matchExisting($legacyMarket);
            if ($match['market']) {
                $stats['marketeer']['markets']['MATCHED_EXISTING']++;
                $this->maps->store(
                    MigrationEntityMapRepository::TYPE_MARKET,
                    (string) $legacyId,
                    Market::class,
                    $match['market']->id,
                    $match['method'] ?? 'matched',
                    $match['confidence'],
                    null,
                    $runId
                );
                $this->stageMarket($runId, $legacyId, $match['market']->id, 'matched_existing');

                continue;
            }

            if (! $promote) {
                $stats['marketeer']['markets']['WOULD_CREATE']++;
                $this->stageMarket($runId, $legacyId, null, 'would_create');

                continue;
            }

            $location = $this->defaultLocation();
            if (! $location) {
                $stats['marketeer']['markets']['MANUAL_REVIEW']++;
                $this->stageMarket($runId, $legacyId, null, 'manual_review', 'missing_province_district');

                continue;
            }

            $market = Market::create([
                'name' => $legacyMarket['name'] ?? ('Legacy Market '.$legacyId),
                'code' => $this->marketMatcher->legacyCode($legacyId),
                'address_line1' => $legacyMarket['location'] ?? $legacyMarket['description'] ?? 'Legacy market location',
                'city' => 'Lusaka',
                'province_id' => $location['province_id'],
                'district_id' => $location['district_id'],
                'contact_person_name' => $legacyMarket['contact_person'] ?? 'Market Manager',
                'contact_person_phone' => $legacyMarket['contact_number'] ?? '260000000000',
                'contact_person_email' => $legacyMarket['email'] ?? null,
                'is_active' => (bool) ($legacyMarket['is_active'] ?? true),
            ]);

            $stats['marketeer']['markets']['CREATED']++;
            $this->maps->store(
                MigrationEntityMapRepository::TYPE_MARKET,
                (string) $legacyId,
                Market::class,
                $market->id,
                'created',
                'HIGH',
                null,
                $runId,
                ['legacy_weekly_rate' => $legacyMarket['weekly_rate'] ?? null]
            );
            $this->maps->trackCreated($runId, Market::class, $market->id);
            $this->stageMarket($runId, $legacyId, $market->id, 'created');
        }
    }

    /**
     * @return array{province_id: int, district_id: int}|null
     */
    private function defaultLocation(): ?array
    {
        $province = Province::query()->where('code', 'LUSA')->first()
            ?? Province::query()->where('name', 'Lusaka')->first();
        if (! $province) {
            return null;
        }

        $district = District::query()
            ->where('province_id', $province->id)
            ->where('code', 'LUSA-LUSA')
            ->first()
            ?? District::query()->where('province_id', $province->id)->first();

        if (! $district) {
            return null;
        }

        return ['province_id' => $province->id, 'district_id' => $district->id];
    }

    private function stageMarket(int $runId, int $legacyId, ?int $mappedId, string $status, ?string $exception = null): void
    {
        if (! DB::getSchemaBuilder()->hasTable('migration_markets')) {
            return;
        }

        DB::table('migration_markets')->updateOrInsert(
            ['migration_run_id' => $runId, 'legacy_market_id' => $legacyId],
            [
                'mapped_market_id' => $mappedId,
                'migration_status' => $status,
                'exception' => $exception,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}
