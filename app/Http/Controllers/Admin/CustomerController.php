<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\CustomerPaymentDetail;
use App\Models\District;
use App\Models\CustomerRegistrationRequest;
use App\Models\FinancialInstitution;
use App\Models\FinancialInstitutionBranch;
use App\Models\KycDocument;
use App\Models\LoanProduct;
use App\Models\Market;
use App\Models\MarketeerCustomerDetail;
use App\Models\Ministry;
use App\Models\Province;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\WalletProvider;
use App\Support\NationalIdRules;
use App\Support\ZambianPhoneRules;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Rules\ValidNationalIdNumber;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(auth('admin')->user()?->can('customers.view'), 403);

        $admin = auth('admin')->user();
        $companyFilterId = $admin->getCompanyFilterId();

        $query = Customer::with(['company', 'loanProduct', 'customerGroup']);

        // Filter by company if not primary company admin
        if ($companyFilterId !== null) {
            $query->where('company_id', $companyFilterId);
        }

        // Search filter
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('national_id', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        // Approval status filter
        if ($request->has('approval_status') && $request->approval_status) {
            $query->where('approval_status', $request->approval_status);
        }

        // Product filter
        if ($request->has('loan_product_id') && $request->loan_product_id) {
            $query->where('loan_product_id', $request->loan_product_id);
        }

        // Customer group filter
        if ($request->has('customer_group_id') && $request->customer_group_id) {
            $query->where('customer_group_id', $request->customer_group_id);
        }

        // Company filter (only if primary admin, otherwise already filtered)
        if ($companyFilterId === null && $request->has('company_id') && $request->company_id) {
            $query->where('company_id', $request->company_id);
        }

        // Date from filter
        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        // Date to filter
        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $customers = $query->latest()->paginate(20);

        // Get filter options (also filtered by company if needed)
        $loanProductsQuery = LoanProduct::where('is_active', true);
        $customerGroupsQuery = CustomerGroup::where('is_active', true)->with('loanProduct');
        $companiesQuery = Company::where('status', 'active');
        
        if ($companyFilterId !== null) {
            $loanProductsQuery->where('company_id', $companyFilterId);
            $customerGroupsQuery->whereHas('loanProduct', function ($q) use ($companyFilterId) {
                $q->where('company_id', $companyFilterId);
            });
            $companiesQuery->where('id', $companyFilterId);
        }
        
        $loanProducts = $loanProductsQuery->orderBy('name')->get();
        $customerGroups = $customerGroupsQuery->orderBy('name')->get();
        $companies = $companiesQuery->orderBy('name')->get();

        return view('admin.customers.index', compact('customers', 'loanProducts', 'customerGroups', 'companies'));
    }

    /**
     * Show product type selection page
     */
    public function selectProductType(): View
    {
        $products = LoanProduct::where('is_active', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get()
            ->groupBy('category');

        return view('admin.customers.select-product-type', compact('products'));
    }

    /**
     * Show the form for creating a new customer based on product type
     */
    public function create(Request $request): View|RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('customers.create'), 403);
        $productId = $request->query('product_id');
        
        if (!$productId) {
            return redirect()->route('admin.customers.select-product-type')
                ->with('error', 'Please select a product type first.');
        }

        $product = LoanProduct::findOrFail($productId);
        $companies = Company::orderBy('name')->get();
        $relationshipManagers = Admin::where('is_relationship_manager', true)
            ->orderBy('first_name')
            ->get();
        
        $ministries = Ministry::where('is_active', true)->orderBy('name')->get();
        $provinces = Province::where('is_active', true)->orderBy('name')->get();
        $districts = District::where('is_active', true)->orderBy('name')->get();

        // Prefill from approved self-registration request, if provided and no existing old input
        $registrationRequestId = $request->query('registration_request');
        if ($registrationRequestId && empty(session()->getOldInput())) {
            $registrationRequest = CustomerRegistrationRequest::query()
                ->where('id', $registrationRequestId)
                ->where('loan_product_id', $product->id)
                ->where('status', 'approved')
                ->first();

            if ($registrationRequest) {
                $payload = $registrationRequest->payload ?? [];
                $employment = $registrationRequest->employment_details ?? [];
                $collateral = $registrationRequest->collateral_details ?? [];

                $ministryId = $employment['ministry_id'] ?? null;
                if (! empty($employment['ministry_is_other'])) {
                    $ministryId = \App\Support\PublicRegistrationPaths::MINISTRY_OTHER;
                }

                $oldInput = array_merge($payload, $employment, $collateral, [
                    'loan_product_id' => $registrationRequest->loan_product_id,
                    'customer_group_id' => $registrationRequest->customer_group_id,
                    'first_name' => $registrationRequest->first_name,
                    'last_name' => $registrationRequest->last_name,
                    'email' => $registrationRequest->email,
                    'phone' => $registrationRequest->phone,
                    'national_id' => $registrationRequest->national_id,
                    'national_id_type' => $registrationRequest->national_id_type,
                    'tpin' => $registrationRequest->tpin,
                    'requested_loan_amount' => $registrationRequest->requested_loan_amount,
                    'employee_number' => $employment['employee_number'] ?? null,
                    'ministry_id' => $ministryId,
                    'employer_name' => $employment['employer_name'] ?? null,
                    'department' => $employment['department'] ?? null,
                    'gross_salary' => $employment['gross_salary'] ?? null,
                    'net_salary' => $employment['net_salary'] ?? null,
                    'work_address_line1' => $employment['work_address_line1'] ?? null,
                    'work_address_line2' => $employment['work_address_line2'] ?? null,
                    'work_city' => $employment['work_city'] ?? null,
                    'work_province_id' => $employment['work_province_id'] ?? null,
                    'work_district_id' => $employment['work_district_id'] ?? null,
                    'date_of_employment' => $employment['date_of_employment'] ?? null,
                    'address_line1' => $employment['address_line1'] ?? $collateral['address_line1'] ?? null,
                    'address_line2' => $employment['address_line2'] ?? $collateral['address_line2'] ?? null,
                    'city' => $employment['city'] ?? $collateral['city'] ?? null,
                    'province_id' => $employment['province_id'] ?? $collateral['province_id'] ?? null,
                    'district_id' => $employment['district_id'] ?? $collateral['district_id'] ?? null,
                    'postal_code' => $employment['postal_code'] ?? $collateral['postal_code'] ?? null,
                    'country' => $employment['country'] ?? $collateral['country'] ?? null,
                    'collateral_type_id' => $collateral['collateral_type_id'] ?? null,
                    'collateral_description' => $collateral['collateral_description'] ?? null,
                    'estimated_collateral_value' => $collateral['estimated_collateral_value'] ?? null,
                    'registration_request_id' => $registrationRequest->id,
                ]);

                $request->session()->flash('_old_input', $oldInput);
            }
        }
        
        // Get customer groups for products that require group assignment.
        $customerGroups = collect();
        if (in_array($product->category, ['character', 'collateral', 'group_loans'], true)) {
            $customerGroups = CustomerGroup::where('loan_product_id', $product->id)
                ->where('is_active', true)
                ->orderBy('risk_level')
                ->orderBy('name')
                ->get();
        }

        // Get markets for marketeer loans
        $markets = collect();
        if ($product->category === 'marketeer') {
            $markets = Market::where('is_active', true)
                ->with(['province', 'district', 'portfolioManager'])
                ->orderBy('name')
                ->get();
        }

        $companyCustomers = collect();
        if ($product->category === 'sme') {
            $companyCustomers = Customer::where('loan_product_id', $product->id)
                ->where('customer_type', 'company')
                ->orderBy('registered_name')
                ->orderBy('first_name')
                ->get();
        }

        $referredByCustomers = Customer::query()
            ->select(['id', 'first_name', 'last_name', 'registered_name', 'phone'])
            ->orderBy('registered_name')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        // Check for pending failed upload batches
        $pendingFailedBatches = \App\Models\CustomerUploadBatch::where('status', 'completed')
            ->where('failed_records', '>', 0)
            ->whereHas('failedRecords')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return view('admin.customers.create', [
            'product' => $product,
            'companies' => $companies,
            'relationshipManagers' => $relationshipManagers,
            'ministries' => $ministries,
            'provinces' => $provinces,
            'districts' => $districts,
            'customerGroups' => $customerGroups,
            'markets' => $markets,
            'companyCustomers' => $companyCustomers,
            'referredByCustomers' => $referredByCustomers,
            'registrationRequestId' => $registrationRequestId ?? null,
            'pendingFailedBatches' => $pendingFailedBatches,
        ]);
    }

    /**
     * Store a newly created customer
     */
    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('customers.create'), 403);
        $product = LoanProduct::findOrFail($request->input('loan_product_id'));
        $requiresApproval = config('approval.customers.create', false);

        // Base validation rules
        $rules = [
            'loan_product_id' => 'required|exists:loan_products,id',
            'customer_type' => 'nullable|in:individual,company,representative',
            'parent_customer_id' => 'nullable|exists:customers,id',
            'referred_by' => 'nullable|exists:customers,id',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'registered_name' => 'nullable|string|max:255',
            'email' => 'required|email|unique:customers,email',
            'phone' => ZambianPhoneRules::nullableUnique(),
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:' . now()->subYears(16)->format('Y-m-d')],
            'gender' => ['nullable', 'in:male,female,other'],
            'address_line1' => 'required|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'required|string|max:100',
            'province_id' => 'nullable|exists:provinces,id',
            'state' => 'nullable|string|max:100', // Keep for backward compatibility
            'postal_code' => 'nullable|string|max:20',
            'country' => 'required|string|max:100',
            'registration_request_id' => 'nullable|exists:customer_registration_requests,id',
        ];
        // SME: company-as-customer with optional representatives
        if ($product->category === 'sme') {
            $rules = [
                'loan_product_id' => 'required|exists:loan_products,id',
                'customer_type' => 'required|in:company,representative',
                'company_id' => 'required|exists:companies,id',
                'parent_customer_id' => 'required_if:customer_type,representative|exists:customers,id',
                'referred_by' => 'nullable|exists:customers,id',
                'registered_name' => 'required_if:customer_type,company|nullable|string|max:255',
                'monthly_net_revenue' => 'required_if:customer_type,company|nullable|numeric|min:0',
                'qualification_percentage' => 'required_if:customer_type,company|nullable|numeric|between:0,100',
                'first_name' => 'required_if:customer_type,representative|nullable|string|max:255',
                'last_name' => 'required_if:customer_type,representative|nullable|string|max:255',
                'national_id_type' => 'required_if:customer_type,representative|nullable|'.Rule::in(array_keys(NationalIdRules::typeLabels())),
                'national_id' => array_merge(
                    ['required_if:customer_type,representative', 'nullable', 'string', 'max:50', new ValidNationalIdNumber()],
                    [Rule::unique('customers', 'national_id')]
                ),
                'email' => 'required|email|unique:customers,email',
                'phone' => ZambianPhoneRules::nullableUnique(),
                'tpin' => NationalIdRules::tpin(),
                'address_line1' => 'nullable|string|max:255',
                'address_line2' => 'nullable|string|max:255',
                'city' => 'nullable|string|max:100',
                'province_id' => 'nullable|exists:provinces,id',
                'state' => 'nullable|string|max:100',
                'postal_code' => 'nullable|string|max:20',
                'country' => 'nullable|string|max:100',
            ];
        }

        // Add product-specific validation rules
        if (in_array($product->category, ['government', 'character', 'collateral', 'marketeer', 'group_loans'], true)) {
            // Government employees, character-based, collateral-based, and marketeer loans don't require company_id
            $rules['company_id'] = 'nullable|exists:companies,id';
        } else {
            // Other product types require company_id
            $rules['company_id'] = 'required|exists:companies,id';
        }

        if ($product->category === 'government') {
            $rules = array_merge($rules, [
                'employee_number' => 'required|string|max:50',
                'ministry_id' => 'required|exists:ministries,id',
                'date_of_employment' => 'required|date',
                'contract_end_date' => 'nullable|date|after:date_of_employment',
                'gross_salary' => 'required|numeric|min:0',
                'net_salary' => 'required|numeric|min:0',
                'deductions' => 'nullable|numeric|min:0',
                'verified_by' => 'required|exists:admins,id',
                'work_address_line1' => 'nullable|string|max:255',
                'work_address_line2' => 'nullable|string|max:255',
                'work_city' => 'nullable|string|max:100',
                'work_province_id' => 'nullable|exists:provinces,id',
                'work_district_id' => 'nullable|exists:districts,id',
                'work_postal_code' => 'nullable|string|max:20',
                'work_country' => 'nullable|string|max:100',
                'next_of_kin_name' => 'required|string|max:255',
                'next_of_kin_phone' => ZambianPhoneRules::required(),
                'next_of_kin_relationship' => 'required|string|max:50',
                'next_of_kin_address_line1' => 'nullable|string|max:255',
                'next_of_kin_address_line2' => 'nullable|string|max:255',
                'next_of_kin_city' => 'nullable|string|max:100',
                'next_of_kin_country' => 'nullable|string|max:100',
            ]);
        } elseif ($product->category === 'mou') {
            $rules = array_merge($rules, [
                'employee_number' => 'required|string|max:50',
                'position' => 'required|string|max:255',
                'unit' => 'nullable|string|max:255',
                'department' => 'required|string|max:255',
                'date_of_employment' => 'required|date',
                'contract_end_date' => 'nullable|date|after:date_of_employment',
                'gross_salary' => 'required|numeric|min:0',
                'net_salary' => 'required|numeric|min:0',
                'verified_by' => 'required|exists:admins,id',
            ]);
        } elseif ($product->category === 'character' || $product->category === 'collateral') {
            $rules = array_merge($rules, [
                'customer_group_id' => 'required|exists:customer_groups,id',
                'next_of_kin_name' => 'required|string|max:255',
                'next_of_kin_phone' => ZambianPhoneRules::required(),
                'next_of_kin_relationship' => 'required|string|max:50',
                'next_of_kin_address_line1' => 'nullable|string|max:255',
                'next_of_kin_address_line2' => 'nullable|string|max:255',
                'next_of_kin_city' => 'nullable|string|max:100',
                'next_of_kin_country' => 'nullable|string|max:100',
                'is_employed' => 'required|boolean',
                'payday' => 'nullable|integer|min:1|max:31',
                'gross_salary' => 'nullable|numeric|min:0',
                'net_salary' => 'required|numeric|min:0',
            ]);
            if ($product->category === 'character') {
                $rules['employee_number'] = [
                    'nullable',
                    'string',
                    'max:50',
                    Rule::requiredIf(fn () => filter_var(request()->input('is_employed'), FILTER_VALIDATE_BOOLEAN)),
                ];
            }
        } elseif ($product->category === 'group_loans') {
            $rules = array_merge($rules, [
                'customer_group_id' => 'nullable|exists:customer_groups,id',
                'occupation_type' => 'required|in:employed,business_owner',
                'employer_or_business_name' => 'required|string|max:255',
                'average_income' => 'required|numeric|min:0',
                'work_address_line1' => 'required|string|max:255',
                'work_address_line2' => 'nullable|string|max:255',
                'work_city' => 'required|string|max:100',
                'work_province_id' => 'nullable|exists:provinces,id',
                'work_district_id' => 'nullable|exists:districts,id',
                'work_postal_code' => 'nullable|string|max:20',
                'work_country' => 'required|string|max:100',
            ]);
        } elseif ($product->category === 'marketeer') {
            $rules = array_merge($rules, [
                'company_id' => 'nullable|exists:companies,id',
                'market_id' => 'required|exists:markets,id',
                'next_of_kin_name' => 'required|string|max:255',
                'next_of_kin_phone' => ZambianPhoneRules::required(),
                'next_of_kin_relationship' => 'required|string|max:50',
                'next_of_kin_address_line1' => 'required|string|max:255',
                'next_of_kin_address_line2' => 'nullable|string|max:255',
                'next_of_kin_city' => 'required|string|max:100',
                'next_of_kin_country' => 'required|string|max:100',
                'stand_number' => 'nullable|string|max:255',
                'stand_description' => 'nullable|string',
                'monthly_income' => 'required|numeric|min:0',
            ]);
        }

        if ($product->category !== 'sme') {
            $rules = NationalIdRules::merge($rules);
        }

        $messages = array_merge(ZambianPhoneRules::messages(), NationalIdRules::messages(), [
            'date_of_birth.before_or_equal' => 'The customer must be at least 16 years old.',
            'contract_end_date.after' => 'Contract End Date must be greater than Date of Employment.',
        ]);

        $validated = $request->validate($rules, $messages, array_merge(
            ZambianPhoneRules::attributes(),
            NationalIdRules::attributes()
        ));

        if (
            array_key_exists('gross_salary', $validated)
            && $validated['gross_salary'] !== null
            && $validated['gross_salary'] !== ''
            && array_key_exists('net_salary', $validated)
            && $validated['net_salary'] !== null
            && (float) $validated['net_salary'] > (float) $validated['gross_salary']
        ) {
            throw ValidationException::withMessages([
                'net_salary' => 'Net Salary must be less than or equal to Gross Salary.',
            ]);
        }

        $registrationRequest = null;
        if (!empty($validated['registration_request_id'] ?? null)) {
            $registrationRequest = CustomerRegistrationRequest::query()
                ->where('id', $validated['registration_request_id'])
                ->where('loan_product_id', $product->id)
                ->where('status', 'approved')
                ->whereNull('created_customer_id')
                ->first();
        }

        try {
            // Generate 4-digit PIN
            $pin = str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);

            $customerData = [
                'company_id' => in_array($product->category, ['government', 'character', 'collateral', 'group_loans'], true) ? null : ($validated['company_id'] ?? null),
                'loan_product_id' => $validated['loan_product_id'],
                'customer_type' => $validated['customer_type'] ?? 'individual',
                'parent_customer_id' => $validated['parent_customer_id'] ?? null,
                'referred_by' => $validated['referred_by'] ?? null,
                'first_name' => $validated['first_name'] ?? null,
                'last_name' => $validated['last_name'] ?? null,
                'registered_name' => $validated['registered_name'] ?? null,
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'password' => Hash::make($pin), // Store PIN in password field
                'must_change_pin' => true,
                'date_of_birth' => $validated['date_of_birth'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'national_id' => $validated['national_id'] ?? null,
                'national_id_type' => $validated['national_id_type'] ?? null,
                'tpin' => $validated['tpin'] ?? null,
                'address_line1' => $validated['address_line1'] ?? null,
                'address_line2' => $validated['address_line2'] ?? null,
                'city' => $validated['city'] ?? null,
                'province_id' => $validated['province_id'] ?? null,
                'state' => $validated['state'] ?? null,
                'postal_code' => $validated['postal_code'] ?? null,
                'country' => $validated['country'] ?? null,
                'status' => 'pending', // Will be activated after KYC upload
                'kyc_status' => 'unverified',
                'must_change_password' => true,
                'approval_status' => $requiresApproval ? 'pending' : 'approved',
            ];

            if ($product->category === 'sme') {
                if (($customerData['customer_type'] ?? 'company') === 'company') {
                    // Use registered name as display name for company customer
                    $customerData['first_name'] = $customerData['registered_name'];
                    $customerData['last_name'] = $customerData['registered_name']; // keep non-null for DB constraint
                    $customerData['parent_customer_id'] = null;
                } elseif (!empty($customerData['parent_customer_id'])) {
                    // Representatives inherit company and product from parent company customer
                    $parent = Customer::find($customerData['parent_customer_id']);
                    if ($parent) {
                        $customerData['company_id'] = $parent->company_id;
                        $customerData['loan_product_id'] = $parent->loan_product_id;
                    }
                }
            }

            // Add product-specific data
            if ($product->category === 'government') {
                // Get or create DEFAULT customer group for government product
                $defaultGroup = CustomerGroup::where('loan_product_id', $product->id)
                    ->where('code', 'GOV-DEFAULT')
                    ->first();

                if (!$defaultGroup) {
                    // Create DEFAULT group if it doesn't exist
                    $defaultGroup = CustomerGroup::create([
                        'loan_product_id' => $product->id,
                        'name' => 'DEFAULT',
                        'code' => 'GOV-DEFAULT',
                        'description' => 'Default customer group for all government employees',
                        'risk_level' => 'medium',
                        'max_loan_amount' => $product->max_amount ?? null,
                        'max_loan_tenure_months' => $product->tenure_months ?? null,
                        'is_active' => true,
                    ]);
                }

                $customerData = array_merge($customerData, [
                    'customer_group_id' => $defaultGroup->id,
                    'employee_number' => $validated['employee_number'],
                    'ministry_id' => $validated['ministry_id'],
                    'date_of_employment' => $validated['date_of_employment'],
                    'contract_end_date' => $validated['contract_end_date'] ?? null,
                    'gross_salary' => $validated['gross_salary'],
                    'net_salary' => $validated['net_salary'],
                    'deductions' => $validated['deductions'] ?? 0,
                    'verified_by' => $validated['verified_by'],
                    'employment_status' => 'employed',
                    'work_address_line1' => $validated['work_address_line1'] ?? null,
                    'work_address_line2' => $validated['work_address_line2'] ?? null,
                    'work_city' => $validated['work_city'] ?? null,
                    'work_province_id' => $validated['work_province_id'] ?? null,
                    'work_district_id' => $validated['work_district_id'] ?? null,
                    'work_postal_code' => $validated['work_postal_code'] ?? null,
                    'work_country' => $validated['work_country'] ?? 'Zambia',
                    'next_of_kin_name' => $validated['next_of_kin_name'],
                    'next_of_kin_phone' => $validated['next_of_kin_phone'],
                    'next_of_kin_relationship' => $validated['next_of_kin_relationship'],
                    'next_of_kin_address_line1' => $validated['next_of_kin_address_line1'] ?? null,
                    'next_of_kin_address_line2' => $validated['next_of_kin_address_line2'] ?? null,
                    'next_of_kin_city' => $validated['next_of_kin_city'] ?? null,
                    'next_of_kin_country' => $validated['next_of_kin_country'] ?? 'Zambia',
                ]);
            } elseif ($product->category === 'mou') {
                $customerData = array_merge($customerData, [
                    'company_id' => $validated['company_id'],
                    'employee_number' => $validated['employee_number'],
                    'position' => $validated['position'],
                    'unit' => $validated['unit'] ?? null,
                    'department' => $validated['department'],
                    'date_of_employment' => $validated['date_of_employment'],
                    'contract_end_date' => $validated['contract_end_date'] ?? null,
                    'gross_salary' => $validated['gross_salary'],
                    'net_salary' => $validated['net_salary'],
                    'verified_by' => $validated['verified_by'],
                    'employment_status' => 'employed',
                ]);
            } elseif ($product->category === 'character' || $product->category === 'collateral') {
                $characterExtras = [];
                if ($product->category === 'character') {
                    $characterExtras['employee_number'] = $validated['is_employed']
                        ? ($validated['employee_number'] ?? null)
                        : null;
                }
                $customerData = array_merge($customerData, [
                    'customer_group_id' => $validated['customer_group_id'],
                    'next_of_kin_name' => $validated['next_of_kin_name'],
                    'next_of_kin_phone' => $validated['next_of_kin_phone'],
                    'next_of_kin_relationship' => $validated['next_of_kin_relationship'],
                    'next_of_kin_address_line1' => $validated['next_of_kin_address_line1'] ?? null,
                    'next_of_kin_address_line2' => $validated['next_of_kin_address_line2'] ?? null,
                    'next_of_kin_city' => $validated['next_of_kin_city'] ?? null,
                    'next_of_kin_country' => $validated['next_of_kin_country'] ?? 'Zambia',
                    'is_employed' => $validated['is_employed'],
                    'payday' => $validated['payday'] ?? null,
                    'gross_salary' => $validated['gross_salary'] ?? null,
                    'net_salary' => $validated['net_salary'],
                    'employment_status' => $validated['is_employed'] ? 'employed' : 'unemployed',
                ], $characterExtras);
            } elseif ($product->category === 'group_loans') {
                $selectedGroup = null;
                if (! empty($validated['customer_group_id'])) {
                    $selectedGroup = CustomerGroup::where('id', $validated['customer_group_id'])
                        ->where('loan_product_id', $product->id)
                        ->where('is_active', true)
                        ->first();

                    if (! $selectedGroup) {
                        throw ValidationException::withMessages([
                            'customer_group_id' => 'The selected group does not belong to this Group Loans product.',
                        ]);
                    }
                }

                if (! $selectedGroup) {
                    $selectedGroup = CustomerGroup::firstOrCreate(
                        [
                            'loan_product_id' => $product->id,
                            'code' => 'GL-DEFAULT',
                        ],
                        [
                            'name' => 'Default Group',
                            'description' => 'Default active group for Group Loans onboarding fallback.',
                            'risk_level' => 'medium',
                            'max_loan_amount' => $product->max_amount ?? null,
                            'max_loan_tenure_months' => $product->tenure_months ?? null,
                            'is_active' => true,
                        ]
                    );
                }

                $metadata = is_array($customerData['metadata'] ?? null) ? $customerData['metadata'] : [];
                $customerData = array_merge($customerData, [
                    'company_id' => null,
                    'customer_group_id' => $selectedGroup->id,
                    'net_salary' => $validated['average_income'],
                    'employment_status' => $validated['occupation_type'] === 'business_owner' ? 'self_employed' : 'employed',
                    'work_address_line1' => $validated['work_address_line1'],
                    'work_address_line2' => $validated['work_address_line2'] ?? null,
                    'work_city' => $validated['work_city'],
                    'work_province_id' => $validated['work_province_id'] ?? null,
                    'work_district_id' => $validated['work_district_id'] ?? null,
                    'work_postal_code' => $validated['work_postal_code'] ?? null,
                    'work_country' => $validated['work_country'],
                    'metadata' => array_merge($metadata, [
                        'group_loans_occupation_type' => $validated['occupation_type'],
                        'group_loans_employer_or_business_name' => $validated['employer_or_business_name'],
                    ]),
                ]);
            } elseif ($product->category === 'sme') {
                if (($customerData['customer_type'] ?? 'company') === 'company') {
                    $qualificationPercentage = max(0, min(100, (float) ($validated['qualification_percentage'] ?? 0)));
                    $metadata = is_array($customerData['metadata'] ?? null) ? $customerData['metadata'] : [];

                    $customerData = array_merge($customerData, [
                        'net_salary' => $validated['monthly_net_revenue'],
                        'metadata' => array_merge($metadata, [
                            'sme_qualification_percentage' => $qualificationPercentage,
                        ]),
                        'employment_status' => 'self_employed',
                    ]);
                }
            } elseif ($product->category === 'marketeer') {
                $customerData = array_merge($customerData, [
                    'company_id' => null, // Market customers don't require company
                    'next_of_kin_name' => $validated['next_of_kin_name'],
                    'next_of_kin_phone' => $validated['next_of_kin_phone'],
                    'next_of_kin_relationship' => $validated['next_of_kin_relationship'],
                    'next_of_kin_address_line1' => $validated['next_of_kin_address_line1'],
                    'next_of_kin_address_line2' => $validated['next_of_kin_address_line2'] ?? null,
                    'next_of_kin_city' => $validated['next_of_kin_city'],
                    'next_of_kin_country' => $validated['next_of_kin_country'],
                    'net_salary' => $validated['monthly_income'], // Monthly income is net for marketeers
                    'employment_status' => 'self_employed',
                ]);
            }

            $customer = Customer::create($customerData);

	            if ($registrationRequest && $product->category === 'government') {
	                $employment = $registrationRequest->employment_details ?? [];

	                $bankInstitutionId = isset($employment['bank_financial_institution_id'])
	                    ? (int) $employment['bank_financial_institution_id']
	                    : null;
	                $bankBranchId = isset($employment['bank_financial_institution_branch_id'])
	                    ? (int) $employment['bank_financial_institution_branch_id']
	                    : null;

	                $institution = $bankInstitutionId ? FinancialInstitution::query()->find($bankInstitutionId) : null;
	                $branch = $bankBranchId ? FinancialInstitutionBranch::query()->find($bankBranchId) : null;

	                $bankName = trim((string) ($employment['bank_name'] ?? ''));
	                $bankBranch = trim((string) ($employment['bank_branch'] ?? ''));
	                if ($institution) {
	                    $bankName = trim((string) $institution->name);
	                }
	                if ($branch) {
	                    $bankBranch = trim((string) $branch->name);
	                }

	                $accountName = trim((string) ($employment['bank_account_name'] ?? ''));
	                $accountNumber = trim((string) ($employment['bank_account_number'] ?? ''));

	                if ($bankName !== '' && $bankBranch !== '' && $accountName !== '' && $accountNumber !== '') {
	                    CustomerPaymentDetail::updateOrCreate(
	                        ['customer_id' => $customer->id],
	                        [
	                            'method_type' => 'bank',
	                            'bank_financial_institution_id' => $institution?->id,
	                            'bank_financial_institution_branch_id' => $branch?->id,
	                            'bank_name' => Str::upper($bankName),
	                            'bank_branch' => Str::upper($bankBranch),
	                            'account_name' => Str::upper($accountName),
	                            'account_number' => Str::upper($accountNumber),
	                            'wallet_provider_id' => null,
	                            'wallet_provider' => null,
	                            'wallet_number' => null,
	                        ]
	                    );
	                }
	            }

            // If this customer comes from a registration request, link it and copy KYC
            if ($registrationRequest) {
                $payload = $registrationRequest->payload ?? [];
                $kycPaths = $payload['kyc_paths'] ?? [];
                $documentType = $payload['document_type'] ?? 'nrc';

                if (!empty($kycPaths) && is_array($kycPaths)) {
                    KycDocument::create([
                        'customer_id' => $customer->id,
                        'document_type' => $documentType,
                        'front_image_path' => $kycPaths['front_image_path'] ?? null,
                        'back_image_path' => $kycPaths['back_image_path'] ?? null,
                        'profile_picture_path' => $kycPaths['profile_picture_path'] ?? null,
                        'stand_picture_path' => $kycPaths['stand_picture_path'] ?? null,
                        'bank_statement_path' => $kycPaths['bank_statement_path'] ?? null,
                        'payslip_path' => $kycPaths['payslip_path'] ?? null,
                        'status' => 'pending',
                    ]);
                }

                $registrationRequest->update([
                    'created_customer_id' => $customer->id,
                    'created_by_admin_id' => auth('admin')->id(),
                    'created_customer_at' => now(),
                ]);
            }

            // Create marketeer customer details if applicable
            if ($product->category === 'marketeer') {
                MarketeerCustomerDetail::create([
                    'customer_id' => $customer->id,
                    'market_id' => $validated['market_id'],
                    'stand_number' => $validated['stand_number'] ?? null,
                    'stand_description' => $validated['stand_description'] ?? null,
                    'monthly_income' => $validated['monthly_income'],
                ]);
            }

            if ($product->category === 'sme' && ($customer->customer_type ?? null) === 'company') {
                $netRevenue = (float) ($customer->net_salary ?? 0);
                $qualificationPercentage = max(0, min(100, (float) data_get($customer->metadata ?? [], 'sme_qualification_percentage', 60)));
                $maximumLoanTake = $netRevenue > 0 ? ($netRevenue * ($qualificationPercentage / 100)) : 0.00;
                $customer->update(['maximum_loan_take' => $maximumLoanTake]);
            } else {
                // Default affordability logic for non-SME products
                $netSalary = $customer->net_salary ?? 0;
                if ($netSalary > 0) {
                    $customer->update(['maximum_loan_take' => $netSalary * 0.6]);
                }
            }

            // Send onboarding message immediately only when approval is not required.
            if (!$requiresApproval) {
                $customer->notify(new \App\Notifications\CustomerRegistrationNotification(
                    $pin,
                    $customer->phone ?? $customer->email
                ));
                // Note: Communication logging is handled in the CustomerRegistrationNotification class
            }

            // Redirect to KYC upload page
            $message = 'Customer created successfully. Please upload KYC documents.';
            if ($requiresApproval) {
                $message .= ' The customer will be activated after approval. Onboarding communication will be sent after approval.';
            } else {
                $message .= ' The account will be activated after KYC upload.';
            }
            
            return redirect()
                ->route('admin.customers.kyc.create', $customer)
                ->with('status', $message);
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Failed to create customer: '.$e->getMessage());
        }
    }

    public function show(Customer $customer): View
    {
        abort_unless(auth('admin')->user()?->can('customers.view'), 403);
        $customer->load(['company', 'loanProduct', 'customerGroup', 'groupMemberTitle', 'verifier', 'ministry', 'workProvince', 'workDistrict', 'latestKycDocument', 'referredBy', 'approver', 'paymentDetail', 'loans.paymentSchedules']);

        $today = Carbon::today();
        $startOfToday = $today->copy()->startOfDay();
        $endOfToday = $today->copy()->endOfDay();
        $startOfWeek = $today->copy()->subDays(6)->startOfDay();
        $startOfThreeMonths = $today->copy()->startOfMonth()->subMonths(2); // current month + previous two

        $monthWindows = collect(range(0, 2))->map(function ($i) use ($today) {
            $month = $today->copy()->startOfMonth()->subMonths($i);
            return [
                'key' => $month->format('Y-m'),
                'label' => $month->format('M Y'),
            ];
        });

        $disbursementBase = Loan::query()
            ->where('customer_id', $customer->id)
            ->where('disbursement_status', 'completed')
            ->whereNotNull('disbursed_at');

        $disbursementMonthlyRows = (clone $disbursementBase)
            ->where('disbursed_at', '>=', $startOfThreeMonths)
            ->selectRaw('DATE_FORMAT(disbursed_at, "%Y-%m") as month, SUM(principal_amount) as total')
            ->groupBy('month')
            ->orderBy('month', 'desc')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->month => (float) $row->total]);

        $disbursementMonthly = [];
        foreach ($monthWindows as $window) {
            $disbursementMonthly[$window['key']] = $disbursementMonthlyRows[$window['key']] ?? 0.0;
        }

        $disbursementTotals = [
            'total' => (float) (clone $disbursementBase)->sum('principal_amount'),
            'daily' => (float) (clone $disbursementBase)->whereBetween('disbursed_at', [$startOfToday, $endOfToday])->sum('principal_amount'),
            'weekly' => (float) (clone $disbursementBase)->whereBetween('disbursed_at', [$startOfWeek, $endOfToday])->sum('principal_amount'),
            'monthly' => $disbursementMonthly,
        ];

        $repaymentBase = LoanRepayment::query()
            ->join('repayments', 'repayments.id', '=', 'loan_repayments.repayment_id')
            ->join('loans', 'loans.id', '=', 'loan_repayments.loan_id')
            ->where('repayments.customer_id', $customer->id);

        $repaymentMonthlyRows = (clone $repaymentBase)
            ->where('repayments.processed_at', '>=', $startOfThreeMonths)
            ->selectRaw('DATE_FORMAT(repayments.processed_at, "%Y-%m") as month, SUM(loan_repayments.amount) as total')
            ->groupBy('month')
            ->orderBy('month', 'desc')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->month => (float) $row->total]);

        $repaymentMonthly = [];
        foreach ($monthWindows as $window) {
            $repaymentMonthly[$window['key']] = $repaymentMonthlyRows[$window['key']] ?? 0.0;
        }

        $repaymentTotals = [
            'total' => (float) (clone $repaymentBase)->sum('loan_repayments.amount'),
            'daily' => (float) (clone $repaymentBase)->whereBetween('repayments.processed_at', [$startOfToday, $endOfToday])->sum('loan_repayments.amount'),
            'weekly' => (float) (clone $repaymentBase)->whereBetween('repayments.processed_at', [$startOfWeek, $endOfToday])->sum('loan_repayments.amount'),
            'monthly' => $repaymentMonthly,
        ];

        $activeLoans = $customer->loans()
            ->whereIn('status', ['approved', 'active'])
            ->with('paymentSchedules')
            ->get();

        $portfolioTotal = (float) $activeLoans->sum('outstanding_balance');
        $arrearsTotal = (float) $activeLoans->sum(fn ($loan) => $loan->getOverdueAmount());
        $par = $portfolioTotal > 0 ? ($arrearsTotal / $portfolioTotal) * 100 : 0.0;

        $customerCashflowStats = [
            'disbursements' => $disbursementTotals,
            'repayments' => $repaymentTotals,
            'portfolio' => [
                'total' => $portfolioTotal,
                'arrears' => $arrearsTotal,
                'par' => $par,
            ],
            'months' => $monthWindows,
        ];

        // Check for duplicates if user has fraud protection permission
        $duplicateInfo = null;
        if (auth('admin')->user()?->can('fraud-protection.view')) {
            $duplicateInfo = \App\Support\DuplicateDetectionService::detectDuplicates($customer);
        }

        return view('admin.customers.show', compact('customer', 'duplicateInfo', 'customerCashflowStats'));
    }

    /**
     * Recalculate credit score for a customer
     */
    public function recalculateCreditScore(Customer $customer): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('customers.update'), 403);

        try {
            \App\Support\CreditScoreService::updateCreditScore($customer);
            
            return redirect()
                ->route('admin.customers.show', $customer)
                ->with('status', 'Credit score recalculated successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.customers.show', $customer)
                ->with('error', 'Failed to recalculate credit score: ' . $e->getMessage());
        }
    }

    /**
     * Show login audit for a customer
     */
    public function loginAudit(Customer $customer, Request $request): View
    {
        abort_unless(auth('admin')->user()?->can('customers.view'), 403);

        $query = \App\Models\CustomerLoginAudit::where('customer_id', $customer->id)
            ->orWhere('phone', $customer->phone)
            ->orderBy('attempted_at', 'desc');

        // Filter by status if provided
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        // Filter by date range if provided
        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('attempted_at', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('attempted_at', '<=', $request->date_to);
        }

        $loginAudits = $query->paginate(50)->withQueryString();

        return view('admin.customers.login-audit', compact('customer', 'loginAudits'));
    }

    public function edit(Customer $customer): View
    {
        abort_unless(auth('admin')->user()?->can('customers.update'), 403);
        // Load marketeer customer detail if applicable
        if ($customer->loanProduct && $customer->loanProduct->category === 'marketeer') {
            $customer->load('marketeerCustomerDetail');
        }
        
        $product = $customer->loanProduct;
        $companies = Company::orderBy('name')->get();
        $relationshipManagers = Admin::where('is_relationship_manager', true)
            ->orderBy('first_name')
            ->get();
        
        $ministries = Ministry::where('is_active', true)->orderBy('name')->get();
        $provinces = Province::where('is_active', true)->orderBy('name')->get();
        $districts = District::where('is_active', true)->orderBy('name')->get();
        
        // Get customer groups for products that require group assignment.
        $customerGroups = collect();
        if (in_array($product->category, ['character', 'collateral', 'group_loans'], true)) {
            $customerGroups = CustomerGroup::where('loan_product_id', $product->id)
                ->where('is_active', true)
                ->orderBy('risk_level')
                ->orderBy('name')
                ->get();
        }

        // Get markets for marketeer loans
        $markets = collect();
        if ($product->category === 'marketeer') {
            $markets = Market::where('is_active', true)
                ->with(['province', 'district', 'portfolioManager'])
                ->orderBy('name')
                ->get();
        }

        $companyCustomers = collect();
        if ($product && $product->category === 'sme') {
            $companyCustomers = Customer::where('loan_product_id', $product->id)
                ->where('customer_type', 'company')
                ->orderBy('registered_name')
                ->orderBy('first_name')
                ->get();
        }

        $referredByCustomers = Customer::query()
            ->select(['id', 'first_name', 'last_name', 'registered_name', 'phone'])
            ->whereKeyNot($customer->id)
            ->orderBy('registered_name')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        return view('admin.customers.edit', [
            'customer' => $customer,
            'product' => $product,
            'companies' => $companies,
            'relationshipManagers' => $relationshipManagers,
            'ministries' => $ministries,
            'provinces' => $provinces,
            'districts' => $districts,
            'customerGroups' => $customerGroups,
            'markets' => $markets,
            'companyCustomers' => $companyCustomers,
            'referredByCustomers' => $referredByCustomers,
        ]);
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('customers.update'), 403);
        $product = $customer->loanProduct;
        $rules = [
            'loan_product_id' => 'required|exists:loan_products,id',
            'customer_type' => 'nullable|in:individual,company,representative',
            'parent_customer_id' => 'nullable|exists:customers,id',
            'referred_by' => 'nullable|exists:customers,id|not_in:'.$customer->id,
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'registered_name' => 'nullable|string|max:255',
            'email' => 'required|email|unique:customers,email,'.$customer->id,
            'phone' => ZambianPhoneRules::nullableUnique(ignoreId: $customer->id),
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:' . now()->subYears(16)->format('Y-m-d')],
            'gender' => ['nullable', 'in:male,female,other'],
            'address_line1' => 'required|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'required|string|max:100',
            'province_id' => 'nullable|exists:provinces,id',
            'state' => 'nullable|string|max:100', // Keep for backward compatibility
            'postal_code' => 'nullable|string|max:20',
            'country' => 'required|string|max:100',
        ];

        // Get the product from request (may be different from current product)
        $requestProduct = $request->has('loan_product_id') ? LoanProduct::find($request->input('loan_product_id')) : $product;
        $productToUse = $requestProduct ?? $product;

        // SME: company-as-customer with representatives
        if ($productToUse && $productToUse->category === 'sme') {
            $rules = [
                'loan_product_id' => 'required|exists:loan_products,id',
                'customer_type' => 'required|in:company,representative',
                'company_id' => 'required|exists:companies,id',
                'parent_customer_id' => 'required_if:customer_type,representative|exists:customers,id',
                'registered_name' => 'required_if:customer_type,company|nullable|string|max:255',
                'monthly_net_revenue' => 'required_if:customer_type,company|nullable|numeric|min:0',
                'qualification_percentage' => 'required_if:customer_type,company|nullable|numeric|between:0,100',
                'first_name' => 'required_if:customer_type,representative|nullable|string|max:255',
                'last_name' => 'required_if:customer_type,representative|nullable|string|max:255',
                'national_id_type' => 'required_if:customer_type,representative|nullable|'.Rule::in(array_keys(NationalIdRules::typeLabels())),
                'national_id' => array_merge(
                    ['required_if:customer_type,representative', 'nullable', 'string', 'max:50', new ValidNationalIdNumber()],
                    [Rule::unique('customers', 'national_id')->ignore($customer->id)]
                ),
                'email' => 'required|email|unique:customers,email,'.$customer->id,
                'phone' => ZambianPhoneRules::nullableUnique(ignoreId: $customer->id),
                'tpin' => NationalIdRules::tpin(ignoreId: $customer->id),
                'address_line1' => 'nullable|string|max:255',
                'address_line2' => 'nullable|string|max:255',
                'city' => 'nullable|string|max:100',
                'province_id' => 'nullable|exists:provinces,id',
                'state' => 'nullable|string|max:100',
                'postal_code' => 'nullable|string|max:20',
                'country' => 'nullable|string|max:100',
            ];
        }

        // Add company_id validation based on product type
        if ($productToUse && in_array($productToUse->category, ['government', 'character', 'collateral', 'marketeer', 'group_loans'], true)) {
            // Government employees, character-based, collateral-based, and marketeer loans don't require company_id
            $rules['company_id'] = 'nullable|exists:companies,id';
        } else {
            // Other product types require company_id
            $rules['company_id'] = 'required|exists:companies,id';
        }

        if ($productToUse && $productToUse->category === 'government') {
            $rules = array_merge($rules, [
                'employee_number' => 'required|string|max:50',
                'ministry_id' => 'required|exists:ministries,id',
                'date_of_employment' => 'required|date',
                'contract_end_date' => 'nullable|date|after:date_of_employment',
                'gross_salary' => 'required|numeric|min:0',
                'net_salary' => 'required|numeric|min:0',
                'deductions' => 'nullable|numeric|min:0',
                'verified_by' => 'required|exists:admins,id',
                'work_address_line1' => 'nullable|string|max:255',
                'work_address_line2' => 'nullable|string|max:255',
                'work_city' => 'nullable|string|max:100',
                'work_province_id' => 'nullable|exists:provinces,id',
                'work_district_id' => 'nullable|exists:districts,id',
                'work_postal_code' => 'nullable|string|max:20',
                'work_country' => 'nullable|string|max:100',
                'next_of_kin_name' => 'required|string|max:255',
                'next_of_kin_phone' => ZambianPhoneRules::required(),
                'next_of_kin_relationship' => 'required|string|max:50',
                'next_of_kin_address_line1' => 'nullable|string|max:255',
                'next_of_kin_address_line2' => 'nullable|string|max:255',
                'next_of_kin_city' => 'nullable|string|max:100',
                'next_of_kin_country' => 'nullable|string|max:100',
            ]);
        } elseif ($product && $product->category === 'mou') {
            $rules = array_merge($rules, [
                'employee_number' => 'required|string|max:50',
                'position' => 'required|string|max:255',
                'unit' => 'nullable|string|max:255',
                'department' => 'required|string|max:255',
                'date_of_employment' => 'required|date',
                'contract_end_date' => 'nullable|date|after:date_of_employment',
                'gross_salary' => 'required|numeric|min:0',
                'net_salary' => 'required|numeric|min:0',
                'verified_by' => 'required|exists:admins,id',
            ]);
        } elseif ($productToUse && ($productToUse->category === 'character' || $productToUse->category === 'collateral')) {
            $rules = array_merge($rules, [
                'customer_group_id' => 'required|exists:customer_groups,id',
                'next_of_kin_name' => 'required|string|max:255',
                'next_of_kin_phone' => ZambianPhoneRules::required(),
                'next_of_kin_relationship' => 'required|string|max:50',
                'next_of_kin_address_line1' => 'nullable|string|max:255',
                'next_of_kin_address_line2' => 'nullable|string|max:255',
                'next_of_kin_city' => 'nullable|string|max:100',
                'next_of_kin_country' => 'nullable|string|max:100',
                'is_employed' => 'required|boolean',
                'payday' => 'nullable|integer|min:1|max:31',
                'gross_salary' => 'nullable|numeric|min:0',
                'net_salary' => 'required|numeric|min:0',
            ]);
            if ($productToUse->category === 'character') {
                $rules['employee_number'] = [
                    'nullable',
                    'string',
                    'max:50',
                    Rule::requiredIf(fn () => filter_var(request()->input('is_employed'), FILTER_VALIDATE_BOOLEAN)),
                ];
            }
        } elseif ($productToUse && $productToUse->category === 'group_loans') {
            $rules = array_merge($rules, [
                'customer_group_id' => 'nullable|exists:customer_groups,id',
                'occupation_type' => 'required|in:employed,business_owner',
                'employer_or_business_name' => 'required|string|max:255',
                'average_income' => 'required|numeric|min:0',
                'work_address_line1' => 'required|string|max:255',
                'work_address_line2' => 'nullable|string|max:255',
                'work_city' => 'required|string|max:100',
                'work_province_id' => 'nullable|exists:provinces,id',
                'work_district_id' => 'nullable|exists:districts,id',
                'work_postal_code' => 'nullable|string|max:20',
                'work_country' => 'required|string|max:100',
            ]);
        } elseif ($productToUse && $productToUse->category === 'marketeer') {
            $rules = array_merge($rules, [
                'market_id' => 'required|exists:markets,id',
                'stand_number' => 'nullable|string|max:255',
                'stand_description' => 'nullable|string|max:1000',
                'monthly_income' => 'required|numeric|min:0',
                'next_of_kin_name' => 'required|string|max:255',
                'next_of_kin_phone' => ZambianPhoneRules::required(),
                'next_of_kin_relationship' => 'required|string|max:50',
                'next_of_kin_address_line1' => 'required|string|max:255',
                'next_of_kin_address_line2' => 'nullable|string|max:255',
                'next_of_kin_city' => 'required|string|max:100',
                'next_of_kin_country' => 'required|string|max:100',
            ]);
        }

        if (! $productToUse || $productToUse->category !== 'sme') {
            $rules = NationalIdRules::merge($rules, $customer->id);
        }

        $messages = array_merge(ZambianPhoneRules::messages(), NationalIdRules::messages(), [
            'date_of_birth.before_or_equal' => 'The customer must be at least 16 years old.',
            'referred_by.not_in' => 'A customer cannot refer themselves.',
        ]);

        $validated = $request->validate($rules, $messages, array_merge(
            ZambianPhoneRules::attributes(),
            NationalIdRules::attributes()
        ));

        try {
            // Get the new product if loan_product_id is being updated
            $newProduct = LoanProduct::findOrFail($validated['loan_product_id']);
            $productChanged = $product && $product->id !== $newProduct->id;
            
            $updateData = [
                'loan_product_id' => $validated['loan_product_id'],
                'company_id' => in_array($newProduct->category, ['government', 'character', 'collateral', 'marketeer', 'group_loans'], true) ? null : ($validated['company_id'] ?? null),
                'customer_type' => $validated['customer_type'] ?? $customer->customer_type,
                'parent_customer_id' => $validated['parent_customer_id'] ?? null,
                'referred_by' => $validated['referred_by'] ?? $customer->referred_by,
                // Keep aligned with create flow; SME company branch sets these from registered_name.
                'first_name' => $validated['first_name'] ?? null,
                'last_name' => $validated['last_name'] ?? null,
                'registered_name' => $validated['registered_name'] ?? null,
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'date_of_birth' => $validated['date_of_birth'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'national_id' => $validated['national_id'] ?? null,
                'national_id_type' => $validated['national_id_type'] ?? null,
                'tpin' => $validated['tpin'] ?? null,
                'address_line1' => $validated['address_line1'] ?? null,
                'address_line2' => $validated['address_line2'] ?? null,
                'city' => $validated['city'] ?? null,
                'province_id' => $validated['province_id'] ?? null,
                'state' => $validated['state'] ?? null,
                'postal_code' => $validated['postal_code'] ?? null,
                'country' => $validated['country'] ?? null,
            ];

            if ($newProduct && $newProduct->category === 'sme') {
                if (($updateData['customer_type'] ?? 'company') === 'company') {
                    $updateData['first_name'] = $updateData['registered_name'];
                    $updateData['last_name'] = $updateData['registered_name']; // keep non-null for DB constraint
                    $updateData['parent_customer_id'] = null;
                } elseif (!empty($updateData['parent_customer_id'])) {
                    $parent = Customer::find($updateData['parent_customer_id']);
                    if ($parent) {
                        $updateData['company_id'] = $parent->company_id;
                        $updateData['loan_product_id'] = $parent->loan_product_id;
                    }
                }
            }

            if ($newProduct && $newProduct->category === 'government') {
                // Get or create DEFAULT customer group for government product
                $defaultGroup = CustomerGroup::where('loan_product_id', $newProduct->id)
                    ->where('code', 'GOV-DEFAULT')
                    ->first();

                if (!$defaultGroup) {
                    // Create DEFAULT group if it doesn't exist
                    $defaultGroup = CustomerGroup::create([
                        'loan_product_id' => $newProduct->id,
                        'name' => 'DEFAULT',
                        'code' => 'GOV-DEFAULT',
                        'description' => 'Default customer group for all government employees',
                        'risk_level' => 'medium',
                        'max_loan_amount' => $newProduct->max_amount ?? null,
                        'max_loan_tenure_months' => $newProduct->tenure_months ?? null,
                        'is_active' => true,
                    ]);
                }

                $updateData = array_merge($updateData, [
                    'customer_group_id' => $defaultGroup->id,
                    'employee_number' => $validated['employee_number'],
                    'ministry_id' => $validated['ministry_id'],
                    'date_of_employment' => $validated['date_of_employment'],
                    'contract_end_date' => $validated['contract_end_date'] ?? null,
                    'gross_salary' => $validated['gross_salary'],
                    'net_salary' => $validated['net_salary'],
                    'deductions' => $validated['deductions'] ?? 0,
                    'verified_by' => $validated['verified_by'],
                    'work_address_line1' => $validated['work_address_line1'] ?? null,
                    'work_address_line2' => $validated['work_address_line2'] ?? null,
                    'work_city' => $validated['work_city'] ?? null,
                    'work_province_id' => $validated['work_province_id'] ?? null,
                    'work_district_id' => $validated['work_district_id'] ?? null,
                    'work_postal_code' => $validated['work_postal_code'] ?? null,
                    'work_country' => $validated['work_country'] ?? 'Zambia',
                    'next_of_kin_name' => $validated['next_of_kin_name'],
                    'next_of_kin_phone' => $validated['next_of_kin_phone'],
                    'next_of_kin_relationship' => $validated['next_of_kin_relationship'],
                    'next_of_kin_address_line1' => $validated['next_of_kin_address_line1'] ?? null,
                    'next_of_kin_address_line2' => $validated['next_of_kin_address_line2'] ?? null,
                    'next_of_kin_city' => $validated['next_of_kin_city'] ?? null,
                    'next_of_kin_country' => $validated['next_of_kin_country'] ?? 'Zambia',
                ]);
            } elseif ($newProduct && $newProduct->category === 'mou') {
                $updateData = array_merge($updateData, [
                    'company_id' => $validated['company_id'],
                    'employee_number' => $validated['employee_number'],
                    'position' => $validated['position'],
                    'unit' => $validated['unit'] ?? null,
                    'department' => $validated['department'],
                    'date_of_employment' => $validated['date_of_employment'],
                    'contract_end_date' => $validated['contract_end_date'] ?? null,
                    'gross_salary' => $validated['gross_salary'],
                    'net_salary' => $validated['net_salary'],
                    'verified_by' => $validated['verified_by'],
                ]);
            } elseif ($newProduct && ($newProduct->category === 'character' || $newProduct->category === 'collateral')) {
                $characterExtras = [];
                if ($newProduct->category === 'character') {
                    $characterExtras['employee_number'] = $validated['is_employed']
                        ? ($validated['employee_number'] ?? null)
                        : null;
                }
                $updateData = array_merge($updateData, [
                    'customer_group_id' => $validated['customer_group_id'],
                    'next_of_kin_name' => $validated['next_of_kin_name'],
                    'next_of_kin_phone' => $validated['next_of_kin_phone'],
                    'next_of_kin_relationship' => $validated['next_of_kin_relationship'],
                    'next_of_kin_address_line1' => $validated['next_of_kin_address_line1'] ?? null,
                    'next_of_kin_address_line2' => $validated['next_of_kin_address_line2'] ?? null,
                    'next_of_kin_city' => $validated['next_of_kin_city'] ?? null,
                    'next_of_kin_country' => $validated['next_of_kin_country'] ?? 'Zambia',
                    'is_employed' => $validated['is_employed'],
                    'payday' => $validated['payday'] ?? null,
                    'gross_salary' => $validated['gross_salary'] ?? null,
                    'net_salary' => $validated['net_salary'],
                    'employment_status' => $validated['is_employed'] ? 'employed' : 'unemployed',
                ], $characterExtras);
            } elseif ($newProduct && $newProduct->category === 'group_loans') {
                $selectedGroup = null;
                if (! empty($validated['customer_group_id'])) {
                    $selectedGroup = CustomerGroup::where('id', $validated['customer_group_id'])
                        ->where('loan_product_id', $newProduct->id)
                        ->where('is_active', true)
                        ->first();

                    if (! $selectedGroup) {
                        throw ValidationException::withMessages([
                            'customer_group_id' => 'The selected group does not belong to this Group Loans product.',
                        ]);
                    }
                }

                if (! $selectedGroup) {
                    $selectedGroup = CustomerGroup::firstOrCreate(
                        [
                            'loan_product_id' => $newProduct->id,
                            'code' => 'GL-DEFAULT',
                        ],
                        [
                            'name' => 'Default Group',
                            'description' => 'Default active group for Group Loans onboarding fallback.',
                            'risk_level' => 'medium',
                            'max_loan_amount' => $newProduct->max_amount ?? null,
                            'max_loan_tenure_months' => $newProduct->tenure_months ?? null,
                            'is_active' => true,
                        ]
                    );
                }

                $metadata = is_array($customer->metadata ?? null) ? $customer->metadata : [];
                $updateData = array_merge($updateData, [
                    'company_id' => null,
                    'customer_group_id' => $selectedGroup->id,
                    'net_salary' => $validated['average_income'],
                    'employment_status' => $validated['occupation_type'] === 'business_owner' ? 'self_employed' : 'employed',
                    'work_address_line1' => $validated['work_address_line1'],
                    'work_address_line2' => $validated['work_address_line2'] ?? null,
                    'work_city' => $validated['work_city'],
                    'work_province_id' => $validated['work_province_id'] ?? null,
                    'work_district_id' => $validated['work_district_id'] ?? null,
                    'work_postal_code' => $validated['work_postal_code'] ?? null,
                    'work_country' => $validated['work_country'],
                    'metadata' => array_merge($metadata, [
                        'group_loans_occupation_type' => $validated['occupation_type'],
                        'group_loans_employer_or_business_name' => $validated['employer_or_business_name'],
                    ]),
                ]);
            } elseif ($newProduct && $newProduct->category === 'sme') {
                if (($updateData['customer_type'] ?? 'company') === 'company') {
                    $qualificationPercentage = max(0, min(100, (float) ($validated['qualification_percentage'] ?? data_get($customer->metadata ?? [], 'sme_qualification_percentage', 60))));
                    $metadata = is_array($customer->metadata ?? null) ? $customer->metadata : [];
                    $metadata['sme_qualification_percentage'] = $qualificationPercentage;

                    $updateData = array_merge($updateData, [
                        'net_salary' => $validated['monthly_net_revenue'],
                        'metadata' => $metadata,
                        'employment_status' => 'self_employed',
                    ]);
                }
            } elseif ($newProduct && $newProduct->category === 'marketeer') {
                $updateData = array_merge($updateData, [
                    'company_id' => null, // Market customers don't require company
                    'next_of_kin_name' => $validated['next_of_kin_name'],
                    'next_of_kin_phone' => $validated['next_of_kin_phone'],
                    'next_of_kin_relationship' => $validated['next_of_kin_relationship'],
                    'next_of_kin_address_line1' => $validated['next_of_kin_address_line1'],
                    'next_of_kin_address_line2' => $validated['next_of_kin_address_line2'] ?? null,
                    'next_of_kin_city' => $validated['next_of_kin_city'],
                    'next_of_kin_country' => $validated['next_of_kin_country'],
                    'net_salary' => $validated['monthly_income'], // Monthly income is net for marketeers
                    'employment_status' => 'self_employed',
                ]);
            }

            if ($newProduct && $newProduct->category === 'sme' && (($updateData['customer_type'] ?? $customer->customer_type) === 'company')) {
                $netRevenue = (float) ($updateData['net_salary'] ?? $customer->net_salary ?? 0);
                $qualificationPercentage = max(0, min(100, (float) data_get($updateData, 'metadata.sme_qualification_percentage', data_get($customer->metadata ?? [], 'sme_qualification_percentage', 60))));
                $updateData['maximum_loan_take'] = $netRevenue > 0 ? ($netRevenue * ($qualificationPercentage / 100)) : 0.00;
            } else {
                // Default affordability logic for non-SME products
                $netSalary = $updateData['net_salary'] ?? $customer->net_salary ?? 0;
                $updateData['maximum_loan_take'] = $netSalary > 0 ? ($netSalary * 0.6) : 0.00;
            }

            $customer->update($updateData);

            // Update or create marketeer customer details if applicable
            if ($newProduct && $newProduct->category === 'marketeer') {
                MarketeerCustomerDetail::updateOrCreate(
                    ['customer_id' => $customer->id],
                    [
                        'market_id' => $validated['market_id'],
                        'stand_number' => $validated['stand_number'] ?? null,
                        'stand_description' => $validated['stand_description'] ?? null,
                        'monthly_income' => $validated['monthly_income'],
                    ]
                );
            }

            return redirect()
                ->route('admin.customers.show', $customer)
                ->with('status', 'Customer updated successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Failed to update customer: '.$e->getMessage());
        }
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('customers.delete'), 403);
        try {
            // Check if customer has any loans
            $hasLoans = $customer->loans()->exists();
            
            // Check if customer has any repayments
            $hasRepayments = \App\Models\Repayment::where('customer_id', $customer->id)->exists();

            if ($hasLoans || $hasRepayments) {
                return redirect()
                    ->route('admin.customers.show', $customer)
                    ->with('error', 'Cannot delete customer. This customer has loans or repayments associated with their account. Deleting would corrupt financial data.');
            }

            $customer->delete();

            return redirect()
                ->route('admin.customers.index')
                ->with('status', 'Customer deleted successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.customers.show', $customer)
                ->with('error', 'Failed to delete customer: '.$e->getMessage());
        }
    }

    /**
     * Show the form for changing/linking customer to a group
     */
    public function changeGroup(Customer $customer): View|RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('customers.change-group'), 403);
        $customer->load('loanProduct');

        // Only allow group assignment for products that use groups
        if (!$customer->loanProduct || !in_array($customer->loanProduct->category, ['character', 'collateral', 'government', 'group_loans'], true)) {
            return redirect()
                ->route('admin.customers.show', $customer)
                ->with('error', 'This product type does not use customer groups.');
        }

        // Get groups for this product
        $customerGroups = CustomerGroup::where('loan_product_id', $customer->loan_product_id)
            ->where('is_active', true)
            ->orderBy('risk_level')
            ->orderBy('name')
            ->get();

        return view('admin.customers.change-group', [
            'customer' => $customer,
            'customerGroups' => $customerGroups,
        ]);
    }

    /**
     * Update the customer's group assignment
     */
    public function updateGroup(Request $request, Customer $customer): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('customers.change-group'), 403);
        $customer->load('loanProduct');

        // Only allow group assignment for products that use groups
        if (!$customer->loanProduct || !in_array($customer->loanProduct->category, ['character', 'collateral', 'government', 'group_loans'], true)) {
            return redirect()
                ->route('admin.customers.show', $customer)
                ->with('error', 'This product type does not use customer groups.');
        }

        $rules = [
            'customer_group_id' => ['required', 'exists:customer_groups,id'],
        ];
        $messages = [
            'customer_group_id.required' => 'Please select a customer group.',
            'customer_group_id.exists' => 'The selected customer group does not exist.',
        ];

        $validated = $request->validate($rules, $messages);

        // Verify the group belongs to the customer's product
        $group = CustomerGroup::where('id', $validated['customer_group_id'])
            ->where('loan_product_id', $customer->loan_product_id)
            ->first();

        if (!$group) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'The selected group does not belong to this customer\'s product.');
        }

        try {
            $updateData = [
                'customer_group_id' => $validated['customer_group_id'],
            ];

            $customer->update($updateData);

            return redirect()
                ->route('admin.customers.show', $customer)
                ->with('status', 'Customer group updated successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Failed to update customer group: '.$e->getMessage());
        }
    }

    /**
     * Reset customer PIN and send via email
     */
    public function resetPin(Customer $customer): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('customers.reset-pin'), 403);
        try {
            // Generate new 4-digit PIN
            $pin = str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);

            // Update customer password (PIN) and force change
            $customer->update([
                'password' => Hash::make($pin),
                'must_change_pin' => true,
            ]);

            // Log the PIN for development purposes
            Log::info('Customer PIN Reset', [
                'customer_id' => $customer->id,
                'customer_email' => $customer->email,
                'customer_name' => $customer->full_name,
                'new_pin' => $pin,
                'reset_by' => auth('admin')->user()->email ?? 'System',
                'reset_at' => now()->toDateTimeString(),
            ]);

            // Send email notification with new PIN
            $customer->notify(new \App\Notifications\CustomerRegistrationNotification(
                $pin,
                $customer->phone ?? $customer->email
            ));
            
            // Note: Communication logging is handled in the CustomerRegistrationNotification class

            return redirect()
                ->route('admin.customers.show', $customer)
                ->with('status', 'Customer PIN has been reset successfully. The new PIN has been sent to the customer via email.');
        } catch (\Exception $e) {
            Log::error('Customer PIN Reset Failed', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()
                ->back()
                ->with('error', 'Failed to reset customer PIN: '.$e->getMessage());
        }
    }

    public function editPaymentDetails(Customer $customer): View
    {
        abort_unless(auth('admin')->user()?->can('customers.update'), 403);
        $customer->load('paymentDetail');

        $paymentDetail = $customer->paymentDetail;

        $financialInstitutions = FinancialInstitution::query()
            ->active()
            ->with(['branches' => fn ($query) => $query->active()->orderBy('name')])
            ->orderBy('name')
            ->get();

        $walletProviders = WalletProvider::query()
            ->active()
            ->orderBy('name')
            ->get();

        $resolvedBankInstitutionId = $paymentDetail?->bank_financial_institution_id;
        $resolvedBankBranchId = $paymentDetail?->bank_financial_institution_branch_id;
        $resolvedWalletProviderId = $paymentDetail?->wallet_provider_id;

        if ($paymentDetail) {
            if (! $resolvedBankInstitutionId && filled($paymentDetail->bank_name)) {
                $institution = FinancialInstitution::query()
                    ->active()
                    ->where('name', (string) $paymentDetail->bank_name)
                    ->first();
                $resolvedBankInstitutionId = $institution?->id;
            }

            if (! $resolvedBankBranchId && $resolvedBankInstitutionId && filled($paymentDetail->bank_branch)) {
                $branch = FinancialInstitutionBranch::query()
                    ->active()
                    ->where('financial_institution_id', (int) $resolvedBankInstitutionId)
                    ->where('name', (string) $paymentDetail->bank_branch)
                    ->first();
                $resolvedBankBranchId = $branch?->id;
            }

            if (! $resolvedWalletProviderId && filled($paymentDetail->wallet_provider)) {
                $provider = WalletProvider::query()
                    ->active()
                    ->where('name', (string) $paymentDetail->wallet_provider)
                    ->first();
                $resolvedWalletProviderId = $provider?->id;
            }
        }

        return view('admin.customers.edit-payment-details', [
            'customer' => $customer,
            'financialInstitutions' => $financialInstitutions,
            'walletProviders' => $walletProviders,
            'resolvedBankInstitutionId' => $resolvedBankInstitutionId,
            'resolvedBankBranchId' => $resolvedBankBranchId,
            'resolvedWalletProviderId' => $resolvedWalletProviderId,
        ]);
    }

    public function updatePaymentDetails(Request $request, Customer $customer): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('customers.update'), 403);

        $method = (string) $request->input('method_type');
        $selectedInstitutionId = $request->input('bank_financial_institution_id');
        $branchRule = Rule::exists('financial_institution_branches', 'id')
            ->whereNull('deleted_at')
            ->where('is_active', true);
        if (filled($selectedInstitutionId)) {
            $branchRule = $branchRule->where('financial_institution_id', (int) $selectedInstitutionId);
        }

        $validated = $request->validate([
            'method_type' => ['required', 'string', Rule::in(['bank', 'wallet'])],

            'bank_financial_institution_id' => [
                'nullable',
                Rule::requiredIf(fn () => $method === 'bank'),
                'integer',
                Rule::exists('financial_institutions', 'id')
                    ->whereNull('deleted_at')
                    ->where('is_active', true),
            ],
            'bank_financial_institution_branch_id' => [
                'nullable',
                Rule::requiredIf(fn () => $method === 'bank'),
                'integer',
                $branchRule,
            ],
            'account_name' => ['nullable', Rule::requiredIf(fn () => $method === 'bank'), 'string', 'max:255'],
            'account_number' => ['nullable', Rule::requiredIf(fn () => $method === 'bank'), 'string', 'max:50'],

            'wallet_provider_id' => [
                'nullable',
                Rule::requiredIf(fn () => $method === 'wallet'),
                'integer',
                Rule::exists('wallet_providers', 'id')
                    ->whereNull('deleted_at')
                    ->where('is_active', true),
            ],
            'wallet_number' => ['nullable', Rule::requiredIf(fn () => $method === 'wallet'), 'string', 'max:20'],
        ]);

        $method = (string) $validated['method_type'];

        $institution = null;
        $branch = null;
        if ($method === 'bank') {
            $institution = FinancialInstitution::query()->find((int) $validated['bank_financial_institution_id']);
            $branch = FinancialInstitutionBranch::query()->find((int) $validated['bank_financial_institution_branch_id']);

            if (
                ! $institution
                || ! $branch
                || (int) $branch->financial_institution_id !== (int) $institution->id
            ) {
                return back()
                    ->withInput()
                    ->withErrors(['bank_financial_institution_branch_id' => 'Please select a valid bank branch.']);
            }
        }

        $walletProvider = null;
        if ($method === 'wallet') {
            $walletProvider = WalletProvider::query()->find((int) $validated['wallet_provider_id']);
            if (! $walletProvider) {
                return back()
                    ->withInput()
                    ->withErrors(['wallet_provider_id' => 'Please select a valid wallet provider.']);
            }
        }

        CustomerPaymentDetail::updateOrCreate(
            ['customer_id' => $customer->id],
            [
                'method_type' => $method,
                'bank_financial_institution_id' => $method === 'bank' ? $institution->id : null,
                'bank_financial_institution_branch_id' => $method === 'bank' ? $branch->id : null,
                'bank_name' => $method === 'bank' ? Str::upper($institution->name) : null,
                'bank_branch' => $method === 'bank' ? Str::upper($branch->name) : null,
                'account_name' => $method === 'bank' ? Str::upper(trim((string) $validated['account_name'])) : null,
                'account_number' => $method === 'bank' ? Str::upper(trim((string) $validated['account_number'])) : null,
                'wallet_provider_id' => $method === 'wallet' ? $walletProvider->id : null,
                'wallet_provider' => $method === 'wallet' ? Str::upper($walletProvider->name) : null,
                'wallet_number' => $method === 'wallet' ? Str::upper(trim((string) $validated['wallet_number'])) : null,
            ]
        );

        return redirect()
            ->route('admin.customers.show', $customer)
            ->with('status', 'Customer payment details updated successfully.');
    }

    /**
     * Export customers to Excel
     */
    public function export(Request $request)
    {
        abort_unless(auth('admin')->user()?->can('customers.export'), 403);
        
        $admin = auth('admin')->user();
        $companyFilterId = $admin->getCompanyFilterId();
        
        $query = Customer::with(['company', 'loanProduct', 'customerGroup']);

        // Filter by company if not primary company admin
        if ($companyFilterId !== null) {
            $query->where('company_id', $companyFilterId);
        }

        // Apply same filters as index method
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('national_id', 'like', "%{$search}%");
            });
        }

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('loan_product_id') && $request->loan_product_id) {
            $query->where('loan_product_id', $request->loan_product_id);
        }

        if ($request->has('customer_group_id') && $request->customer_group_id) {
            $query->where('customer_group_id', $request->customer_group_id);
        }

        // Company filter (only if primary admin, otherwise already filtered)
        if ($companyFilterId === null && $request->has('company_id') && $request->company_id) {
            $query->where('company_id', $request->company_id);
        }

        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $customers = $query->latest()->get();

        $exportData = $customers->map(function ($customer) {
            return [
                'First Name' => $customer->first_name,
                'Last Name' => $customer->last_name,
                'Email' => $customer->email,
                'Phone' => $customer->phone ?? '—',
                'National ID' => $customer->national_id ?? '—',
                'TPIN' => $customer->tpin ?? '—',
                'Date of Birth' => $customer->date_of_birth ? $customer->date_of_birth->format('Y-m-d') : '—',
                'Product' => $customer->loanProduct->name ?? '—',
                'Product Category' => $customer->loanProduct->category ?? '—',
                'Company' => $customer->company->name ?? '—',
                'Customer Group' => $customer->customerGroup->name ?? '—',
                'Status' => ucfirst($customer->status),
                'KYC Status' => ucfirst($customer->kyc_status ?? 'unverified'),
                'Employment Status' => $customer->employment_status ?? '—',
                'Address Line 1' => $customer->address_line1 ?? '—',
                'Address Line 2' => $customer->address_line2 ?? '—',
                'City' => $customer->city ?? '—',
                'State' => $customer->state ?? '—',
                'Postal Code' => $customer->postal_code ?? '—',
                'Country' => $customer->country ?? '—',
                'Maximum Loan Take' => number_format($customer->maximum_loan_take ?? 0, 2),
                'Created At' => $customer->created_at->format('Y-m-d H:i:s'),
                'Last Login At' => $customer->last_login_at ? $customer->last_login_at->format('Y-m-d H:i:s') : '—',
            ];
        });

        $filename = 'customers-export-' . now()->format('Y-m-d_His') . '.xlsx';

        return Excel::download(new class($exportData) implements FromCollection, WithHeadings, WithColumnWidths, WithStyles {
            protected $data;

            public function __construct($data)
            {
                $this->data = $data;
            }

            public function collection()
            {
                return collect($this->data)->map(function ($row) {
                    return array_values($row);
                });
            }

            public function headings(): array
            {
                return [
                    'First Name',
                    'Last Name',
                    'Email',
                    'Phone',
                    'National ID',
                    'TPIN',
                    'Date of Birth',
                    'Product',
                    'Product Category',
                    'Company',
                    'Customer Group',
                    'Status',
                    'KYC Status',
                    'Employment Status',
                    'Address Line 1',
                    'Address Line 2',
                    'City',
                    'State',
                    'Postal Code',
                    'Country',
                    'Maximum Loan Take',
                    'Created At',
                    'Last Login At',
                ];
            }

            public function columnWidths(): array
            {
                return [
                    'A' => 15, 'B' => 15, 'C' => 25, 'D' => 15, 'E' => 15,
                    'F' => 15, 'G' => 15, 'H' => 20, 'I' => 18, 'J' => 20,
                    'K' => 18, 'L' => 12, 'M' => 12, 'N' => 18, 'O' => 20,
                    'P' => 20, 'Q' => 15, 'R' => 15, 'S' => 12, 'T' => 15,
                    'U' => 18, 'V' => 20, 'W' => 20,
                ];
            }

            public function styles(Worksheet $sheet)
            {
                return [
                    1 => ['font' => ['bold' => true, 'size' => 12]],
                ];
            }
        }, $filename);
    }

    /**
     * Show customer loans with summary
     */
    public function loans(Customer $customer): View
    {
        abort_unless(auth('admin')->user()?->can('customers.loans'), 403);
        $loans = $customer->loans()
            ->with(['loanProduct', 'customerGroup', 'channel', 'approver'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Calculate summary statistics
        $summary = [
            'total_loans' => $loans->count(),
            'total_principal' => $loans->sum('principal_amount'),
            'total_amount' => $loans->sum('total_amount'),
            'total_outstanding' => $loans->sum('outstanding_balance'),
            'active_loans' => $loans->whereIn('status', ['approved', 'active'])->count(),
            'completed_loans' => $loans->where('status', 'completed')->count(),
            'defaulted_loans' => $loans->where('status', 'defaulted')->count(),
        ];

        return view('admin.customers.loans', compact('customer', 'loans', 'summary'));
    }

    /**
     * Show customer repayments with summary
     */
    public function repayments(Customer $customer): View
    {
        abort_unless(auth('admin')->user()?->can('customers.repayments'), 403);
        $repayments = \App\Models\Repayment::where('customer_id', $customer->id)
            ->with(['channel', 'loanRepayments.loan.loanProduct'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // Calculate summary statistics
        $allRepayments = \App\Models\Repayment::where('customer_id', $customer->id)
            ->where('status', 'completed')
            ->get();

        $summary = [
            'total_repayments' => $allRepayments->count(),
            'total_amount' => $allRepayments->sum('total_amount'),
            'total_principal' => $allRepayments->sum(function($repayment) {
                return $repayment->loanRepayments->sum('principal_amount');
            }),
            'total_interest' => $allRepayments->sum(function($repayment) {
                return $repayment->loanRepayments->sum('interest_amount');
            }),
            'total_fees' => $allRepayments->sum(function($repayment) {
                return $repayment->loanRepayments->sum('processing_fee_amount');
            }),
        ];

        return view('admin.customers.repayments', compact('customer', 'repayments', 'summary'));
    }
}
