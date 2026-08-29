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
}
