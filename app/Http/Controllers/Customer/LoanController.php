<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Concerns\EnforcesCustomerLoanEligibility;
use App\Http\Controllers\Concerns\ResolvesDisbursementDestination;
use App\Http\Controllers\Concerns\UsesLoanPricing;
use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\FinancialInstitution;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\LoanRate;
use App\Models\LoanRateType;
use App\Services\LoanPayDayScheduleService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class LoanController extends Controller
{
    use EnforcesCustomerLoanEligibility;
    use ResolvesDisbursementDestination;
    use UsesLoanPricing;

    /**
     * Show channel selection page (Step 1)
     */
    public function selectChannel(): View|RedirectResponse
    {
        $customer = auth('customer')->user();
        $customer->load('customerGroup');
        
        if ($redirect = $this->customerPortalLoanEligibilityRedirect($customer)) {
            return $redirect;
        }
        
        // Get active channels that support disbursement
        $channels = Channel::where('is_active', true)
            ->where('can_disburse', true)
            ->orderBy('name')
            ->get();

        return view('customer.loans.select-channel', [
            'channels' => $channels,
        ]);
    }

    /**
     * Store selected channel and redirect to amount entry (Step 2)
     */
    public function storeChannel(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'channel_id' => 'required|exists:channels,id',
        ]);

        if ((int) session('loan_application.channel_id') !== (int) $validated['channel_id']) {
            $this->forgetLoanApplicationDestinationSession();
        }

        session(['loan_application.channel_id' => $validated['channel_id']]);

        return redirect()->route('customer.loans.enter-amount');
    }

    /**
     * Show amount entry page (Step 2)
     */
    public function enterAmount(): View|RedirectResponse
    {
        $customer = auth('customer')->user();
        $customer->load('customerGroup');
        
        // Check if channel was selected
        if (!session('loan_application.channel_id')) {
            return redirect()->route('customer.loans.select-channel')
                ->with('error', 'Please select a payment channel first.');
        }

        if ($redirect = $this->customerPortalLoanEligibilityRedirect($customer)) {
            return $redirect;
        }

        $channel = Channel::findOrFail(session('loan_application.channel_id'));
        $availableLoanAmount = $customer->getAvailableLoanAmount();

        return view('customer.loans.enter-amount', [
            'channel' => $channel,
            'maximumLoanTake' => $customer->maximum_loan_take ?? 0,
            'availableLoanAmount' => $availableLoanAmount,
        ]);
    }

    /**
     * Store loan amount and redirect to disbursement destination step.
     */
    public function storeAmount(Request $request): RedirectResponse
    {
        $customer = auth('customer')->user();
        $customer->load('customerGroup');
        
        if ($redirect = $this->customerPortalLoanEligibilityRedirect($customer)) {
            return $redirect;
        }
        
        $availableLoanAmount = $customer->getAvailableLoanAmount();
        $maximumLoanTake = $customer->maximum_loan_take ?? 0;

        $validated = $request->validate([
            'amount' => [
                'required',
                'numeric',
                'min:1',
                'max:' . min($maximumLoanTake, $availableLoanAmount),
            ],
        ], [
            'amount.max' => 'The loan amount cannot exceed your available loan limit of ZMW ' . number_format($availableLoanAmount, 2) . '.',
        ]);

        session(['loan_application.amount' => $validated['amount']]);

        return redirect()->route('customer.loans.enter-destination')
            ->with('success', 'Loan amount saved. Enter where you would like to receive your disbursement.');
    }

    /**
     * Show disbursement destination details (Step 3).
     */
    public function enterDestination(): View|RedirectResponse
    {
        $customer = auth('customer')->user();

        if (! session('loan_application.channel_id') || ! session('loan_application.amount')) {
            return redirect()->route('customer.loans.select-channel')
                ->with('error', 'Please complete the previous steps first.');
        }

        $channel = Channel::findOrFail(session('loan_application.channel_id'));

        return view('customer.loans.enter-destination', [
            'channel' => $channel,
            'customerPhone' => $customer->phone,
            'financialInstitutions' => $this->activeFinancialInstitutions(),
            'disbursementPhoneNumber' => session('loan_application.disbursement_phone_number')
                ?? session('loan_application.phone_number'),
            'disbursementFinancialInstitutionId' => session('loan_application.disbursement_financial_institution_id'),
            'disbursementFinancialInstitutionBranchId' => session('loan_application.disbursement_financial_institution_branch_id'),
            'disbursementAccountHolderName' => session('loan_application.disbursement_account_holder_name'),
            'disbursementAccountNumber' => session('loan_application.disbursement_account_number'),
            'disbursementNotes' => session('loan_application.disbursement_notes'),
        ]);
    }

    /**
     * Validate and store disbursement destination in session.
     */
    public function storeDestination(Request $request): RedirectResponse
    {
        if (! session('loan_application.channel_id') || ! session('loan_application.amount')) {
            return redirect()->route('customer.loans.select-channel')
                ->with('error', 'Please complete the previous steps first.');
        }

        $channelId = (int) session('loan_application.channel_id');

        try {
            $normalized = $this->normalizeDisbursementDestination(
                $this->destinationPayloadFromRequest($request, $channelId)
            );
        } catch (\Illuminate\Validation\ValidationException $exception) {
            return redirect()->route('customer.loans.enter-destination')
                ->withErrors($exception->errors())
                ->withInput();
        }

        $this->mergeLoanApplicationDestinationSession($normalized);

        return redirect()->route('customer.loans.calculate')
            ->with('success', 'Disbursement details saved. Review your loan calculation.');
    }

    /**
     * Show loan calculation page (Step 4)
     */
    public function calculate(Request $request)
    {
        $customer = auth('customer')->user();

        if (! session('loan_application.amount') || ! $this->hasLoanApplicationDestinationInSession()) {
            if (session('loan_application.amount') && session('loan_application.channel_id')) {
                return redirect()->route('customer.loans.enter-destination')
                    ->with('error', 'Please enter your disbursement destination details.');
            }

            return redirect()->route('customer.loans.select-channel')
                ->with('error', 'Please complete the previous steps first.');
        }

        $loanAmount = session('loan_application.amount');
        $channelId = session('loan_application.channel_id');
        $loanData = $this->loanApplicationDestinationDataForView();
        $channel = Channel::find($channelId);

        // Load customer with relationships
        $customer->load(['customerGroup.loanRateType.loanRates', 'company.loanRateType.loanRates', 'loanProduct']);

        $loanProduct = $customer->loanProduct;
        $customerGroup = $customer->customerGroup;
        $company = $customer->company;
        $netSalary = $customer->net_salary ?? 0;

        if (!$loanProduct) {
            return redirect()->route('customer.dashboard')
                ->with('error', 'No loan product is assigned to your account. Please contact support.');
        }

        $usesCompanyConfig = in_array($loanProduct->category, ['mou', 'sme'], true);

        if ($usesCompanyConfig) {
            if (!$company) {
                return redirect()->route('customer.dashboard')
                    ->with('error', 'Your account is not linked to a company. Please contact support.');
            }

            if (!$company->loanRateType) {
                return redirect()->route('customer.dashboard')
                    ->with('error', 'Your company does not have a rate type configured. Please contact support.');
            }

            $rateType = $company->loanRateType;
            $maxTenureMonths = $company->maximum_loan_tenure_months ?? $loanProduct->tenure_months ?? 12;
            $instalmentCrossOverPercentage = $company->instalment_cross_over_percentage ?? 0;
        } else {
            if (!$customerGroup) {
                return redirect()->route('customer.dashboard')
                    ->with('error', 'Your account is not assigned to a customer group. Please contact support.');
            }

            if (!$customerGroup->loanRateType) {
                return redirect()->route('customer.dashboard')
                    ->with('error', 'Your customer group does not have a rate type configured. Please contact support.');
            }

            $rateType = $customerGroup->loanRateType;
            $maxTenureMonths = $customerGroup->max_loan_tenure_months ?? $loanProduct->tenure_months ?? 12;
            $instalmentCrossOverPercentage = $customerGroup->instalment_cross_over_percentage ?? 0;
        }

        $maxTenureMonths = max(1, (int) $maxTenureMonths);

        // Calculate crossover threshold
        $crossoverThreshold = ($instalmentCrossOverPercentage / 100) * $netSalary;
        $exceedsCrossover = $loanAmount > $crossoverThreshold;

        // Handle POST request (tenure selection)
        if ($request->isMethod('post')) {
            $validated = $request->validate([
                'tenure_months' => [
                    'required',
                    'integer',
                    'min:1',
                    'max:' . $maxTenureMonths,
                ],
            ]);

            $selectedTenure = (int) $validated['tenure_months'];
            
            $loanRate = $this->loanPricing()->resolveRateForAmount($rateType, $selectedTenure, $loanAmount);

            if (! $loanRate) {
                return redirect()->route('customer.loans.calculate')
                    ->with('error', 'Loan rate not found for the selected tenure and amount.');
            }

            $loanStartDate = now()->startOfDay();
            if ($loanProduct->category === 'sme') {
                $smeSchedule = $this->calculateSmeSchedule($loanStartDate, $selectedTenure);
                $loanEndDate = $smeSchedule['loan_end_date'];
                $days = $smeSchedule['days'];
            } else {
                $loanEndDate = $loanStartDate->copy()->addMonths($selectedTenure);
                $days = $loanStartDate->diffInDays($loanEndDate);
            }

            $calculation = $this->buildAndStoreCustomerPricingCalculation(
                $loanAmount,
                $selectedTenure,
                $loanStartDate,
                $loanEndDate,
                $days,
                $loanProduct,
                $rateType,
                $loanRate
            );

            return view('customer.loans.calculate', [
                'customer' => $customer,
                'loanAmount' => $loanAmount,
                'loanData' => $loanData,
                'channel' => $channel,
                'customerGroup' => $customerGroup,
                'rateType' => $rateType,
                'netSalary' => $netSalary,
                'instalmentCrossOverPercentage' => $instalmentCrossOverPercentage,
                'crossoverThreshold' => $crossoverThreshold,
                'exceedsCrossover' => $exceedsCrossover,
                'maxTenureMonths' => $maxTenureMonths,
                'selectedTenure' => $selectedTenure,
                'loanRate' => $loanRate,
                'loanStartDate' => $loanStartDate,
                'loanEndDate' => $loanEndDate,
                'days' => $days,
                'processingFee' => $calculation['processing_fee'],
                'interest' => $calculation['interest'],
                'totalAmount' => $calculation['total_amount'],
                'projectedTotalAmount' => $calculation['projected_total_amount'],
                'monthlyPayment' => $calculation['monthly_payment'],
                'showCalculation' => true,
            ]);
        }

        // GET request - show initial calculation or tenure selection
        $showCalculation = false;
        $selectedTenure = null;
        $loanRate = null;
        $loanStartDate = null;
        $loanEndDate = null;
        $days = null;
        $processingFee = null;
        $interest = null;
        $totalAmount = null;
        $monthlyPayment = null;

        // If amount doesn't exceed crossover, show 1-month calculation immediately
        if (!$exceedsCrossover) {
            $defaultTenure = 1;
            $loanRate = $rateType->loanRates()
                ->where('tenure_months', $defaultTenure)
                ->where('is_active', true)
                ->first();

            if ($loanRate) {
                $loanStartDate = now()->startOfDay();
                if ($loanProduct->category === 'sme') {
                    $smeSchedule = $this->calculateSmeSchedule($loanStartDate, $defaultTenure);
                    $loanEndDate = $smeSchedule['loan_end_date'];
                    $days = $smeSchedule['days'];
                } else {
                    $loanEndDate = $loanStartDate->copy()->addMonths($defaultTenure);
                    $days = $loanStartDate->diffInDays($loanEndDate);
                }

                $calculation = $this->buildAndStoreCustomerPricingCalculation(
                    $loanAmount,
                    $defaultTenure,
                    $loanStartDate,
                    $loanEndDate,
                    $days,
                    $loanProduct,
                    $rateType,
                    $loanRate
                );

                $processingFee = $calculation['processing_fee'];
                $interest = $calculation['interest'];
                $totalAmount = $calculation['total_amount'];
                $monthlyPayment = $calculation['monthly_payment'];
                $selectedTenure = $defaultTenure;
                $showCalculation = true;
            }
        }

        return view('customer.loans.calculate', [
            'customer' => $customer,
            'loanAmount' => $loanAmount,
            'loanData' => $loanData,
            'channel' => $channel,
            'customerGroup' => $customerGroup,
            'rateType' => $rateType,
            'netSalary' => $netSalary,
            'instalmentCrossOverPercentage' => $instalmentCrossOverPercentage,
            'crossoverThreshold' => $crossoverThreshold,
            'exceedsCrossover' => $exceedsCrossover,
            'maxTenureMonths' => $maxTenureMonths,
            'selectedTenure' => $selectedTenure,
            'loanRate' => $loanRate,
            'loanStartDate' => $loanStartDate,
            'loanEndDate' => $loanEndDate,
            'days' => $days,
            'processingFee' => $processingFee,
            'interest' => $interest,
            'totalAmount' => $totalAmount,
            'monthlyPayment' => $monthlyPayment,
            'showCalculation' => $showCalculation,
        ]);
    }

    /**
     * Store/Submit loan application
     */
    public function store(Request $request): RedirectResponse
    {
        $customer = auth('customer')->user();

        if (! session('loan_application.amount') || ! $this->hasLoanApplicationDestinationInSession()) {
            return redirect()->route('customer.loans.select-channel')
                ->with('error', 'Please complete the previous steps first.');
        }

        // Check if calculation data exists
        if (!session('loan_application.tenure_months') || !session('loan_application.loan_rate_id')) {
            return redirect()->route('customer.loans.calculate')
                ->with('error', 'Please complete the loan calculation first.');
        }

        $loanAmount = session('loan_application.amount');
        $channelId = session('loan_application.channel_id');
        $tenureMonths = (int) session('loan_application.tenure_months');

        try {
            $destinationAttributes = $this->loanDestinationAttributes(
                $this->destinationPayloadFromLoanApplicationSession()
            );
        } catch (\Illuminate\Validation\ValidationException) {
            return redirect()->route('customer.loans.enter-destination')
                ->with('error', 'Your disbursement details are incomplete. Please update them and try again.');
        }
        $loanRateId = session('loan_application.loan_rate_id');

        // Load customer with relationships
        $customer->load(['customerGroup.loanRateType', 'company.loanRateType', 'loanProduct']);

        $customerGroup = $customer->customerGroup;
        $company = $customer->company;
        $loanProduct = $customer->loanProduct;

        if (!$loanProduct) {
            return redirect()->route('customer.dashboard')
                ->with('error', 'No loan product is assigned to your account. Please contact support.');
        }

        $usesCompanyConfig = in_array($loanProduct->category, ['mou', 'sme'], true);

        if ($usesCompanyConfig) {
            if (!$company) {
                return redirect()->route('customer.dashboard')
                    ->with('error', 'Your account is not linked to a company. Please contact support.');
            }

            if (!$company->loanRateType) {
                return redirect()->route('customer.dashboard')
                    ->with('error', 'Your company does not have a rate type configured. Please contact support.');
            }

            $rateType = $company->loanRateType;
        } else {
            if (!$customerGroup) {
                return redirect()->route('customer.dashboard')
                    ->with('error', 'Your account is not assigned to a customer group. Please contact support.');
            }

            if (!$customerGroup->loanRateType) {
                return redirect()->route('customer.dashboard')
                    ->with('error', 'Your customer group does not have a rate type configured. Please contact support.');
            }

            $rateType = $customerGroup->loanRateType;
        }

        $loanRate = LoanRate::where('id', $loanRateId)
            ->where('loan_rate_type_id', $rateType->id)
            ->where('is_active', true)
            ->first();

        if (!$loanRate) {
            return redirect()->route('customer.loans.calculate')
                ->with('error', 'The selected loan rate is invalid or inactive. Please recalculate your loan.');
        }

        // Calculate loan and payment dates
        $loanStartDate = Carbon::today();
        $firstPaymentDate = null;
        $lastPaymentDate = null;
        $paymentDueDates = null;

        if ($loanProduct->category === 'sme') {
            $smeSchedule = $this->calculateSmeSchedule($loanStartDate, $tenureMonths);
            $loanEndDate = $smeSchedule['loan_end_date'];
            $firstPaymentDate = $smeSchedule['first_payment_date'];
            $lastPaymentDate = $smeSchedule['last_payment_date'];
        } else {
            $cutOffDay = null;
            $paymentDay = null;

            if ($loanProduct->category === 'government' && $customerGroup) {
                $cutOffDay = (int) $customerGroup->loan_cut_off_day;
                $paymentDay = (int) $customerGroup->loan_payment_date;
            } elseif ($loanProduct->category === 'mou' && $company) {
                $cutOffDay = (int) $company->monthly_cut_off_day;
                $paymentDay = (int) $company->pay_day;
            }

            if ($cutOffDay && $paymentDay) {
                $payDaySchedule = app(LoanPayDayScheduleService::class)->calculateDueDates(
                    $loanStartDate,
                    $tenureMonths,
                    $cutOffDay,
                    $paymentDay
                );
                $loanEndDate = $payDaySchedule['loan_end_date'];
                $firstPaymentDate = $payDaySchedule['first_payment_date'];
                $lastPaymentDate = $payDaySchedule['last_payment_date'];
                $paymentDueDates = $payDaySchedule['payment_due_dates'];
            } else {
                $loanEndDate = $loanStartDate->copy()->addMonths($tenureMonths);
                $firstPaymentDate = $loanStartDate->copy()->addMonth();
                $lastPaymentDate = $firstPaymentDate->copy()->addMonths($tenureMonths - 1);
            }
        }

        $days = $loanStartDate->diffInDays($loanEndDate);

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

        $financials = $this->loanFinancialAttributesFromQuote($quote);
        $pricingMeta = $financials['pricing_metadata'] ?? [];
        unset($financials['pricing_metadata']);

        $requiresApproval = config('approval.loans.create', true);
        $status = $requiresApproval ? 'pending_approval' : 'approved';

        try {
            $loan = Loan::create(array_merge([
                'customer_id' => $customer->id,
                'loan_product_id' => $loanProduct->id,
                'customer_group_id' => $customerGroup?->id,
                'loan_rate_id' => $loanRate->id,
                'channel_id' => $channelId,
                'loan_number' => Loan::generateLoanNumber($loanProduct),
                'principal_amount' => $loanAmount,
                'tenure_months' => $tenureMonths,
                'loan_start_date' => $loanStartDate,
                'loan_end_date' => $loanEndDate,
                'first_payment_date' => $firstPaymentDate,
                'last_payment_date' => $lastPaymentDate,
                'amount_paid' => 0,
                'last_accrual_date' => ($financials['accrual_type'] ?? 'daily') === 'daily' ? $loanStartDate : null,
                'status' => $status,
                'disbursement_status' => 'pending',
                'metadata' => array_merge($pricingMeta, array_filter([
                    'calculated_days' => $days,
                    'rate_type_accrual_period' => $rateType->accrual_period,
                    'loan_rate_type_id' => $quote['loan_rate_type_id'] ?? $rateType->id,
                    'created_via' => 'customer_self_service',
                    'payment_due_dates' => $paymentDueDates,
                ])),
            ], $financials, $destinationAttributes));

            if ($firstPaymentDate) {
                $loan->createPaymentSchedule();
            }

            $this->applyPostLoanPricingSetup($loan);

            session()->forget([
                'loan_application.amount',
                'loan_application.channel_id',
                'loan_application.tenure_months',
                'loan_application.loan_rate_id',
                'loan_application.loan_start_date',
                'loan_application.loan_end_date',
                'loan_application.processing_fee',
                'loan_application.interest',
                'loan_application.total_amount',
                'loan_application.projected_total_amount',
                'loan_application.monthly_payment',
            ]);
            $this->forgetLoanApplicationDestinationSession();

            if ($requiresApproval) {
                return redirect()->route('customer.dashboard')
                    ->with('status', 'Your loan application has been submitted and is pending approval. You will be notified once it is reviewed.');
            } else {
                return redirect()->route('customer.dashboard')
                    ->with('status', 'Your loan has been approved and will be disbursed shortly.');
            }
        } catch (\Exception $e) {
            return redirect()->route('customer.loans.calculate')
                ->with('error', 'Failed to submit loan application: ' . $e->getMessage());
        }
    }

    /**
     * @return array{
     *     processing_fee: float,
     *     interest: float,
     *     total_amount: float,
     *     projected_total_amount: float,
     *     monthly_payment: float
     * }
     */
    private function buildAndStoreCustomerPricingCalculation(
        float $loanAmount,
        int $tenureMonths,
        Carbon $loanStartDate,
        Carbon $loanEndDate,
        int $days,
        LoanProduct $loanProduct,
        LoanRateType $rateType,
        LoanRate $loanRate,
    ): array {
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

        $financials = $this->loanFinancialAttributesFromQuote($quote);
        $projectedTotal = (float) $quote['total_amount'];
        $bookedTotal = (float) $financials['total_amount'];
        $monthlyPayment = $tenureMonths > 0 ? $projectedTotal / $tenureMonths : $projectedTotal;

        session([
            'loan_application.tenure_months' => $tenureMonths,
            'loan_application.loan_rate_id' => $quote['loan_rate_id'],
            'loan_application.loan_start_date' => $loanStartDate->toDateString(),
            'loan_application.loan_end_date' => $loanEndDate->toDateString(),
            'loan_application.processing_fee' => (float) $quote['processing_fee'],
            'loan_application.interest' => (float) $quote['interest'],
            'loan_application.total_amount' => $bookedTotal,
            'loan_application.projected_total_amount' => $projectedTotal,
            'loan_application.monthly_payment' => $monthlyPayment,
        ]);

        return [
            'processing_fee' => (float) $quote['processing_fee'],
            'interest' => (float) $quote['interest'],
            'total_amount' => $bookedTotal,
            'projected_total_amount' => $projectedTotal,
            'monthly_payment' => $monthlyPayment,
        ];
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
    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, FinancialInstitution>
     */
    private function activeFinancialInstitutions()
    {
        return FinancialInstitution::query()
            ->active()
            ->with(['branches' => fn ($query) => $query->active()->orderBy('name')])
            ->orderBy('name')
            ->get();
    }

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
}
