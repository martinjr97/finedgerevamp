<?php

namespace App\Migration\Phases\Support;

/**
 * Approved duplicate-NRC resolutions from forensic audit (2026-08-22).
 * Legacy DB is never modified — mappings only.
 *
 * @return array<string, array<string, mixed>>
 */
class CustomerIdentityResolutionRegistry
{
    public const CLASS_SAME_PERSON_MAP_ONE = 'SAME_PERSON_KEEP_SEPARATE_HISTORY_MAP_ONE_TARGET';

    public const CLASS_DISTINCT_MANUAL = 'POSSIBLE_DUPLICATE_MANUAL_REVIEW';

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function approved(): array
    {
        return [
            '730989/11/1' => [
                'classification' => self::CLASS_SAME_PERSON_MAP_ONE,
                'primary_legacy_user_id' => 14,
                'alias_legacy_user_ids' => [19],
                'target_customer_id' => 7,
                'reason' => 'Same NRC and name (Christopher Banda). Target biodata matches user 14 (emp 0000021, email didcottcb@hotmail.com). User 19 is suspended duplicate (status 606) with separate loan/repayment history on client 7.',
            ],
            '631351/11/1' => [
                'classification' => self::CLASS_SAME_PERSON_MAP_ONE,
                'primary_legacy_user_id' => 127,
                'alias_legacy_user_ids' => [126],
                'target_customer_id' => null,
                'reason' => 'Same NRC and name (Mundia Sekeli). User 127 holds all loan/repayment history (13 loans). User 126 is empty duplicate (0 loans, blocked status 604).',
            ],
        ];
    }

    public static function primaryUserId(int $legacyUserId): ?int
    {
        foreach (self::approved() as $resolution) {
            if ((int) $resolution['primary_legacy_user_id'] === $legacyUserId) {
                return $legacyUserId;
            }
            if (in_array($legacyUserId, $resolution['alias_legacy_user_ids'] ?? [], true)) {
                return (int) $resolution['primary_legacy_user_id'];
            }
        }

        return null;
    }

    public static function isAlias(int $legacyUserId): bool
    {
        foreach (self::approved() as $resolution) {
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
            $count += count($resolution['alias_legacy_user_ids'] ?? []);
        }

        return $count;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function forNrc(string $nrc): ?array
    {
        return self::approved()[$nrc] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function forUser(int $legacyUserId): ?array
    {
        foreach (self::approved() as $nrc => $resolution) {
            if ((int) $resolution['primary_legacy_user_id'] === $legacyUserId
                || in_array($legacyUserId, $resolution['alias_legacy_user_ids'] ?? [], true)) {
                return array_merge($resolution, ['nrc' => $nrc]);
            }
        }

        return null;
    }
}
