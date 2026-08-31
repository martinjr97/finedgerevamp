<?php

namespace App\Migration\Dashboard;

class MigrationDashboardSupport
{
    public static function environmentLabel(): string
    {
        return strtoupper((string) config('app.env', 'local'));
    }

    public static function isProductionEnvironment(): bool
    {
        return app()->environment('production');
    }

    public static function maskNrc(?string $nrc): ?string
    {
        if ($nrc === null || trim($nrc) === '') {
            return null;
        }

        if (! str_contains($nrc, '/')) {
            return '******';
        }

        $parts = explode('/', $nrc);

        return '*******/'.($parts[1] ?? '**').'/'.($parts[2] ?? '*');
    }

    public static function maskPhone(?string $phone): ?string
    {
        if ($phone === null || strlen($phone) < 6) {
            return $phone;
        }

        return substr($phone, 0, 6).'****';
    }

    /**
     * @param  array<string, mixed>|null  $summary
     * @return array{read: int, created: int, matched: int, skipped: int, manual: int, failed: int}
     */
    public static function extractRunCounts(?array $summary): array
    {
        $summary ??= [];

        return [
            'read' => (int) ($summary['read'] ?? $summary['legacy_users_total'] ?? $summary['loans_scanned'] ?? 0),
            'created' => self::sumNestedCounts($summary, ['created', 'CREATED', 'would_create', 'WOULD_CREATE']),
            'matched' => self::sumNestedCounts($summary, ['matched_existing', 'MATCHED_EXISTING', 'matched']),
            'skipped' => self::sumNestedCounts($summary, ['skipped', 'SKIPPED', 'SKIP_UNUSED']),
            'manual' => self::sumNestedCounts($summary, ['manual_review', 'MANUAL_REVIEW']),
            'failed' => (int) ($summary['failed'] ?? $summary['FAIL'] ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  list<string>  $keys
     */
    private static function sumNestedCounts(array $summary, array $keys): int
    {
        $total = 0;

        foreach ($summary as $key => $value) {
            if (is_array($value)) {
                foreach ($keys as $needle) {
                    if (isset($value[$needle]) && is_numeric($value[$needle])) {
                        $total += (int) $value[$needle];
                    }
                }
            } elseif (in_array((string) $key, $keys, true) && is_numeric($value)) {
                $total += (int) $value;
            }
        }

        return $total;
    }

    public static function formatZmw(?float $amount): string
    {
        return 'ZMW '.number_format((float) ($amount ?? 0), 2);
    }

    /**
     * @return array{title: string, description: string, guidance: string}
     */
    public static function customerExceptionMeta(?string $exception): array
    {
        $catalog = [
            'national_id' => [
                'title' => 'Possible duplicate — national ID',
                'description' => 'More than one revamp customer shares this NRC. The system cannot pick a unique match automatically.',
                'guidance' => 'Compare names and loan history. If same person, resolve under Identity (merge) or map to the correct target customer. If different people sharing an NRC, use keep-separate resolution.',
            ],
            'uncertain_national_id' => [
                'title' => 'Uncertain match — national ID',
                'description' => 'A single revamp customer matches this NRC but confidence was not high enough to auto-link.',
                'guidance' => 'Verify the candidate below is the same person, then add an entity map or re-run promotion after confirming.',
            ],
            'email' => [
                'title' => 'Possible duplicate — email address',
                'description' => 'A revamp customer already uses this email. Email matches are treated as ambiguous because legacy data often reuses addresses.',
                'guidance' => 'Compare full name, NRC, and employee number with the candidate below. If they are different people, keep separate (do not merge). If same person, map legacy user to the existing customer.',
            ],
            'uncertain_email' => [
                'title' => 'Uncertain match — email address',
                'description' => 'An email match was found but confidence was medium — promotion was held for verification.',
                'guidance' => 'Confirm whether the candidate customer is the same person before mapping.',
            ],
            'missing_user_row' => [
                'title' => 'Broken legacy relation',
                'description' => 'The legacy customer row references a user ID that no longer exists in the legacy users table.',
                'guidance' => 'Inspect legacy data integrity. This record may need exclusion or a manual legacy fix before migration.',
            ],
            'missing_product' => [
                'title' => 'Missing loan product',
                'description' => 'The resolved product code does not exist in the revamp loan_products table.',
                'guidance' => 'Run reference-data migration or create the missing product, then re-run customer promotion for this user.',
            ],
            'marketeer_missing_market' => [
                'title' => 'Marketeer without market mapping',
                'description' => 'This MARK-001 customer has no mapped target market.',
                'guidance' => 'Complete marketeer / market reference migration, then re-run customer promotion.',
            ],
            'identity_alias_pending_primary' => [
                'title' => 'Identity alias waiting for primary',
                'description' => 'This legacy user is an alias of another user; the primary user must be migrated first.',
                'guidance' => 'Ensure the primary legacy user in the duplicate NRC group is promoted, or resolve the group on the Identity tab.',
            ],
        ];

        if ($exception !== null && isset($catalog[$exception])) {
            return $catalog[$exception];
        }

        if ($exception !== null && str_starts_with($exception, 'uncertain_')) {
            return [
                'title' => 'Uncertain match — '.str_replace('_', ' ', substr($exception, 9)),
                'description' => 'An automatic match was found but did not meet confidence rules for promotion.',
                'guidance' => 'Review candidate matches and confirm the correct target customer before mapping.',
            ];
        }

        return [
            'title' => $exception ? 'Manual review — '.$exception : 'Manual review',
            'description' => 'This customer was flagged during migration and was not promoted automatically.',
            'guidance' => 'Review legacy and target identity fields below, then apply the appropriate identity resolution or entity map.',
        ];
    }

    public static function customerExceptionLabel(?string $exception): string
    {
        return self::customerExceptionMeta($exception)['title'];
    }
}
