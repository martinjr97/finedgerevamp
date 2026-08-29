<?php

namespace App\Migration\Phases;

use App\Migration\LegacyConnection;
use App\Migration\Phases\Support\MigratedLoanAttributes;
use App\Migration\RepaymentAttributionService;
use App\Migration\Replay\LegacyRepaymentReplayService;
use App\Migration\Phases\Support\RepaymentManualClassifier;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\Repayment;
use App\Services\LoanRepaymentLedgerService;
use Illuminate\Support\Facades\DB;

class RepaymentMigrator
{
    public function __construct(
        private readonly MigrationRunManager $runManager,
        private readonly MigrationEntityMapRepository $maps,
        private readonly MigrationDependencyGate $gate,
        private readonly LegacyRepaymentReplayService $replayService,
        private readonly RepaymentManualClassifier $manualClassifier,
        private readonly LoanRepaymentLedgerService $ledgerService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function run(bool $promote = false, ?int $legacyUserId = null, ?string $runUuid = null): array
    {
        if ($promote && ! $this->gate->hasAnyLoanMaps()) {
            throw new \RuntimeException('No loan mappings found. Run migration:active-loans --promote first.');
        }

        $replaySummary = $this->replayService->dryRun(null, null);
        $replayRunId = (int) $replaySummary['migration_run_id'];

        $run = $this->runManager->start('m2-repayments', $legacyUserId ? 'single' : 'active-portfolio', $runUuid);
        $runId = $run['id'];

        LegacyConnection::configureFromLegacyEnvFile();
        $legacy = LegacyConnection::connection();

        $activeLoanIds = $legacy->table('loans')->where('status_code', '301')->pluck('id')->all();
        $activeLoanSet = array_flip($activeLoanIds);

        $repayments = DB::table('migration_repayments')
            ->where('migration_run_id', $replayRunId)
            ->when($legacyUserId, fn ($q) => $q->where('legacy_user_id', $legacyUserId))
            ->get();

        $stats = [
            'run_uuid' => $run['run_uuid'],
            'migration_run_id' => $runId,
            'replay_run_id' => $replayRunId,
            'promote' => $promote,
            'A_DIRECT' => 0,
            'B_RECONSTRUCTED' => 0,
            'C_AMBIGUOUS' => 0,
            'D_MANUAL' => 0,
            'would_promote' => 0,
            'promoted' => 0,
            'excluded_ambiguous' => 0,
            'excluded_manual' => 0,
            'blocked_missing_loan' => 0,
        ];

        foreach ($repayments as $row) {
            $class = (string) $row->attribution_class;
            $stats[$class] = ($stats[$class] ?? 0) + 1;

            if ($class === RepaymentAttributionService::C_AMBIGUOUS) {
                $stats['excluded_ambiguous']++;
                $this->stageRepayment($runId, $row, null, 'manual_review');

                continue;
            }

            if ($class === RepaymentAttributionService::D_MANUAL) {
                $sub = $this->manualClassifier->subclassify($class, [], $row->exception);
                $stats['excluded_manual']++;
                $this->stageRepayment($runId, $row, null, 'excluded_'.$sub);

                continue;
            }

            if (! in_array($class, [RepaymentAttributionService::A_DIRECT, RepaymentAttributionService::B_RECONSTRUCTED], true)) {
                continue;
            }

            $allocations = json_decode($row->allocations ?? '[]', true) ?: [];
            if ($allocations === []) {
                continue;
            }

            $existingRepaymentMap = $this->maps->find(
                MigrationEntityMapRepository::TYPE_REPAYMENT,
                (string) $row->legacy_repayment_id
            );
            $existingRepayment = Repayment::query()
                ->where('external_reference', 'LEG-R-'.$row->legacy_repayment_id)
                ->first();

            if (! $promote) {
                if ($existingRepaymentMap || $existingRepayment) {
                    $stats['promoted']++;
                    $this->stageRepayment($runId, $row, $existingRepayment?->id, 'matched_existing');

                    continue;
                }

                $stats['would_promote']++;
                $this->stageRepayment($runId, $row, null, 'would_promote');

                continue;
            }

            try {
                $customerId = $this->gate->requireCustomerMapped((int) $row->legacy_user_id);
            } catch (\RuntimeException) {
                $stats['blocked_missing_loan']++;
                continue;
            }

            $legacyRepayment = (array) $legacy->table('repayments')->where('id', $row->legacy_repayment_id)->first();
            $reference = 'LEG-R-'.$row->legacy_repayment_id;

            $existingRepayment = Repayment::query()->where('external_reference', $reference)->first();
            if ($existingRepayment) {
                $this->maps->store(
                    MigrationEntityMapRepository::TYPE_REPAYMENT,
                    (string) $row->legacy_repayment_id,
                    Repayment::class,
                    $existingRepayment->id,
                    'matched_existing',
                    'HIGH',
                    null,
                    $runId
                );
                $stats['promoted']++;
                continue;
            }

            $repaymentModel = Repayment::create([
                'customer_id' => $customerId,
                'repayment_number' => Repayment::generateRepaymentNumber(),
                'external_reference' => $reference,
                'total_amount' => (float) $row->repayment_amount,
                'recovery_method' => 'normal',
                'phone_number' => $legacyRepayment['phone_number'] ?? null,
                'status' => 'completed',
                'processed_at' => $legacyRepayment['created_at'] ?? now(),
                'metadata' => [
                    'legacy_repayment_id' => $row->legacy_repayment_id,
                    'attribution_class' => $class,
                ],
            ]);

            $affectedLoanIds = [];

            foreach ($allocations as $alloc) {
                $legacyLoanId = (int) ($alloc['legacy_loan_id'] ?? $alloc['loan_id'] ?? 0);
                if (! isset($activeLoanSet[$legacyLoanId])) {
                    continue;
                }

                try {
                    $loanId = $this->gate->requireLoanMapped($legacyLoanId);
                } catch (\RuntimeException) {
                    $stats['blocked_missing_loan']++;

                    continue;
                }

                $amount = (float) ($alloc['allocated_amount'] ?? $alloc['amount_applied'] ?? 0);
                if ($amount <= 0) {
                    continue;
                }

                $loan = Loan::query()->find($loanId);
                if (! $loan) {
                    continue;
                }

                $netPaidBefore = $this->ledgerService->calculateNetPaid($loan);
                $before = $this->ledgerService->calculateOutstandingBalance($loan, $netPaidBefore);
                $netPaidAfter = round($netPaidBefore + $amount, 2);
                $after = $this->ledgerService->calculateOutstandingBalance($loan, $netPaidAfter);
                $splits = MigratedLoanAttributes::repaymentSplitsFromAllocation($alloc, $loan);

                LoanRepayment::create([
                    'repayment_id' => $repaymentModel->id,
                    'loan_id' => $loanId,
                    'transaction_type' => LoanRepayment::TRANSACTION_TYPE_PAYMENT,
                    'amount' => $amount,
                    'principal_amount' => $splits['principal_amount'],
                    'interest_amount' => $splits['interest_amount'],
                    'processing_fee_amount' => $splits['processing_fee_amount'],
                    'outstanding_balance_before' => $before,
                    'outstanding_balance_after' => $after,
                ]);

                $affectedLoanIds[$loanId] = true;
            }

            foreach (array_keys($affectedLoanIds) as $loanId) {
                $loan = Loan::query()->find($loanId);
                if ($loan) {
                    $this->ledgerService->syncLoanLedger($loan);
                }
            }

            $this->maps->store(
                MigrationEntityMapRepository::TYPE_REPAYMENT,
                (string) $row->legacy_repayment_id,
                Repayment::class,
                $repaymentModel->id,
                'created',
                'HIGH',
                null,
                $runId
            );
            $this->maps->trackCreated($runId, Repayment::class, $repaymentModel->id);
            $stats['promoted']++;
            $this->stageRepayment($runId, $row, $repaymentModel->id, 'promoted');
        }

        $this->runManager->complete($runId, $stats);

        return $stats;
    }

    private function stageRepayment(int $runId, object $row, ?int $mappedId, string $status): void
    {
        DB::table('migration_repayments')->updateOrInsert(
            ['migration_run_id' => $runId, 'legacy_repayment_id' => $row->legacy_repayment_id],
            [
                'legacy_user_id' => $row->legacy_user_id,
                'mapped_repayment_id' => $mappedId,
                'attribution_class' => $row->attribution_class,
                'repayment_amount' => $row->repayment_amount,
                'migration_status' => $status,
                'allocations' => $row->allocations,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}
