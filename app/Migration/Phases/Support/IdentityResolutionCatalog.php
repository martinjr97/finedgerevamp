<?php

namespace App\Migration\Phases\Support;

use App\Migration\LegacyConnection;
use App\Models\MigrationIdentityResolution;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class IdentityResolutionCatalog
{
    /**
     * Built-in resolutions from the original forensic audit (bootstrap).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function bootstrapApproved(): array
    {
        return CustomerIdentityResolutionRegistry::bootstrapApproved();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function approved(): array
    {
        $merged = self::bootstrapApproved();

        MigrationIdentityResolution::query()
            ->approved()
            ->orderBy('id')
            ->get()
            ->each(function (MigrationIdentityResolution $row) use (&$merged): void {
                $merged[$row->nrc] = $row->toCatalogEntry();
            });

        return $merged;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function forNrc(string $nrc): ?array
    {
        $nrc = trim($nrc);
        if ($nrc === '') {
            return null;
        }

        $db = MigrationIdentityResolution::query()->approved()->where('nrc', $nrc)->first();
        if ($db) {
            return array_merge($db->toCatalogEntry(), ['nrc' => $nrc]);
        }

        $bootstrap = self::bootstrapApproved()[$nrc] ?? null;

        return $bootstrap ? array_merge($bootstrap, ['nrc' => $nrc, 'source' => 'bootstrap']) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function forUser(int $legacyUserId): ?array
    {
        foreach (self::approved() as $nrc => $resolution) {
            if ((int) ($resolution['primary_legacy_user_id'] ?? 0) === $legacyUserId
                || in_array($legacyUserId, $resolution['alias_legacy_user_ids'] ?? [], true)
                || in_array($legacyUserId, $resolution['excluded_legacy_user_ids'] ?? [], true)) {
                return array_merge($resolution, ['nrc' => $nrc]);
            }
        }

        return null;
    }

    public static function primaryUserId(int $legacyUserId): ?int
    {
        $resolution = self::forUser($legacyUserId);
        if (! $resolution || ! self::isMergeResolution($resolution)) {
            return null;
        }

        if (in_array($legacyUserId, $resolution['alias_legacy_user_ids'] ?? [], true)) {
            return (int) $resolution['primary_legacy_user_id'];
        }

        if ((int) ($resolution['primary_legacy_user_id'] ?? 0) === $legacyUserId) {
            return $legacyUserId;
        }

        return null;
    }

    public static function isAlias(int $legacyUserId): bool
    {
        foreach (self::approved() as $resolution) {
            if (! self::isMergeResolution($resolution)) {
                continue;
            }

            if (in_array($legacyUserId, $resolution['alias_legacy_user_ids'] ?? [], true)) {
                return true;
            }
        }

        return false;
    }

    public static function aliasLegacyUserCount(): int
    {
        $count = 0;
        foreach (self::approved() as $resolution) {
            if (($resolution['classification'] ?? '') === MigrationIdentityResolution::CLASS_SAME_PERSON_MAP_ONE) {
                $count += count($resolution['alias_legacy_user_ids'] ?? []);
            }
        }

        return $count;
    }

    public static function isExcludedFromMigration(int $legacyUserId): bool
    {
        $resolution = self::forUser($legacyUserId);
        if (! $resolution) {
            return false;
        }

        if (($resolution['classification'] ?? '') === MigrationIdentityResolution::CLASS_EXCLUDE) {
            $memberIds = array_unique(array_merge(
                [(int) $resolution['primary_legacy_user_id']],
                $resolution['alias_legacy_user_ids'] ?? [],
                $resolution['excluded_legacy_user_ids'] ?? [],
            ));

            return in_array($legacyUserId, $memberIds, true);
        }

        return in_array($legacyUserId, $resolution['excluded_legacy_user_ids'] ?? [], true);
    }

    public static function shouldMigrateAsSeparateIdentity(int $legacyUserId): bool
    {
        $resolution = self::forUser($legacyUserId);

        return $resolution
            && ($resolution['classification'] ?? '') === MigrationIdentityResolution::CLASS_KEEP_SEPARATE;
    }

    public static function isMergeResolution(?array $resolution): bool
    {
        return $resolution
            && ($resolution['classification'] ?? '') === MigrationIdentityResolution::CLASS_SAME_PERSON_MAP_ONE;
    }

    /**
     * @return Collection<int, string>
     */
    public static function duplicateNrcKeys(): Collection
    {
        LegacyConnection::configureFromLegacyEnvFile();
        $legacy = LegacyConnection::connection();

        return $legacy->table('customers')
            ->whereNotNull('nrc')
            ->where('nrc', '!=', '')
            ->get(['nrc'])
            ->groupBy('nrc')
            ->filter(fn ($group) => $group->count() > 1)
            ->keys()
            ->map(fn ($nrc) => (string) $nrc)
            ->values();
    }

    public static function duplicateGroupsResolved(): bool
    {
        foreach (self::duplicateNrcKeys() as $nrc) {
            if (! self::forNrc($nrc)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    public static function unresolvedDuplicateNrcs(): array
    {
        $unresolved = [];

        foreach (self::duplicateNrcKeys() as $nrc) {
            if (! self::forNrc($nrc)) {
                $unresolved[] = $nrc;
            }
        }

        return $unresolved;
    }

    public static function encodeNrcKey(string $nrc): string
    {
        return rtrim(strtr(base64_encode($nrc), '+/', '-_'), '=');
    }

    public static function decodeNrcKey(string $nrcKey): string
    {
        $padded = strtr($nrcKey, '-_', '+/');
        $padLength = strlen($padded) % 4;
        if ($padLength > 0) {
            $padded .= str_repeat('=', 4 - $padLength);
        }

        return (string) base64_decode($padded, true);
    }
}
