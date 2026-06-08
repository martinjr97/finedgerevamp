<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CollateralCategory;
use App\Models\CollateralType;
use App\Models\LoanProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CollateralTypeController extends Controller
{
    /**
     * Display a listing of collateral types for a loan product.
     */
    public function index(LoanProduct $loanProduct): View
    {
        abort_unless(auth('admin')->user()?->can('loan-products.view'), 403);

        $collateralTypes = $loanProduct->collateralTypes()
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return view('admin.collateral-types.index', compact('loanProduct', 'collateralTypes'));
    }

    /**
     * Show the form for creating a new collateral type.
     */
    public function create(LoanProduct $loanProduct): View
    {
        abort_unless(auth('admin')->user()?->can('loan-products.update'), 403);

        $categories = CollateralCategory::optionsForSelect();

        return view('admin.collateral-types.create', compact('loanProduct', 'categories'));
    }

    /**
     * Store a newly created collateral type.
     */
    public function store(Request $request, LoanProduct $loanProduct): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('loan-products.update'), 403);

        try {
            $data = $request->validate([
                'name' => 'required|string|max:255',
                'code' => 'required|string|max:255|unique:collateral_types,code',
                'category' => ['required', 'string', 'max:255', Rule::in(CollateralCategory::pluck('name'))],
                'description' => 'nullable|string',
                'min_value' => 'nullable|numeric|min:0',
                'max_value' => 'nullable|numeric|min:0|gt:min_value',
                'loan_to_value_ratio' => 'nullable|numeric|min:0|max:100',
                'is_active' => 'boolean',
            ]);

            $loanProduct->collateralTypes()->create($data);

            return redirect()
                ->route('admin.loan-products.collateral-types.index', $loanProduct)
                ->with('status', 'Collateral type created successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.loan-products.collateral-types.create', $loanProduct)
                ->withInput()
                ->with('error', 'Failed to create collateral type: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified collateral type.
     */
    public function show(LoanProduct $loanProduct, CollateralType $collateralType): View
    {
        abort_unless(auth('admin')->user()?->can('loan-products.view'), 403);
        
        // Ensure the collateral type belongs to the loan product
        abort_if($collateralType->loan_product_id !== $loanProduct->id, 404);

        return view('admin.collateral-types.show', compact('loanProduct', 'collateralType'));
    }

    /**
     * Show the form for editing the specified collateral type.
     */
    public function edit(LoanProduct $loanProduct, CollateralType $collateralType): View
    {
        abort_unless(auth('admin')->user()?->can('loan-products.update'), 403);
        
        // Ensure the collateral type belongs to the loan product
        abort_if($collateralType->loan_product_id !== $loanProduct->id, 404);

        $categories = CollateralCategory::optionsForSelect();

        return view('admin.collateral-types.edit', compact('loanProduct', 'collateralType', 'categories'));
    }

    /**
     * Update the specified collateral type.
     */
    public function update(Request $request, LoanProduct $loanProduct, CollateralType $collateralType): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('loan-products.update'), 403);
        
        // Ensure the collateral type belongs to the loan product
        abort_if($collateralType->loan_product_id !== $loanProduct->id, 404);

        try {
            $allowedCategories = array_merge(
                CollateralCategory::pluck('name')->toArray(),
                [$collateralType->category]
            );
            $data = $request->validate([
                'name' => 'required|string|max:255',
                'code' => 'required|string|max:255|unique:collateral_types,code,' . $collateralType->id,
                'category' => ['required', 'string', 'max:255', Rule::in($allowedCategories)],
                'description' => 'nullable|string',
                'min_value' => 'nullable|numeric|min:0',
                'max_value' => 'nullable|numeric|min:0|gt:min_value',
                'loan_to_value_ratio' => 'nullable|numeric|min:0|max:100',
                'is_active' => 'boolean',
            ]);

            $collateralType->update($data);

            return redirect()
                ->route('admin.loan-products.collateral-types.index', $loanProduct)
                ->with('status', 'Collateral type updated successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.loan-products.collateral-types.edit', [$loanProduct, $collateralType])
                ->withInput()
                ->with('error', 'Failed to update collateral type: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified collateral type.
     */
    public function destroy(LoanProduct $loanProduct, CollateralType $collateralType): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('loan-products.delete'), 403);
        
        // Ensure the collateral type belongs to the loan product
        abort_if($collateralType->loan_product_id !== $loanProduct->id, 404);

        try {
            $collateralType->delete();

            return redirect()
                ->route('admin.loan-products.collateral-types.index', $loanProduct)
                ->with('status', 'Collateral type deleted successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.loan-products.collateral-types.index', $loanProduct)
                ->with('error', 'Failed to delete collateral type: ' . $e->getMessage());
        }
    }
}
