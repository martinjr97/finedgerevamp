<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Support\RepaymentRecoveryMethod;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CustomerLifetimeStatementService
{
    private const DEFAULT_MONTHS_WHEN_BUSY = 12;

    private const BUSY_ROW_THRESHOLD = 80;

    public function __construct(
        private readonly LoanRepaymentLedgerService $ledgerService
    ) {}

    /**
     * @return array{
     *     summary: array<string, float|int>,
     *     rows: Collection<int, array<string, mixed>>,
     *     opening_balance: array{balance_owed: float, customer_credit: float},
     *     closing_balance: array{balance_owed: float, customer_credit: float},
     *     loans: Collection<int, Loan>,
     *     filters: array<string, mixed>,
     *     defaulted_date_range: bool
     * }
     */
    public function build(
        Customer $customer,
        ?Carbon $fromDate = null,
        ?Carbon $toDate = null,
        ?int $loanId = null,
        bool $includeSchedules = true
    ): array {
        $loans = $this->loadLoans($customer, $loanId);
        $allRows = $this->collectRows($loans, $includeSchedules);
        $allRows = $this->sortRows($allRows);

        $defaultedDateRange = false;
        if ($fromDate === null && $toDate === null && $allRows->count() > self::BUSY_ROW_THRESHOLD) {
            $fromDate = now()->subMonths(self::DEFAULT_MONTHS_WHEN_BUSY)->startOfDay();
            $defaultedDateRange = true;
        }

        $fromBoundary = $fromDate?->copy()->startOfDay();
        $toBoundary = $toDate?->copy()->endOfDay();

        $openingBalance = $this->calculateBalanceAtBoundary($allRows, $fromBoundary, before: true);
        $displayRows = $allRows->filter(function (array $row) use ($fromBoundary, $toBoundary): bool {
            $date = $row['date'];
            if ($fromBoundary && $date->lt($fromBoundary)) {
                return false;
            }
            if ($toBoundary && $date->gt($toBoundary)) {
                return false;
            }

            return true;
        })->values();

        $displayRows = $this->applyRunningBalances($displayRows, $openingBalance);

        $summary = $this->buildSummary($loans);
        $closingBalance = $this->balanceFromNet($this->netBalanceFromOpening($openingBalance) + $this->sumCashMovement($displayRows));

        return [
            'summary' => $summary,
            'rows' => $displayRows,
            'opening_balance' => $openingBalance,
            'closing_balance' => $closingBalance,
            'loans' => $loans,
            'filters' => [
                'from_date' => $fromDate?->toDateString(),
                'to_date' => $toDate?->toDateString(),
                'loan_id' => $loanId,
            ],
            'defaulted_date_range' => $defaultedDateRange,
        ];
    }

    /**
     * @return Collection<int, Loan>
     */
    private function loadLoans(Customer $customer, ?int $loanId): Collection
    {
        $query = $customer->loans()
            ->with([
                'loanProduct',
                'paymentSchedules' => fn ($q) => $q->orderBy('due_date')->orderBy('period_number'),
                'loanRepayments' => fn ($q) => $q->orderBy('created_at')->orderBy('id'),
                'loanRepayments.repayment',
                'loanRepayments.refundOf.repayment',
            ])
            ->orderBy('loan_start_date')
            ->orderBy('id');

        if ($loanId) {
            $query->where('id', $loanId);
        }

        return $query->get();
    }

    /**
     * @param  Collection<int, Loan>  $loans
     * @return Collection<int, array<string, mixed>>
     */
    private function collectRows(Collection $loans, bool $includeSchedules): Collection
    {
        $rows = collect();

        foreach ($loans as $loan) {
            $loanReference = $loan->loan_number;
            $expectedSettlement = $this->ledgerService->getExpectedSettlementAmount($loan);

            $disbursementDate = $this->resolveDisbursementDate($loan);
            if ($disbursementDate && $expectedSettlement > 0) {
                $rows->push($this->makeRow(
                    date: $disbursementDate,
                    sortOrder: 10,
                    loan: $loan,
                    loanReference: $loanReference,
                    description: 'Loan disbursed',
                    transactionType: 'disbursement',
                    debit: $expectedSettlement,
                    credit: null,
                    isCash: true,
                    reference: $loan->disbursement_reference,
                    notes: $loan->loanProduct?->name,
                ));
            }

            if ($includeSchedules) {
                foreach ($loan->paymentSchedules as $schedule) {
                    $dueDate = Carbon::parse($schedule->due_date)->endOfDay();
                    $rows->push($this->makeRow(
                        date: $dueDate,
                        sortOrder: 20,
                        loan: $loan,
                        loanReference: $loanReference,
                        description: 'Scheduled installment due (Period '.$schedule->period_number.')',
                        transactionType: 'schedule',
                        debit: null,
                        credit: null,
                        isCash: false,
                        reference: null,
                        notes: 'Expected ZMW '.number_format((float) $schedule->expected_amount, 2),
                        meta: ['expected_amount' => (float) $schedule->expected_amount],
                    ));
                }
            }

            $runningNetPaid = 0.0;

            foreach ($loan->loanRepayments->sortBy(fn (LoanRepayment $lr) => [$lr->created_at, $lr->id]) as $loanRepayment) {
                $repayment = $loanRepayment->repayment;
                if (! $repayment) {
                    continue;
                }

                $txnDate = ($repayment->processed_at ?? $repayment->created_at)->copy();
                $amount = abs((float) $loanRepayment->amount);
                $isRefund = $loanRepayment->isRefund();
                $runningNetPaid = round($runningNetPaid + (float) $loanRepayment->amount, 2);
                $suspenseAfter = $this->ledgerService->calculateSuspenseAmount($loan, $runningNetPaid);
                $suspenseBefore = $this->ledgerService->calculateSuspenseAmount($loan, round($runningNetPaid - (float) $loanRepayment->amount, 2));

                if ($isRefund) {
                    $originalRef = $loanRepayment->refundOf?->repayment?->repayment_number;
                    $rows->push($this->makeRow(
                        date: $txnDate,
                        sortOrder: 40,
                        loan: $loan,
                        loanReference: $loanReference,
                        description: 'Refund issued'.($originalRef ? ' (against '.$originalRef.')' : ''),
                        transactionType: 'refund',
                        debit: $amount,
                        credit: null,
                        isCash: true,
                        reference: $repayment->repayment_number,
                        notes: $loanRepayment->notes,
                    ));
                } else {
                    $recoveryNote = RepaymentRecoveryMethod::label($repayment->recovery_method);
                    $statementNotes = trim(collect([
                        $loanRepayment->notes,
                        $recoveryNote !== RepaymentRecoveryMethod::label(RepaymentRecoveryMethod::NORMAL)
                            ? 'Recovery method: '.$recoveryNote
                            : null,
                    ])->filter()->implode(' | '));

                    $rows->push($this->makeRow(
                        date: $txnDate,
                        sortOrder: 30,
                        loan: $loan,
                        loanReference: $loanReference,
                        description: 'Repayment received',
                        transactionType: 'payment',
                        debit: null,
                        credit: $amount,
                        isCash: true,
                        reference: $repayment->repayment_number,
                        notes: $statementNotes !== '' ? $statementNotes : null,
                    ));

                    if ($suspenseAfter > 0 && $suspenseAfter > $suspenseBefore) {
                        $rows->push($this->makeRow(
                            date: $txnDate->copy()->addSecond(),
                            sortOrder: 35,
                            loan: $loan,
                            loanReference: $loanReference,
                            description: 'Suspense/overpayment',
                            transactionType: 'suspense',
                            debit: null,
                            credit: null,
                            isCash: false,
                            reference: $repayment->repayment_number,
                            notes: 'Customer credit ZMW '.number_format($suspenseAfter, 2).' above full loan obligation',
                            meta: ['suspense_amount' => $suspenseAfter],
                        ));
                    }
                }
            }

            if (in_array($loan->status, ['completed', 'settled'], true) && $loan->loan_settled_date) {
                $rows->push($this->makeRow(
                    date: Carbon::parse($loan->loan_settled_date)->endOfDay(),
                    sortOrder: 50,
                    loan: $loan,
                    loanReference: $loanReference,
                    description: 'Loan settled',
                    transactionType: 'settlement',
                    debit: null,
                    credit: null,
                    isCash: false,
                    reference: null,
                    notes: 'Loan obligation met',
                ));
            }
        }

        return $rows;
    }

    private function resolveDisbursementDate(Loan $loan): ?Carbon
    {
        if ($loan->disbursed_at) {
            return $loan->disbursed_at->copy();
        }

        if ($loan->disbursement_status === 'completed' && $loan->loan_start_date) {
            return Carbon::parse($loan->loan_start_date)->startOfDay();
        }

        if (in_array($loan->status, ['active', 'settled', 'completed'], true) && $loan->loan_start_date) {
            return Carbon::parse($loan->loan_start_date)->startOfDay();
        }

        return null;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function sortRows(Collection $rows): Collection
    {
        return $rows->sortBy(function (array $row): string {
            return $row['date']->format('Y-m-d H:i:s.u')
                .'-'.str_pad((string) $row['sort_order'], 3, '0', STR_PAD_LEFT)
                .'-'.($row['loan_id'] ?? 0)
                .'-'.$row['transaction_type'];
        })->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array{balance_owed: float, customer_credit: float}  $openingBalance
     * @return Collection<int, array<string, mixed>>
     */
    private function applyRunningBalances(Collection $rows, array $openingBalance): Collection
    {
        $net = $this->netBalanceFromOpening($openingBalance);

        return $rows->map(function (array $row) use (&$net): array {
            if ($row['is_cash']) {
                $debit = (float) ($row['debit'] ?? 0);
                $credit = (float) ($row['credit'] ?? 0);
                $net = round($net + $debit - $credit, 2);
            }

            $balance = $this->balanceFromNet($net);
            $row['running_balance'] = $balance;
            $row['balance_label'] = $balance['customer_credit'] > 0
                ? 'Customer credit'
                : 'Balance owed';

            return $row;
        });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{balance_owed: float, customer_credit: float}
     */
    private function calculateBalanceAtBoundary(Collection $rows, ?Carbon $boundary, bool $before): array
    {
        if ($boundary === null) {
            return ['balance_owed' => 0.0, 'customer_credit' => 0.0];
        }

        $net = 0.0;

        foreach ($rows as $row) {
            if (! $row['is_cash']) {
                continue;
            }

            $date = $row['date'];
            $matches = $before ? $date->lt($boundary) : $date->lte($boundary);
            if (! $matches) {
                continue;
            }

            $net = round($net + (float) ($row['debit'] ?? 0) - (float) ($row['credit'] ?? 0), 2);
        }

        return $this->balanceFromNet($net);
    }

    /**
     * @return array{balance_owed: float, customer_credit: float}
     */
    private function balanceFromNet(float $net): array
    {
        if ($net < 0) {
            return [
                'balance_owed' => 0.0,
                'customer_credit' => round(abs($net), 2),
            ];
        }

        return [
            'balance_owed' => round($net, 2),
            'customer_credit' => 0.0,
        ];
    }

    /**
     * @param  array{balance_owed: float, customer_credit: float}  $opening
     */
    private function netBalanceFromOpening(array $opening): float
    {
        return round((float) $opening['balance_owed'] - (float) $opening['customer_credit'], 2);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function sumCashMovement(Collection $rows): float
    {
        return round($rows->sum(function (array $row): float {
            if (! $row['is_cash']) {
                return 0.0;
            }

            return (float) ($row['debit'] ?? 0) - (float) ($row['credit'] ?? 0);
        }), 2);
    }

    /**
     * @param  Collection<int, Loan>  $loans
     * @return array<string, float|int>
     */
    private function buildSummary(Collection $loans): array
    {
        $disbursedLoans = $loans->filter(fn (Loan $loan): bool => $this->resolveDisbursementDate($loan) !== null);

        $totalExpected = 0.0;
        $totalNetPaid = 0.0;
        $totalOutstanding = 0.0;
        $totalSuspense = 0.0;
        $totalRefunded = 0.0;

        foreach ($loans as $loan) {
            $totalExpected += $this->ledgerService->getExpectedSettlementAmount($loan);
            $netPaid = $this->ledgerService->calculateNetPaid($loan);
            $totalNetPaid += $netPaid;
            $totalOutstanding += $this->ledgerService->calculateOutstandingBalance($loan, $netPaid);
            $totalSuspense += $this->ledgerService->calculateSuspenseAmount($loan, $netPaid);
        }

        $totalRefunded = (float) LoanRepayment::query()
            ->whereIn('loan_id', $loans->pluck('id'))
            ->where(function ($query): void {
                $query->where('transaction_type', LoanRepayment::TRANSACTION_TYPE_REFUND)
                    ->orWhere('amount', '<', 0);
            })
            ->sum('amount');

        $totalRefunded = round(abs($totalRefunded), 2);

        return [
            'loans_collected' => $disbursedLoans->count(),
            'total_expected_settlement' => round($totalExpected, 2),
            'total_net_paid' => round($totalNetPaid, 2),
            'total_refunded' => $totalRefunded,
            'total_outstanding' => round($totalOutstanding, 2),
            'total_suspense' => round($totalSuspense, 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function makeRow(
        Carbon $date,
        int $sortOrder,
        Loan $loan,
        string $loanReference,
        string $description,
        string $transactionType,
        ?float $debit,
        ?float $credit,
        bool $isCash,
        ?string $reference,
        ?string $notes,
        array $meta = []
    ): array {
        return [
            'date' => $date,
            'sort_order' => $sortOrder,
            'loan_id' => $loan->id,
            'loan' => $loan,
            'loan_reference' => $loanReference,
            'description' => $description,
            'transaction_type' => $transactionType,
            'debit' => $debit,
            'credit' => $credit,
            'is_cash' => $isCash,
            'reference' => $reference,
            'notes' => $notes,
            'meta' => $meta,
        ];
    }
}
