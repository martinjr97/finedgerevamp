<?php

namespace App\Services;

use App\Models\LoanProduct;
use App\Models\LoanRate;
use App\Models\LoanRateType;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Central pricing engine for loan quotations.
 *
 * Quote-only in Phase 2: does not book loans, accrue interest, or mutate balances.
 *
 * Term-rate to daily factor (daily_accrual + term_percentage):
 *   derived_daily_rate = (term_interest_percentage / 100) / term_days
 *
 * Projected interest for a full term (quotation):
 *   principal × derived_daily_rate × term_days  (= principal × term% / 100)
 *
 * Upfront flat (upfront_flat + term_percentage):
 *   interest = principal × term_interest_percentage / 100
 */
class LoanPricingService
{
    private const MONEY_SCALE = 2;

    private const RATE_SCALE = 8;

    private const CALC_SCALE = 12;

    public function quoteLoan(array $payload): array
    {
        $principal = $this->normalizeAmount($payload['principal'] ?? null, 'principal');
        $tenureMonths = (int) ($payload['tenure_months'] ?? 0);

        if ($tenureMonths < 1) {
            throw new InvalidArgumentException('tenure_months must be at least 1.');
        }

        $rateType = $this->resolveRateType($payload);
        $loanRate = $this->resolveLoanRate($payload, $rateType, $principal, $tenureMonths);

        $startDate = $this->parseStartDate($payload['start_date'] ?? null);
        $termDays = (int) ($payload['term_days'] ?? $this->calculateTermDays($startDate, $tenureMonths));

        if ($termDays < 1) {
            throw new InvalidArgumentException('term_days must be at least 1.');
        }

        $loanProduct = $payload['loan_product'] ?? null;
        $interestBehavior = $this->resolveInterestBehavior($payload, $rateType, $loanProduct, $loanRate);
        $rateInputMode = $this->resolveRateInputMode($payload, $rateType, $loanRate);

        $processingFeePercentage = $this->normalizeRate(
            $loanRate->processing_fee_percentage ?? 0,
            'processing_fee_percentage'
        );

        $processingFee = $this->calculateProcessingFee($principal, $processingFeePercentage);

        $interestPayload = [
            'principal' => $principal,
            'term_days' => $termDays,
            'loan_rate' => $loanRate,
            'loan_rate_type' => $rateType,
            'interest_behavior' => $interestBehavior,
            'rate_input_mode' => $rateInputMode,
        ];

        $interest = $this->calculateInterest($interestPayload);
        $totalAmount = $this->roundMoney($this->bcAdd($principal, $this->bcAdd($processingFee, $interest)));

        $installmentResult = $this->calculateInstallments($totalAmount, $tenureMonths);

        $quotedTermRate = $loanRate->term_interest_percentage !== null
            ? $this->formatRate((string) $loanRate->term_interest_percentage, 4)
            : null;

        $derivedDailyRate = $this->resolveDerivedDailyRateForQuote(
            $loanRate,
            $rateInputMode,
            $interestBehavior,
            $termDays
        );

        return [
            'principal' => $this->roundMoney($principal),
            'processing_fee' => $processingFee,
            'processing_fee_percentage' => $this->formatRate($processingFeePercentage, 2),
            'interest' => $interest,
            'total_amount' => $totalAmount,
            'installment_amount' => $installmentResult['installment_amount'],
            'installments' => $installmentResult['installments'],
            'term_days' => $termDays,
            'tenure_months' => $tenureMonths,
            'rate_input_mode' => $rateInputMode,
            'interest_behavior' => $interestBehavior,
            'accrual_period' => $rateType->accrual_period,
            'quoted_term_rate' => $quotedTermRate,
            'daily_rate' => $loanRate->daily_rate !== null
                ? $this->formatRate((string) $loanRate->daily_rate, self::RATE_SCALE)
                : null,
            'weekly_rate' => $loanRate->weekly_rate !== null
                ? $this->formatRate((string) $loanRate->weekly_rate, self::RATE_SCALE)
                : null,
            'derived_daily_rate' => $derivedDailyRate,
            'loan_rate_id' => $loanRate->id,
            'loan_rate_type_id' => $rateType->id,
            'start_date' => $startDate->toDateString(),
            'loan_end_date' => $startDate->copy()->addMonths($tenureMonths)->toDateString(),
            'projected_interest' => $interest,
            'projected_total_amount' => $totalAmount,
        ];
    }

