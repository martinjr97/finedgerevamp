<?php

namespace App\Migration\Phases;

use App\Models\LoanProduct;
use RuntimeException;

class MigrationDependencyGate
{
    public function __construct(
        private readonly MigrationEntityMapRepository $maps,
    ) {}

    public function requireProductsMapped(): void
    {
        foreach (['MOU-001', 'GOV-001', 'CHAR-001', 'MARK-001'] as $code) {
            $existing = LoanProduct::query()->where('code', $code)->first();
            if ($existing && ! $this->maps->find(MigrationEntityMapRepository::TYPE_PRODUCT, $code)) {
                $this->maps->store(
                    MigrationEntityMapRepository::TYPE_PRODUCT,
                    $code,
                    LoanProduct::class,
                    $existing->id,
                    'existing_target_auto',
                    'HIGH'
                );
            }

            if (! $this->maps->find(MigrationEntityMapRepository::TYPE_PRODUCT, $code) && ! $existing) {
                throw new RuntimeException(
                    "Missing product {$code}. Run migration:reference-data first."
                );
            }
        }
    }

    public function requireCustomerMapped(int $legacyUserId): int
    {
        $targetId = $this->maps->targetId(
            MigrationEntityMapRepository::TYPE_CUSTOMER,
            (string) $legacyUserId
        );

        if (! $targetId) {
            throw new RuntimeException(
                "No customer mapping for legacy user {$legacyUserId}. Run migration:customers first."
            );
        }

        return $targetId;
    }

    public function requireLoanMapped(int $legacyLoanId): int
    {
        $targetId = $this->maps->targetId(
            MigrationEntityMapRepository::TYPE_LOAN,
            (string) $legacyLoanId
        );

        if (! $targetId) {
            throw new RuntimeException(
                "No loan mapping for legacy loan {$legacyLoanId}. Run migration:active-loans first."
            );
        }

        return $targetId;
    }

    public function hasAnyCustomerMaps(): bool
    {
        return \Illuminate\Support\Facades\DB::table('migration_entity_maps')
            ->where('entity_type', MigrationEntityMapRepository::TYPE_CUSTOMER)
            ->exists();
    }

    public function hasAnyLoanMaps(): bool
    {
        return \Illuminate\Support\Facades\DB::table('migration_entity_maps')
            ->where('entity_type', MigrationEntityMapRepository::TYPE_LOAN)
            ->exists();
    }
}
