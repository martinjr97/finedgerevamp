<?php

namespace App\Migration\Phases;

use App\Migration\Phases\Support\CustomerIdentityResolver;
use App\Migration\Phases\Support\IdentityResolutionCatalog;
use RuntimeException;

class MigrationPromotionGate
{
    public function __construct(
        private readonly PrePromotionAuditService $audit,
        private readonly CustomerIdentityResolver $identityResolver,
    ) {}

    public function assertReferenceDataPromote(): void
    {
        $report = $this->audit->run();
        $gates = $report['promotion_gates'];

        if (($gates['REFERENCE_DATA'] ?? '') !== 'READY') {
            throw new RuntimeException(
                'Reference-data promotion blocked. Run migration:audit and resolve: '.
                json_encode($gates['conditions'] ?? [])
            );
        }

        $clients = $report['client_reconciliation'];
        if (! ($clients['reconciles'] ?? false)) {
            throw new RuntimeException('Client classification totals do not reconcile to legacy client count.');
        }
    }

    public function assertCustomersPromote(): void
    {
        if (! $this->identityResolver->duplicateGroupsResolved()) {
            $pending = IdentityResolutionCatalog::unresolvedDuplicateNrcs();
            $list = $pending === [] ? 'unknown' : implode(', ', $pending);

            throw new RuntimeException(
                'Customer promotion blocked: unresolved duplicate NRC group(s): '.$list.'. '.
                'Resolve each on the migration dashboard Identity tab (/legacy/migration-dashboard/identity), then run migration:identity-resolve --apply if merging to an existing customer.'
            );
        }

        $report = $this->audit->run();

        foreach ($report['existing_customer_matches']['rows'] ?? [] as $row) {
            if (($row['confidence'] ?? '') === 'MANUAL_REVIEW') {
                $map = \Illuminate\Support\Facades\DB::table('migration_entity_maps')
                    ->where('entity_type', MigrationEntityMapRepository::TYPE_CUSTOMER)
                    ->where('legacy_identifier', (string) ($row['legacy_user_id'] ?? ''))
                    ->first();
                if ($map && $this->identityResolver->isApprovedAliasMap($map)) {
                    continue;
                }
                throw new RuntimeException(
                    'Customer promotion blocked: uncertain existing customer match for legacy user '.($row['legacy_user_id'] ?? '?')
                );
            }
        }
    }

    public function assertActiveLoansPromote(): void
    {
        $customerMaps = \Illuminate\Support\Facades\DB::table('migration_entity_maps')
            ->where('entity_type', MigrationEntityMapRepository::TYPE_CUSTOMER)
            ->count();

        if ($customerMaps < 700) {
            throw new RuntimeException(
                "Active-loan promotion blocked: only {$customerMaps} customer maps. Run migration:customers --promote first."
            );
        }
    }
}