    /**
     * Resolve how interest should be booked for a new loan.
     *
     * Legacy multiplier rate types defer to loan_products.accrual_type:
     *   product at_beginning => upfront_flat (full interest at origination)
     *   product daily        => daily_accrual (interest earned over time)
     *
     * Term-percentage rate types use loan_rate_types.interest_behavior explicitly.
     */
    public function resolveInterestBehaviorForBooking(
        LoanRateType $rateType,
        LoanProduct $loanProduct,
        LoanRate $loanRate
    ): string {
        if ($loanRate->term_interest_percentage !== null
            || $rateType->rate_input_mode === LoanRateType::RATE_INPUT_TERM_PERCENTAGE) {
            return $this->normalizeInterestBehavior($rateType->interest_behavior);
        }

        if ($rateType->usesLegacyMultiplierInput()) {
            return ($loanProduct->accrual_type ?? 'at_beginning') === 'daily'
                ? LoanRateType::INTEREST_BEHAVIOR_DAILY_ACCRUAL
                : LoanRateType::INTEREST_BEHAVIOR_UPFRONT_FLAT;
        }

        return $this->normalizeInterestBehavior($rateType->interest_behavior);
    }

    /**
     * Map interest_behavior to loans.accrual_type (frozen on the loan).
     *
     * upfront_flat   => at_beginning (no cron accrual)
     * daily_accrual  => daily (cron accrues earned interest)
     */
    public function mapAccrualTypeFromInterestBehavior(string $interestBehavior): string
    {
        return $interestBehavior === LoanRateType::INTEREST_BEHAVIOR_UPFRONT_FLAT
            ? 'at_beginning'
            : 'daily';
    }

    /**
     * Build financial snapshot fields for Loan::create from a pricing quote.
     *
     * @return array<string, mixed>
     */
    public function buildLoanFinancialSnapshot(array $quote): array
    {
        $behavior = $this->normalizeInterestBehavior($quote['interest_behavior'] ?? null);
        $accrualType = $this->mapAccrualTypeFromInterestBehavior($behavior);

        $principal = $quote['principal'];
        $processingFee = $quote['processing_fee'];
        $projectedInterest = $quote['interest'];
        $projectedTotal = $quote['total_amount'];

        $dailyRate = $quote['derived_daily_rate'] ?? $quote['daily_rate'];
        $weeklyRate = $quote['weekly_rate'];

        if ($behavior === LoanRateType::INTEREST_BEHAVIOR_UPFRONT_FLAT) {
            return [
                'processing_fee' => $processingFee,
                'processing_fee_percentage' => $quote['processing_fee_percentage'],
                'interest_accrued' => $projectedInterest,
                'total_amount' => $projectedTotal,
                'outstanding_balance' => $projectedTotal,
                'daily_rate' => $dailyRate,
                'weekly_rate' => $weeklyRate,
                'quoted_term_rate' => $quote['quoted_term_rate'],
                'interest_behavior' => $behavior,
                'accrual_type' => $accrualType,
                'accrual_period' => $quote['accrual_period'] ?? null,
                'pricing_metadata' => [
                    'projected_interest' => $projectedInterest,
                    'projected_total_amount' => $projectedTotal,
                    'booked_total_amount' => $projectedTotal,
                    'schedule_basis' => 'booked_total',
                    'schedule_uses_projected_interest' => false,
                    'rate_input_mode' => $quote['rate_input_mode'] ?? null,
                    'term_days' => $quote['term_days'] ?? null,
                ],
            ];
        }

        // daily_accrual: book principal + processing fee only; interest is earned over time.
        $initialBookedTotal = $this->roundMoney($this->bcAdd($principal, $processingFee));

        return [
            'processing_fee' => $processingFee,
            'processing_fee_percentage' => $quote['processing_fee_percentage'],
            'interest_accrued' => '0.00',
            'total_amount' => $initialBookedTotal,
            'outstanding_balance' => $initialBookedTotal,
            'daily_rate' => $dailyRate,
            'weekly_rate' => $weeklyRate,
            'quoted_term_rate' => $quote['quoted_term_rate'],
            'interest_behavior' => $behavior,
            'accrual_type' => $accrualType,
            'accrual_period' => $quote['accrual_period'] ?? null,
            'pricing_metadata' => [
                'projected_interest' => $projectedInterest,
                'projected_total_amount' => $projectedTotal,
                'booked_total_amount' => $initialBookedTotal,
                'schedule_basis' => 'projected_total',
                'schedule_uses_projected_interest' => true,
                'rate_input_mode' => $quote['rate_input_mode'] ?? null,
                'term_days' => $quote['term_days'] ?? null,
                'booked_balance_excludes_projected_interest' => true,
            ],
        ];
    }

