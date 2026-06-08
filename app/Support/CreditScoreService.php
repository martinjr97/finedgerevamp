<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanPaymentSchedule;
use Carbon\Carbon;

class CreditScoreService
{
    /**
     * Calculate credit score for a customer (0-100 scale)
     * 
     * Factors:
     * 1. Payment Punctuality Score (30%)
     * 2. Loan Completion History (25%)
     * 3. Defaults & Delays (25%)
     * 4. Loan Frequency (10%)
     * 5. Loan Size Growth (10%)
     */
    public static function calculate(Customer $customer): float
    {
        // Get all loans for the customer
        $loans = $customer->loans()->with('paymentSchedules')->get();
        
        if ($loans->isEmpty()) {
            // New customer with no loan history - default score of 50
            return 50.0;
        }

        // 1. Payment Punctuality Score (30% weight)
        $punctualityScore = self::calculatePunctualityScore($loans);
        
        // 2. Loan Completion History (25% weight)
        $completionScore = self::calculateCompletionScore($loans);
        
        // 3. Defaults & Delays (25% weight)
        $defaultsScore = self::calculateDefaultsScore($loans);
        
        // 4. Loan Frequency (10% weight)
        $frequencyScore = self::calculateFrequencyScore($loans);
        
        // 5. Loan Size Growth (10% weight)
        $growthScore = self::calculateGrowthScore($loans);

        // Weighted average
        $totalScore = (
            ($punctualityScore * 0.30) +
            ($completionScore * 0.25) +
            ($defaultsScore * 0.25) +
            ($frequencyScore * 0.10) +
            ($growthScore * 0.10)
        );

        // Ensure score is between 0 and 100
        return max(0, min(100, round($totalScore, 2)));
    }

    /**
     * Calculate payment punctuality score (0-100)
     * Based on on-time payments vs late payments
     */
    private static function calculatePunctualityScore($loans): float
    {
        $totalPayments = 0;
        $onTimePayments = 0;
        $latePayments = 0;
        $severelyLatePayments = 0; // > 30 days

        foreach ($loans as $loan) {
            $schedules = $loan->paymentSchedules;
            
            foreach ($schedules as $schedule) {
                // Only count completed payments (paid or partially paid)
                if ($schedule->amount_paid > 0) {
                    $totalPayments++;
                    
                    // Check if payment was made
                    if ($schedule->paid_at) {
                        $paidDate = Carbon::parse($schedule->paid_at);
                        $daysLate = $schedule->due_date->diffInDays($paidDate, false);
                        
                        if ($daysLate <= 0) {
                            // On time or early
                            $onTimePayments++;
                        } elseif ($daysLate <= 7) {
                            // Slightly late (1-7 days)
                            $latePayments++;
                        } elseif ($daysLate <= 30) {
                            // Moderately late (8-30 days)
                            $latePayments += 2;
                        } else {
                            // Severely late (>30 days)
                            $severelyLatePayments++;
                        }
                    } elseif ($schedule->due_date->isPast()) {
                        // Payment not made and due date has passed - count as late
                        $daysLate = Carbon::today()->diffInDays($schedule->due_date, false);
                        if ($daysLate > 30) {
                            $severelyLatePayments++;
                        } else {
                            $latePayments++;
                        }
                    }
                }
            }
        }

        if ($totalPayments === 0) {
            return 50.0; // No payment history
        }

        // Calculate score: on-time payments boost score, late payments reduce it
        $score = 100;
        $score -= ($latePayments / $totalPayments) * 30; // Deduct up to 30 points for late payments
        $score -= ($severelyLatePayments / $totalPayments) * 50; // Deduct up to 50 points for severely late payments

        return max(0, min(100, $score));
    }

    /**
     * Calculate loan completion score (0-100)
     * Based on percentage of loans fully completed
     */
    private static function calculateCompletionScore($loans): float
    {
        if ($loans->isEmpty()) {
            return 50.0;
        }

        $completedLoans = 0;
        $defaultedLoans = 0;

        foreach ($loans as $loan) {
            if ($loan->status === 'settled' || $loan->status === 'completed') {
                $completedLoans++;
            } elseif ($loan->status === 'defaulted' || $loan->status === 'written_off') {
                $defaultedLoans++;
            }
        }

        $totalLoans = $loans->count();
        $completionRate = $completedLoans / $totalLoans;
        $defaultRate = $defaultedLoans / $totalLoans;

        // Score based on completion rate, penalize defaults
        $score = ($completionRate * 100) - ($defaultRate * 100);

        return max(0, min(100, $score));
    }

