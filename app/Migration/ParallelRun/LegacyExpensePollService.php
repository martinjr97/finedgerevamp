<?php

namespace App\Migration\ParallelRun;

use App\Migration\LegacyConnection;
use App\Migration\Phases\MigrationEntityMapRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LegacyExpensePollService
{
    public function __construct(
        private readonly MigrationExpenseInboxRepository $inbox,
        private readonly MigrationSyncState $syncState,
    ) {}

    /**
     * @return array{detected: int, skipped_mapped: int, skipped_loan_disb: int, skipped_category: int}
     */
    public function poll(): array
    {
        LegacyConnection::configureFromLegacyEnvFile();
        $legacy = LegacyConnection::connection();

        $mappedExpenseIds = DB::table('migration_entity_maps')
            ->where('entity_type', MigrationEntityMapRepository::TYPE_FINANCIAL_TRANSACTION)
            ->where('legacy_secondary', 'expense')
            ->pluck('legacy_identifier')
            ->flip()
            ->all();

        $limit = (int) config('legacy-parallel-run.poll_batch_limit', 200);
        $fromBoundary = $this->resolveFromBoundary();
        $lastPoll = $this->syncState->getTimestamp(MigrationSyncState::KEY_LAST_EXPENSE_POLL_AT);

        $query = $legacy->table('expenses')
            ->whereDate('expense_date', '>=', $fromBoundary->toDateString())
            ->orderBy('id');

        if ($lastPoll !== null) {
            $query->where('created_at', '>=', $lastPoll->copy()->subHour());
        }

        $rows = $query->limit($limit)->get();

        $stats = [
            'detected' => 0,
            'skipped_mapped' => 0,
            'skipped_loan_disb' => 0,
            'skipped_category' => 0,
        ];

        foreach ($rows as $row) {
            $expense = (array) $row;
            $legacyExpenseId = (int) ($expense['id'] ?? 0);

            if ($legacyExpenseId < 1) {
                continue;
            }

            if (isset($mappedExpenseIds[(string) $legacyExpenseId])) {
                $stats['skipped_mapped']++;

                continue;
            }

            $reference = (string) ($expense['reference_number'] ?? '');
            if (Str::startsWith(Str::upper($reference), 'LOAN-DISB-')) {
                $stats['skipped_loan_disb']++;

                continue;
            }

            if ($this->isExcludedCategory($legacy, $expense)) {
                $stats['skipped_category']++;

                continue;
            }

            $this->inbox->upsertDetected($legacyExpenseId, $expense);
            $stats['detected']++;
        }

        $this->syncState->touchNow(MigrationSyncState::KEY_LAST_EXPENSE_POLL_AT);

        return $stats;
    }

    private function resolveFromBoundary(): Carbon
    {
        $configured = config('legacy-parallel-run.financial_from_date')
            ?: config('migration.financial_from_date')
            ?: env('MIGRATION_FINANCIAL_FROM_DATE');

        return $configured
            ? Carbon::parse($configured)->startOfDay()
            : Carbon::today()->startOfDay();
    }

    /**
     * @param  array<string, mixed>  $expense
     */
    private function isExcludedCategory($legacy, array $expense): bool
    {
        $categoryId = $expense['expense_category_id'] ?? null;
        if (! $categoryId) {
            return false;
        }

        $category = $legacy->table('expense_categories')->where('id', $categoryId)->value('name');
        if (! $category) {
            return false;
        }

        $normalized = Str::lower(trim((string) $category));

        return in_array($normalized, ['loan take out', 'inhouse transfer', 'in-house transfer'], true);
    }
}