    /**
     * Split principal, fee, and interest evenly across installments; final period absorbs rounding per component.
     *
     * @return array{
     *     installments: list<array{
     *         period: int,
     *         expected_amount: string,
     *         principal_component: string,
     *         interest_component: string,
     *         fee_component: string
     *     }>
     * }
     */
    public function calculateComponentInstallments(
        float|int|string $principal,
        float|int|string $processingFee,
        float|int|string $interest,
        int $tenureMonths,
    ): array {
        if ($tenureMonths < 1) {
            throw new InvalidArgumentException('tenure_months must be at least 1.');
        }

        $principalParts = $this->splitComponentAcrossTenure($principal, $tenureMonths, 'principal');
        $feeParts = $this->splitComponentAcrossTenure($processingFee, $tenureMonths, 'processing_fee');
        $interestParts = $this->splitComponentAcrossTenure($interest, $tenureMonths, 'interest');

        $installments = [];

        foreach ($principalParts as $index => $principalRow) {
            $period = (int) $principalRow['period'];
            $principalComponent = $principalRow['amount'];
            $feeComponent = $feeParts[$index]['amount'];
            $interestComponent = $interestParts[$index]['amount'];
            $expected = $this->roundMoney(
                $this->bcAdd($this->bcAdd($principalComponent, $feeComponent), $interestComponent)
            );

            $installments[] = [
                'period' => $period,
                'expected_amount' => $expected,
                'principal_component' => $principalComponent,
                'interest_component' => $interestComponent,
                'fee_component' => $feeComponent,
            ];
        }

        return ['installments' => $installments];
    }

    /**
     * Format a quote for repayment calculator API responses (backward compatible keys).
     *
     * @return array<string, mixed>
     */
    public function formatRepaymentQuoteResponse(array $quote, array $schedule = []): array
    {
        $financials = $this->buildLoanFinancialSnapshot($quote);

        return array_merge([
            'principal_amount' => (float) $quote['principal'],
            'processing_fee' => (float) $financials['processing_fee'],
            'processing_fee_percentage' => (float) $quote['processing_fee_percentage'],
            'interest' => (float) $quote['interest'],
            'projected_interest' => (float) $quote['interest'],
            'total_amount' => (float) $quote['total_amount'],
            'projected_total_amount' => (float) $quote['total_amount'],
            'booked_total_amount' => (float) $financials['total_amount'],
            'booked_interest_accrued' => (float) $financials['interest_accrued'],
            'booked_outstanding_balance' => (float) $financials['outstanding_balance'],
            'interest_behavior' => $quote['interest_behavior'],
            'accrual_type' => $financials['accrual_type'],
            'rate_input_mode' => $quote['rate_input_mode'],
            'loan_rate_id' => $quote['loan_rate_id'],
            'loan_rate_type_id' => $quote['loan_rate_type_id'],
            'daily_rate' => $quote['derived_daily_rate'] ?? $quote['daily_rate'],
            'weekly_rate' => $quote['weekly_rate'],
            'quoted_term_rate' => $quote['quoted_term_rate'],
            'derived_daily_rate' => $quote['derived_daily_rate'],
            'installment_amount' => (float) $quote['installment_amount'],
            'installments' => $quote['installments'],
            'days' => $quote['term_days'],
            'loan_start_date' => $quote['start_date'],
            'loan_end_date' => $quote['loan_end_date'],
            'accrual_period' => $quote['accrual_period'] ?? null,
        ], $schedule);
    }

