<?php

namespace App\Migration\Phases\Support;

use App\Migration\Phases\MigrationEntityMapRepository;
use App\Models\Admin;
use App\Models\Branch;

class CustomerBranchResolver
{
    public function __construct(
        private readonly MigrationEntityMapRepository $maps,
        private readonly LegacyRelationshipManagerResolver $relationshipManagerResolver,
        private readonly ReferenceMatcher $referenceMatcher,
    ) {}

    /**
     * @param  array<string, mixed>  $legacyCustomer
     * @param  array<string, mixed>|null  $legacyClient
     * @return array{branch_id: int|null, legacy_relationship_manager_id: int|null, resolution: string}
     */
    public function resolve(array $legacyCustomer, ?array $legacyClient): array
    {
        $legacyRmId = $this->relationshipManagerResolver->resolveLegacyRelationshipManagerId($legacyCustomer, $legacyClient);
        if ($legacyRmId) {
            $mappedAdminId = $this->maps->targetId(
                MigrationEntityMapRepository::TYPE_RELATIONSHIP_MANAGER,
                (string) $legacyRmId
            );
            if ($mappedAdminId) {
                $admin = Admin::query()->find($mappedAdminId);
                if ($admin?->branch_id) {
                    return [
                        'branch_id' => (int) $admin->branch_id,
                        'legacy_relationship_manager_id' => $legacyRmId,
                        'resolution' => 'relationship_manager_admin_branch',
                    ];
                }
            }

            $legacyRm = $this->legacyRelationshipManagerRow($legacyRmId);
            if ($legacyRm) {
                $legacyBranchId = $legacyRm['branch_id'] ?? null;
                if ($legacyBranchId) {
                    $mappedBranchId = $this->maps->targetId(
                        MigrationEntityMapRepository::TYPE_BRANCH,
                        (string) $legacyBranchId
                    );
                    if ($mappedBranchId) {
                        return [
                            'branch_id' => $mappedBranchId,
                            'legacy_relationship_manager_id' => $legacyRmId,
                            'resolution' => 'relationship_manager_legacy_branch_map',
                        ];
                    }
                }

                $inferredCode = $this->referenceMatcher->inferBranchCodeFromAddress($legacyRm['address'] ?? null);
                if ($inferredCode) {
                    $branchId = Branch::query()->where('code', $inferredCode)->value('id')
                        ?? $this->maps->targetId(MigrationEntityMapRepository::TYPE_BRANCH, 'inferred:'.$inferredCode);
                    if ($branchId) {
                        return [
                            'branch_id' => (int) $branchId,
                            'legacy_relationship_manager_id' => $legacyRmId,
                            'resolution' => 'relationship_manager_address_inference',
                        ];
                    }
                }
            }
        }

        $fallback = Branch::query()->where('code', 'HEAD_OFFICE')->value('id')
            ?? Branch::query()->where('is_active', true)->orderBy('id')->value('id');

        return [
            'branch_id' => $fallback ? (int) $fallback : null,
            'legacy_relationship_manager_id' => $legacyRmId,
            'resolution' => $legacyRmId ? 'head_office_fallback_with_rm' : 'head_office_fallback',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function legacyRelationshipManagerRow(int $legacyRmId): ?array
    {
        try {
            $row = \App\Migration\LegacyConnection::connection()
                ->table('relationship_managers')
                ->where('id', $legacyRmId)
                ->first();
        } catch (\Throwable) {
            return null;
        }

        return $row ? (array) $row : null;
    }
}
