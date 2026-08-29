<?php

namespace App\Migration\Phases;

use App\Migration\Phases\Support\ReferenceMatcher;
use App\Models\Admin;
use App\Models\Branch;
use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BranchRelationshipManagerReferenceMigrator
{
    public function __construct(
        private readonly MigrationEntityMapRepository $maps,
        private readonly ReferenceMatcher $referenceMatcher,
    ) {}

    /**
     * @param  array<string, mixed>  $stats
     */
    public function migrate($legacy, int $runId, bool $promote, array &$stats): void
    {
        $this->migrateBranches($legacy, $runId, $promote, $stats);
        $this->migrateRelationshipManagers($legacy, $runId, $promote, $stats);
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function migrateBranches($legacy, int $runId, bool $promote, array &$stats): void
    {
        foreach ($this->referenceMatcher->legacyBranchCatalog($legacy) as $legacyBranch) {
            $legacyId = (string) $legacyBranch['id'];
            $existingMap = $this->maps->find(MigrationEntityMapRepository::TYPE_BRANCH, $legacyId);
            if ($existingMap) {
                $stats['branches']['MATCHED_EXISTING']++;
                $this->stageBranch($runId, $legacyId, (int) $existingMap->target_id, 'matched_existing', 'HIGH', null, $legacyBranch);

                continue;
            }

            $result = $this->referenceMatcher->resolveBranch($legacyBranch);
            if ($result->isConflict()) {
                $stats['branches']['MANUAL_REVIEW']++;
                $this->stageBranch(
                    $runId,
                    $legacyId,
                    null,
                    'manual_review',
                    'LOW',
                    $result->reason,
                    $legacyBranch,
                    $result->candidateTargetIds
                );

                continue;
            }

            if ($result->isMatched() && $result->target instanceof Branch) {
                $stats['branches']['MATCHED_EXISTING']++;
                $this->maps->store(
                    MigrationEntityMapRepository::TYPE_BRANCH,
                    $legacyId,
                    Branch::class,
                    $result->target->id,
                    $result->method,
                    'HIGH',
                    null,
                    $runId,
                    ['legacy_code' => $legacyBranch['code'] ?? null]
                );
                $this->stageBranch($runId, $legacyId, $result->target->id, 'matched_existing', 'HIGH', null, $legacyBranch);

                continue;
            }

            if (! $promote) {
                $stats['branches']['WOULD_CREATE']++;
                $this->stageBranch($runId, $legacyId, null, 'would_create', 'MEDIUM', null, $legacyBranch);

                continue;
            }

            $code = strtoupper((string) ($legacyBranch['code'] ?? 'LEG-B'.$legacyId));
            if (Branch::query()->where('code', $code)->exists()) {
                $stats['branches']['MANUAL_REVIEW']++;
                $this->stageBranch(
                    $runId,
                    $legacyId,
                    null,
                    'manual_review',
                    'LOW',
                    'target_code_exists_without_map',
                    $legacyBranch,
                    Branch::query()->where('code', $code)->pluck('id')->all()
                );

                continue;
            }

            $branch = Branch::create([
                'name' => $legacyBranch['name'] ?? ('Legacy Branch '.$legacyId),
                'code' => $code,
                'is_active' => true,
            ]);
            $stats['branches']['CREATED']++;
            $this->maps->store(
                MigrationEntityMapRepository::TYPE_BRANCH,
                $legacyId,
                Branch::class,
                $branch->id,
                'created',
                'HIGH',
                null,
                $runId,
                ['legacy_code' => $code, 'inferred' => (bool) ($legacyBranch['inferred'] ?? false)]
            );
            $this->maps->trackCreated($runId, Branch::class, $branch->id);
            $this->stageBranch($runId, $legacyId, $branch->id, 'created', 'HIGH', null, $legacyBranch);
        }
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function migrateRelationshipManagers($legacy, int $runId, bool $promote, array &$stats): void
    {
        try {
            $rows = $legacy->table('relationship_managers')->get();
        } catch (\Throwable) {
            return;
        }

        $operatorCompanyId = Company::query()->where('type', 'operator')->value('id')
            ?? Company::query()->value('id');

        foreach ($rows as $row) {
            $legacyRm = (array) $row;
            $legacyRmId = (int) ($legacyRm['id'] ?? 0);
            if ($legacyRmId <= 0) {
                continue;
            }

            $existingMap = $this->maps->find(MigrationEntityMapRepository::TYPE_RELATIONSHIP_MANAGER, (string) $legacyRmId);
            if ($existingMap) {
                $stats['relationship_managers']['MATCHED_EXISTING']++;
                $this->stageRelationshipManager($runId, $legacyRmId, (int) $existingMap->target_id, null, 'matched_existing', 'HIGH', null, $legacyRm);

                continue;
            }

            $result = $this->referenceMatcher->resolveRelationshipManagerAdmin($legacyRm);
            if ($result->isConflict()) {
                $stats['relationship_managers']['MANUAL_REVIEW']++;
                $this->stageRelationshipManager(
                    $runId,
                    $legacyRmId,
                    null,
                    null,
                    'manual_review',
                    'LOW',
                    $result->reason,
                    $legacyRm,
                    $result->candidateTargetIds
                );

                continue;
            }

            $targetBranchId = $this->resolveMappedBranchId($legacyRm, $runId);

            if ($result->isMatched() && $result->target instanceof Admin) {
                $stats['relationship_managers']['MATCHED_EXISTING']++;
                if ($promote && $targetBranchId && ! $result->target->branch_id) {
                    $result->target->update(['branch_id' => $targetBranchId, 'is_relationship_manager' => true]);
                }
                $this->maps->store(
                    MigrationEntityMapRepository::TYPE_RELATIONSHIP_MANAGER,
                    (string) $legacyRmId,
                    Admin::class,
                    $result->target->id,
                    $result->method,
                    'HIGH',
                    null,
                    $runId
                );
                $this->stageRelationshipManager($runId, $legacyRmId, $result->target->id, $targetBranchId, 'matched_existing', 'HIGH', null, $legacyRm);

                continue;
            }

            if (! $promote) {
                $stats['relationship_managers']['WOULD_CREATE']++;
                $this->stageRelationshipManager($runId, $legacyRmId, null, $targetBranchId, 'would_create', 'MEDIUM', null, $legacyRm);

                continue;
            }

            $email = trim((string) ($legacyRm['email'] ?? ''));
            if ($email === '') {
                $email = 'legacy-rm-'.$legacyRmId.'@migration.local';
            } elseif (Admin::query()->where('email', $email)->exists()) {
                $email = 'legacy-rm-'.$legacyRmId.'@migration.local';
            }

            $admin = Admin::create([
                'company_id' => $operatorCompanyId,
                'branch_id' => $targetBranchId,
                'first_name' => $legacyRm['first_name'] ?? 'Legacy',
                'last_name' => $legacyRm['last_name'] ?? 'RM',
                'email' => $email,
                'password' => bcrypt('Migration!'.Str::random(12)),
                'phone' => $legacyRm['phone_number'] ?? null,
                'nrc' => $legacyRm['nrc'] ?? null,
                'is_active' => true,
                'is_relationship_manager' => true,
                'approval_status' => 'approved',
            ]);

            $stats['relationship_managers']['CREATED']++;
            $this->maps->store(
                MigrationEntityMapRepository::TYPE_RELATIONSHIP_MANAGER,
                (string) $legacyRmId,
                Admin::class,
                $admin->id,
                'created',
                'HIGH',
                null,
                $runId
            );
            $this->maps->trackCreated($runId, Admin::class, $admin->id);
            $this->stageRelationshipManager($runId, $legacyRmId, $admin->id, $targetBranchId, 'created', 'HIGH', null, $legacyRm);
        }
    }

    /**
     * @param  array<string, mixed>  $legacyRm
     */
    private function resolveMappedBranchId(array $legacyRm, int $runId): ?int
    {
        $legacyBranchId = $legacyRm['branch_id'] ?? null;
        if ($legacyBranchId) {
            $mapped = $this->maps->targetId(MigrationEntityMapRepository::TYPE_BRANCH, (string) $legacyBranchId);
            if ($mapped) {
                return $mapped;
            }
        }

        $inferredCode = $this->referenceMatcher->inferBranchCodeFromAddress($legacyRm['address'] ?? null);
        if ($inferredCode) {
            $branch = Branch::query()->where('code', $inferredCode)->first();
            if ($branch) {
                return $branch->id;
            }

            $mappedInferred = $this->maps->targetId(MigrationEntityMapRepository::TYPE_BRANCH, 'inferred:'.$inferredCode);
            if ($mappedInferred) {
                return $mappedInferred;
            }
        }

        return Branch::query()->where('code', 'HEAD_OFFICE')->value('id')
            ?? Branch::query()->where('is_active', true)->orderBy('id')->value('id');
    }

    /**
     * @param  array<string, mixed>  $legacyBranch
     * @param  list<int>  $candidateTargetIds
     */
    private function stageBranch(
        int $runId,
        string $legacyId,
        ?int $mappedBranchId,
        string $status,
        string $confidence,
        ?string $exception,
        array $legacyBranch,
        array $candidateTargetIds = [],
    ): void {
        DB::table('migration_branches')->updateOrInsert(
            ['migration_run_id' => $runId, 'legacy_identifier' => $legacyId],
            [
                'mapped_branch_id' => $mappedBranchId,
                'migration_status' => $status,
                'confidence' => $confidence,
                'exception' => $exception,
                'candidate_target_ids' => $candidateTargetIds === [] ? null : json_encode($candidateTargetIds),
                'raw_context' => json_encode($legacyBranch),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $legacyRm
     * @param  list<int>  $candidateTargetIds
     */
    private function stageRelationshipManager(
        int $runId,
        int $legacyRmId,
        ?int $mappedAdminId,
        ?int $mappedBranchId,
        string $status,
        string $confidence,
        ?string $exception,
        array $legacyRm,
        array $candidateTargetIds = [],
    ): void {
        DB::table('migration_relationship_managers')->updateOrInsert(
            ['migration_run_id' => $runId, 'legacy_relationship_manager_id' => $legacyRmId],
            [
                'mapped_admin_id' => $mappedAdminId,
                'mapped_branch_id' => $mappedBranchId,
                'migration_status' => $status,
                'confidence' => $confidence,
                'exception' => $exception,
                'candidate_target_ids' => $candidateTargetIds === [] ? null : json_encode($candidateTargetIds),
                'raw_context' => json_encode($legacyRm),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}
