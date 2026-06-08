<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoanProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LoanProductController extends Controller
{
    public function index(): View
    {
        abort_unless(auth('admin')->user()?->can('loan-products.view'), 403);

        $products = LoanProduct::orderBy('name')->get();

        return view('admin.loan-products.index', compact('products'));
    }

    public function show(LoanProduct $loanProduct): View
    {
        abort_unless(auth('admin')->user()?->can('loan-products.view'), 403);

        $data = [
            'product' => $loanProduct,
        ];

        // Load category-specific data
        if ($loanProduct->category === 'mou') {
            // Get companies that have customers with this loan product
            $data['companies'] = \App\Models\Company::whereHas('customers', function ($query) use ($loanProduct) {
                $query->where('loan_product_id', $loanProduct->id);
            })->withCount(['customers' => function ($query) use ($loanProduct) {
                $query->where('loan_product_id', $loanProduct->id);
            }])->orderBy('name')->get();
        } elseif (in_array($loanProduct->category, ['character', 'collateral', 'group_loans'], true)) {
            // Get customer groups for this loan product
            $data['customerGroups'] = $loanProduct->customerGroups()
                ->with(['loanRateType'])
                ->withCount('customers')
                ->orderBy('risk_level')
                ->orderBy('name')
                ->get();
        } elseif ($loanProduct->category === 'government') {
            // Get stats for government product
            $allCustomers = $loanProduct->customers;
            $data['stats'] = [
                'total_customers' => $allCustomers->count(),
                'active_customers' => $allCustomers->where('status', 'active')->count(),
                'pending_customers' => $allCustomers->where('status', 'pending')->count(),
                'suspended_customers' => $allCustomers->where('status', 'suspended')->count(),
                'total_loan_amount' => $allCustomers->sum('maximum_loan_take') ?? 0,
                'average_loan_amount' => $allCustomers->avg('maximum_loan_take') ?? 0,
            ];
            // Get customer groups for government product (should have DEFAULT group)
            $data['customerGroups'] = $loanProduct->customerGroups()
                ->with(['loanRateType'])
                ->withCount('customers')
                ->orderBy('name')
                ->get();
        } elseif ($loanProduct->category === 'marketeer') {
            // Get all active markets (they can be used for this product)
            // Also show markets that have customers with this loan product
            $data['markets'] = \App\Models\Market::where('is_active', true)
                ->with(['province', 'district', 'portfolioManager'])
                ->withCount(['marketeerCustomerDetails' => function ($query) use ($loanProduct) {
                    $query->whereHas('customer', function ($q) use ($loanProduct) {
                        $q->where('loan_product_id', $loanProduct->id);
                    });
                }])
                ->orderBy('name')
                ->get();
        }

        return view('admin.loan-products.show', $data);
    }

    public function create(): View
    {
        abort_unless(auth('admin')->user()?->can('loan-products.create'), 403);

        return view('admin.loan-products.create');
    }

    public function store(): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('loan-products.create'), 403);

        try {
            $data = request()->validate([
                'name' => 'required|string|max:255',
                'code' => 'required|string|max:255|unique:loan_products,code',
                'category' => 'required|in:government,mou,character,collateral,marketeer,sme,group_loans',
                'description' => 'nullable|string',
                'tenure_months' => 'nullable|integer|min:1',
                'max_amount' => 'nullable|numeric|min:0',
                'requires_collateral' => 'boolean',
                'requires_reference' => 'boolean',
                'is_active' => 'boolean',
            ]);

            LoanProduct::create(array_merge($data, ['company_id' => 1]));

            return redirect()
                ->route('admin.loan-products.index')
                ->with('status', 'Loan product created successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.loan-products.create')
                ->withInput()
                ->with('error', 'Failed to create loan product: '.$e->getMessage());
        }
    }

    public function edit(LoanProduct $loanProduct): View
    {
        abort_unless(auth('admin')->user()?->can('loan-products.update'), 403);

        return view('admin.loan-products.edit', [
            'product' => $loanProduct,
        ]);
    }

    public function update(LoanProduct $loanProduct): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('loan-products.update'), 403);

        try {
            $data = request()->validate([
                'name' => 'required|string|max:255',
                'code' => 'required|string|max:255|unique:loan_products,code,'.$loanProduct->id,
                'category' => 'required|in:government,mou,character,collateral,marketeer,sme,group_loans',
                'description' => 'nullable|string',
                'tenure_months' => 'nullable|integer|min:1',
                'max_amount' => 'nullable|numeric|min:0',
                'requires_collateral' => 'boolean',
                'requires_reference' => 'boolean',
                'is_active' => 'boolean',
            ]);

            $loanProduct->update(array_merge($data, ['company_id' => 1]));

            return redirect()
                ->route('admin.loan-products.edit', $loanProduct)
                ->with('status', 'Loan product updated successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.loan-products.edit', $loanProduct)
                ->withInput()
                ->with('error', 'Failed to update loan product: '.$e->getMessage());
        }
    }

    public function destroy(LoanProduct $loanProduct): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('loan-products.delete'), 403);

        try {
            $loanProduct->delete();

            return redirect()
                ->route('admin.loan-products.index')
                ->with('status', 'Loan product deleted successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.loan-products.index')
                ->with('error', 'Failed to delete loan product: '.$e->getMessage());
        }
    }
}
