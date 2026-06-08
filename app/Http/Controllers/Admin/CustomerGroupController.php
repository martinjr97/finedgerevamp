<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerGroup;
use App\Models\LoanProduct;
use App\Models\LoanRateType;
use App\Models\Admin;
use App\Models\CustomerGroupRelationshipManagerHistory;
use App\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerGroupController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(auth('admin')->user()?->can('customers.view'), 403);

        $admin = auth('admin')->user();
        $companyFilterId = $admin->getCompanyFilterId();

        $query = CustomerGroup::with(['loanProduct.company', 'branch', 'relationshipManager'])
            ->withCount('customers');

        // Filter by company if not primary company admin
        if ($companyFilterId !== null) {
            $query->whereHas('loanProduct', function ($q) use ($companyFilterId) {
                $q->where('company_id', $companyFilterId);
            });
        }

        // Search filter
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('loanProduct', function ($productQuery) use ($search) {
                        $productQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    });
            });
        }

        // Product filter
        if ($request->has('loan_product_id') && $request->loan_product_id) {
            $query->where('loan_product_id', $request->loan_product_id);
        }

        // Company filter (only if primary admin, otherwise already filtered)
        if ($companyFilterId === null && $request->has('company_id') && $request->company_id) {
            $query->whereHas('loanProduct', function ($q) use ($request) {
                $q->where('company_id', $request->company_id);
            });
        }

        // Status filter
        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active === '1');
        }

        // Risk level filter
        if ($request->has('risk_level') && $request->risk_level) {
            $query->where('risk_level', $request->risk_level);
        }

        $customerGroups = $query->latest()->paginate(20);

        // Get filter options
        $loanProductsQuery = LoanProduct::where('is_active', true)->with('company');
        $companiesQuery = \App\Models\Company::where('status', 'active');
        
        if ($companyFilterId !== null) {
            $loanProductsQuery->where('company_id', $companyFilterId);
            $companiesQuery->where('id', $companyFilterId);
        }
        
        $loanProducts = $loanProductsQuery->orderBy('name')->get();
        $companies = $companiesQuery->orderBy('name')->get();

        return view('admin.customer-groups.index', compact('customerGroups', 'loanProducts', 'companies'));
    }

    public function create(Request $request): View
    {
        abort_unless(auth('admin')->user()?->can('loan-products.view'), 403);

        $loanProductId = $request->query('loan_product_id');
        
        if (!$loanProductId) {
            return redirect()->route('admin.loan-products.index')
                ->with('error', 'Please select a loan product first.');
        }

        $loanProduct = LoanProduct::findOrFail($loanProductId);

        $relationshipManagers = Admin::where('is_relationship_manager', true)
            ->orderBy('first_name')
            ->get();

        $branches = Branch::where('is_active', true)
            ->orderBy('name')
            ->get();

        // Get loan rate types for this loan product
        $loanRateTypes = LoanRateType::where('loan_product_id', $loanProduct->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('admin.customer-groups.create', [
            'loanProduct' => $loanProduct,
            'loanRateTypes' => $loanRateTypes,
            'relationshipManagers' => $relationshipManagers,
            'branches' => $branches,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('loan-products.view'), 403);

        $validated = $request->validate([
            'loan_product_id' => 'required|exists:loan_products,id',
            'loan_rate_type_id' => 'nullable|exists:loan_rate_types,id',
            'branch_id' => 'required|exists:branches,id',
            'relationship_manager_id' => 'nullable|exists:admins,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:customer_groups,code',
            'description' => 'nullable|string',
            'risk_level' => 'required|in:low,medium,high',
            'max_loan_amount' => 'nullable|numeric|min:0',
            'max_loan_tenure_months' => 'nullable|integer|min:1',
            'allow_multiple_loans' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $validated['allow_multiple_loans'] = $request->boolean('allow_multiple_loans');

        try {
            $customerGroup = CustomerGroup::create($validated);

            if (!empty($validated['relationship_manager_id'])) {
                CustomerGroupRelationshipManagerHistory::create([
                    'customer_group_id' => $customerGroup->id,
                    'relationship_manager_id' => $validated['relationship_manager_id'],
                    'started_at' => now(),
                    'change_reason' => 'Initial assignment',
                    'changed_by' => auth('admin')->id(),
                ]);
            }

            return redirect()
                ->route('admin.loan-products.show', $validated['loan_product_id'])
                ->with('status', 'Customer group created successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.customer-groups.create', ['loan_product_id' => $validated['loan_product_id']])
                ->withInput()
                ->with('error', 'Failed to create customer group: '.$e->getMessage());
        }
    }

    /**
     * Update financial configuration for a customer group
     */
    public function updateFinancial(Request $request, CustomerGroup $customerGroup): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('loan-products.view'), 403);

        $validated = $request->validate([
            'max_loan_amount' => ['nullable', 'numeric', 'min:0'],
            'loan_cut_off_day' => ['nullable', 'integer', 'between:1,31'],
            'loan_payment_date' => ['nullable', 'integer', 'between:1,31'],
            'maximum_debit_ratio' => ['nullable', 'numeric', 'between:0,100'],
            'instalment_cross_over_percentage' => ['nullable', 'numeric', 'between:0,100'],
            'allow_multiple_loans' => ['boolean'],
        ]);

        try {
            $customerGroup->update([
                'max_loan_amount' => $validated['max_loan_amount'] ?? $customerGroup->max_loan_amount,
                'loan_cut_off_day' => $validated['loan_cut_off_day'] ?? $customerGroup->loan_cut_off_day,
                'loan_payment_date' => $validated['loan_payment_date'] ?? $customerGroup->loan_payment_date,
                'maximum_debit_ratio' => $validated['maximum_debit_ratio'] ?? $customerGroup->maximum_debit_ratio,
                'instalment_cross_over_percentage' => $validated['instalment_cross_over_percentage'] ?? $customerGroup->instalment_cross_over_percentage,
                'allow_multiple_loans' => $request->boolean('allow_multiple_loans'),
            ]);

            return redirect()
                ->route('admin.customer-groups.show', $customerGroup)
                ->with('status', 'Financial configuration updated successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.customer-groups.show', $customerGroup)
                ->withInput()
                ->with('error', 'Failed to update financial configuration: '.$e->getMessage());
        }
    }

    /**
     * Display the specified customer group
     */
    public function show(CustomerGroup $customerGroup): View
    {
        abort_unless(auth('admin')->user()?->can('loan-products.view'), 403);

        $customerGroup->load([
            'loanProduct',
            'loanRateType',
            'relationshipManager',
            'customers',
            'relationshipManagerHistories.relationshipManager',
            'relationshipManagerHistories.changedBy',
        ]);

        $relationshipManagers = Admin::where('is_relationship_manager', true)
            ->orderBy('first_name')
            ->get();

        return view('admin.customer-groups.show', [
            'customerGroup' => $customerGroup,
            'relationshipManagers' => $relationshipManagers,
        ]);
    }

    /**
     * Update the relationship manager for a customer group
     */
    public function updateRelationshipManager(Request $request, CustomerGroup $customerGroup): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('loan-products.view'), 403);

        $validated = $request->validate([
            'relationship_manager_id' => ['nullable', 'exists:admins,id'],
            'change_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $currentId = $customerGroup->relationship_manager_id;
        $newId = $validated['relationship_manager_id'] ?? null;

        // If nothing changed, do nothing
        if ($currentId === $newId) {
            return redirect()
                ->route('admin.customer-groups.show', $customerGroup)
                ->with('status', 'No changes were made to the relationship manager.');
        }

        // Require a reason if there was already a manager assigned
        if ($currentId !== null && empty($validated['change_reason'])) {
            return redirect()
                ->route('admin.customer-groups.show', $customerGroup)
                ->withInput()
                ->withErrors([
                    'change_reason' => 'Please provide a reason for changing the relationship manager.',
                ]);
        }

        try {
            // Close any existing active history record
            CustomerGroupRelationshipManagerHistory::where('customer_group_id', $customerGroup->id)
                ->whereNull('ended_at')
                ->update(['ended_at' => now()]);

            // Update the current relationship manager on the group
            $customerGroup->update([
                'relationship_manager_id' => $newId,
            ]);

            // Record new history if assigning a manager
            if ($newId !== null) {
                CustomerGroupRelationshipManagerHistory::create([
                    'customer_group_id' => $customerGroup->id,
                    'relationship_manager_id' => $newId,
                    'started_at' => now(),
                    'ended_at' => null,
                    'change_reason' => $validated['change_reason'] ?? null,
                    'changed_by' => auth('admin')->id(),
                ]);
            }

            return redirect()
                ->route('admin.customer-groups.show', $customerGroup)
                ->with('status', 'Relationship manager updated successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.customer-groups.show', $customerGroup)
                ->withInput()
                ->with('error', 'Failed to update relationship manager: '.$e->getMessage());
        }
    }

    /**
     * Show the page to manage rate type for a customer group
     */
    public function manageRateType(CustomerGroup $customerGroup): View
    {
        abort_unless(auth('admin')->user()?->can('loan-products.view'), 403);

        $loanProduct = $customerGroup->loanProduct;
        
        // Get all active rate types for this loan product
        $loanRateTypes = LoanRateType::where('loan_product_id', $loanProduct->id)
            ->where('is_active', true)
            ->with('loanRates')
            ->orderBy('name')
            ->get();

        return view('admin.customer-groups.manage-rate-type', [
            'customerGroup' => $customerGroup,
            'loanProduct' => $loanProduct,
            'loanRateTypes' => $loanRateTypes,
        ]);
    }

    /**
     * Update the rate type for a customer group
     */
    public function updateRateType(Request $request, CustomerGroup $customerGroup): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('loan-products.view'), 403);

        $validated = $request->validate([
            'loan_rate_type_id' => 'nullable|exists:loan_rate_types,id',
        ]);

        try {
            $customerGroup->update([
                'loan_rate_type_id' => $validated['loan_rate_type_id'] ?? null,
            ]);

            return redirect()
                ->route('admin.loan-products.show', $customerGroup->loan_product_id)
                ->with('status', 'Rate type updated successfully for customer group.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.customer-groups.manage-rate-type', $customerGroup)
                ->with('error', 'Failed to update rate type: '.$e->getMessage());
        }
    }
}

