<?php

namespace App\Services;

use Carbon\Carbon;
use InvalidArgumentException;

class GroupLoanCalculationService
{
    /**
     * @param array<int|string, mixed> $input
     * @return array{members: array<int, array<string, float|int>>, totals: array<string, float>, installment_count: int, duration_days: int}
     */
    public function calculate(array $input): array
    {
        $processingFeePercentage = (float) ($input['processing_fee_percentage'] ?? 0);
        // Kept as monthly_interest_rate key for backward compatibility, but for
        // group loans this is treated as a flat rate for the full loan period.
        $periodInterestRate = (float) ($input['monthly_interest_rate'] ?? 0);
        $arrearsRate = (float) ($input['arrears_rate'] ?? 0);
        $repaymentStructure = (string) ($input['repayment_structure'] ?? 'monthly');
        $principals = $input['principals'] ?? [];

        if (! in_array($repaymentStructure, ['weekly', 'monthly'], true)) {
            throw new InvalidArgumentException('Repayment structure must be weekly or monthly.');
        }

        $startDate = Carbon::parse((string) ($input['start_date'] ?? ''));
        $dueDate = Carbon::parse((string) ($input['due_date'] ?? ''));

        if ($dueDate->lessThanOrEqualTo($startDate)) {
            throw new InvalidArgumentException('Due date must be after start date.');
        }

        $durationDays = $startDate->diffInDays($dueDate);
        $installmentCount = $repaymentStructure === 'weekly'
            ? (int) max(1, ceil($durationDays / 7))
            : (int) max(1, ceil($durationDays / 30));

        $members = [];
        $totals = [
            'principal_amount' => 0.0,
            'processing_fee_amount' => 0.0,
            'interest_amount' => 0.0,
            'arrears_basis_amount' => 0.0,
            'repayment_amount' => 0.0,
            'disbursement_amount' => 0.0,
        ];

        foreach ($principals as $customerId => $principalValue) {
            $principal = round((float) $principalValue, 2);
            if ($principal <= 0) {
                throw new InvalidArgumentException('Principal amount must be greater than zero for all members.');
            }

            $processingFeeAmount = round(($principal * $processingFeePercentage) / 100, 2);
            $interestAmount = round($principal * ($periodInterestRate / 100), 2);
            $arrearsBasisAmount = round(($principal * $arrearsRate) / 100, 2);
            $totalRepayment = round($principal + $processingFeeAmount + $interestAmount, 2);
            $disbursementAmount = $principal;

            $members[] = [
                'customer_id' => (int) $customerId,
                'principal_amount' => $principal,
                'processing_fee_amount' => $processingFeeAmount,
                'interest_amount' => $interestAmount,
                'arrears_basis_amount' => $arrearsBasisAmount,
                'total_repayment_amount' => $totalRepayment,
                'disbursement_amount' => $disbursementAmount,
            ];

            $totals['principal_amount'] += $principal;
            $totals['processing_fee_amount'] += $processingFeeAmount;
            $totals['interest_amount'] += $interestAmount;
            $totals['arrears_basis_amount'] += $arrearsBasisAmount;
            $totals['repayment_amount'] += $totalRepayment;
            $totals['disbursement_amount'] += $disbursementAmount;
        }

        foreach ($totals as $key => $value) {
            $totals[$key] = round($value, 2);
        }

        return [
            'members' => $members,
            'totals' => $totals,
            'installment_count' => $installmentCount,
            'duration_days' => $durationDays,
        ];
    }
}