    public function calculateProcessingFee(float|int|string $principal, float|int|string $processingFeePercentage): string
    {
        $principal = $this->normalizeAmount($principal, 'principal');
        $percentage = $this->normalizeRate($processingFeePercentage, 'processing_fee_percentage');

        $fee = $this->bcDiv($this->bcMul($principal, $percentage), '100');

        return $this->roundMoney($fee);
    }

    public function calculateInterest(array $payload): string
    {
        $principal = $this->normalizeAmount($payload['principal'] ?? null, 'principal');
        $termDays = (int) ($payload['term_days'] ?? 0);

        if ($termDays < 1) {
            throw new InvalidArgumentException('term_days must be at least 1.');
        }

        $loanRate = $payload['loan_rate'] ?? null;
        if (! $loanRate instanceof LoanRate) {
            throw new InvalidArgumentException('loan_rate is required.');
        }

        $rateType = $payload['loan_rate_type'] ?? $loanRate->loanRateType;
        if (! $rateType instanceof LoanRateType) {
            throw new InvalidArgumentException('loan_rate_type is required.');
        }

        $loanProduct = $payload['loan_product'] ?? null;
        $interestBehavior = $this->resolveInterestBehavior($payload, $rateType, $loanProduct, $loanRate);
        $rateInputMode = $this->resolveRateInputMode($payload, $rateType, $loanRate);

        $interest = match ($rateInputMode) {
            LoanRateType::RATE_INPUT_TERM_PERCENTAGE => $this->calculateTermPercentageInterest(
                $principal,
                $loanRate,
                $termDays,
                $interestBehavior,
                isset($payload['term_interest_percentage']) ? (string) $payload['term_interest_percentage'] : null
            ),
            LoanRateType::RATE_INPUT_WEEKLY_MULTIPLIER => $this->calculateWeeklyMultiplierInterest(
                $principal,
                $loanRate,
                $termDays,
                isset($payload['weekly_rate']) ? (string) $payload['weekly_rate'] : null
            ),
            default => $this->calculateDailyMultiplierInterest(
                $principal,
                $loanRate,
                $termDays,
                isset($payload['daily_rate']) ? (string) $payload['daily_rate'] : null
            ),
        };

        return $this->roundMoney($interest);
    }

    /**
     * Derive per-day factor from a business term rate.
     *
     * Formula: derived_daily_rate = (term_interest_percentage / 100) / term_days
     */
    public function calculateDerivedDailyRateFromTerm(float|int|string $termInterestPercentage, int $termDays): string
    {
        if ($termDays < 1) {
            throw new InvalidArgumentException('term_days must be at least 1.');
        }

        $termRate = $this->normalizeRate($termInterestPercentage, 'term_interest_percentage');
        $termFactor = $this->bcDiv($termRate, '100');
        $derived = $this->bcDiv($termFactor, (string) $termDays);

        return $this->formatRate($derived, self::RATE_SCALE);
    }

