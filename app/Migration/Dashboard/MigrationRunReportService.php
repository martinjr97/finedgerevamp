<?php

namespace App\Migration\Dashboard;

use App\Migration\Dashboard\MigrationDashboardSupport;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class MigrationRunReportService
{
    public function paginateRuns(int $perPage = 25): LengthAwarePaginator
    {
        return DB::table('migration_runs')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->through(function ($run) {
                $summary = json_decode($run->summary ?? '{}', true) ?: [];
                $counts = MigrationDashboardSupport::extractRunCounts($summary);

                return (object) [
                    'id' => $run->id,
                    'run_uuid' => $run->run_uuid,
                    'phase' => $run->phase,
                    'scope' => $run->scope,
                    'status' => $run->status,
                    'started_at' => $run->started_at,
                    'completed_at' => $run->completed_at,
                    'promote' => (bool) ($summary['promote'] ?? false),
                    'counts' => $counts,
                    'summary' => $summary,
                ];
            });
    }

    public function findRun(int $runId): ?object
    {
        $run = DB::table('migration_runs')->where('id', $runId)->first();
        if (! $run) {
            return null;
        }

        $summary = json_decode($run->summary ?? '{}', true) ?: [];

        return (object) [
            'run' => $run,
            'summary' => $summary,
            'counts' => MigrationDashboardSupport::extractRunCounts($summary),
            'created_records' => DB::table('migration_created_records')
                ->where('migration_run_id', $runId)
                ->orderBy('record_type')
                ->get(),
            'entity_maps' => DB::table('migration_entity_maps')
                ->where('migration_run_id', $runId)
                ->orderBy('entity_type')
                ->limit(500)
                ->get(),
        ];
    }
}
