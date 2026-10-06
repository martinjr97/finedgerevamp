<?php

namespace App\Migration\ParallelRun;

use Illuminate\Support\Facades\DB;

class MigrationSyncState
{
    public const KEY_LAST_LOAN_POLL_AT = 'last_loan_poll_at';

    public const KEY_LAST_REPAYMENT_POLL_AT = 'last_repayment_poll_at';

    public const KEY_LAST_REPAYMENT_SYNC_AT = 'last_repayment_sync_at';

    public function get(string $key): ?string
    {
        $row = DB::table('migration_sync_state')->where('key', $key)->first();

        return $row?->value;
    }

    public function put(string $key, ?string $value): void
    {
        DB::table('migration_sync_state')->updateOrInsert(
            ['key' => $key],
            ['value' => $value, 'updated_at' => now()]
        );
    }

    public function getTimestamp(string $key): ?\Carbon\Carbon
    {
        $value = $this->get($key);

        return $value ? \Carbon\Carbon::parse($value) : null;
    }

    public function touchNow(string $key): void
    {
        $this->put($key, now()->toIso8601String());
    }
}
