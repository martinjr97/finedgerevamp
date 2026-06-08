<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Concerns\EnforcesCustomerLoanEligibility;
use App\Http\Controllers\Concerns\ResolvesDisbursementDestination;
use App\Http\Controllers\Controller;
use App\Support\DocumentUploadRules;
use App\Models\Channel;
use App\Models\FinancialInstitution;
use App\Models\CollateralLoanDetail;
use App\Models\CollateralType;
use App\Models\Loan;
use App\Models\LoanRate;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;

class CollateralLoanController extends Controller
{
    use EnforcesCustomerLoanEligibility;
    use ResolvesDisbursementDestination;

    /**
     * Show step 1: Loan details form (customer is already logged in)
     */
    public function loanDetails(): View|RedirectResponse
    {
        $customer = auth('customer')->user();
        $customer->load(['loanProduct', 'customerGroup']);
        
        // Verify customer belongs to a collateral loan product
        if (!$customer->loanProduct || $customer->loanProduct->category !== 'collateral') {
            return redirect()->route('customer.dashboard')
                ->with('error', 'This loan application flow is only available for collateral-based loans.');
        }
        
        if ($redirect = $this->customerPortalLoanEligibilityRedirect($customer)) {
            return $redirect;
        }
        
        // Get customer group and rate type
        $customerGroup = $customer->customerGroup;
        if (!$customerGroup) {
            return redirect()->route('customer.dashboard')
                ->with('error', 'You do not have an assigned customer group.');
        }
        
        $rateType = $customerGroup->loanRateType;
        if (!$rateType) {
            return redirect()->route('customer.dashboard')
                ->with('error', 'Your customer group does not have a rate type configured.');
        }
        
        // Get available loan rates
        $loanRates = LoanRate::where('loan_rate_type_id', $rateType->id)
            ->where('is_active', true)
            ->orderBy('tenure_months')
            ->get();
        
        // Get disbursement channels
        $channels = Channel::where('can_disburse', true)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        
        // Get available loan amount
        $availableLoanAmount = $customer->getAvailableLoanAmount();
        $maxLoanAmount = min(
            $availableLoanAmount,
            $customerGroup->max_loan_amount ?? PHP_INT_MAX,
            $customer->loanProduct->max_amount ?? PHP_INT_MAX
        );
        
        $financialInstitutions = FinancialInstitution::query()
            ->active()
            ->with(['branches' => fn ($query) => $query->active()->orderBy('name')])
            ->orderBy('name')
            ->get();

        return view('customer.collateral-loans.loan-details', [
            'customer' => $customer,
            'customerGroup' => $customerGroup,
            'rateType' => $rateType,
            'loanRates' => $loanRates,
            'channels' => $channels,
            'financialInstitutions' => $financialInstitutions,
            'maxLoanAmount' => $maxLoanAmount,
            'availableLoanAmount' => $availableLoanAmount,
        ]);
    }

    /**
     * Calculate loan repayment amount
     */
    public function calculateRepayment(Request $request): JsonResponse
    {
        $customer = auth('customer')->user();
        $customer->load(['loanProduct', 'customerGroup']);
        
        // Verify it's a collateral product
        if (!$customer->loanProduct || $customer->loanProduct->category !== 'collateral') {
            return response()->json([
                'error' => 'This loan application flow is only available for collateral-based loans.',
            ], 403);
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
        
        // Get customer group and rate
        $customerGroup = $customer->customerGroup;
        $rateType = $customerGroup->loanRateType;
        
        // Validate loan amount doesn't exceed maximum allowed
        $availableLoanAmount = $customer->getAvailableLoanAmount();
        $maxLoanAmount = min(
            $availableLoanAmount,
            $customerGroup->max_loan_amount ?? PHP_INT_MAX,
            $customer->loanProduct->max_amount ?? PHP_INT_MAX
        );
        
        if ($loanAmount > $maxLoanAmount) {
            return response()->json([
                'error' => "Loan amount cannot exceed your maximum qualified amount of " . number_format($maxLoanAmount, 2) . ". Your available loan amount is " . number_format($availableLoanAmount, 2) . ".",
            ], 422);
        }
        
        $loanRate = LoanRate::where('loan_rate_type_id', $rateType->id)
            ->where('tenure_months', $tenureMonths)
            ->where('is_active', true)
            ->first();
        
        if (!$loanRate) {
            return response()->json([
                'error' => 'Loan rate not found for the selected tenure.',
            ], 422);
        }
        
        // Calculate processing fee
        $processingFeePercentage = $loanRate->processing_fee_percentage ?? 0;
        $processingFee = ($loanAmount * $processingFeePercentage) / 100;
        
        // Calculate interest
        $interest = 0;
        if ($rateType->accrual_period === 'daily' && $loanRate->daily_rate) {
            $interest = $loanAmount * $loanRate->daily_rate * $days;
        } elseif ($rateType->accrual_period === 'weekly' && $loanRate->weekly_rate) {
            $weeks = ceil($days / 7);
            $interest = $loanAmount * $loanRate->weekly_rate * $weeks;
        }
        
        // Calculate total
        $totalAmount = $loanAmount + $processingFee + $interest;
        
        return response()->json([
            'principal_amount' => $loanAmount,
            'processing_fee' => $processingFee,
            'processing_fee_percentage' => $processingFeePercentage,
            'interest' => $interest,
            'total_amount' => $totalAmount,
            'loan_start_date' => $loanStartDate->toDateString(),
            'loan_end_date' => $loanEndDate->toDateString(),
            'days' => $days,
            'daily_rate' => $loanRate->daily_rate,
            'weekly_rate' => $loanRate->weekly_rate,
            'accrual_period' => $rateType->accrual_period,
        ]);
    }

    /**
     * Store loan calculation data in session
     */
    public function storeCalculation(Request $request): JsonResponse
    {
        $customer = auth('customer')->user();
        
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

        try {
            $destinationNormalized = $this->normalizeDisbursementDestination(
                $this->destinationPayloadFromRequest($request)
            );
        } catch (\Illuminate\Validation\ValidationException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => $exception->errors(),
            ], 422);
        }