    /**
     * Calculate defaults and delays score (0-100)
     * Based on current overdue amounts and default history
     */
    private static function calculateDefaultsScore($loans): float
    {
        $totalOverdueAmount = 0;
        $totalLoanAmount = 0;
        $hasActiveDefaults = false;
        $maxDaysOverdue = 0;

        foreach ($loans as $loan) {
            if (in_array($loan->status, ['approved', 'active'])) {
                $totalLoanAmount += $loan->total_amount;
                $overdueAmount = $loan->getOverdueAmount();
                $totalOverdueAmount += $overdueAmount;

                if ($overdueAmount > 0) {
                    $hasActiveDefaults = true;
                    
                    // Get max days overdue
                    $overduePeriods = $loan->getOverduePeriods();
                    foreach ($overduePeriods as $period) {
                        $daysOverdue = Carbon::today()->diffInDays($period->due_date, false);
                        $maxDaysOverdue = max($maxDaysOverdue, $daysOverdue);
                    }
                }
            }
        }

        if ($totalLoanAmount === 0) {
            return 50.0; // No active loans
        }

        $overduePercentage = ($totalOverdueAmount / $totalLoanAmount) * 100;

        // Start with 100 and deduct based on overdue percentage and severity
        $score = 100;
        $score -= min(50, $overduePercentage * 0.5); // Deduct up to 50 points for overdue percentage
        
        // Additional penalty for severe delays (>90 days)
        if ($maxDaysOverdue > 90) {
            $score -= 30;
        } elseif ($maxDaysOverdue > 60) {
            $score -= 20;
        } elseif ($maxDaysOverdue > 30) {
            $score -= 10;
        }

        return max(0, min(100, $score));
    }

    /**
     * Calculate loan frequency score (0-100)
     * Based on how frequently customer takes loans (too frequent = bad, moderate = good)
     */
    private static function calculateFrequencyScore($loans): float
    {
        if ($loans->count() < 2) {
            return 50.0; // Need at least 2 loans to assess frequency
        }

        $sortedLoans = $loans->sortBy('loan_start_date');
        $loanDates = $sortedLoans->pluck('loan_start_date')->filter()->values();
        
        if ($loanDates->count() < 2) {
            return 50.0;
        }

        // Calculate average days between loans
        $totalDays = 0;
        $intervals = 0;
        $datesArray = $loanDates->toArray();
        
        for ($i = 1; $i < count($datesArray); $i++) {
            $daysBetween = Carbon::parse($datesArray[$i])->diffInDays(Carbon::parse($datesArray[$i - 1]), false);
            $totalDays += abs($daysBetween);
            $intervals++;
        }

        if ($intervals === 0) {
            return 50.0;
        }

        $avgDaysBetween = $totalDays / $intervals;

        // Score: 6-12 months between loans = good (100), <3 months = bad (0), >24 months = moderate (50)
        if ($avgDaysBetween >= 180 && $avgDaysBetween <= 365) {
            // 6-12 months: ideal frequency
            return 100.0;
        } elseif ($avgDaysBetween < 90) {
            // <3 months: too frequent
            return max(0, 100 - (90 - $avgDaysBetween) * 2);
        } elseif ($avgDaysBetween > 730) {
            // >24 months: infrequent but not necessarily bad
            return 60.0;
        } else {
            // 3-6 months or 12-24 months: moderate
            return 70.0;
        }
    }