    /**
     * Equal installments; final period absorbs rounding remainder.
     *
     * @return array{
     *     installment_amount: string,
     *     installments: list<array{period: int, amount: string}>
     * }
     */
    /**
     * Split a loan component (principal, fee, or interest) across installments.
     * Zero components produce zero amounts for every period instead of throwing.
     *
     * @return list<array{period: int, amount: string}>
     */
    private function splitComponentAcrossTenure(
        float|int|string $amount,
        int $tenureMonths,
        string $field,
    ): array {
        if ($tenureMonths < 1) {
            throw new InvalidArgumentException('tenure_months must be at least 1.');
        }

        $normalized = $this->parseMoneyAmount($amount, $field, allowZero: true);

        if ($this->bcComp($normalized, '0') <= 0) {
            $installments = [];
            for ($period = 1; $period <= $tenureMonths; $period++) {
                $installments[] = ['period' => $period, 'amount' => '0.00'];
            }

            return $installments;
        }

        return $this->calculateInstallments($normalized, $tenureMonths)['installments'];
    }

    public function calculateInstallments(float|int|string $totalAmount, int $tenureMonths): array
    {
        if ($tenureMonths < 1) {
            throw new InvalidArgumentException('tenure_months must be at least 1.');
        }

        $total = $this->normalizeAmount($totalAmount, 'total_amount');

        if ($tenureMonths === 1) {
            $single = $this->roundMoney($total);

            return [
                'installment_amount' => $single,
                'installments' => [
                    ['period' => 1, 'amount' => $single],
                ],
            ];
        }

        $baseInstallment = $this->roundMoney($this->bcDiv($total, (string) $tenureMonths));
        $installments = [];
        $allocated = '0';

        for ($period = 1; $period < $tenureMonths; $period++) {
            $installments[] = [
                'period' => $period,
                'amount' => $baseInstallment,
            ];
            $allocated = $this->bcAdd($allocated, $baseInstallment);
        }

        $finalAmount = $this->roundMoney($this->bcSub($total, $allocated));
        $installments[] = [
            'period' => $tenureMonths,
            'amount' => $finalAmount,
        ];

        return [
            'installment_amount' => $baseInstallment,
            'installments' => $installments,
        ];
    }

    public function resolveRateForAmount(LoanRateType $rateType, int $tenureMonths, float|int|string $principal): ?LoanRate
    {
        if ($tenureMonths < 1) {
            throw new InvalidArgumentException('tenure_months must be at least 1.');
        }

        $principalAmount = (float) $this->normalizeAmount($principal, 'principal');

        $candidates = $rateType->loanRates()
            ->where('tenure_months', $tenureMonths)
            ->where('is_active', true)
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        $bandMatches = $candidates->filter(
            fn (LoanRate $rate) => $rate->matchesPrincipalAmount($principalAmount)
                && ($rate->min_principal !== null || $rate->max_principal !== null)
        );

        if ($bandMatches->isNotEmpty()) {
            return $this->selectMostSpecificBand($bandMatches);
        }

        $openEnded = $candidates->filter(
            fn (LoanRate $rate) => $rate->min_principal === null && $rate->max_principal === null
        );

        if ($openEnded->isNotEmpty()) {
            return $openEnded->sortBy('id')->first();
        }

        $matching = $candidates->filter(
            fn (LoanRate $rate) => $rate->matchesPrincipalAmount($principalAmount)
        );

        return $matching->sortBy('id')->first();
    }

    /**
     * Tenure months that have an active rate applicable to the principal amount.
     *
     * @return list<int>
     */
    public function resolveAvailableTenureMonths(
        LoanRateType $rateType,
        float|int|string $principal,
        ?int $maxTenureMonths = null,
    ): array {
        $configuredTenures = $rateType->loanRates()
            ->where('is_active', true)
            ->orderBy('tenure_months')
            ->pluck('tenure_months')
            ->map(fn ($months) => (int) $months)
            ->unique()
            ->values();

        if ($maxTenureMonths !== null) {
            $configuredTenures = $configuredTenures->filter(
                fn (int $months) => $months <= $maxTenureMonths
            );
        }

        return $configuredTenures
            ->filter(fn (int $months) => $this->resolveRateForAmount($rateType, $months, $principal) !== null)
            ->values()
            ->all();
    }

