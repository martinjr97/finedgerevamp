<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\EnforcesCustomerLoanEligibility;
use App\Http\Controllers\Concerns\ResolvesDisbursementDestination;
use App\Http\Controllers\Controller;
use App\Support\DocumentUploadRules;
use App\Http\Controllers\Concerns\UsesLoanPricing;
use App\Models\FinancialInstitution;
use App\Models\Admin;
use App\Services\CustomerPaymentDetailPrefillService;
use App\Services\LoanPayDayScheduleService;
use App\Models\Channel;
use App\Models\CollateralLoanDetail;
use App\Models\CollateralType;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Loan;
use App\Models\LoanPurpose;
use App\Models\LoanProduct;
use App\Models\LoanRate;
use App\Models\LoanRateType;
use App\Models\Company;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;

class LoanApplicationController extends Controller
{
    use EnforcesCustomerLoanEligibility;
    use ResolvesDisbursementDestination;
    use UsesLoanPricing;

    /**
     * Show the loan application product selection page
     */
    public function index(): View
    {
        abort_unless(auth('admin')->user()?->can('loans.create'), 403);
        
        // Get collateral-based loan products
        $collateralProducts = LoanProduct::where('category', 'collateral')
            ->where('is_active', true)
            ->with('company')
            ->get();

        // Get government loan products
        $governmentProducts = LoanProduct::where('category', 'government')
            ->where('is_active', true)
            ->with('company')
            ->get();

        // Get character-based loan products
        $characterProducts = LoanProduct::where('category', 'character')
            ->where('is_active', true)
            ->with('company')
            ->get();

        // Get MOU-based loan products
        $mouProducts = LoanProduct::where('category', 'mou')
            ->where('is_active', true)
            ->with('company')
            ->get();

        // Get SME products
        $smeProducts = LoanProduct::where('category', 'sme')
            ->where('is_active', true)
            ->with('company')
            ->get();

        // Get group loan products
        $groupLoanProducts = LoanProduct::where('category', 'group_loans')
            ->where('is_active', true)
            ->with('company')
            ->get();
        
        return view('admin.loan-applications.index', [
            'collateralProducts' => $collateralProducts,
            'governmentProducts' => $governmentProducts,
            'characterProducts' => $characterProducts,
            'mouProducts' => $mouProducts,
            'smeProducts' => $smeProducts,
            'groupLoanProducts' => $groupLoanProducts,
        ]);
    }

