<?php

namespace App\Services\Hr\EmployeeLoans;

use App\Models\LoanProduct;
use App\Models\LoanRate;
use App\Models\LoanRateType;
use App\Services\LoanPricingService;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class EmployeeLoanPricingService
{
    public const EMPLOYEE_PRODUCT_CODE = 'EMPLOYEE_LOAN';

    public const EMPLOYEE_RATE_TYPE_CODE = 'EMPLOYEE_RATE';

    public function __construct(
        private readonly LoanPricingService $loanPricingService,
    ) {}

    public function employeeProduct(): LoanProduct
    {
        return LoanProduct::query()->where('code', self::EMPLOYEE_PRODUCT_CODE)->firstOrFail();
    }

    public function employeeRateType(): LoanRateType
    {
        return LoanRateType::query()->where('code', self::EMPLOYEE_RATE_TYPE_CODE)->firstOrFail();
    }

    public function assertEmployeeRate(LoanRate $loanRate): void
    {
        $rateType = $loanRate->loanRateType;
        if (! $rateType || $rateType->code !== self::EMPLOYEE_RATE_TYPE_CODE) {
            throw ValidationException::withMessages([
                'loan_rate_id' => 'Selected rate must belong to the Employee Rate plan.',
            ]);
        }

        $product = $rateType->loanProduct;
        if (! $product || ! $product->isEmployeeLoanProduct()) {
            throw ValidationException::withMessages([
                'loan_rate_id' => 'Selected rate is not valid for employee loans.',
            ]);
        }
    }

    /**
     * @param  array{
     *     loan_rate_id: int,
     *     principal: float|int|string,
     *     tenure_months: int,
     *     start_date?: string|null,
     *     first_payment_date?: string|null,
     *     repayment_frequency?: string|null
     * }  $input
     * @return array{quote: array<string, mixed>, snapshot: array<string, mixed>, schedule_plan: array<string, mixed>}
     */
    public function quote(array $input): array
    {
        $loanRate = LoanRate::query()->with('loanRateType.loanProduct')->findOrFail($input['loan_rate_id']);
        $this->assertEmployeeRate($loanRate);

        $tenureMonths = (int) $input['tenure_months'];
        $principal = (float) $input['principal'];
        $startDate = isset($input['start_date'])
            ? Carbon::parse($input['start_date'])
            : Carbon::today();

        $quote = $this->loanPricingService->quoteLoan([
            'loan_rate_id' => $loanRate->id,
            'loan_rate' => $loanRate,
            'loan_rate_type' => $loanRate->loanRateType,
            'loan_rate_type_id' => $loanRate->loan_rate_type_id,
            'loan_product' => $loanRate->loanRateType->loanProduct,
            'principal' => $principal,
            'tenure_months' => $tenureMonths,
            'start_date' => $startDate->toDateString(),
        ]);

        $snapshot = $this->buildEmployeeLoanSnapshot($quote);
        $schedulePlan = $this->buildSchedulePlan($quote, $snapshot);

        return [
            'quote' => $quote,
            'snapshot' => $snapshot,
            'schedule_plan' => $schedulePlan,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildEmployeeLoanSnapshot(array $quote): array
    {
        $base = $this->loanPricingService->buildLoanFinancialSnapshot($quote);

        return array_merge($base, [
            'loan_rate_id' => $quote['loan_rate_id'] ?? null,
            'principal_amount' => $quote['principal'],
            'tenure_months' => (int) $quote['tenure_months'],
            'metadata' => [
                'pricing_quote' => $quote,
                'pricing_snapshot_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * @return array{principal: float, processing_fee: float, interest: float, schedule_basis: string, is_projected_interest: bool}
     */
    public function buildSchedulePlan(array $quote, array $snapshot): array
    {
        $meta = $snapshot['pricing_metadata'] ?? [];
        $behavior = $snapshot['interest_behavior'] ?? null;
        $usesProjected = (bool) ($meta['schedule_uses_projected_interest'] ?? false);

        $principal = (float) $quote['principal'];
        $processingFee = (float) $quote['processing_fee'];
        $interest = $usesProjected ? (float) $quote['interest'] : (float) ($meta['projected_interest'] ?? $quote['interest']);

        return [
            'principal' => $principal,
            'processing_fee' => $processingFee,
            'interest' => $interest,
            'schedule_basis' => $meta['schedule_basis'] ?? 'booked_total',
            'is_projected_interest' => $usesProjected,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function preview(array $input): array
    {
        $result = $this->quote($input);
        $quote = $result['quote'];
        $installments = $this->loanPricingService->calculateComponentInstallments(
            $result['schedule_plan']['principal'],
            $result['schedule_plan']['processing_fee'],
            $result['schedule_plan']['interest'],
            (int) $quote['tenure_months'],
        );

        return [
            'principal' => $quote['principal'],
            'interest' => $quote['interest'],
            'processing_fee' => $quote['processing_fee'],
            'total_repayable' => $quote['total_amount'],
            'installment_amount' => $quote['installment_amount'] ?? ($installments['installments'][0]['expected_amount'] ?? null),
            'installment_count' => count($installments['installments']),
            'quoted_term_rate' => $quote['quoted_term_rate'] ?? null,
            'arrear_rate' => $quote['arrear_rate'] ?? null,
            'installments' => $installments['installments'],
            'quote' => $quote,
        ];
    }

    public function resolveRateForTenureAndPrincipal(int $tenureMonths, float $principal): LoanRate
    {
        $rateType = $this->employeeRateType();
        $loanRate = $this->loanPricingService->resolveRateForAmount($rateType, $tenureMonths, $principal);

        if (! $loanRate) {
            throw ValidationException::withMessages([
                'tenure_months' => 'No active Employee Rate found for this tenure and principal amount.',
            ]);
        }

        $this->assertEmployeeRate($loanRate);

        return $loanRate;
    }
}