    public function calculateTermDays(CarbonInterface|string $startDate, int $tenureMonths): int
    {
        if ($tenureMonths < 1) {
            throw new InvalidArgumentException('tenure_months must be at least 1.');
        }

        $start = $startDate instanceof CarbonInterface
            ? $startDate->copy()->startOfDay()
            : Carbon::parse($startDate)->startOfDay();

        $end = $start->copy()->addMonths($tenureMonths);

        return max(1, (int) $start->diffInDays($end));
    }

    private function calculateTermPercentageInterest(
        string $principal,
        LoanRate $loanRate,
        int $termDays,
        string $interestBehavior,
        ?string $termInterestPercentageOverride = null
    ): string {
        $termRateSource = $termInterestPercentageOverride ?? $loanRate->term_interest_percentage;

        if ($termRateSource === null) {
            throw new InvalidArgumentException('term_interest_percentage is required for term percentage pricing.');
        }

        $termRate = $this->normalizeRate($termRateSource, 'term_interest_percentage');

        if ($interestBehavior === LoanRateType::INTEREST_BEHAVIOR_UPFRONT_FLAT) {
            return $this->bcDiv($this->bcMul($principal, $termRate), '100');
        }

        // daily_accrual (and amortized until implemented): projected full-term interest for quotes only
        $derivedDaily = $this->calculateDerivedDailyRateFromTerm($termRate, $termDays);

        return $this->bcMul($this->bcMul($principal, $derivedDaily), (string) $termDays);
    }

    private function calculateDailyMultiplierInterest(
        string $principal,
        LoanRate $loanRate,
        int $termDays,
        ?string $dailyRateOverride = null
    ): string {
        $dailyRate = $dailyRateOverride ?? $loanRate->daily_rate ?? $loanRate->derived_daily_rate;

        if ($dailyRate === null) {
            throw new InvalidArgumentException('daily_rate is required for daily multiplier pricing.');
        }

        $daily = $this->normalizeRate($dailyRate, 'daily_rate');

        return $this->bcMul($this->bcMul($principal, $daily), (string) $termDays);
    }

    private function calculateWeeklyMultiplierInterest(
        string $principal,
        LoanRate $loanRate,
        int $termDays,
        ?string $weeklyRateOverride = null
    ): string {
        $weeklyRate = $weeklyRateOverride ?? $loanRate->weekly_rate;

        if ($weeklyRate === null) {
            throw new InvalidArgumentException('weekly_rate is required for weekly multiplier pricing.');
        }

        $weekly = $this->normalizeRate($weeklyRate, 'weekly_rate');
        $weeks = (int) ceil($termDays / 7);

        return $this->bcMul($this->bcMul($principal, $weekly), (string) $weeks);
    }

    private function resolveDerivedDailyRateForQuote(
        LoanRate $loanRate,
        string $rateInputMode,
        string $interestBehavior,
        int $termDays
    ): ?string {
        if ($loanRate->derived_daily_rate !== null) {
            return $this->formatRate((string) $loanRate->derived_daily_rate, self::RATE_SCALE);
        }

        if ($rateInputMode !== LoanRateType::RATE_INPUT_TERM_PERCENTAGE) {
            if ($loanRate->daily_rate !== null) {
                return $this->formatRate((string) $loanRate->daily_rate, self::RATE_SCALE);
            }

            return null;
        }

        if ($loanRate->term_interest_percentage === null) {
            return null;
        }

        if ($interestBehavior === LoanRateType::INTEREST_BEHAVIOR_UPFRONT_FLAT) {
            return null;
        }

        return $this->calculateDerivedDailyRateFromTerm(
            (string) $loanRate->term_interest_percentage,
            $termDays
        );
    }

