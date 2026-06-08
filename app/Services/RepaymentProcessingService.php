<?php

namespace App\Services;

use App\Models\Channel;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanPaymentSchedule;
use App\Models\LoanRepayment;
use App\Models\Repayment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class RepaymentProcessingService
{
    public function __construct(
        private readonly LoanRepaymentLedgerService $ledgerService
    ) {}

    /**
     * @return array{success: bool, reference?: string, transaction_id?: string, message?: string, metadata?: array}
     */
    public function processPayment(float $amount, Channel $channel, ?string $phoneNumber): array
    {
        // TODO: Replace with real payment provider integration.
        Log::info('Processing repayment through gateway', [
            'amount' => $amount,
            'channel' => $channel->name,
            'phone_number' => $phoneNumber,
        ]);

        return [
            'success' => true,
            'reference' => 'EXT-'.strtoupper($channel->code).'-'.now()->format('YmdHis').'-'.str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT),
            'transaction_id' => 'TXN-'.now()->timestamp.'-'.random_int(1000, 9999),
            'message' => 'Payment prompt sent. Approve the prompt on your device to complete the repayment.',
            'metadata' => [
                'channel' => $channel->name,
                'gateway_initiated_at' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * Finalize an integrated repayment once the provider confirms success.
     *
     * @param  array{reference?: string, transaction_id?: string, message?: string, metadata?: array}  $providerResult
     */
    public function finalizeIntegratedRepayment(
        Repayment $repayment,
        array $providerResult = [],
        string $notePrefix = 'Integrated repayment'
    ): void {
        $repayment->loadMissing(['customer']);

        $customer = $repayment->customer;
        if (! $customer) {
            throw new \RuntimeException('Repayment customer record is missing.');
        }

        $metadata = $repayment->metadata ?? [];
        $repaymentType = (string) ($metadata['repayment_type'] ?? 'full');
        $loanId = isset($metadata['loan_id']) ? (int) $metadata['loan_id'] : null;

        $repayment->update([
            'external_reference' => $providerResult['reference'] ?? $repayment->external_reference,
            'external_transaction_id' => $providerResult['transaction_id'] ?? $repayment->external_transaction_id,
            'status' => 'completed',
            'processed_at' => now(),
            'status_message' => $providerResult['message'] ?? 'Payment confirmed by provider and repayment completed.',
            'metadata' => array_merge($metadata, $providerResult['metadata'] ?? [], [
                'provider_confirmed_at' => now()->toIso8601String(),
            ]),
        ]);

        if (! $repayment->loanRepayments()->exists()) {
            $this->applyRepaymentToLoans(
                $repayment,
                $customer,
                $repaymentType,
                $loanId,
                (float) $repayment->total_amount,
                $notePrefix
            );
        }
    }

    public function applyRepaymentToLoans(
        Repayment $repayment,
        Customer $customer,
        string $type,
        ?int $loanId,
        float $amount,
        string $notePrefix = 'Repayment'
    ): void {
        if ($type === 'partial' && $loanId) {
            $loan = Loan::where('id', $loanId)
                ->where('customer_id', $customer->id)
                ->whereIn('status', ['approved', 'active'])
                ->firstOrFail();

            $this->applyPaymentToLoan($repayment, $loan, $amount, $notePrefix);
            $this->refreshCustomerCreditScore($customer);
            return;
        }

        $activeLoans = $customer->activeLoans()
            ->filter(fn (Loan $loan) => (float) $loan->outstanding_balance > 0)
            ->values();
        $remainingAmount = $amount;

        // For "all loans" across multiple active loans, prioritize missed/nearest due dates.
        if ($activeLoans->count() > 1) {
            $remainingAmount = $this->applyAcrossLoansByDueDatePriority($repayment, $activeLoans, $remainingAmount, $notePrefix);
        }

        // Fallback allocation for any remaining amount (or loans with no schedules).
        foreach ($activeLoans->map(function (Loan $loan) {
            $loan->refresh();
            return $loan;
        })->sortBy(fn (Loan $loan) => $this->loanPriorityDate($loan)) as $loan) {
            if ($remainingAmount <= 0) {
                break;
            }

            if ($loan->outstanding_balance <= 0) {
                continue;
            }

            $payAmount = min($loan->outstanding_balance, $remainingAmount);
            $this->applyPaymentToLoan($repayment, $loan, $payAmount, $notePrefix);
            $remainingAmount -= $payAmount;
        }

        $this->refreshCustomerCreditScore($customer);
    }

    private function applyAcrossLoansByDueDatePriority(
        Repayment $repayment,
        Collection $activeLoans,
        float $amount,
        string $notePrefix
    ): float {
        if ($amount <= 0 || $activeLoans->isEmpty()) {
            return max(0, $amount);
        }

        $remainingAmount = $amount;
        $loanMap = $activeLoans->keyBy('id');
        $loanIds = $loanMap->keys()->all();

        while ($remainingAmount > 0.00001 && ! empty($loanIds)) {
            $nextSchedule = LoanPaymentSchedule::query()
                ->whereIn('loan_id', $loanIds)
                ->where('remaining_amount', '>', 0)
                ->orderBy('due_date')
                ->orderBy('period_number')
                ->orderBy('id')
                ->first();

            if (! $nextSchedule) {
                break;
            }

            /** @var Loan|null $loan */
            $loan = $loanMap->get($nextSchedule->loan_id);
            if (! $loan) {
                $loanIds = array_values(array_diff($loanIds, [(int) $nextSchedule->loan_id]));
                continue;
            }

            $loan->refresh();
            if ((float) $loan->outstanding_balance <= 0) {
                $loanIds = array_values(array_diff($loanIds, [$loan->id]));
                continue;
            }

            $payAmount = min(
                (float) $nextSchedule->remaining_amount,
                (float) $loan->outstanding_balance,
                $remainingAmount
            );

            if ($payAmount <= 0) {
                $loanIds = array_values(array_diff($loanIds, [$loan->id]));
                continue;
            }

            $this->applyPaymentToLoan($repayment, $loan, $payAmount, "{$notePrefix} (due-date priority)");
            $remainingAmount -= $payAmount;

            $loan->refresh();
            $loanMap->put($loan->id, $loan);
            if ((float) $loan->outstanding_balance <= 0) {
                $loanIds = array_values(array_diff($loanIds, [$loan->id]));
            }
        }

        return max(0, $remainingAmount);
    }

    private function loanPriorityDate(Loan $loan): string
    {
        $nextDueDate = $loan->paymentSchedules()
            ->where('remaining_amount', '>', 0)
            ->orderBy('due_date')
            ->value('due_date');

        return (string) ($nextDueDate ?? $loan->loan_start_date?->toDateString() ?? now()->toDateString());
    }

    private function applyPaymentToLoan(Repayment $repayment, Loan $loan, float $amount, string $notePrefix): void
    {
        $netPaidBefore = $this->ledgerService->calculateNetPaid($loan);
        $outstandingBalanceBefore = $this->ledgerService->calculateOutstandingBalance($loan, $netPaidBefore);
        $allocation = $loan->calculateRepaymentAllocation($amount);

        $principalAmount = (float) $allocation['principal_amount'];
        $interestAmount = (float) $allocation['interest_amount'];
        $processingFeeAmount = (float) $allocation['processing_fee_amount'];

        $totalAllocated = $principalAmount + $interestAmount + $processingFeeAmount;
        if (abs($totalAllocated - $amount) > 0.01) {
            $principalAmount += ($amount - $totalAllocated);
            $principalAmount = max(0, $principalAmount);
        }

        if ($loan->paymentSchedules()->exists()) {
            $loan->updatePaymentSchedule($amount);
        }

        $netPaidAfter = round($netPaidBefore + $amount, 2);
        $outstandingBalanceAfter = $this->ledgerService->calculateOutstandingBalance($loan, $netPaidAfter);

        $loanRepaymentNotes = "{$notePrefix} applied to loan {$loan->loan_number}";
        $overpaymentMeta = ($repayment->metadata ?? [])['overpayment'] ?? null;
        if (is_array($overpaymentMeta) && filled($overpaymentMeta['reason'] ?? null)) {
            $loanRepaymentNotes .= ' | Overpayment reason: '.$overpaymentMeta['reason'];
        }

        LoanRepayment::create([
            'repayment_id' => $repayment->id,
            'loan_id' => $loan->id,
            'transaction_type' => LoanRepayment::TRANSACTION_TYPE_PAYMENT,
            'amount' => $amount,
            'principal_amount' => round($principalAmount, 2),
            'interest_amount' => round($interestAmount, 2),
            'processing_fee_amount' => round($processingFeeAmount, 2),
            'outstanding_balance_before' => $outstandingBalanceBefore,
            'outstanding_balance_after' => $outstandingBalanceAfter,
            'notes' => $loanRepaymentNotes,
            'metadata' => is_array($overpaymentMeta) ? ['overpayment' => $overpaymentMeta] : null,
        ]);

        $this->ledgerService->syncLoanLedger($loan->fresh());
    }

    private function refreshCustomerCreditScore(Customer $customer): void
    {
        try {
            \App\Support\CreditScoreService::updateCreditScore($customer);
        } catch (\Throwable $e) {
            Log::error('Failed to update credit score after repayment', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
