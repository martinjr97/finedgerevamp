<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\UsesLoanPricing;
use App\Http\Controllers\Controller;
use App\Models\CustomerGroup;
use App\Models\LoanProduct;
use App\Models\LoanRate;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LoanCalculatorController extends Controller
{
    use UsesLoanPricing;

    public function index(): View
    {
        abort_unless(auth('admin')->user()?->can('loans.view'), 403);

        $loanProducts = LoanProduct::with(['company', 'customerGroups' => function ($q) {
            $q->where('is_active', true)->orderBy('name');
        }, 'customerGroups.loanRateType' => function ($q) {
            $q->with('loanRates');
        }])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('admin.loan-calculator.index', [
            'loanProducts' => $loanProducts,
        ]);
    }

    public function groups(Request $request): JsonResponse
    {
        abort_unless(auth('admin')->user()?->can('loans.view'), 403);

        $validated = $request->validate([
            'loan_product_id' => 'required|exists:loan_products,id',
        ]);

        $groups = CustomerGroup::where('loan_product_id', $validated['loan_product_id'])
            ->where('is_active', true)
            ->with(['loanRateType' => function ($q) {
                $q->with(['loanRates' => fn ($rates) => $rates->where('is_active', true)->orderBy('tenure_months')]);
            }])
            ->orderBy('name')
            ->get()
            ->map(function ($group) {
                $rateType = $group->loanRateType;

                $configuredTenures = $rateType?->loanRates
                    ->pluck('tenure_months')
                    ->map(fn ($months) => (int) $months)
                    ->unique()
                    ->sort()
                    ->values();

                $groupMaxTenure = $group->max_loan_tenure_months !== null
                    ? (int) $group->max_loan_tenure_months
                    : null;

                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'code' => $group->code,
                    'max_amount' => $group->max_loan_amount,
                    'max_tenure_months' => $groupMaxTenure,
                    'loan_rate_type_id' => $group->loan_rate_type_id,
                    'rate_type_name' => $rateType?->name,
                    'accrual_period' => $rateType?->accrual_period,
                    'interest_behavior' => $rateType?->interest_behavior,
                    'rate_input_mode' => $rateType?->rate_input_mode,
                    'available_tenures' => $configuredTenures->all(),
                    'allowed_tenures' => $groupMaxTenure
                        ? $configuredTenures->filter(fn (int $months) => $months <= $groupMaxTenure)->values()->all()
                        : $configuredTenures->all(),
                ];
            });

        return response()->json(['groups' => $groups]);
    }

    public function calculate(Request $request): JsonResponse
    {
        abort_unless(auth('admin')->user()?->can('loans.view'), 403);

        $validated = $request->validate([
            'loan_product_id' => 'required|exists:loan_products,id',
            'customer_group_id' => 'required|exists:customer_groups,id',
            'amount' => 'required|numeric|min:1',
            'start_date' => 'nullable|date',
        ]);

        $group = CustomerGroup::with(['loanRateType.loanRates' => function ($q) {
            $q->where('is_active', true)->orderBy('tenure_months');
        }, 'loanProduct'])
            ->where('id', $validated['customer_group_id'])
            ->where('loan_product_id', $validated['loan_product_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $rateType = $group->loanRateType;
        if (! $rateType || $rateType->loanRates->isEmpty()) {
            return response()->json(['error' => 'No active loan rates configured for this group.'], 422);
        }

        $loanProduct = $group->loanProduct ?? LoanProduct::findOrFail($validated['loan_product_id']);

        if ((int) $rateType->loan_product_id !== (int) $loanProduct->id) {
            return response()->json([
                'error' => 'This group\'s rate card is not linked to the selected product. Update the group or choose a matching product.',
            ], 422);
        }
        $loanAmount = (float) $validated['amount'];
        $startDate = Carbon::parse($validated['start_date'] ?? now())->startOfDay();

        $groupMaxTenure = $group->max_loan_tenure_months !== null
            ? (int) $group->max_loan_tenure_months
            : null;
        $maxAmount = min(array_filter([
            $group->max_loan_amount,
            $loanProduct->max_amount,
        ], fn ($v) => $v !== null && $v > 0)) ?: null;

        if ($maxAmount && $loanAmount > $maxAmount) {
            return response()->json([
                'error' => 'Amount exceeds this group/product limit of '.number_format($maxAmount, 2),
            ], 422);
        }

        $rows = [];

        $tenureMonthsList = $rateType->loanRates
            ->pluck('tenure_months')
            ->map(fn ($months) => (int) $months)
            ->unique()
            ->sort()
            ->values();

        foreach ($tenureMonthsList as $tenureMonths) {
            $rate = $this->loanPricing()->resolveRateForAmount($rateType, $tenureMonths, $loanAmount);
            if ($rate === null) {
                continue;
            }

            $exceedsGroupMaxTenure = $groupMaxTenure !== null && $tenureMonths > $groupMaxTenure;

            $endDate = $startDate->copy()->addMonths($tenureMonths);
            $days = $this->loanPricing()->calculateTermDays($startDate, $tenureMonths);

            try {
                $quote = $this->buildLoanPricingQuote(
                    principal: $loanAmount,
                    tenureMonths: $tenureMonths,
                    loanStartDate: $startDate,
                    loanProduct: $loanProduct,
                    rateType: $rateType,
                    loanRate: $rate,
                    termDays: $days,
                    loanEndDate: $endDate,
                );
            } catch (\InvalidArgumentException) {
                continue;
            }

            $financials = $this->loanFinancialAttributesFromQuote($quote);
            $projectedTotal = (float) $quote['total_amount'];
            $bookedTotal = (float) $financials['total_amount'];
            $bookedInterest = (float) ($financials['interest_accrued'] ?? 0);
            $monthly = $tenureMonths > 0 ? $bookedTotal / $tenureMonths : $bookedTotal;

            $rows[] = [
                'tenure_months' => $tenureMonths,
                'exceeds_group_max_tenure' => $exceedsGroupMaxTenure,
                'processing_fee' => (float) $quote['processing_fee'],
                'interest' => (float) $quote['interest'],
                'projected_interest' => (float) $quote['interest'],
                'booked_interest' => $bookedInterest,
                'total' => $bookedTotal,
                'projected_total' => $projectedTotal,
                'booked_total' => $bookedTotal,
                'monthly' => $monthly,
                'daily_rate' => $financials['daily_rate'] ?? $quote['derived_daily_rate'] ?? $quote['daily_rate'],
                'weekly_rate' => $quote['weekly_rate'],
                'processing_fee_percentage' => $quote['processing_fee_percentage'],
                'term_interest_percentage' => $quote['quoted_term_rate'],
                'loan_rate_id' => $rate->id,
                'interest_behavior' => $quote['interest_behavior'],
                'rate_input_mode' => $quote['rate_input_mode'],
                'accrual_type' => $financials['accrual_type'],
                'rate_type_name' => $rateType->name,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'days' => $days,
            ];
        }

        if (empty($rows)) {
            return response()->json(['error' => 'No valid rate tenures found for this group.'], 422);
        }

        return response()->json([
            'amount' => $loanAmount,
            'accrual_period' => $rateType->accrual_period,
            'interest_behavior' => $rateType->interest_behavior,
            'rate_input_mode' => $rateType->rate_input_mode,
            'rate_type_name' => $rateType->name,
            'group_name' => $group->name,
            'product_name' => $loanProduct->name,
            'group_max_tenure_months' => $groupMaxTenure,
            'rows' => $rows,
        ]);
    }
}