        $request->session()->put('collateral_loan_application_data', array_merge($calcValidated, $destinationNormalized));

        return response()->json(['success' => true]);
    }

    /**
     * Show step 2: Collateral selection
     */
    public function collateral(Request $request): View|RedirectResponse
    {
        $customer = auth('customer')->user();
        $customer->load(['loanProduct']);
        
        // Verify it's a collateral product
        if (!$customer->loanProduct || $customer->loanProduct->category !== 'collateral') {
            return redirect()->route('customer.dashboard')
                ->with('error', 'This loan application flow is only available for collateral-based loans.');
        }
        
        // Get loan details from session
        $loanData = $request->session()->get('collateral_loan_application_data');
        
        if (!$loanData) {
            return redirect()->route('customer.collateral-loans.loan-details')
                ->with('error', 'Please complete the loan details first.');
        }
        
        // Get collateral types for this product
        $collateralTypes = CollateralType::where('loan_product_id', $customer->loanProduct->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        
        if ($collateralTypes->isEmpty()) {
            return redirect()->route('customer.collateral-loans.loan-details')
                ->with('error', 'No collateral types configured for this loan product.');
        }
        
        return view('customer.collateral-loans.collateral', [
            'customer' => $customer,
            'collateralTypes' => $collateralTypes,
            'loanData' => $loanData,
        ]);
    }

    /**
     * Calculate LTV amount
     */
    public function calculateLTV(Request $request): JsonResponse
    {
        $customer = auth('customer')->user();
        
        $validated = $request->validate([
            'collateral_type_id' => 'required|exists:collateral_types,id',
            'collateral_value' => 'required|numeric|min:0',
        ]);
        
        $collateralType = CollateralType::findOrFail($validated['collateral_type_id']);
        
        // Verify collateral type belongs to customer's loan product
        if ($collateralType->loan_product_id !== $customer->loanProduct->id) {
            return response()->json([
                'error' => 'Invalid collateral type for your loan product.',
            ], 403);
        }
        
        $collateralValue = (float) $validated['collateral_value'];
        
        // Validate collateral value is within range
        if ($collateralValue < $collateralType->min_value || $collateralValue > $collateralType->max_value) {
            return response()->json([
                'error' => "Collateral value must be between " . number_format($collateralType->min_value, 2) . " and " . number_format($collateralType->max_value, 2) . ".",
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
    public function store(Request $request): RedirectResponse
    {
        $customer = auth('customer')->user();
        $customer->load(['loanProduct', 'customerGroup']);
        
        // Verify it's a collateral product
        if (!$customer->loanProduct || $customer->loanProduct->category !== 'collateral') {
            return redirect()->route('customer.dashboard')
                ->with('error', 'This loan application flow is only available for collateral-based loans.');
        }

        $validated = $request->validate([
            'loan_amount' => 'required|numeric|min:1',
            'tenure_months' => 'required|integer|min:1',
            'loan_start_date' => 'required|date',
            'channel_id' => 'required|exists:channels,id',
            'collateral_type_id' => 'required|exists:collateral_types,id',
            'collateral_value' => 'required|numeric|min:0',
            'collateral_description' => 'nullable|string|max:1000',
            'serial_number' => 'nullable|string|max:255',
            'item_quantity' => 'nullable|integer|min:1',
            'item_condition' => 'nullable|string|in:excellent,good,fair,poor',
            'location' => 'nullable|string|max:500',
            'images.*' => DocumentUploadRules::nullableMultipleImages(),
        ]);
        
        // Get loan data from session
        $loanData = $request->session()->get('collateral_loan_application_data');
        if (!$loanData) {
            return redirect()->route('customer.collateral-loans.loan-details')
                ->with('error', 'Loan calculation data not found. Please recalculate.');
        }
        
        $loanData = array_merge($loanData, [
            'loan_amount' => $validated['loan_amount'],
            'tenure_months' => $validated['tenure_months'],
            'loan_start_date' => $validated['loan_start_date'],
            'channel_id' => $validated['channel_id'],
        ]);

        try {
            $destinationAttributes = $this->destinationAttributesFromLoanData($loanData);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }
        
        // Verify collateral type belongs to this product
        $collateralType = CollateralType::where('id', $validated['collateral_type_id'])
            ->where('loan_product_id', $customer->loanProduct->id)
            ->firstOrFail();
        
        // Validate collateral value
        if ($validated['collateral_value'] < $collateralType->min_value || 
            $validated['collateral_value'] > $collateralType->max_value) {
            return back()->withErrors([
                'collateral_value' => "Collateral value must be between " . number_format($collateralType->min_value, 2) . " and " . number_format($collateralType->max_value, 2) . ".",
            ])->withInput();
        }
        
        // Calculate LTV
        $ltvRatio = $collateralType->loan_to_value_ratio / 100;
        $ltvAmount = $validated['collateral_value'] * $ltvRatio;
        
        // Verify loan amount doesn't exceed LTV
        if ($validated['loan_amount'] > $ltvAmount) {
            return back()->withErrors([
                'loan_amount' => "Loan amount cannot exceed the LTV amount of " . number_format($ltvAmount, 2) . ".",
            ])->withInput();
        }
        
        // Get customer group and rate
        $customerGroup = $customer->customerGroup;
        $rateType = $customerGroup->loanRateType;
        
        $tenureMonths = (int) $validated['tenure_months'];
        $loanRate = LoanRate::where('loan_rate_type_id', $rateType->id)
            ->where('tenure_months', $tenureMonths)
            ->where('is_active', true)
            ->firstOrFail();
        
        $loanStartDate = Carbon::parse($validated['loan_start_date']);
        $tenureMonths = (int) $validated['tenure_months'];
        $loanEndDate = $loanStartDate->copy()->addMonths($tenureMonths);
        $days = $loanStartDate->diffInDays($loanEndDate);
        
        // Calculate payment dates
        $firstPaymentDate = $loanStartDate->copy()->addMonth();
        $lastPaymentDate = $loanEndDate;
        
        DB::beginTransaction();
        try {
            // Create loan
            $loan = Loan::create(array_merge([
                'customer_id' => $customer->id,
                'loan_product_id' => $customer->loanProduct->id,
                'customer_group_id' => $customerGroup->id,
                'loan_rate_id' => $loanRate->id,
                'channel_id' => $validated['channel_id'],
                'loan_number' => Loan::generateLoanNumber($customer->loanProduct),
                'principal_amount' => $validated['loan_amount'],
                'processing_fee' => $loanData['processing_fee'],
                'processing_fee_percentage' => $loanRate->processing_fee_percentage,
                'daily_rate' => $loanRate->daily_rate,
                'weekly_rate' => $loanRate->weekly_rate,
                'accrual_period' => $rateType->accrual_period,
                'interest_accrued' => $loanData['interest'],
                'total_amount' => $loanData['total_amount'],
                'amount_paid' => 0,
                'outstanding_balance' => $loanData['total_amount'],
                'tenure_months' => $tenureMonths,
                'loan_start_date' => $loanStartDate,
                'loan_end_date' => $loanEndDate,
                'first_payment_date' => $firstPaymentDate,
                'last_payment_date' => $lastPaymentDate,
                'accrual_type' => $customer->loanProduct->accrual_type ?? 'at_beginning',
                'last_accrual_date' => ($customer->loanProduct->accrual_type ?? 'at_beginning') === 'daily' ? $loanStartDate : null,
                'status' => config('approval.loans.create', true) ? 'pending_approval' : 'approved',
                'disbursement_status' => 'pending',
                'metadata' => [
                    'calculated_days' => $days,
                    'rate_type_accrual_period' => $rateType->accrual_period,
                    'created_via' => 'customer_application',
                    'created_by_customer' => $customer->id,
                ],
            ], $destinationAttributes));
            
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
                'is_inspected' => false, // Customer can't mark as inspected
                'inspected_by' => null,
                'inspected_at' => null,
                'location' => $validated['location'] ?? null,
                'images' => !empty($imagePaths) ? $imagePaths : null,
            ]);
            
            // Create payment schedule
            $loan->createPaymentSchedule();
            
            // Create accrual records if needed
            if ($loan->accrual_type === 'at_beginning') {
                $loan->createAtBeginningAccruals();
            }
            
            DB::commit();
            
            // Clear session
            $request->session()->forget('collateral_loan_application_data');
            
            $statusMessage = config('approval.loans.create', true)
                ? 'Loan application submitted successfully and is pending approval.'
                : 'Loan created successfully.';
            
            return redirect()->route('customer.dashboard')
                ->with('status', $statusMessage);
                
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to create loan application: ' . $e->getMessage())->withInput();
        }
    }
}

