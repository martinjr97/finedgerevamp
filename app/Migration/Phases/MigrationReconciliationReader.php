<?php

namespace App\Migration\Phases;

use App\Migration\LegacyConnection;
use App\Migration\LegacyLoanBalanceCalculator;
use App\Models\Loan;
use Illuminate\Support\Facades\DB;

class MigrationReconciliationReader
{
    public function __construct(
        private readonly LegacyLoanBalanceCalculator $balanceCalculator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function reconcile(?int $legacyLoanId = null, ?int $legacyUserId = null): array
    {
        LegacyConnection::configureFromLegacyEnvFile();
        $legacy = LegacyConnection::connection();

        $loanMaps = DB::table('migration_entity_maps')
            ->where('entity_type', MigrationEntityMapRepository::TYPE_LOAN)
            ->when($legacyLoanId, fn ($q) => $q->where('legacy_identifier', (string) $legacyLoanId))
            ->get();

        $results = [];
        $pass = 0;
        $fail = 0;
        $manual = 0;

        foreach ($loanMaps as $map) {
            $legacyLoan = (array) $legacy->table('loans')->where('id', $map->legacy_identifier)->first();
            if ($legacyLoan === []) {
                continue;
            }
            if ($legacyUserId && (int) $legacyLoan['user_id'] !== $legacyUserId) {
                continue;
            }

            $targetLoan = Loan::find($map->target_id);
            $legacyEffective = $this->balanceCalculator->effectiveOutstanding($legacyLoan);
            $targetOutstanding = $targetLoan ? (float) $targetLoan->outstanding_balance : 0;
            $variance = abs(round($legacyEffective - $targetOutstanding, 2));

            $replay = DB::table('migration_loan_replay_results')
                ->where('legacy_loan_id', $map->legacy_identifier)
                ->orderByDesc('id')
                ->first();

            $status = 'FAIL';
            if ($replay && $replay->reconciliation_status === 'MANUAL_REVIEW') {
                $status = 'MANUAL_REVIEW';
                $manual++;
            } elseif ($variance <= 0.01) {
                $status = 'PASS';
                $pass++;
            } else {
                $fail++;
            }

            $results[] = [
                'legacy_loan_id' => (int) $map->legacy_identifier,
                'target_loan_id' => (int) $map->target_id,
                'legacy_effective' => round($legacyEffective, 2),
                'target_outstanding' => round($targetOutstanding, 2),
                'variance' => $variance,
                'status' => $status,
            ];
        }

        return [
            'read_only' => true,
            'loans_checked' => count($results),
            'PASS' => $pass,
            'FAIL' => $fail,
            'MANUAL_REVIEW' => $manual,
            'results' => $results,
        ];
    }
}
