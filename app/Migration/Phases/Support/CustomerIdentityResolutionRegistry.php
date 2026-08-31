<?php

namespace App\Migration\Phases\Support;

/**
 * Approved duplicate-NRC resolutions from forensic audit (2026-08-22).
 * Legacy DB is never modified — mappings only.
 *
 * User-approved resolutions are stored in migration_identity_resolutions
 * and merged via IdentityResolutionCatalog.
 */
class CustomerIdentityResolutionRegistry
{
    public const CLASS_SAME_PERSON_MAP_ONE = 'SAME_PERSON_KEEP_SEPARATE_HISTORY_MAP_ONE_TARGET';

    public const CLASS_DISTINCT_MANUAL = 'POSSIBLE_DUPLICATE_MANUAL_REVIEW';

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function bootstrapApproved(): array
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
            '553364/10/1' => [
                'classification' => self::CLASS_SAME_PERSON_MAP_ONE,
                'primary_legacy_user_id' => 192,
                'alias_legacy_user_ids' => [838],
                'target_customer_id' => null,
                'reason' => 'Same NRC and name (Samuel Kalunga). User 192 is the older account (56 loans, client 7). User 838 is a later duplicate (26 loans, client 8).',
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function approved(): array
    {
        return IdentityResolutionCatalog::approved();
    }

    public static function primaryUserId(int $legacyUserId): ?int
    {
        return IdentityResolutionCatalog::primaryUserId($legacyUserId);
    }

    public static function isAlias(int $legacyUserId): bool
    {
        return IdentityResolutionCatalog::isAlias($legacyUserId);
    }

    public static function aliasLegacyUserCount(): int
    {
        return IdentityResolutionCatalog::aliasLegacyUserCount();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function forNrc(string $nrc): ?array
    {
        return IdentityResolutionCatalog::forNrc($nrc);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function forUser(int $legacyUserId): ?array
    {
        return IdentityResolutionCatalog::forUser($legacyUserId);
    }
}
