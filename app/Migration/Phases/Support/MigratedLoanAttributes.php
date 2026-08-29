<?php

namespace App\Migration\Phases\Support;

use App\Models\Loan;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class MigratedLoanAttributes
{
    public static function applyMigratedLoanScope(Builder $query): Builder
    {
        return $query->where(function (Builder $scoped) {
            $scoped->where('loan_number', 'like', 'LEG-%')
                ->orWhereNotNull('metadata->legacy_loan_id');
        });
    }

    /**
     * @param  array<string, mixed>  $legacyLoan
     */
    public static function resolveLoanStartDate(array $legacyLoan): string
    {
        $raw = $legacyLoan['created_at'] ?? null;

        return $raw ? date('Y-m-d', strtotime((string) $raw)) : now()->toDateString();
    }

    /**
     * @param  array<string, mixed>  $legacyLoan
     */
    public static function resolveDisbursedAt(array $legacyLoan, ?Carbon $loanStartDate = null): Carbon
    {
        $raw = $legacyLoan['created_at'] ?? $legacyLoan['loan_start_date'] ?? null;
        if ($raw) {
            return Carbon::parse($raw);
        }

        return $loanStartDate ?? now();
    }

    /**
     * @param  array<string, mixed>  $legacyLoan
     */
    public static function resolveFirstPaymentDate(array $legacyLoan, string $productCode): Carbon
    {
        $tenure = max(1, (int) ($legacyLoan['payment_period'] ?? 1));
        $start = Carbon::parse(self::resolveLoanStartDate($legacyLoan));
        $end = ! empty($legacyLoan['due_date']) ? Carbon::parse((string) $legacyLoan['due_date']) : null;
        $weekly = self::repaymentStructureForProduct($productCode) === 'weekly';

        if ($end !== null && $tenure > 1) {
            return $weekly
                ? $end->copy()->subWeeks($tenure - 1)
                : $end->copy()->subMonths($tenure - 1);
        }

        return $weekly ? $start->copy()->addWeek() : $start->copy()->addMonth();
    }

    public static function repaymentStructureForProduct(string $productCode): string
    {
        return $productCode === 'MARK-001' ? 'weekly' : 'monthly';
    }

    /**
     * @param  array<string, mixed>  $alloc
     * @return array{principal_amount: float, interest_amount: float, processing_fee_amount: float}
     */
    public static function repaymentSplitsFromAllocation(array $alloc, ?Loan $loan = null): array
    {
        $amount = (float) ($alloc['allocated_amount'] ?? $alloc['amount_applied'] ?? 0);
        $hasExplicitSplit = array_key_exists('principal_amount', $alloc)
            || array_key_exists('interest_amount', $alloc)
            || array_key_exists('fee_amount', $alloc)
            || array_key_exists('processing_fee_amount', $alloc);

        if ($hasExplicitSplit) {
            return [
                'principal_amount' => round((float) ($alloc['principal_amount'] ?? 0), 2),
                'interest_amount' => round((float) ($alloc['interest_amount'] ?? 0), 2),
                'processing_fee_amount' => round((float) ($alloc['fee_amount'] ?? $alloc['processing_fee_amount'] ?? 0), 2),
            ];
        }

        if ($loan !== null && $amount > 0) {
            $allocation = $loan->calculateRepaymentAllocation($amount);

            return [
                'principal_amount' => round((float) $allocation['principal_amount'], 2),
                'interest_amount' => round((float) $allocation['interest_amount'], 2),
                'processing_fee_amount' => round((float) $allocation['processing_fee_amount'], 2),
            ];
        }

        return [
            'principal_amount' => round($amount, 2),
            'interest_amount' => 0.0,
            'processing_fee_amount' => 0.0,
        ];
    }
}
