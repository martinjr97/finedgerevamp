<?php

namespace App\Migration\Phases;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MigrationRunManager
{
    public function start(string $phase, string $scope, ?string $runUuid = null): array
    {
        $uuid = $runUuid ?: (string) Str::uuid();

        $existing = DB::table('migration_runs')->where('run_uuid', $uuid)->first();
        if ($existing) {
            return ['id' => (int) $existing->id, 'run_uuid' => $uuid, 'reused' => true];
        }

        $id = DB::table('migration_runs')->insertGetId([
            'run_uuid' => $uuid,
            'name' => "{$phase}-{$scope}-".now()->format('Ymd-His'),
            'phase' => $phase,
            'scope' => $scope,
            'status' => 'running',
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['id' => $id, 'run_uuid' => $uuid, 'reused' => false];
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    public function complete(int $runId, array $summary, string $status = 'completed'): void
    {
        DB::table('migration_runs')->where('id', $runId)->update([
            'status' => $status,
            'summary' => json_encode($summary),
            'completed_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function findByUuid(string $runUuid): ?object
    {
        return DB::table('migration_runs')->where('run_uuid', $runUuid)->first();
    }

    public function resolveRunId(?string $runUuid): ?int
    {
        if (! $runUuid) {
            return null;
        }

        return DB::table('migration_runs')->where('run_uuid', $runUuid)->value('id');
    }
}