    private function selectMostSpecificBand($rates): LoanRate
    {
        return $rates->sort(function (LoanRate $a, LoanRate $b): int {
            $widthA = $this->bandWidth($a);
            $widthB = $this->bandWidth($b);

            if ($widthA !== $widthB) {
                return $widthA <=> $widthB;
            }

            $boundsA = ($a->min_principal !== null ? 1 : 0) + ($a->max_principal !== null ? 1 : 0);
            $boundsB = ($b->min_principal !== null ? 1 : 0) + ($b->max_principal !== null ? 1 : 0);

            if ($boundsA !== $boundsB) {
                return $boundsB <=> $boundsA;
            }

            return $a->id <=> $b->id;
        })->first();
    }

    private function bandWidth(LoanRate $rate): string
    {
        $min = $rate->min_principal !== null ? (string) $rate->min_principal : '0';
        $max = $rate->max_principal !== null ? (string) $rate->max_principal : '999999999999.99';

        return $this->bcSub($max, $min);
    }

    private function resolveRateType(array $payload): LoanRateType
    {
        if (($payload['loan_rate_type'] ?? null) instanceof LoanRateType) {
            return $payload['loan_rate_type'];
        }

        if (($payload['loan_rate'] ?? null) instanceof LoanRate) {
            $rateType = $payload['loan_rate']->loanRateType;
            if ($rateType instanceof LoanRateType) {
                return $rateType;
            }
        }

        throw new InvalidArgumentException('loan_rate_type or loan_rate with a loaded loanRateType is required.');
    }

    private function resolveLoanRate(
        array $payload,
        LoanRateType $rateType,
        string $principal,
        int $tenureMonths
    ): LoanRate {
        if (($payload['loan_rate'] ?? null) instanceof LoanRate) {
            return $payload['loan_rate'];
        }

        $resolved = $this->resolveRateForAmount($rateType, $tenureMonths, $principal);

        if ($resolved === null) {
            throw new InvalidArgumentException('No active loan rate found for the given tenure and principal.');
        }

        return $resolved;
    }

    private function resolveInterestBehavior(
        array $payload,
        LoanRateType $rateType,
        ?LoanProduct $loanProduct = null,
        ?LoanRate $loanRate = null
    ): string {
        if (! empty($payload['interest_behavior'])) {
            return $this->normalizeInterestBehavior($payload['interest_behavior']);
        }

        if ($loanProduct instanceof LoanProduct && $loanRate instanceof LoanRate) {
            return $this->resolveInterestBehaviorForBooking($rateType, $loanProduct, $loanRate);
        }

        return $this->normalizeInterestBehavior($rateType->interest_behavior);
    }

    private function normalizeInterestBehavior(?string $behavior): string
    {
        return in_array($behavior, [
            LoanRateType::INTEREST_BEHAVIOR_DAILY_ACCRUAL,
            LoanRateType::INTEREST_BEHAVIOR_UPFRONT_FLAT,
            LoanRateType::INTEREST_BEHAVIOR_AMORTIZED,
        ], true)
            ? $behavior
            : LoanRateType::INTEREST_BEHAVIOR_DAILY_ACCRUAL;
    }

    private function resolveRateInputMode(array $payload, LoanRateType $rateType, LoanRate $loanRate): string
    {
        if (! empty($payload['rate_input_mode'])) {
            return (string) $payload['rate_input_mode'];
        }

        if ($rateType->rate_input_mode) {
            return $rateType->rate_input_mode;
        }

        if ($loanRate->term_interest_percentage !== null) {
            return LoanRateType::RATE_INPUT_TERM_PERCENTAGE;
        }

        if ($loanRate->weekly_rate !== null && $rateType->accrual_period === LoanRateType::ACCRUAL_PERIOD_WEEKLY) {
            return LoanRateType::RATE_INPUT_WEEKLY_MULTIPLIER;
        }

        return LoanRateType::RATE_INPUT_DAILY_MULTIPLIER;
    }