    /**
     * Show step 2: Customer search
     */
    public function searchCustomer(Request $request, LoanProduct $loanProduct): View|RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('loans.create'), 403);

        if ($loanProduct->category === 'group_loans') {
            return redirect()->route('admin.loan-applications.group-loans.members', $loanProduct);
        }

        // Allow only configured categories in this flow
        if (! in_array($loanProduct->category, ['collateral', 'mou', 'character', 'government', 'sme'], true)) {
            abort(404, 'This loan product is not supported in the loan applications flow.');
        }
        
        $flowType = $loanProduct->category;
        
        return view('admin.loan-applications.search-customer', compact('loanProduct', 'flowType'));
    }

    /**
     * Search for customer via AJAX
     */
    public function searchCustomerAjax(Request $request, LoanProduct $loanProduct): JsonResponse
    {
        abort_unless(auth('admin')->user()?->can('loans.create'), 403);
        
        $search = $request->input('search', '');
        
        if (empty($search)) {
            return response()->json(['customers' => []]);
        }
        
        // Search customers by phone, NRC, or name - must belong to this loan product and be active
        $customers = Customer::where('loan_product_id', $loanProduct->id)
            ->where('status', 'active')
            ->where(function ($query) use ($search) {
                $query->where('phone', 'like', "%{$search}%")
                    ->orWhere('national_id', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"]);
            })
            ->with(['customerGroup', 'company', 'parentCustomer'])
            ->limit(10)
            ->get()
            ->map(function ($customer) {
                $borrower = $customer;
                if ($customer->customer_type === 'representative' && $customer->parentCustomer) {
                    $borrower = $customer->parentCustomer;
                }

                return [
                    'id' => $customer->id,
                    'borrower_id' => $borrower->id,
                    'customer_type' => $customer->customer_type ?? 'individual',
                    'parent_name' => $customer->parentCustomer?->registered_name ?? $customer->parentCustomer?->full_name,
                    'company_name' => $borrower->company->name ?? null,
                    'name' => trim(($customer->registered_name ?? '') . ' ' . ($customer->first_name . ' ' . $customer->last_name)),
                    'phone' => $customer->phone,
                    'national_id' => $customer->national_id,
                    'email' => $customer->email,
                    'customer_group' => $customer->customerGroup ? $customer->customerGroup->name : 'N/A',
                    'maximum_loan_take' => $borrower->maximum_loan_take ?? 0,
                    'available_loan_amount' => $borrower->getAvailableLoanAmount(),
                ];
            });
        
        return response()->json(['customers' => $customers]);
    }

    /**
     * Show step 3: Loan details form
     */
    public function loanDetails(Request $request, LoanProduct $loanProduct, Customer $customer): View|RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('loans.create'), 403);
        
        // Verify customer belongs to this loan product
        if ($customer->loan_product_id !== $loanProduct->id) {
            return redirect()->route('admin.loan-applications.search-customer', $loanProduct)
                ->with('error', 'Customer does not belong to this loan product.');
        }
        
        $flowType = $loanProduct->category;
        $representative = null;

        // For SME representatives, switch borrower to parent company customer
        if ($loanProduct->category === 'sme' && $customer->customer_type === 'representative') {
            $representative = $customer;
            $customer = $customer->parentCustomer;
            if (! $customer) {
                return redirect()->route('admin.loan-applications.search-customer', $loanProduct)
                    ->with('error', 'Representative is not linked to a company borrower.');
            }
        }

        $borrower = $customer;

        if ($redirect = $this->loanEligibilityRedirect($borrower, $loanProduct)) {
            return $redirect;
        }

        if ($loanProduct->category === 'collateral') {
            // Get customer group and rate type from group
            $customerGroup = $customer->customerGroup;
            if (! $customerGroup) {
                return redirect()->route('admin.loan-applications.search-customer', $loanProduct)
                    ->with('error', 'Customer does not have an assigned group.');
            }

            $rateType = $customerGroup->loanRateType;
            if (! $rateType) {
                return redirect()->route('admin.loan-applications.search-customer', $loanProduct)
                    ->with('error', 'Customer group does not have a rate type configured.');
            }

            // Get available loan rates
            $loanRates = LoanRate::where('loan_rate_type_id', $rateType->id)
                ->where('is_active', true)
                ->orderBy('tenure_months')
                ->get();

            // Get available loan amount (group + product constraints)
            $availableLoanAmount = $borrower->getAvailableLoanAmount();
            $maxLoanAmount = min(
                $availableLoanAmount,
                $customerGroup->max_loan_amount ?? PHP_INT_MAX,
                $loanProduct->max_amount ?? PHP_INT_MAX
            );

            $contextLabel = 'Customer Group';
            $contextName = $customerGroup->name;
        } elseif ($loanProduct->category === 'mou') {
            // Use company-level configuration for MOU loans
            $company = $customer->company;
            if (! $company) {
                return redirect()->route('admin.loan-applications.search-customer', $loanProduct)
                    ->with('error', 'Customer is not linked to a company.');
            }

            $rateType = $company->loanRateType;
            if (! $rateType) {
                return redirect()->route('admin.loan-applications.search-customer', $loanProduct)
                    ->with('error', 'Company does not have a rate type configured.');
            }

            $loanRates = LoanRate::where('loan_rate_type_id', $rateType->id)
                ->where('is_active', true)
                ->orderBy('tenure_months')
                ->get();

            $availableLoanAmount = $borrower->getAvailableLoanAmount();
            $maxLoanAmount = min(
                $availableLoanAmount,
                $loanProduct->max_amount ?? PHP_INT_MAX
            );

            $contextLabel = 'Company';
            $contextName = $company->name;
            $customerGroup = null;
        } elseif ($loanProduct->category === 'sme') {
            // SME uses company-level rate type, borrower is the company customer
            $company = $customer->company;
            if (! $company) {
                return redirect()->route('admin.loan-applications.search-customer', $loanProduct)
                    ->with('error', 'Customer is not linked to a company.');
            }

            $rateType = $company->loanRateType;
            if (! $rateType) {
                return redirect()->route('admin.loan-applications.search-customer', $loanProduct)
                    ->with('error', 'Company does not have a rate type configured.');
            }

            $loanRates = LoanRate::where('loan_rate_type_id', $rateType->id)
                ->where('is_active', true)
                ->orderBy('tenure_months')
                ->get();

            $availableLoanAmount = $borrower->getAvailableLoanAmount();
            $maxLoanAmount = min(
                $availableLoanAmount,
                $loanProduct->max_amount ?? PHP_INT_MAX
            );

            $contextLabel = 'Company';
            $contextName = $company->name;
            $customerGroup = null;
        } elseif ($loanProduct->category === 'character') {
            // Character-based loans use customer group configuration (similar to collateral) but without collateral step
            $customerGroup = $customer->customerGroup;
            if (! $customerGroup) {
                return redirect()->route('admin.loan-applications.search-customer', $loanProduct)
                    ->with('error', 'Customer does not have an assigned group.');
            }

            $rateType = $customerGroup->loanRateType;
            if (! $rateType) {
                return redirect()->route('admin.loan-applications.search-customer', $loanProduct)
                    ->with('error', 'Customer group does not have a rate type configured.');
            }

            $loanRates = LoanRate::where('loan_rate_type_id', $rateType->id)
                ->where('is_active', true)
                ->orderBy('tenure_months')
                ->get();

            $availableLoanAmount = $borrower->getAvailableLoanAmount();
            $maxLoanAmount = min(
                $availableLoanAmount,
                $customerGroup->max_loan_amount ?? PHP_INT_MAX,
                $loanProduct->max_amount ?? PHP_INT_MAX
            );

            $contextLabel = 'Customer Group';
            $contextName = $customerGroup->name;
        } elseif ($loanProduct->category === 'government') {
            // Government loans use customer group configuration with cut-off and pay dates (similar to MOU logic)
            $customerGroup = $customer->customerGroup;
            if (! $customerGroup) {
                return redirect()->route('admin.loan-applications.search-customer', $loanProduct)
                    ->with('error', 'Customer does not have an assigned group.');
            }

            $rateType = $customerGroup->loanRateType;
            if (! $rateType) {
                return redirect()->route('admin.loan-applications.search-customer', $loanProduct)
                    ->with('error', 'Customer group does not have a rate type configured.');
            }

            $loanRates = LoanRate::where('loan_rate_type_id', $rateType->id)
                ->where('is_active', true)
                ->orderBy('tenure_months')
                ->get();

            $availableLoanAmount = $borrower->getAvailableLoanAmount();
            $maxLoanAmount = min(
                $availableLoanAmount,
                $customerGroup->max_loan_amount ?? PHP_INT_MAX,
                $loanProduct->max_amount ?? PHP_INT_MAX
            );

            $contextLabel = 'Customer Group';
            $contextName = $customerGroup->name;
        } else {
            abort(404, 'This loan product is not supported in the loan applications flow.');
        }

        // Get disbursement channels
        $channels = Channel::where('can_disburse', true)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        if ($loanProduct->category === 'mou' || $loanProduct->category === 'sme') {
            $nextStepUrl = route('admin.loan-applications.review', [$loanProduct, $customer]);
        } elseif ($loanProduct->category === 'character') {
            $nextStepUrl = route('admin.loan-applications.review-character', [$loanProduct, $customer]);
        } elseif ($loanProduct->category === 'government') {
            $nextStepUrl = route('admin.loan-applications.review-government', [$loanProduct, $customer]);
        } else {
            $nextStepUrl = route('admin.loan-applications.collateral', [$loanProduct, $customer]);
        }

        $financialInstitutions = FinancialInstitution::query()
            ->active()
            ->with(['branches' => fn ($query) => $query->active()->orderBy('name')])
            ->orderBy('name')
            ->get();

        $customer->loadMissing('paymentDetail');

        $sessionLoanData = $request->session()->get('loan_application_data', []);
        $paymentDetailPrefill = app(CustomerPaymentDetailPrefillService::class)
            ->disbursementDefaults($customer, $channels);

        $disbursementFieldKeys = [
            'channel_id',
            'disbursement_phone_number',
            'disbursement_financial_institution_id',
            'disbursement_financial_institution_branch_id',
            'disbursement_account_holder_name',
            'disbursement_account_number',
            'disbursement_notes',
        ];

        $disbursementDefaults = [];
        foreach ($disbursementFieldKeys as $key) {
            $sessionValue = $sessionLoanData[$key] ?? null;
            $prefillValue = $paymentDetailPrefill[$key] ?? null;
            $disbursementDefaults[$key] = filled($sessionValue) ? $sessionValue : $prefillValue;
        }

        $hasSavedPaymentDetails = $customer->paymentDetail !== null;
        $paymentDetailsPrefilled = $paymentDetailPrefill !== null
            && collect($disbursementFieldKeys)->contains(
                fn (string $key): bool => filled($disbursementDefaults[$key] ?? null)
                    && ! filled($sessionLoanData[$key] ?? null)
            );

        return view('admin.loan-applications.loan-details', [
            'loanProduct' => $loanProduct,
            'customer' => $customer,
            'customerGroup' => $customerGroup,
            'rateType' => $rateType,
            'loanRates' => $loanRates,
            'channels' => $channels,
            'financialInstitutions' => $financialInstitutions,
            'maxLoanAmount' => $maxLoanAmount,
            'availableLoanAmount' => $availableLoanAmount,
            'contextLabel' => $contextLabel,
            'contextName' => $contextName,
            'flowType' => $flowType,
            'nextStepUrl' => $nextStepUrl,
            'representative' => $representative,
            'sessionLoanData' => $sessionLoanData,
            'disbursementDefaults' => $disbursementDefaults,
            'hasSavedPaymentDetails' => $hasSavedPaymentDetails,
            'paymentDetailsPrefilled' => $paymentDetailsPrefilled,
            'loanPurposes' => LoanPurpose::orderedActive(),
        ]);
    }

    /**
     * Calculate loan repayment amount
     */
    public function calculateRepayment(Request $request, LoanProduct $loanProduct, Customer $customer): JsonResponse
    {
        abort_unless(auth('admin')->user()?->can('loans.create'), 403);

        $borrower = $this->resolveLoanApplicationBorrower($customer, $loanProduct);
        if ($error = $this->loanEligibilityJsonError($borrower, $loanProduct)) {
            return $error;
        }

        $validated = $request->validate([
            'loan_amount' => 'required|numeric|min:1',
            'tenure_months' => 'required|integer|min:1',
            'loan_start_date' => 'required|date',
        ]);
        
        $loanAmount = (float) $validated['loan_amount'];
        $tenureMonths = (int) $validated['tenure_months'];
        $loanStartDate = Carbon::parse($validated['loan_start_date']);
        $loanEndDate = $loanStartDate->copy()->addMonths($tenureMonths);
        $days = $loanStartDate->diffInDays($loanEndDate);

        if (in_array($loanProduct->category, ['collateral', 'character'], true)) {
            // Get customer group and rate from group
            $customerGroup = $borrower->customerGroup;
            $rateType = $customerGroup->loanRateType;

            $availableLoanAmount = $borrower->getAvailableLoanAmount();
            $maxLoanAmount = min(
                $availableLoanAmount,
                $customerGroup->max_loan_amount ?? PHP_INT_MAX,
                $loanProduct->max_amount ?? PHP_INT_MAX
            );
        } elseif ($loanProduct->category === 'mou' || $loanProduct->category === 'sme') {
            // Use company configuration for MOU/SME products
            $company = $borrower->company;
            $rateType = $company?->loanRateType;

            if (! $company || ! $rateType) {
                return response()->json([
                    'error' => 'Company or loan rate configuration is missing for this customer.',
                ], 422);
            }

            $availableLoanAmount = $borrower->getAvailableLoanAmount();
            $maxLoanAmount = min(
                $availableLoanAmount,
                $loanProduct->max_amount ?? PHP_INT_MAX
            );

            if ($loanProduct->category === 'sme') {
                // SME repayments are monthly from loan start date.
                $schedule = $this->calculateSmeSchedule($loanStartDate, $tenureMonths);
            } else {
                // MOU repayments use company's monthly cut-off and pay day.
                $schedule = $this->calculateMouSchedule($loanStartDate, $company, $tenureMonths);
                if (isset($schedule['error'])) {
                    return response()->json(['error' => $schedule['error']], 422);
                }
            }

            $loanEndDate = $schedule['loan_end_date'];
            $days = $schedule['days'];
        } elseif ($loanProduct->category === 'government') {
            // Use customer group configuration for government products (cut-off and pay dates)
            $customerGroup = $borrower->customerGroup;
            $rateType = $customerGroup?->loanRateType;

            if (! $customerGroup || ! $rateType) {
                return response()->json([
                    'error' => 'Customer group or loan rate configuration is missing for this customer.',
                ], 422);
            }

            $availableLoanAmount = $borrower->getAvailableLoanAmount();
            $maxLoanAmount = min(
                $availableLoanAmount,
                $customerGroup->max_loan_amount ?? PHP_INT_MAX,
                $loanProduct->max_amount ?? PHP_INT_MAX
            );

            $schedule = $this->calculateGovernmentSchedule($loanStartDate, $customerGroup, $tenureMonths);
            if (isset($schedule['error'])) {
                return response()->json(['error' => $schedule['error']], 422);
            }

            $loanEndDate = $schedule['loan_end_date'];
            $days = $schedule['days'];
        } else {
            return response()->json([
                'error' => 'This loan product is not supported in the loan applications flow.',
            ], 422);
        }
        
        if ($loanAmount > $maxLoanAmount) {
            return response()->json([
                'error' => "Loan amount cannot exceed your maximum qualified amount of " . number_format($maxLoanAmount, 2) . ". Your available loan amount is " . number_format($availableLoanAmount, 2) . ".",
            ], 422);
        }
        
        $loanRate = $this->loanPricing()->resolveRateForAmount($rateType, $tenureMonths, $loanAmount);

        if (! $loanRate) {
            return response()->json([
                'error' => 'Loan rate not found for the selected tenure and amount.',
            ], 422);
        }

        $quote = $this->buildLoanPricingQuote(
            principal: $loanAmount,
            tenureMonths: $tenureMonths,
            loanStartDate: $loanStartDate,
            loanProduct: $loanProduct,
            rateType: $rateType,
            loanRate: $loanRate,
            termDays: $days,
            loanEndDate: $loanEndDate,
        );

        $ratesForTenure = $rateType->loanRates()
            ->where('tenure_months', $tenureMonths)
            ->where('is_active', true)
            ->orderBy('min_principal')
            ->orderBy('id')
            ->get()
            ->map(fn (LoanRate $rate) => [
                'id' => $rate->id,
                'tenure_months' => $rate->tenure_months,
                'processing_fee_percentage' => (float) $rate->processing_fee_percentage,
                'term_interest_percentage' => $rate->term_interest_percentage !== null
                    ? (float) $rate->term_interest_percentage
                    : null,
                'min_principal' => $rate->min_principal !== null ? (float) $rate->min_principal : null,
                'max_principal' => $rate->max_principal !== null ? (float) $rate->max_principal : null,
                'daily_rate' => $rate->daily_rate !== null ? (float) $rate->daily_rate : null,
                'weekly_rate' => $rate->weekly_rate !== null ? (float) $rate->weekly_rate : null,
                'arrear_rate' => $rate->arrear_rate !== null ? (float) $rate->arrear_rate : null,
                'is_applied' => $rate->id === $loanRate->id,
            ])
            ->values()
            ->all();

        $paymentDueDates = $this->resolvePaymentDueDatesForApplication(
            $loanProduct,
            $customer,
            $loanStartDate,
            $tenureMonths
        );

        return response()->json($this->loanPricing()->formatRepaymentQuoteResponse($quote, [
            'loan_end_date' => $loanEndDate->toDateString(),
            'days' => $days,
            'rate_type_name' => $rateType->name,
            'rate_input_mode' => $rateType->rate_input_mode,
            'applied_rate' => [
                'id' => $loanRate->id,
                'tenure_months' => $loanRate->tenure_months,
                'processing_fee_percentage' => (float) $loanRate->processing_fee_percentage,
                'term_interest_percentage' => $loanRate->term_interest_percentage !== null
                    ? (float) $loanRate->term_interest_percentage
                    : null,
                'min_principal' => $loanRate->min_principal !== null ? (float) $loanRate->min_principal : null,
                'max_principal' => $loanRate->max_principal !== null ? (float) $loanRate->max_principal : null,
                'daily_rate' => $loanRate->daily_rate !== null ? (float) $loanRate->daily_rate : null,
                'weekly_rate' => $loanRate->weekly_rate !== null ? (float) $loanRate->weekly_rate : null,
                'arrear_rate' => $loanRate->arrear_rate !== null ? (float) $loanRate->arrear_rate : null,
            ],
            'rates_for_tenure' => $ratesForTenure,
            'repayment_schedule' => $this->buildApplicationRepaymentSchedulePreview(
                $quote,
                $paymentDueDates,
                $tenureMonths,
                $loanStartDate
            ),
        ]));
    }

    /**
     * Store loan calculation data in session
     */
    public function storeCalculation(Request $request, LoanProduct $loanProduct, Customer $customer): JsonResponse
    {
        abort_unless(auth('admin')->user()?->can('loans.create'), 403);

        $borrower = $this->resolveLoanApplicationBorrower($customer, $loanProduct);
        if ($error = $this->loanEligibilityJsonError($borrower, $loanProduct)) {
            return $error;
        }
        
        if ($request->boolean('include_destination')) {
            $existing = $request->session()->get('loan_application_data', []);

            if (! data_get($existing, 'total_amount')) {
                return response()->json([
                    'error' => 'Please calculate the loan before continuing.',
                ], 422);
            }

            $purposeValidated = $request->validate(LoanPurpose::idValidationRules());

            $destinationNormalized = $this->normalizeDisbursementDestination(
                $this->destinationPayloadFromArray($request->all())
            );

            $sessionData = array_merge($existing, $destinationNormalized, $purposeValidated);

            if ($request->boolean('save_customer_payment_details')) {
                app(CustomerPaymentDetailPrefillService::class)
                    ->syncFromNormalizedDestination($customer, $destinationNormalized);
            }

            $request->session()->put('loan_application_data', $sessionData);

            return response()->json([
                'success' => true,
                'redirect_url' => $this->nextStepUrlForProduct($loanProduct, $customer),
            ]);
        }

        $calcValidated = $request->validate([
            'loan_amount' => 'required|numeric|min:1',
            'tenure_months' => 'required|integer|min:1',
            'loan_start_date' => 'required|date',
            'processing_fee' => 'required|numeric',
            'interest' => 'required|numeric',
            'total_amount' => 'required|numeric',
            'loan_end_date' => 'required|date',
            'days' => 'required|integer',
            'loan_rate_id' => 'required|exists:loan_rates,id',
            'daily_rate' => 'nullable|numeric',
            'weekly_rate' => 'nullable|numeric',
            'accrual_period' => 'required|string',
        ]);

        $scheduleExtras = $this->buildRepaymentScheduleExtrasForSession($loanProduct, $customer, $calcValidated);

        $request->session()->put('loan_application_data', array_merge(
            $request->session()->get('loan_application_data', []),
            $calcValidated,
            $scheduleExtras
        ));

        return response()->json(['success' => true]);
    }

    private function nextStepUrlForProduct(LoanProduct $loanProduct, Customer $customer): string
    {
        if (in_array($loanProduct->category, ['mou', 'sme'], true)) {
            return route('admin.loan-applications.review', [$loanProduct, $customer]);
        }

        if ($loanProduct->category === 'character') {
            return route('admin.loan-applications.review-character', [$loanProduct, $customer]);
        }

        if ($loanProduct->category === 'government') {
            return route('admin.loan-applications.review-government', [$loanProduct, $customer]);
        }

        return route('admin.loan-applications.collateral', [$loanProduct, $customer]);
    }

    /**
     * Show step 4: Collateral selection
     */
    public function collateral(Request $request, LoanProduct $loanProduct, Customer $customer): View|RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('loans.create'), 403);

        if ($loanProduct->category !== 'collateral') {
            abort(404);
        }
        
        // Get loan details from session
        $loanData = $request->session()->get('loan_application_data');
        
        if (!$loanData) {
            return redirect()->route('admin.loan-applications.loan-details', [$loanProduct, $customer])
                ->with('error', 'Please complete the loan details first.');
        }

        if ($redirect = $this->redirectIfLoanPurposeMissing($loanProduct, $customer, $loanData)) {
            return $redirect;
        }
        
        // Get collateral types for this product
        $collateralTypes = CollateralType::where('loan_product_id', $loanProduct->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        
        if ($collateralTypes->isEmpty()) {
            return redirect()->route('admin.loan-applications.loan-details', [$loanProduct, $customer])
                ->with('error', 'No collateral types configured for this loan product.');
        }
        
        // Get relationship managers for inspection dropdown
        $relationshipManagers = Admin::where('is_relationship_manager', true)
            ->where('is_active', true)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        return view('admin.loan-applications.collateral', [
            'loanProduct' => $loanProduct,
            'customer' => $customer,
            'collateralTypes' => $collateralTypes,
            'loanData' => $loanData,
            'loanPurpose' => $this->loanPurposeFromLoanData($loanData),
            'relationshipManagers' => $relationshipManagers,
        ]);
    }

    /**
     * Calculate LTV amount
     */
    public function calculateLTV(Request $request): JsonResponse
    {
        abort_unless(auth('admin')->user()?->can('loans.create'), 403);
        
        $validated = $request->validate([
            'collateral_type_id' => 'required|exists:collateral_types,id',
            'collateral_value' => 'required|numeric|min:0',
        ]);
        
        $collateralType = CollateralType::findOrFail($validated['collateral_type_id']);
        $collateralValue = (float) $validated['collateral_value'];
        
        // Validate collateral value is within range
        if ($collateralValue < $collateralType->min_value || $collateralValue > $collateralType->max_value) {
            return response()->json([
                'error' => "Collateral value must be between {$collateralType->min_value} and {$collateralType->max_value}.",
            ], 422);
        }
        
        // Calculate LTV amount
        $ltvRatio = $collateralType->loan_to_value_ratio / 100;
        $ltvAmount = $collateralValue * $ltvRatio;
        
        return response()->json([
            'ltv_amount' => $ltvAmount,
            'ltv_ratio' => $collateralType->loan_to_value_ratio,
            'collateral_value' => $collateralValue,
        ]);
    }

    /**
     * Store loan application
     */
    public function store(Request $request, LoanProduct $loanProduct, Customer $customer): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('loans.create'), 403);

        if ($loanProduct->category !== 'collateral') {
            abort(404);
        }

        $borrower = $this->resolveLoanApplicationBorrower($customer, $loanProduct);
        if ($redirect = $this->loanEligibilityRedirect($borrower, $loanProduct)) {
            return $redirect;
        }
        
        $validated = $request->validate([
            'loan_amount' => 'required|numeric|min:1',
            'tenure_months' => 'required|integer|min:1',
            'loan_start_date' => 'required|date',
            'collateral_type_id' => 'required|exists:collateral_types,id',
            'collateral_value' => 'required|numeric|min:0',
            'collateral_description' => 'nullable|string|max:1000',
            'serial_number' => 'nullable|string|max:255',
            'item_quantity' => 'nullable|integer|min:1',
            'item_condition' => 'nullable|string|in:excellent,good,fair,poor',
            'is_inspected' => 'nullable|boolean',
            'inspected_by' => 'nullable|exists:admins,id',
            'inspected_at' => 'nullable|date',
            'location' => 'nullable|string|max:500',
            'images.*' => DocumentUploadRules::nullableMultipleImages(),
        ]);

        $loanData = $request->session()->get('loan_application_data');
        if (! $loanData) {
            return redirect()->route('admin.loan-applications.loan-details', [$loanProduct, $customer])
                ->with('error', 'Loan calculation data not found. Please recalculate.');
        }

        $loanData = array_merge($loanData, [
            'loan_amount' => $validated['loan_amount'],
            'tenure_months' => $validated['tenure_months'],
            'loan_start_date' => $validated['loan_start_date'],
        ]);

        $destinationAttributes = $this->destinationAttributesFromLoanData($loanData);
        
        // Verify collateral type belongs to this product
        $collateralType = CollateralType::where('id', $validated['collateral_type_id'])
            ->where('loan_product_id', $loanProduct->id)
            ->firstOrFail();
        
        // Validate collateral value
        if ($validated['collateral_value'] < $collateralType->min_value || 
            $validated['collateral_value'] > $collateralType->max_value) {
            return back()->withErrors([
                'collateral_value' => "Collateral value must be between {$collateralType->min_value} and {$collateralType->max_value}.",
            ])->withInput();
        }
        
        // Calculate LTV
        $ltvRatio = $collateralType->loan_to_value_ratio / 100;
        $ltvAmount = $validated['collateral_value'] * $ltvRatio;
        
        // Verify loan amount doesn't exceed LTV
        if ($validated['loan_amount'] > $ltvAmount) {
            return back()->withErrors([
                'loan_amount' => "Loan amount cannot exceed the LTV amount of {$ltvAmount}.",
            ])->withInput();
        }
        
        // Get customer group and rate
        $customerGroup = $customer->customerGroup;
        $rateType = $customerGroup->loanRateType;
        
        $tenureMonths = (int) $validated['tenure_months'];
        $loanAmount = (float) $validated['loan_amount'];
        $loanRate = $this->loanPricing()->resolveRateForAmount($rateType, $tenureMonths, $loanAmount);

        if (! $loanRate) {
            return back()->with('error', 'Loan rate not found for the selected tenure and amount.')->withInput();
        }

        $loanStartDate = Carbon::parse($validated['loan_start_date']);
        $loanEndDate = $loanStartDate->copy()->addMonths($tenureMonths);
        $days = $loanStartDate->diffInDays($loanEndDate);
        $firstPaymentDate = $loanStartDate->copy()->addMonth();
        $lastPaymentDate = $loanEndDate;

        DB::beginTransaction();
        try {
            $loan = $this->createLoanFromPricingQuote(
                loanProduct: $loanProduct,
                rateType: $rateType,
                loanRate: $loanRate,
                principal: $loanAmount,
                tenureMonths: $tenureMonths,
                loanStartDate: $loanStartDate,
                loanEndDate: $loanEndDate,
                days: $days,
                firstPaymentDate: $firstPaymentDate,
                lastPaymentDate: $lastPaymentDate,
                attributes: array_merge([
                    'customer_id' => $customer->id,
                    'customer_group_id' => $customerGroup->id,
                    'loan_number' => Loan::generateLoanNumber($loanProduct),
                ], $destinationAttributes),
                createdVia: 'admin_application',
            );

            // Handle image uploads
            $imagePaths = [];
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $path = $image->store('collateral/images', 'public');
                    $imagePaths[] = $path;
                }
            }
            
            // Create collateral loan detail
            CollateralLoanDetail::create([
                'loan_id' => $loan->id,
                'collateral_type_id' => $collateralType->id,
                'collateral_value' => $validated['collateral_value'],
                'loan_to_value_amount' => $ltvAmount,
                'loan_to_value_ratio' => $collateralType->loan_to_value_ratio,
                'collateral_description' => $validated['collateral_description'] ?? null,
                'serial_number' => $validated['serial_number'] ?? null,
                'item_quantity' => $validated['item_quantity'] ?? 1,
                'item_condition' => $validated['item_condition'] ?? null,
                'is_inspected' => $validated['is_inspected'] ?? false,
                'inspected_by' => ($validated['is_inspected'] ?? false) ? ($validated['inspected_by'] ?? auth('admin')->id()) : null,
                'inspected_at' => ($validated['is_inspected'] ?? false) && isset($validated['inspected_at']) ? Carbon::parse($validated['inspected_at']) : null,
                'location' => $validated['location'] ?? null,
                'images' => !empty($imagePaths) ? $imagePaths : null,
            ]);
            
            DB::commit();

            $request->session()->forget('loan_application_data');

            $statusMessage = config('approval.loans.create', true)
                ? 'Loan application submitted successfully and is pending approval.'
                : 'Loan created successfully.';

            return redirect()->route('admin.loans.show', $loan)
                ->with('status', $statusMessage);
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Failed to create loan application: '.$e->getMessage())->withInput();
        }
    }

    /**
     * Show review page for MOU/SME loans (no collateral step)
     */
    public function review(Request $request, LoanProduct $loanProduct, Customer $customer): View|RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('loans.create'), 403);

        if (! in_array($loanProduct->category, ['mou', 'sme'], true)) {
            abort(404);
        }

        if ($customer->loan_product_id !== $loanProduct->id) {
            return redirect()->route('admin.loan-applications.search-customer', $loanProduct)
                ->with('error', 'Customer does not belong to this loan product.');
        }

        $loanData = $request->session()->get('loan_application_data');
        if (! $loanData) {
            return redirect()->route('admin.loan-applications.loan-details', [$loanProduct, $customer])
                ->with('error', 'Please complete the loan details first.');
        }

        if ($redirect = $this->redirectIfLoanPurposeMissing($loanProduct, $customer, $loanData)) {
            return $redirect;
        }

        $company = $customer->company;
        if (! $company) {
            return redirect()->route('admin.loan-applications.search-customer', $loanProduct)
                ->with('error', 'Customer is not linked to a company.');
        }

        $rateType = $company->loanRateType;
        if (! $rateType) {
            return redirect()->route('admin.loan-applications.search-customer', $loanProduct)
                ->with('error', 'Company does not have a rate type configured.');
        }

        $loanRate = LoanRate::find($loanData['loan_rate_id'] ?? null);
        $channel = Channel::find($loanData['channel_id'] ?? null);

        return view('admin.loan-applications.review', [
            'loanProduct' => $loanProduct,
            'customer' => $customer,
            'company' => $company,
            'loanData' => $loanData,
            'loanPurpose' => $this->loanPurposeFromLoanData($loanData),
            'rateType' => $rateType,
            'loanRate' => $loanRate,
            'channel' => $channel,
        ]);
    }

    /**
     * Show review page for character-based loans (no collateral step)
     */
    public function reviewCharacter(Request $request, LoanProduct $loanProduct, Customer $customer): View|RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('loans.create'), 403);

        if ($loanProduct->category !== 'character') {
            abort(404);
        }

        if ($customer->loan_product_id !== $loanProduct->id) {
            return redirect()->route('admin.loan-applications.search-customer', $loanProduct)
                ->with('error', 'Customer does not belong to this loan product.');
        }

        $loanData = $request->session()->get('loan_application_data');
        if (! $loanData) {
            return redirect()->route('admin.loan-applications.loan-details', [$loanProduct, $customer])
                ->with('error', 'Please complete the loan details first.');
        }

        if ($redirect = $this->redirectIfLoanPurposeMissing($loanProduct, $customer, $loanData)) {
            return $redirect;
        }

        $customerGroup = $customer->customerGroup;
        if (! $customerGroup) {
            return redirect()->route('admin.loan-applications.search-customer', $loanProduct)
                ->with('error', 'Customer does not have an assigned group.');
        }

        $rateType = $customerGroup->loanRateType;
        if (! $rateType) {
            return redirect()->route('admin.loan-applications.search-customer', $loanProduct)
                ->with('error', 'Customer group does not have a rate type configured.');
        }

        $loanRate = LoanRate::find($loanData['loan_rate_id'] ?? null);
        $channel = Channel::find($loanData['channel_id'] ?? null);

        return view('admin.loan-applications.review-character', [
            'loanProduct' => $loanProduct,
            'customer' => $customer,
            'customerGroup' => $customerGroup,
            'loanData' => $loanData,
            'loanPurpose' => $this->loanPurposeFromLoanData($loanData),
            'rateType' => $rateType,
            'loanRate' => $loanRate,
            'channel' => $channel,
        ]);
    }

    /**
     * Store MOU/SME loan (no collateral)
     */
    public function storeMou(Request $request, LoanProduct $loanProduct, Customer $customer): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('loans.create'), 403);

        if (! in_array($loanProduct->category, ['mou', 'sme'], true)) {
            abort(404);
        }

        $borrower = $this->resolveLoanApplicationBorrower($customer, $loanProduct);
        if ($redirect = $this->loanEligibilityRedirect($borrower, $loanProduct)) {
            return $redirect;
        }

        if ($customer->loan_product_id !== $loanProduct->id) {
            return redirect()->route('admin.loan-applications.search-customer', $loanProduct)
                ->with('error', 'Customer does not belong to this loan product.');
        }

        $loanData = $request->session()->get('loan_application_data');
        if (! $loanData) {
            return redirect()->route('admin.loan-applications.loan-details', [$loanProduct, $customer])
                ->with('error', 'Loan calculation data not found. Please recalculate.');
        }

        $company = $borrower->company;
        $rateType = $company?->loanRateType;

        if (! $company || ! $rateType) {
            return redirect()->route('admin.loan-applications.search-customer', $loanProduct)
                ->with('error', 'Company or loan rate configuration is missing for this customer.');
        }

        $loanRate = LoanRate::where('loan_rate_type_id', $rateType->id)
            ->where('id', $loanData['loan_rate_id'])
            ->where('is_active', true)
            ->first();

        if (! $loanRate) {
            return redirect()->route('admin.loan-applications.loan-details', [$loanProduct, $customer])
                ->with('error', 'Loan rate not found for the selected tenure.');
        }

        $loanAmount = (float) $loanData['loan_amount'];
        $availableLoanAmount = $borrower->getAvailableLoanAmount();
        $maxLoanAmount = min(
            $availableLoanAmount,
            $loanProduct->max_amount ?? PHP_INT_MAX
        );

        if ($loanAmount > $maxLoanAmount) {
            return redirect()->route('admin.loan-applications.loan-details', [$loanProduct, $customer])
                ->with('error', "Loan amount cannot exceed the maximum allowed amount of " . number_format($maxLoanAmount, 2) . ".");
        }

        $loanStartDate = Carbon::parse($loanData['loan_start_date']);
        $tenureMonths = (int) $loanData['tenure_months'];

        if ($loanProduct->category === 'sme') {
            // SME repayments are monthly from loan start date.
            $schedule = $this->calculateSmeSchedule($loanStartDate, $tenureMonths);
        } else {
            // MOU repayments use company's monthly cut-off and pay day.
            $schedule = $this->calculateMouSchedule($loanStartDate, $company, $tenureMonths);
            if (isset($schedule['error'])) {
                return redirect()->route('admin.loan-applications.loan-details', [$loanProduct, $customer])
                    ->with('error', $schedule['error']);
            }
        }

        $loanEndDate = $schedule['loan_end_date'];
        $days = $schedule['days'];
        $firstPaymentDate = $schedule['first_payment_date'];
        $lastPaymentDate = $schedule['last_payment_date'];

        DB::beginTransaction();
        try {
            $loan = $this->createLoanFromPricingQuote(
                loanProduct: $loanProduct,
                rateType: $rateType,
                loanRate: $loanRate,
                principal: $loanAmount,
                tenureMonths: $tenureMonths,
                loanStartDate: $loanStartDate,
                loanEndDate: $loanEndDate,
                days: $days,
                firstPaymentDate: $firstPaymentDate,
                lastPaymentDate: $lastPaymentDate,
                attributes: array_merge([
                    'customer_id' => $customer->id,
                    'customer_group_id' => $customer->customer_group_id ?? null,
                    'loan_number' => Loan::generateLoanNumber($loanProduct),
                ], $this->destinationAttributesFromLoanData($loanData)),
                createdVia: $loanProduct->category === 'sme'
                    ? 'admin_application_sme'
                    : 'admin_application_mou',
                paymentDueDates: $schedule['payment_due_dates'] ?? null,
            );

            DB::commit();

            $request->session()->forget('loan_application_data');

            $statusMessage = config('approval.loans.create', true)
                ? ($loanProduct->category === 'sme'
                    ? 'SME loan application submitted successfully and is pending approval.'
                    : 'MOU loan application submitted successfully and is pending approval.')
                : ($loanProduct->category === 'sme'
                    ? 'SME loan created successfully.'
                    : 'MOU loan created successfully.');

            return redirect()->route('admin.loans.show', $loan)
                ->with('status', $statusMessage);
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to create loan application: ' . $e->getMessage());
        }
    }

    /**
     * Show review page for government loans (no collateral step, group-based cut-off & pay dates)
     */
    public function reviewGovernment(Request $request, LoanProduct $loanProduct, Customer $customer): View|RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('loans.create'), 403);

        if ($loanProduct->category !== 'government') {
            abort(404);
        }

        if ($customer->loan_product_id !== $loanProduct->id) {
            return redirect()->route('admin.loan-applications.search-customer', $loanProduct)
                ->with('error', 'Customer does not belong to this loan product.');
        }

        $loanData = $request->session()->get('loan_application_data');
        if (! $loanData) {
            return redirect()->route('admin.loan-applications.loan-details', [$loanProduct, $customer])
                ->with('error', 'Please complete the loan details first.');
        }

        if ($redirect = $this->redirectIfLoanPurposeMissing($loanProduct, $customer, $loanData)) {
            return $redirect;
        }

        $loanData = $this->ensureRepaymentScheduleInLoanData($loanProduct, $customer, $loanData);
        $request->session()->put('loan_application_data', $loanData);

        $customerGroup = $customer->customerGroup;
        if (! $customerGroup) {
            return redirect()->route('admin.loan-applications.search-customer', $loanProduct)
                ->with('error', 'Customer does not have an assigned group.');
        }

        $rateType = $customerGroup->loanRateType;
        if (! $rateType) {
            return redirect()->route('admin.loan-applications.search-customer', $loanProduct)
                ->with('error', 'Customer group does not have a rate type configured.');
        }

        $loanRate = LoanRate::find($loanData['loan_rate_id'] ?? null);
        $channel = Channel::find($loanData['channel_id'] ?? null);

        return view('admin.loan-applications.review-government', [
            'loanProduct' => $loanProduct,
            'customer' => $customer,
            'customerGroup' => $customerGroup,
            'loanData' => $loanData,
            'loanPurpose' => $this->loanPurposeFromLoanData($loanData),
            'rateType' => $rateType,
            'loanRate' => $loanRate,
            'channel' => $channel,
            'repaymentSchedule' => $loanData['repayment_schedule'] ?? [],
            'installmentAmount' => $loanData['installment_amount'] ?? null,
        ]);
    }

    /**
     * Store government loan (no collateral, group-based schedule)
     */
    public function storeGovernment(Request $request, LoanProduct $loanProduct, Customer $customer): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('loans.create'), 403);

        if ($loanProduct->category !== 'government') {
            abort(404);
        }

        $borrower = $this->resolveLoanApplicationBorrower($customer, $loanProduct);
        if ($redirect = $this->loanEligibilityRedirect($borrower, $loanProduct)) {
            return $redirect;
        }

        if ($customer->loan_product_id !== $loanProduct->id) {
            return redirect()->route('admin.loan-applications.search-customer', $loanProduct)
                ->with('error', 'Customer does not belong to this loan product.');
        }

        $loanData = $request->session()->get('loan_application_data');
        if (! $loanData) {
            return redirect()->route('admin.loan-applications.loan-details', [$loanProduct, $customer])
                ->with('error', 'Loan calculation data not found. Please recalculate.');
        }

        $customerGroup = $borrower->customerGroup;
        $rateType = $customerGroup?->loanRateType;

        if (! $customerGroup || ! $rateType) {
            return redirect()->route('admin.loan-applications.search-customer', $loanProduct)
                ->with('error', 'Customer group or loan rate configuration is missing for this customer.');
        }

        $loanRate = LoanRate::where('loan_rate_type_id', $rateType->id)
            ->where('id', $loanData['loan_rate_id'])
            ->where('is_active', true)
            ->first();

        if (! $loanRate) {
            return redirect()->route('admin.loan-applications.loan-details', [$loanProduct, $customer])
                ->with('error', 'Loan rate not found for the selected tenure.');
        }

        $loanAmount = (float) $loanData['loan_amount'];
        $availableLoanAmount = $borrower->getAvailableLoanAmount();
        $maxLoanAmount = min(
            $availableLoanAmount,
            $customerGroup->max_loan_amount ?? PHP_INT_MAX,
            $loanProduct->max_amount ?? PHP_INT_MAX
        );

        if ($loanAmount > $maxLoanAmount) {
            return redirect()->route('admin.loan-applications.loan-details', [$loanProduct, $customer])
                ->with('error', "Loan amount cannot exceed the maximum allowed amount of " . number_format($maxLoanAmount, 2) . ".");
        }

        $loanStartDate = Carbon::parse($loanData['loan_start_date']);
        $tenureMonths = (int) $loanData['tenure_months'];

        // Calculate schedule based on group loan cut-off day and payment date
        $schedule = $this->calculateGovernmentSchedule($loanStartDate, $customerGroup, $tenureMonths);
        if (isset($schedule['error'])) {
            return redirect()->route('admin.loan-applications.loan-details', [$loanProduct, $customer])
                ->with('error', $schedule['error']);
        }

        $loanEndDate = $schedule['loan_end_date'];
        $days = $schedule['days'];
        $firstPaymentDate = $schedule['first_payment_date'];
        $lastPaymentDate = $schedule['last_payment_date'];

        DB::beginTransaction();
        try {
            $loan = $this->createLoanFromPricingQuote(
                loanProduct: $loanProduct,
                rateType: $rateType,
                loanRate: $loanRate,
                principal: $loanAmount,
                tenureMonths: $tenureMonths,
                loanStartDate: $loanStartDate,
                loanEndDate: $loanEndDate,
                days: $days,
                firstPaymentDate: $firstPaymentDate,
                lastPaymentDate: $lastPaymentDate,
                attributes: array_merge([
                    'customer_id' => $customer->id,
                    'customer_group_id' => $customerGroup->id,
                    'loan_number' => Loan::generateLoanNumber($loanProduct),
                ], $this->destinationAttributesFromLoanData($loanData)),
                createdVia: 'admin_application_government',
                paymentDueDates: $schedule['payment_due_dates'] ?? null,
            );

            DB::commit();

            $request->session()->forget('loan_application_data');

            $statusMessage = config('approval.loans.create', true)
                ? 'Government loan application submitted successfully and is pending approval.'
                : 'Government loan created successfully.';

            return redirect()->route('admin.loans.show', $loan)
                ->with('status', $statusMessage);
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to create government loan application: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Store character-based loan (no collateral)
     */
    public function storeCharacter(Request $request, LoanProduct $loanProduct, Customer $customer): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('loans.create'), 403);

        if ($loanProduct->category !== 'character') {
            abort(404);
        }

        $borrower = $this->resolveLoanApplicationBorrower($customer, $loanProduct);
        if ($redirect = $this->loanEligibilityRedirect($borrower, $loanProduct)) {
            return $redirect;
        }

        if ($customer->loan_product_id !== $loanProduct->id) {
            return redirect()->route('admin.loan-applications.search-customer', $loanProduct)
                ->with('error', 'Customer does not belong to this loan product.');
        }

        $loanData = $request->session()->get('loan_application_data');
        if (! $loanData) {
            return redirect()->route('admin.loan-applications.loan-details', [$loanProduct, $customer])
                ->with('error', 'Loan calculation data not found. Please recalculate.');
        }

        $customerGroup = $borrower->customerGroup;
        $rateType = $customerGroup?->loanRateType;

        if (! $customerGroup || ! $rateType) {
            return redirect()->route('admin.loan-applications.search-customer', $loanProduct)
                ->with('error', 'Customer group or loan rate configuration is missing for this customer.');
        }

        $loanRate = LoanRate::where('loan_rate_type_id', $rateType->id)
            ->where('id', $loanData['loan_rate_id'])
            ->where('is_active', true)
            ->first();

        if (! $loanRate) {
            return redirect()->route('admin.loan-applications.loan-details', [$loanProduct, $customer])
                ->with('error', 'Loan rate not found for the selected tenure.');
        }

        $loanAmount = (float) $loanData['loan_amount'];
        $availableLoanAmount = $borrower->getAvailableLoanAmount();
        $maxLoanAmount = min(
            $availableLoanAmount,
            $customerGroup->max_loan_amount ?? PHP_INT_MAX,
            $loanProduct->max_amount ?? PHP_INT_MAX
        );

        if ($loanAmount > $maxLoanAmount) {
            return redirect()->route('admin.loan-applications.loan-details', [$loanProduct, $customer])
                ->with('error', "Loan amount cannot exceed the maximum allowed amount of " . number_format($maxLoanAmount, 2) . ".");
        }

        $loanStartDate = Carbon::parse($loanData['loan_start_date']);
        $tenureMonths = (int) $loanData['tenure_months'];
        $loanEndDate = $loanStartDate->copy()->addMonths($tenureMonths);
        $days = $loanStartDate->diffInDays($loanEndDate);

        // Calculate payment dates (standard monthly schedule)
        $firstPaymentDate = $loanStartDate->copy()->addMonth();
        $lastPaymentDate = $loanEndDate;

        DB::beginTransaction();
        try {
            $loan = $this->createLoanFromPricingQuote(
                loanProduct: $loanProduct,
                rateType: $rateType,
                loanRate: $loanRate,
                principal: $loanAmount,
                tenureMonths: $tenureMonths,
                loanStartDate: $loanStartDate,
                loanEndDate: $loanEndDate,
                days: $days,
                firstPaymentDate: $firstPaymentDate,
                lastPaymentDate: $lastPaymentDate,
                attributes: array_merge([
                    'customer_id' => $customer->id,
                    'customer_group_id' => $customerGroup->id,
                    'loan_number' => Loan::generateLoanNumber($loanProduct),
                ], $this->destinationAttributesFromLoanData($loanData)),
                createdVia: 'admin_application_character',
            );

            DB::commit();

            $request->session()->forget('loan_application_data');

            $statusMessage = config('approval.loans.create', true)
                ? 'Loan application submitted successfully and is pending approval.'
                : 'Loan created successfully.';

            return redirect()->route('admin.loans.show', $loan)
                ->with('status', $statusMessage);
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to create loan application: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Create a loan using LoanPricingService as the single source of truth for booked amounts.
     */
    protected function createLoanFromPricingQuote(
        LoanProduct $loanProduct,
        LoanRateType $rateType,
        LoanRate $loanRate,
        float $principal,
        int $tenureMonths,
        Carbon $loanStartDate,
        Carbon $loanEndDate,
        int $days,
        ?Carbon $firstPaymentDate,
        ?Carbon $lastPaymentDate,
        array $attributes,
        string $createdVia,
        ?array $paymentDueDates = null,
    ): Loan {
        $quote = $this->buildLoanPricingQuote(
            principal: $principal,
            tenureMonths: $tenureMonths,
            loanStartDate: $loanStartDate,
            loanProduct: $loanProduct,
            rateType: $rateType,
            loanRate: $loanRate,
            termDays: $days,
            loanEndDate: $loanEndDate,
        );

        $financials = $this->loanFinancialAttributesFromQuote($quote);
        $pricingMeta = $financials['pricing_metadata'] ?? [];
        unset($financials['pricing_metadata']);

        $metadata = array_merge($attributes['metadata'] ?? [], $pricingMeta, [
            'calculated_days' => $days,
            'rate_type_accrual_period' => $rateType->accrual_period,
            'loan_rate_type_id' => $quote['loan_rate_type_id'] ?? $rateType->id,
            'created_via' => $createdVia,
            'created_by' => auth('admin')->id(),
        ]);

        if ($paymentDueDates !== null && count($paymentDueDates) === $tenureMonths) {
            $metadata['payment_due_dates'] = array_values($paymentDueDates);
        }

        $loan = Loan::create(array_merge([
            'loan_product_id' => $loanProduct->id,
            'loan_rate_id' => $loanRate->id,
            'principal_amount' => $principal,
            'tenure_months' => $tenureMonths,
            'loan_start_date' => $loanStartDate,
            'loan_end_date' => $loanEndDate,
            'first_payment_date' => $firstPaymentDate,
            'last_payment_date' => $lastPaymentDate,
            'amount_paid' => 0,
            'status' => config('approval.loans.create', true) ? 'pending_approval' : 'approved',
            'disbursement_status' => 'pending',
            'last_accrual_date' => ($financials['accrual_type'] ?? 'daily') === 'daily' ? $loanStartDate : null,
            'metadata' => $metadata,
        ], $attributes, $financials));

        $loan->createPaymentSchedule();
        $this->applyPostLoanPricingSetup($loan);

        return $loan;
    }

    /**
     * Calculate MOU loan schedule based on company monthly cut-off and pay day.
     *
     * @return array{
     *     first_payment_date?: \Carbon\Carbon,
     *     last_payment_date?: \Carbon\Carbon,
     *     loan_end_date?: \Carbon\Carbon,
     *     days?: int,
     *     error?: string
     * }
     */
    private function calculateMouSchedule(Carbon $loanStartDate, Company $company, int $tenureMonths): array
    {
        $cutOffDay = (int) ($company->monthly_cut_off_day ?? 0);
        $payDay = (int) ($company->pay_day ?? 0);

        if ($cutOffDay < 1 || $cutOffDay > 31 || $payDay < 1 || $payDay > 31) {
            return [
                'error' => 'Company monthly cut-off day or pay day is not configured correctly.',
            ];
        }

        try {
            return app(LoanPayDayScheduleService::class)->calculateDueDates(
                $loanStartDate,
                $tenureMonths,
                $cutOffDay,
                $payDay
            );
        } catch (\InvalidArgumentException $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Calculate SME loan schedule with monthly repayments from loan start date.
     *
     * @return array{
     *     first_payment_date: \Carbon\Carbon,
     *     last_payment_date: \Carbon\Carbon,
     *     loan_end_date: \Carbon\Carbon,
     *     days: int
     * }
     */
    private function calculateSmeSchedule(Carbon $loanStartDate, int $tenureMonths): array
    {
        $loanEndDate = $loanStartDate->copy()->addMonths($tenureMonths);
        $firstPaymentDate = $loanStartDate->copy()->addMonth();
        $lastPaymentDate = $loanEndDate->copy();
        $days = $loanStartDate->diffInDays($loanEndDate);

        return [
            'first_payment_date' => $firstPaymentDate,
            'last_payment_date' => $lastPaymentDate,
            'loan_end_date' => $loanEndDate,
            'days' => $days,
        ];
    }

    /**
     * Calculate government loan schedule based on customer group loan cut-off day and payment date.
     *
     * @return array{
     *     first_payment_date?: \Carbon\Carbon,
     *     last_payment_date?: \Carbon\Carbon,
     *     loan_end_date?: \Carbon\Carbon,
     *     days?: int,
     *     error?: string
     * }
     */
    private function calculateGovernmentSchedule(Carbon $loanStartDate, CustomerGroup $group, int $tenureMonths): array
    {
        $cutOffDay = (int) ($group->loan_cut_off_day ?? 0);
        $payDay = (int) ($group->loan_payment_date ?? 0);

        if ($cutOffDay < 1 || $cutOffDay > 31 || $payDay < 1 || $payDay > 31) {
            return [
                'error' => 'Customer group loan cut-off day or payment date is not configured correctly.',
            ];
        }

        try {
            return app(LoanPayDayScheduleService::class)->calculateDueDates(
                $loanStartDate,
                $tenureMonths,
                $cutOffDay,
                $payDay
            );
        } catch (\InvalidArgumentException $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * @return list<string>
     */
    private function resolvePaymentDueDatesForApplication(
        LoanProduct $loanProduct,
        Customer $customer,
        Carbon $loanStartDate,
        int $tenureMonths,
    ): array {
        if ($loanProduct->category === 'government') {
            $group = $customer->customerGroup;
            if ($group) {
                $schedule = $this->calculateGovernmentSchedule($loanStartDate, $group, $tenureMonths);
                if (! isset($schedule['error']) && ! empty($schedule['payment_due_dates'])) {
                    return $schedule['payment_due_dates'];
                }
            }
        }

        if ($loanProduct->category === 'mou') {
            $company = $customer->company;
            if ($company) {
                $schedule = $this->calculateMouSchedule($loanStartDate, $company, $tenureMonths);
                if (! isset($schedule['error']) && ! empty($schedule['payment_due_dates'])) {
                    return $schedule['payment_due_dates'];
                }
            }
        }

        $dates = [];
        for ($period = 1; $period <= $tenureMonths; $period++) {
            $dates[] = $loanStartDate->copy()->addMonths($period)->toDateString();
        }

        return $dates;
    }

    /**
     * @param  array<string, mixed>  $quote
     * @param  list<string>  $paymentDueDates
     * @return list<array{
     *     period_number: int,
     *     due_date: string,
     *     expected_amount: float,
     *     principal_component: float,
     *     interest_component: float,
     *     fee_component: float
     * }>
     */
    private function buildApplicationRepaymentSchedulePreview(
        array $quote,
        array $paymentDueDates,
        int $tenureMonths,
        Carbon $loanStartDate,
    ): array {
        $componentInstallments = $this->loanPricing()->calculateComponentInstallments(
            $quote['principal'],
            $quote['processing_fee'],
            $quote['interest'],
            $tenureMonths,
        )['installments'];

        $rows = [];

        foreach ($componentInstallments as $index => $installment) {
            $period = (int) $installment['period'];
            $dueDate = $paymentDueDates[$index]
                ?? $loanStartDate->copy()->addMonths($period)->toDateString();

            $rows[] = [
                'period_number' => $period,
                'due_date' => $dueDate,
                'expected_amount' => (float) $installment['expected_amount'],
                'principal_component' => (float) $installment['principal_component'],
                'interest_component' => (float) $installment['interest_component'],
                'fee_component' => (float) $installment['fee_component'],
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $calcValidated
     * @return array{installment_amount: float, repayment_schedule: list<array<string, mixed>>}
     */
    private function buildRepaymentScheduleExtrasForSession(
        LoanProduct $loanProduct,
        Customer $customer,
        array $calcValidated,
    ): array {
        $loanAmount = (float) $calcValidated['loan_amount'];
        $tenureMonths = (int) $calcValidated['tenure_months'];
        $loanStartDate = Carbon::parse($calcValidated['loan_start_date']);
        $loanEndDate = Carbon::parse($calcValidated['loan_end_date']);
        $days = (int) $calcValidated['days'];

        $loanRate = LoanRate::with('loanRateType')->findOrFail($calcValidated['loan_rate_id']);
        $rateType = $loanRate->loanRateType;

        if (! $rateType) {
            throw new \RuntimeException('Loan rate type is missing for the selected loan rate.');
        }

        $quote = $this->buildLoanPricingQuote(
            principal: $loanAmount,
            tenureMonths: $tenureMonths,
            loanStartDate: $loanStartDate,
            loanProduct: $loanProduct,
            rateType: $rateType,
            loanRate: $loanRate,
            termDays: $days,
            loanEndDate: $loanEndDate,
        );

        $paymentDueDates = $this->resolvePaymentDueDatesForApplication(
            $loanProduct,
            $customer,
            $loanStartDate,
            $tenureMonths
        );

        return [
            'installment_amount' => (float) $quote['installment_amount'],
            'repayment_schedule' => $this->buildApplicationRepaymentSchedulePreview(
                $quote,
                $paymentDueDates,
                $tenureMonths,
                $loanStartDate
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $loanData
     * @return array<string, mixed>
     */
    private function ensureRepaymentScheduleInLoanData(
        LoanProduct $loanProduct,
        Customer $customer,
        array $loanData,
    ): array {
        if (! empty($loanData['repayment_schedule']) && isset($loanData['installment_amount'])) {
            return $loanData;
        }

        try {
            return array_merge($loanData, $this->buildRepaymentScheduleExtrasForSession(
                $loanProduct,
                $customer,
                $loanData
            ));
        } catch (\Throwable) {
            return $loanData;
        }
    }

    /**
     * @param  array<string, mixed>  $loanData
     */
    private function loanPurposeFromLoanData(array $loanData): ?LoanPurpose
    {
        $loanPurposeId = $loanData['loan_purpose_id'] ?? null;

        return $loanPurposeId ? LoanPurpose::find($loanPurposeId) : null;
    }

    /**
     * @param  array<string, mixed>  $loanData
     */
    private function redirectIfLoanPurposeMissing(
        LoanProduct $loanProduct,
        Customer $customer,
        array $loanData,
    ): ?RedirectResponse {
        if (empty($loanData['loan_purpose_id'])) {
            return redirect()->route('admin.loan-applications.loan-details', [$loanProduct, $customer])
                ->with('error', 'Please select a loan purpose before continuing.');
        }

        return null;
    }
}
