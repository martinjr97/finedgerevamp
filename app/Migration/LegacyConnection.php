<?php

namespace App\Migration;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LegacyConnection
{
    public static function configureFromLegacyEnvFile(?string $path = null): void
    {
        $path ??= '/var/www/personal/finedge/.env';
        if (! is_readable($path)) {
            return;
        }

        $map = [
            'LEGACY_DB_HOST' => 'DB_HOST',
            'LEGACY_DB_PORT' => 'DB_PORT',
            'LEGACY_DB_DATABASE' => 'DB_DATABASE',
            'LEGACY_DB_USERNAME' => 'DB_USERNAME',
            'LEGACY_DB_PASSWORD' => 'DB_PASSWORD',
        ];

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $values = [];
        foreach ($lines as $line) {
            if (! str_contains($line, '=') || str_starts_with(trim($line), '#')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $values[trim($key)] = trim($value, " \t\"'");
        }

        foreach ($map as $target => $source) {
            if (empty(env($target)) && isset($values[$source])) {
                Config::set('database.connections.legacy.'.strtolower(str_replace('LEGACY_DB_', '', $target)), $values[$source]);
            }
        }

        if (empty(env('LEGACY_DB_PASSWORD')) && isset($values['DB_PASSWORD'])) {
            Config::set('database.connections.legacy.password', $values['DB_PASSWORD']);
        }
    }

    public static function connection()
    {
        self::configureFromLegacyEnvFile();

        return DB::connection('legacy');
    }

    public static function assertReadOnly(string $sql): void
    {
        $normalized = ltrim(strtoupper($sql));
        $forbidden = ['INSERT', 'UPDATE', 'DELETE', 'TRUNCATE', 'ALTER', 'DROP', 'CREATE', 'REPLACE', 'GRANT', 'REVOKE'];
        foreach ($forbidden as $prefix) {
            if (str_starts_with($normalized, $prefix)) {
                throw new RuntimeException('Legacy database access is read-only.');
            }
        }
    }
}
