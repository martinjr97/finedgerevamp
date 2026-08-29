<?php

namespace App\Migration\Dashboard;

use App\Migration\Phases\Support\RepaymentManualClassifier;
use App\Migration\RepaymentAttributionService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class MigrationExceptionReportService
{
    public function __construct(
        private readonly RepaymentManualClassifier $manualClassifier,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $sources = [
            'migration_customers' => 'customer',
            'migration_loans' => 'loan',
            'migration_repayments' => 'repayment',
            'migration_companies' => 'company',
            'migration_branches' => 'branch',
            'migration_financial_institutions' => 'bank',
            'migration_wallet_providers' => 'wallet_provider',
            'migration_relationship_managers' => 'relationship_manager',
        ];

        $items = collect();
        foreach ($sources as $table => $entityType) {
            if (! $this->tableExists($table)) {
                continue;
            }

            $rows = DB::table($table)
                ->where('migration_status', 'manual_review')
                ->orWhereNotNull('exception')
                ->orderByDesc('id')
                ->limit(200)
                ->get();

            foreach ($rows as $row) {
                $items->push([
                    'entity_type' => $entityType,
                    'rule_code' => $row->exception ?? 'MANUAL_REVIEW',
                    'legacy_id' => $this->legacyIdFromRow($table, $row),
                    'severity' => ($row->confidence ?? '') === 'LOW' ? 'high' : 'medium',
                    'message' => $row->exception ?? 'Requires manual review',
                    'phase' => null,
                    'run_id' => $row->migration_run_id ?? null,
                    'created_at' => $row->created_at ?? null,
                    'resolved' => false,
                ]);
            }
        }

        return [
            'total' => $items->count(),
            'items' => $items->take(100)->values()->all(),
        ];
    }

    public function paginateExceptions(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $query = DB::table('migration_loans')
            ->where(function ($q) {
                $q->where('migration_status', 'manual_review')
                    ->orWhereNotNull('exception');
            });

        if (! empty($filters['entity']) && $filters['entity'] !== 'loan') {
            $table = match ($filters['entity']) {
                'customer' => 'migration_customers',
                'repayment' => 'migration_repayments',
                'company' => 'migration_companies',
                default => 'migration_loans',
            };
            $query = DB::table($table)->where(function ($q) {
                $q->where('migration_status', 'manual_review')->orWhereNotNull('exception');
            });
        }

        if (! empty($filters['run_id'])) {
            $query->where('migration_run_id', (int) $filters['run_id']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('exception', 'like', "%{$search}%")
                    ->orWhere('legacy_loan_id', $search)
                    ->orWhere('legacy_user_id', $search);
            });
        }

        return $query->orderByDesc('id')->paginate($perPage);
    }

    /**
     * @return array<string, array{count: int, amount: float}>
     */
    public function dManualBreakdown(): array
    {
        $rows = DB::table('migration_repayments')
            ->where('attribution_class', RepaymentAttributionService::D_MANUAL)
            ->get(['repayment_amount', 'exception', 'attribution_class']);

        $breakdown = [
            RepaymentManualClassifier::D1_HISTORICAL_SUPPORT_ONLY => ['count' => 0, 'amount' => 0.0],
            RepaymentManualClassifier::D2_CURRENT_BALANCE_BRIDGED => ['count' => 0, 'amount' => 0.0],
            RepaymentManualClassifier::D3_REQUIRES_REVIEW => ['count' => 0, 'amount' => 0.0],
            RepaymentManualClassifier::D4_BLOCKING => ['count' => 0, 'amount' => 0.0],
        ];

        foreach ($rows as $row) {
            $subclass = $this->manualClassifier->subclassify(
                RepaymentAttributionService::D_MANUAL,
                (array) $row,
                $row->exception
            ) ?? RepaymentManualClassifier::D2_CURRENT_BALANCE_BRIDGED;

            $breakdown[$subclass]['count']++;
            $breakdown[$subclass]['amount'] += (float) $row->repayment_amount;
        }

        return $breakdown;
    }

    private function tableExists(string $table): bool
    {
        return DB::getSchemaBuilder()->hasTable($table);
    }

    private function legacyIdFromRow(string $table, object $row): ?string
    {
        return match ($table) {
            'migration_customers' => (string) ($row->legacy_user_id ?? ''),
            'migration_loans' => (string) ($row->legacy_loan_id ?? ''),
            'migration_repayments' => (string) ($row->legacy_repayment_id ?? ''),
            'migration_companies' => (string) ($row->legacy_client_id ?? ''),
            'migration_branches' => (string) ($row->legacy_identifier ?? ''),
            default => null,
        };
    }
}
