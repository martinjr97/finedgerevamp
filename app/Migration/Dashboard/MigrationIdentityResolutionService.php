<?php

namespace App\Migration\Dashboard;

use App\Migration\LegacyConnection;
use App\Migration\Phases\Support\CustomerIdentityResolver;
use App\Migration\Phases\Support\IdentityResolutionCatalog;
use App\Models\MigrationIdentityResolution;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MigrationIdentityResolutionService
{
    public function __construct(
        private readonly CustomerIdentityResolver $identityResolver,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function pendingDuplicateGroups(): array
    {
        $groups = [];

        foreach ($this->legacyDuplicateGroups() as $nrc => $members) {
            if (IdentityResolutionCatalog::forNrc($nrc)) {
                continue;
            }

            $groups[] = [
                'nrc' => $nrc,
                'nrc_masked' => MigrationDashboardSupport::maskNrc($nrc),
                'nrc_key' => IdentityResolutionCatalog::encodeNrcKey($nrc),
                'legacy_user_ids' => $members->pluck('user_id')->map(fn ($id) => (int) $id)->all(),
                'members' => $members->map(fn ($row) => $this->formatLegacyMember($row))->all(),
            ];
        }

        return $groups;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function pendingGroupByNrcKey(string $nrcKey): ?array
    {
        $nrc = IdentityResolutionCatalog::decodeNrcKey($nrcKey);
        if ($nrc === '') {
            return null;
        }

        $members = $this->legacyDuplicateGroups()->get($nrc);
        if (! $members || $members->count() < 2) {
            return null;
        }

        if (IdentityResolutionCatalog::forNrc($nrc)) {
            return null;
        }

        return [
            'nrc' => $nrc,
            'nrc_masked' => MigrationDashboardSupport::maskNrc($nrc),
            'nrc_key' => $nrcKey,
            'legacy_user_ids' => $members->pluck('user_id')->map(fn ($id) => (int) $id)->all(),
            'members' => $members->map(fn ($row) => $this->formatLegacyMember($row))->all(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function approvedResolutions(): array
    {
        $rows = [];

        foreach (IdentityResolutionCatalog::approved() as $nrc => $resolution) {
            $legacyUsers = array_values(array_unique(array_merge(
                [(int) $resolution['primary_legacy_user_id']],
                $resolution['alias_legacy_user_ids'] ?? [],
                $resolution['excluded_legacy_user_ids'] ?? [],
            )));

            $rows[] = [
                'nrc' => MigrationDashboardSupport::maskNrc($nrc),
                'nrc_full' => $nrc,
                'legacy_users' => $legacyUsers,
                'primary_legacy_user_id' => (int) $resolution['primary_legacy_user_id'],
                'alias_legacy_user_ids' => $resolution['alias_legacy_user_ids'] ?? [],
                'excluded_legacy_user_ids' => $resolution['excluded_legacy_user_ids'] ?? [],
                'target_customer_id' => $resolution['target_customer_id'] ?? null,
                'classification' => $resolution['classification'],
                'classification_label' => $this->classificationLabel($resolution['classification']),
                'reason' => $resolution['reason'] ?? '',
                'source' => $resolution['source'] ?? 'bootstrap',
                'status' => $resolution['status'] ?? MigrationIdentityResolution::STATUS_APPROVED,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function storeResolution(array $input, int $adminId): array
    {
        $group = $this->pendingGroupByNrcKey((string) $input['nrc_key']);
        if (! $group) {
            throw ValidationException::withMessages([
                'nrc_key' => 'This duplicate NRC group is no longer pending or could not be found.',
            ]);
        }

        $classification = (string) $input['classification'];
        $allowed = [
            MigrationIdentityResolution::CLASS_SAME_PERSON_MAP_ONE,
            MigrationIdentityResolution::CLASS_KEEP_SEPARATE,
            MigrationIdentityResolution::CLASS_EXCLUDE,
        ];
        if (! in_array($classification, $allowed, true)) {
            throw ValidationException::withMessages(['classification' => 'Invalid resolution type.']);
        }

        $memberIds = $group['legacy_user_ids'];
        $primaryId = (int) ($input['primary_legacy_user_id'] ?? 0);

        if ($classification === MigrationIdentityResolution::CLASS_EXCLUDE) {
            $excluded = array_map('intval', $memberIds);
            $primaryId = $primaryId > 0 ? $primaryId : $excluded[0];
            $aliases = [];
        } elseif ($classification === MigrationIdentityResolution::CLASS_KEEP_SEPARATE) {
            $primaryId = $memberIds[0];
            $aliases = [];
            $excluded = [];
        } else {
            if ($primaryId <= 0 || ! in_array($primaryId, $memberIds, true)) {
                throw ValidationException::withMessages(['primary_legacy_user_id' => 'Select a primary legacy user from this group.']);
            }
            $aliases = array_values(array_diff($memberIds, [$primaryId]));
            $excluded = [];
        }

        $targetCustomerId = isset($input['target_customer_id']) && $input['target_customer_id'] !== ''
            ? (int) $input['target_customer_id']
            : null;

        $resolution = MigrationIdentityResolution::query()->updateOrCreate(
            ['nrc' => $group['nrc']],
            [
                'primary_legacy_user_id' => $primaryId,
                'alias_legacy_user_ids' => $aliases,
                'excluded_legacy_user_ids' => $classification === MigrationIdentityResolution::CLASS_EXCLUDE ? $excluded : [],
                'target_customer_id' => $targetCustomerId,
                'classification' => $classification,
                'status' => MigrationIdentityResolution::STATUS_APPROVED,
                'reason' => $input['reason'] ?? null,
                'legacy_context' => ['members' => $group['members']],
                'decided_by' => $adminId,
            ],
        );

        if ($classification === MigrationIdentityResolution::CLASS_SAME_PERSON_MAP_ONE) {
            $this->identityResolver->applyResolutionEntry($resolution->fresh()->toCatalogEntry(), $group['nrc']);
            $resolution->update(['applied_at' => now()]);
        }

        return [
            'resolution' => $resolution->fresh(),
            'duplicate_groups_resolved' => IdentityResolutionCatalog::duplicateGroupsResolved(),
            'pending_remaining' => count($this->pendingDuplicateGroups()),
        ];
    }

    public function summary(): array
    {
        $duplicateCount = IdentityResolutionCatalog::duplicateNrcKeys()->count();
        $pending = count($this->pendingDuplicateGroups());
        $approved = count(IdentityResolutionCatalog::approved());

        return [
            'duplicate_nrc_groups' => $duplicateCount,
            'pending_groups' => $pending,
            'approved_resolutions' => $approved,
            'ready_for_customer_promote' => IdentityResolutionCatalog::duplicateGroupsResolved(),
        ];
    }

    /**
     * @return Collection<string, Collection<int, object>>
     */
    private function legacyDuplicateGroups(): Collection
    {
        LegacyConnection::configureFromLegacyEnvFile();
        $legacy = LegacyConnection::connection();

        $customers = $legacy->table('customers')
            ->whereNotNull('nrc')
            ->where('nrc', '!=', '')
            ->get();

        $userIds = $customers->pluck('user_id')->unique()->filter()->all();
        $users = $userIds === []
            ? collect()
            : $legacy->table('users')->whereIn('id', $userIds)->get()->keyBy('id');

        $loanCounts = $userIds === []
            ? collect()
            : $legacy->table('loans')
                ->whereIn('user_id', $userIds)
                ->select('user_id', DB::raw('COUNT(*) as loan_count'))
                ->groupBy('user_id')
                ->pluck('loan_count', 'user_id');

        return $customers
            ->map(function ($row) use ($users, $loanCounts) {
                $user = $users->get($row->user_id);

                return (object) [
                    'user_id' => (int) $row->user_id,
                    'nrc' => (string) $row->nrc,
                    'first_name' => $user->fname ?? $row->first_name ?? '',
                    'last_name' => $user->lname ?? $row->last_name ?? '',
                    'email' => $user->email ?? null,
                    'status_code' => $user->status ?? null,
                    'loan_count' => (int) ($loanCounts[$row->user_id] ?? 0),
                ];
            })
            ->groupBy('nrc')
            ->filter(fn ($group) => $group->count() > 1);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatLegacyMember(object $row): array
    {
        return [
            'legacy_user_id' => (int) $row->user_id,
            'name' => trim(($row->first_name ?? '').' '.($row->last_name ?? '')),
            'email' => $row->email ?? null,
            'status_code' => $row->status_code ?? null,
            'loan_count' => (int) ($row->loan_count ?? 0),
        ];
    }

    private function classificationLabel(string $classification): string
    {
        return match ($classification) {
            MigrationIdentityResolution::CLASS_SAME_PERSON_MAP_ONE => 'Merge — same person, one target customer',
            MigrationIdentityResolution::CLASS_KEEP_SEPARATE => 'Keep separate — distinct customers',
            MigrationIdentityResolution::CLASS_EXCLUDE => 'Exclude — do not migrate these legacy users',
            default => $classification,
        };
    }
}
