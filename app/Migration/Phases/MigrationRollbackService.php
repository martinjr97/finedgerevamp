<?php

namespace App\Migration\Phases;

use Illuminate\Support\Facades\DB;

class MigrationRollbackService
{
    public function __construct(
        private readonly MigrationRunManager $runManager,
        private readonly MigrationEntityMapRepository $maps,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function rollback(string $runUuid, bool $force = false): array
    {
        $run = $this->runManager->findByUuid($runUuid);
        if (! $run) {
            throw new \RuntimeException("Migration run {$runUuid} not found.");
        }

        $runId = (int) $run->id;
        $created = $this->maps->createdByRun($runId);

        $deleted = [];
        $skipped = [];

        foreach ($created as $record) {
            $modelClass = $record->record_type;
            if (! class_exists($modelClass)) {
                $skipped[] = ['type' => $modelClass, 'id' => $record->record_id, 'reason' => 'unknown_class'];

                continue;
            }

            $model = $modelClass::find($record->record_id);
            if (! $model) {
                continue;
            }

            if (! $force && method_exists($model, 'loanRepayments') && $model->loanRepayments()->exists()) {
                $skipped[] = ['type' => $modelClass, 'id' => $record->record_id, 'reason' => 'has_financial_activity'];

                continue;
            }

            $model->delete();
            $deleted[] = ['type' => $modelClass, 'id' => $record->record_id];
        }

        DB::table('migration_entity_maps')->where('migration_run_id', $runId)->delete();
        DB::table('migration_created_records')->where('migration_run_id', $runId)->delete();
        DB::table('migration_runs')->where('id', $runId)->update(['status' => 'rolled_back', 'updated_at' => now()]);

        return [
            'run_uuid' => $runUuid,
            'deleted' => count($deleted),
            'skipped' => count($skipped),
            'details' => ['deleted' => $deleted, 'skipped' => $skipped],
        ];
    }
}
