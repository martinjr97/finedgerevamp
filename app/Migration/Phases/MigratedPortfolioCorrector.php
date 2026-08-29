<?php

namespace App\Migration\Phases;

use App\Migration\LegacyConnection;
use App\Migration\LegacyLoanBalanceCalculator;
use App\Migration\Phases\Support\MigratedLoanAttributes;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Services\LoanRepaymentLedgerService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MigratedPortfolioCorrector
{
    public function __construct(
        private readonly MigrationRunManager $runManager,
        private readonly LoanRepaymentLedgerService $ledgerService,
        private readonly LegacyLoanBalanceCalculator $balanceCalculator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function run(
        bool $promote = false,
        bool $disbursement = true,
        bool $repaymentSplits = true,
        bool $paymentSchedules = true,
        bool $loanLedgers = true,
        ?string $runUuid = null,
    ): array {
        $run = $this->runManager->start('m2-portfolio-correction', 'post-migration', $runUuid);
        $runId = $run['id'];

        $stats = [
            'run_uuid' => $run['run_uuid'],
            'migration_run_id' => $runId,
            'promote' => $promote,
            'loans_scanned' => 0,
            'disbursement_corrected' => 0,
            'first_payment_dates_set' => 0,
            'schedules_created' => 0,
            'schedules_synced' => 0,
            'repayment_splits_updated' => 0,
            'repayment_splits_skipped' => 0,
            'loan_ledgers_synced' => 0,
            'repayment_snapshots_fixed' => 0,
        ];

        if ($disbursement || $paymentSchedules) {
            $this->correctLoans($promote, $disbursement, $paymentSchedules, $stats);
        }

        if ($repaymentSplits) {
            $this->correctRepaymentSplits($promote, $stats);
        }

        if ($loanLedgers) {
            $this->correctLoanLedgers($promote, $stats);
        }

        $this->runManager->complete($runId, $stats);

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function correctLoans(bool $promote, bool $disbursement, bool $paymentSchedules, array &$stats): void
    {
        $loans = MigratedLoanAttributes::applyMigratedLoanScope(Loan::query())
            ->where('status', 'active')
            ->get();

        foreach ($loans as $loan) {
            $stats['loans_scanned']++;

            $needsDisbursement = $loan->disbursement_status !== 'completed' || $loan->disbursed_at === null;
            $needsFirstPaymentDate = $loan->first_payment_date === null;
            $needsSchedules = $paymentSchedules && $loan->paymentSchedules()->count() === 0;

            if (! $needsDisbursement && ! $needsFirstPaymentDate && ! $needsSchedules) {
                continue;
            }

            if (! $promote) {
                if ($needsDisbursement) {
                    $stats['disbursement_corrected']++;
                }
                if ($needsFirstPaymentDate) {
                    $stats['first_payment_dates_set']++;
                }
                if ($needsSchedules) {
                    $stats['schedules_created']++;
                }

                continue;
            }

            if ($needsFirstPaymentDate) {
                $loan->first_payment_date = $this->inferFirstPaymentDate($loan);
                $stats['first_payment_dates_set']++;
            }

            if ($needsDisbursement) {
                $disbursedAt = $loan->loan_start_date
                    ? Carbon::parse($loan->loan_start_date)
                    : ($loan->created_at ?? now());
                $loan->applyDisbursementCompleted($disbursedAt);
                $stats['disbursement_corrected']++;
            }

            $loan->save();

            if ($needsSchedules && $loan->first_payment_date && $loan->tenure_months > 0) {
                $loan->createPaymentSchedule();
                if ($loan->paymentSchedules()->exists()) {
                    $stats['schedules_created']++;
                    $this->syncSchedulePayments($loan, true, $stats);
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function syncSchedulePayments(Loan $loan, bool $promote, array &$stats): void
    {
        if (! $promote || $loan->paymentSchedules()->doesntExist()) {
            return;
        }

        foreach ($loan->paymentSchedules as $schedule) {
            $schedule->amount_paid = 0;
            $schedule->remaining_amount = $schedule->expected_amount;
            $schedule->paid_at = null;
            $schedule->days_overdue = 0;
            $schedule->status = $schedule->due_date?->isPast() ? 'overdue' : 'upcoming';
            if ($schedule->due_date?->isPast()) {
                $schedule->days_overdue = max(0, Carbon::today()->diffInDays($schedule->due_date));
            }
            $schedule->save();
        }

        $repayments = $loan->loanRepayments()
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        foreach ($repayments as $loanRepayment) {
            $loan->updatePaymentSchedule((float) $loanRepayment->amount);
        }

        $stats['schedules_synced']++;
    }

    private function inferFirstPaymentDate(Loan $loan): Carbon
    {
        $legacyLoan = [
            'created_at' => $loan->loan_start_date?->toDateTimeString() ?? $loan->created_at?->toDateTimeString(),
            'due_date' => $loan->loan_end_date?->toDateTimeString(),
            'payment_period' => $loan->tenure_months,
        ];

        $productCode = $loan->loanProduct?->code ?? 'MOU-001';

        return MigratedLoanAttributes::resolveFirstPaymentDate($legacyLoan, $productCode);
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function correctRepaymentSplits(bool $promote, array &$stats): void
    {
        $allocationIndex = $this->buildAllocationIndex();

        $loanRepayments = LoanRepayment::query()
            ->whereHas('repayment', fn ($q) => $q->where('external_reference', 'like', 'LEG-R-%'))
            ->with(['repayment', 'loan.loanProduct'])
            ->get();

        foreach ($loanRepayments as $loanRepayment) {
            $hasSplit = ((float) $loanRepayment->principal_amount) > 0
                || ((float) $loanRepayment->interest_amount) > 0
                || ((float) $loanRepayment->processing_fee_amount) > 0;

            if ($hasSplit) {
                $stats['repayment_splits_skipped']++;

                continue;
            }

            $legacyRepaymentId = $this->legacyRepaymentIdFromReference($loanRepayment->repayment?->external_reference);
            $legacyLoanId = (int) data_get($loanRepayment->loan?->metadata, 'legacy_loan_id', 0);
            if ($legacyLoanId <= 0 && $loanRepayment->loan) {
                $legacyLoanId = (int) str_replace('LEG-', '', (string) $loanRepayment->loan->loan_number);
            }

            $alloc = $allocationIndex[$legacyRepaymentId][$legacyLoanId] ?? null;
            $splits = MigratedLoanAttributes::repaymentSplitsFromAllocation(
                $alloc ?? ['allocated_amount' => $loanRepayment->amount],
                $loanRepayment->loan
            );

            if (! $promote) {
                $stats['repayment_splits_updated']++;

                continue;
            }

            $loanRepayment->update($splits);
            $stats['repayment_splits_updated']++;
        }
    }

    /**
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function buildAllocationIndex(): array
    {
        $latest = DB::table('migration_repayments')
            ->selectRaw('legacy_repayment_id, MAX(id) as max_id')
            ->groupBy('legacy_repayment_id');

        $rows = DB::table('migration_repayments as mr')
            ->joinSub($latest, 'latest', function ($join) {
                $join->on('mr.id', '=', 'latest.max_id');
            })
            ->whereNotNull('mr.allocations')
            ->get(['mr.legacy_repayment_id', 'mr.allocations']);

        $index = [];
        foreach ($rows as $row) {
            $legacyRepaymentId = (int) $row->legacy_repayment_id;
            $allocations = json_decode($row->allocations ?? '[]', true) ?: [];
            foreach ($allocations as $alloc) {
                $legacyLoanId = (int) ($alloc['legacy_loan_id'] ?? $alloc['loan_id'] ?? 0);
                if ($legacyLoanId <= 0) {
                    continue;
                }
                $index[$legacyRepaymentId][$legacyLoanId] = $alloc;
            }
        }

        return $index;
    }

    private function legacyRepaymentIdFromReference(?string $reference): int
    {
        if (! is_string($reference) || ! str_starts_with($reference, 'LEG-R-')) {
            return 0;
        }

        return (int) substr($reference, strlen('LEG-R-'));
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function correctLoanLedgers(bool $promote, array &$stats): void
    {
        $loans = MigratedLoanAttributes::applyMigratedLoanScope(Loan::query())->get();
        $legacyLoans = $this->loadLegacyLoansById($loans);

        foreach ($loans as $loan) {
            $stats['loans_scanned']++;

            $legacyLoanId = (int) data_get($loan->metadata, 'legacy_loan_id', 0);
            if ($legacyLoanId <= 0 && str_starts_with((string) $loan->loan_number, 'LEG-')) {
                $legacyLoanId = (int) str_replace('LEG-', '', (string) $loan->loan_number);
            }

            $repaymentRows = $loan->loanRepayments()
                ->orderBy('created_at')
                ->orderBy('id')
                ->get();

            $ledgerOutstanding = $this->ledgerService->calculateOutstandingBalance($loan);
            $legacyOutstanding = isset($legacyLoans[$legacyLoanId])
                ? $this->balanceCalculator->effectiveOutstanding($legacyLoans[$legacyLoanId])
                : null;

            $needsLedgerSync = abs((float) $loan->outstanding_balance - $ledgerOutstanding) > 0.01
                || ($legacyOutstanding !== null && abs((float) $loan->outstanding_balance - $legacyOutstanding) > 0.01);
            $needsSnapshotFix = $repaymentRows->isNotEmpty();

            if (! $needsSnapshotFix && ! $needsLedgerSync) {
                continue;
            }

            if (! $promote) {
                if ($needsSnapshotFix) {
                    $stats['repayment_snapshots_fixed'] += $repaymentRows->count();
                }
                if ($needsLedgerSync) {
                    $stats['loan_ledgers_synced']++;
                }

                continue;
            }

            if ($needsSnapshotFix) {
                $stats['repayment_snapshots_fixed'] += $this->fixRepaymentSnapshots($loan, $repaymentRows);
            }

            $this->ledgerService->syncLoanLedger($loan->fresh());

            if ($legacyOutstanding !== null) {
                $metadata = $loan->metadata ?? [];
                $metadata['legacy_effective_outstanding'] = $legacyOutstanding;
                $loan->update(['metadata' => $metadata]);
            }

            $stats['loan_ledgers_synced']++;
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, LoanRepayment>  $repaymentRows
     */
    private function fixRepaymentSnapshots(Loan $loan, $repaymentRows): int
    {
        $fixed = 0;
        $runningNetPaid = 0.0;

        foreach ($repaymentRows as $loanRepayment) {
            $amount = (float) $loanRepayment->amount;
            $before = $this->ledgerService->calculateOutstandingBalance($loan, $runningNetPaid);
            $runningNetPaid = round($runningNetPaid + $amount, 2);
            $after = $this->ledgerService->calculateOutstandingBalance($loan, $runningNetPaid);

            if (abs((float) $loanRepayment->outstanding_balance_before - $before) > 0.01
                || abs((float) $loanRepayment->outstanding_balance_after - $after) > 0.01) {
                $loanRepayment->update([
                    'outstanding_balance_before' => $before,
                    'outstanding_balance_after' => $after,
                ]);
                $fixed++;
            }
        }

        return $fixed;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Loan>  $loans
     * @return array<int, array<string, mixed>>
     */
    private function loadLegacyLoansById($loans): array
    {
        $legacyIds = $loans
            ->map(function (Loan $loan) {
                $legacyLoanId = (int) data_get($loan->metadata, 'legacy_loan_id', 0);
                if ($legacyLoanId <= 0 && str_starts_with((string) $loan->loan_number, 'LEG-')) {
                    $legacyLoanId = (int) str_replace('LEG-', '', (string) $loan->loan_number);
                }

                return $legacyLoanId;
            })
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($legacyIds === []) {
            return [];
        }

        try {
            LegacyConnection::configureFromLegacyEnvFile();
            $legacy = LegacyConnection::connection();
        } catch (\Throwable) {
            return [];
        }

        $indexed = [];
        foreach ($legacy->table('loans')->whereIn('id', $legacyIds)->get() as $row) {
            $indexed[(int) $row->id] = (array) $row;
        }

        return $indexed;
    }
}