    /**
     * Calculate loan size growth score (0-100)
     * Based on responsible growth in loan amounts
     */
    private static function calculateGrowthScore($loans): float
    {
        if ($loans->count() < 2) {
            return 50.0; // Need at least 2 loans
        }

        $sortedLoans = $loans->sortBy('loan_start_date');
        $loanAmounts = $sortedLoans->pluck('principal_amount')->filter();
        
        if ($loanAmounts->count() < 2) {
            return 50.0;
        }

        // Calculate growth rate
        $growthRates = [];
        $amountsArray = $loanAmounts->values()->toArray();
        for ($i = 1; $i < count($amountsArray); $i++) {
            if ($amountsArray[$i - 1] > 0) {
                $growthRate = (($amountsArray[$i] - $amountsArray[$i - 1]) / $amountsArray[$i - 1]) * 100;
                $growthRates[] = $growthRate;
            }
        }

        if (empty($growthRates)) {
            return 50.0;
        }

        $avgGrowthRate = array_sum($growthRates) / count($growthRates);

        // Score: Moderate growth (10-30%) = good, excessive growth (>50%) = bad, negative growth = moderate
        if ($avgGrowthRate >= 10 && $avgGrowthRate <= 30) {
            // Healthy growth
            return 100.0;
        } elseif ($avgGrowthRate > 50) {
            // Excessive growth - risky
            return max(0, 100 - ($avgGrowthRate - 50));
        } elseif ($avgGrowthRate < 0) {
            // Decreasing loan size - conservative but not necessarily bad
            return 60.0;
        } else {
            // Low growth (0-10%) or moderate-high (30-50%)
            return 70.0;
        }
    }

    /**
     * Get credit score category and color
     */
    public static function getScoreCategory(float $score): array
    {
        if ($score >= 80) {
            return [
                'category' => 'Excellent',
                'color' => 'emerald',
                'bg_color' => 'bg-emerald-500/20',
                'text_color' => 'text-emerald-300',
                'border_color' => 'border-emerald-500/50',
            ];
        } elseif ($score >= 70) {
            return [
                'category' => 'Good',
                'color' => 'green',
                'bg_color' => 'bg-green-500/20',
                'text_color' => 'text-green-300',
                'border_color' => 'border-green-500/50',
            ];
        } elseif ($score >= 60) {
            return [
                'category' => 'Fair',
                'color' => 'yellow',
                'bg_color' => 'bg-yellow-500/20',
                'text_color' => 'text-yellow-300',
                'border_color' => 'border-yellow-500/50',
            ];
        } elseif ($score >= 50) {
            return [
                'category' => 'Poor',
                'color' => 'orange',
                'bg_color' => 'bg-orange-500/20',
                'text_color' => 'text-orange-300',
                'border_color' => 'border-orange-500/50',
            ];
        } else {
            return [
                'category' => 'Very Poor',
                'color' => 'red',
                'bg_color' => 'bg-red-500/20',
                'text_color' => 'text-red-300',
                'border_color' => 'border-red-500/50',
            ];
        }
    }

    /**
     * Calculate and update credit score for a customer
     */
    public static function updateCreditScore(Customer $customer): void
    {
        $score = self::calculate($customer);
        
        $customer->update([
            'credit_score' => $score,
            'credit_score_updated_at' => now(),
        ]);

        // Auto-adjust maximum_loan_take if enabled
        $settings = \App\Models\GeneralSetting::first();
        if ($settings && $settings->auto_adjust_loan_limit_by_credit_score) {
            self::adjustLoanLimit($customer, $score);
        }
    }

    /**
     * Adjust maximum_loan_take based on credit score
     * Cannot exceed 60% of net_salary, but can go below based on score
     */
    public static function adjustLoanLimit(Customer $customer, ?float $score = null): void
    {
        if ($score === null) {
            $score = $customer->credit_score ?? self::calculate($customer);
        }

        $netSalary = $customer->net_salary ?? 0;
        
        if ($netSalary <= 0) {
            return; // Cannot adjust without net salary
        }

        // Base limit is 60% of net salary
        $baseLimit = $netSalary * 0.6;

        // Adjust based on credit score
        // Score 100 = 100% of base limit (60% of salary)
        // Score 50 = 50% of base limit (30% of salary)
        // Score 0 = 20% of base limit (12% of salary) - minimum
        $adjustmentFactor = ($score / 100);
        
        // Ensure minimum of 20% of base limit
        $adjustmentFactor = max(0.20, $adjustmentFactor);
        
        $newLimit = $baseLimit * $adjustmentFactor;

        $customer->update([
            'maximum_loan_take' => round($newLimit, 2),
        ]);
    }
}