    private function parseStartDate(mixed $startDate): Carbon
    {
        if ($startDate === null) {
            return Carbon::today()->startOfDay();
        }

        if ($startDate instanceof CarbonInterface) {
            return $startDate->copy()->startOfDay();
        }

        return Carbon::parse($startDate)->startOfDay();
    }

    private function normalizeAmount(float|int|string|null $value, string $field): string
    {
        $normalized = $this->parseMoneyAmount($value, $field, allowZero: false);

        if ($this->bcComp($normalized, '0') <= 0) {
            throw new InvalidArgumentException("{$field} must be greater than zero.");
        }

        return $normalized;
    }

    private function parseMoneyAmount(float|int|string|null $value, string $field, bool $allowZero = false): string
    {
        if ($value === null || $value === '') {
            throw new InvalidArgumentException("{$field} is required.");
        }

        $normalized = is_string($value)
            ? str_replace([',', ' '], '', $value)
            : (string) $value;

        if (! is_numeric($normalized)) {
            throw new InvalidArgumentException("{$field} must be numeric.");
        }

        if (! $allowZero && $this->bcComp($normalized, '0') < 0) {
            throw new InvalidArgumentException("{$field} cannot be negative.");
        }

        if ($allowZero && $this->bcComp($normalized, '0') < 0) {
            throw new InvalidArgumentException("{$field} cannot be negative.");
        }

        return $normalized;
    }

    private function normalizeRate(float|int|string|null $value, string $field): string
    {
        if ($value === null || $value === '') {
            throw new InvalidArgumentException("{$field} is required.");
        }

        $normalized = is_string($value)
            ? str_replace([',', ' ', '%'], '', $value)
            : (string) $value;

        if (! is_numeric($normalized)) {
            throw new InvalidArgumentException("{$field} must be numeric.");
        }

        if ($this->bcComp($normalized, '0') < 0) {
            throw new InvalidArgumentException("{$field} must be zero or greater.");
        }

        return $normalized;
    }

    private function formatRate(string $value, int $scale): string
    {
        if (function_exists('bcadd')) {
            return bcadd($value, '0', $scale);
        }

        return number_format((float) $value, $scale, '.', '');
    }

    private function roundMoney(string $value): string
    {
        if (! function_exists('bcadd')) {
            return number_format(round((float) $value, self::MONEY_SCALE), self::MONEY_SCALE, '.', '');
        }

        $negative = str_starts_with($value, '-');
        $absolute = ltrim($value, '-+');

        if ($this->bcComp($absolute, '0') === 0) {
            return '0.00';
        }

        $increment = $this->bcComp($absolute, '0') > 0 ? '0.005' : '-0.005';
        $rounded = bcadd($absolute, $increment, self::MONEY_SCALE);

        return ($negative ? '-' : '').$rounded;
    }

    private function bcAdd(string $left, string $right): string
    {
        return function_exists('bcadd')
            ? bcadd($left, $right, self::CALC_SCALE)
            : (string) ((float) $left + (float) $right);
    }

    private function bcSub(string $left, string $right): string
    {
        return function_exists('bcsub')
            ? bcsub($left, $right, self::CALC_SCALE)
            : (string) ((float) $left - (float) $right);
    }

    private function bcMul(string $left, string $right): string
    {
        return function_exists('bcmul')
            ? bcmul($left, $right, self::CALC_SCALE)
            : (string) ((float) $left * (float) $right);
    }

    private function bcDiv(string $left, string $right): string
    {
        if ($this->bcComp($right, '0') === 0) {
            throw new InvalidArgumentException('Division by zero.');
        }

        return function_exists('bcdiv')
            ? bcdiv($left, $right, self::CALC_SCALE)
            : (string) ((float) $left / (float) $right);
    }


    private function bcComp(string $left, string $right): int
    {
        if (function_exists('bccomp')) {
            return bccomp($left, $right, self::CALC_SCALE);
        }

        return ((float) $left) <=> ((float) $right);
    }
}
