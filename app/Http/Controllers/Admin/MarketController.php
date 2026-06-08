<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Branch;
use App\Models\District;
use App\Models\LoanProduct;
use App\Models\LoanRateType;
use App\Models\Market;
use App\Models\Province;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MarketController extends Controller
{
    public function index(): View
    {
        abort_unless(auth('admin')->user()?->can('loan-products.view'), 403);

        $markets = Market::with(['province', 'district', 'branch', 'portfolioManager'])
            ->orderBy('name')
            ->get();

        return view('admin.markets.index', compact('markets'));
    }

    public function create(): View
    {
        abort_unless(auth('admin')->user()?->can('loan-products.view'), 403);

        $provinces = Province::where('is_active', true)->orderBy('name')->get();
        $districts = District::where('is_active', true)->orderBy('name')->get();
        $branches = Branch::where('is_active', true)->orderBy('name')->get();
        $portfolioManagers = Admin::where('is_relationship_manager', true)
            ->orderBy('first_name')
            ->get();

        // Get loan rate types for Marketeer products
        $marketeerProduct = LoanProduct::where('category', 'marketeer')->first();
        $loanRateTypes = $marketeerProduct 
            ? LoanRateType::where('loan_product_id', $marketeerProduct->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
            : collect();

        return view('admin.markets.create', compact('provinces', 'districts', 'branches', 'portfolioManagers', 'loanRateTypes'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('loan-products.view'), 403);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:markets,code',
            'address_line1' => 'required|string',
            'address_line2' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'province_id' => 'required|exists:provinces,id',
            'district_id' => 'required|exists:districts,id',
            'branch_id' => 'required|exists:branches,id',
            'contact_person_name' => 'required|string|max:255',
            'contact_person_phone' => 'required|string|max:20',
            'contact_person_email' => 'nullable|email|max:255',
            'portfolio_manager_id' => 'nullable|exists:admins,id',
            'loan_rate_type_id' => 'nullable|exists:loan_rate_types,id',
            'is_active' => 'boolean',
        ]);

        try {
            Market::create($validated);

            return redirect()
                ->route('admin.markets.index')
                ->with('status', 'Market created successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.markets.create')
                ->withInput()
                ->with('error', 'Failed to create market: '.$e->getMessage());
        }
    }

    public function show(Market $market): View
    {
        abort_unless(auth('admin')->user()?->can('loan-products.view'), 403);

        $market->load(['province', 'district', 'portfolioManager', 'marketeerCustomerDetails.customer']);

        return view('admin.markets.show', compact('market'));
    }

    public function edit(Market $market): View
    {
        abort_unless(auth('admin')->user()?->can('loan-products.view'), 403);

        $provinces = Province::where('is_active', true)->orderBy('name')->get();
        $districts = District::where('is_active', true)->orderBy('name')->get();
        $branches = Branch::where('is_active', true)->orderBy('name')->get();
        $portfolioManagers = Admin::where('is_relationship_manager', true)
            ->orderBy('first_name')
            ->get();

        // Get loan rate types for Marketeer products
        $marketeerProduct = LoanProduct::where('category', 'marketeer')->first();
        $loanRateTypes = $marketeerProduct 
            ? LoanRateType::where('loan_product_id', $marketeerProduct->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
            : collect();

        return view('admin.markets.edit', compact('market', 'provinces', 'districts', 'branches', 'portfolioManagers', 'loanRateTypes'));
    }

    public function update(Request $request, Market $market): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('loan-products.view'), 403);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:markets,code,'.$market->id,
            'address_line1' => 'required|string',
            'address_line2' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'province_id' => 'required|exists:provinces,id',
            'district_id' => 'required|exists:districts,id',
            'branch_id' => 'required|exists:branches,id',
            'contact_person_name' => 'required|string|max:255',
            'contact_person_phone' => 'required|string|max:20',
            'contact_person_email' => 'nullable|email|max:255',
            'portfolio_manager_id' => 'nullable|exists:admins,id',
            'loan_rate_type_id' => 'nullable|exists:loan_rate_types,id',
            'is_active' => 'boolean',
        ]);

        try {
            $market->update($validated);

            return redirect()
                ->route('admin.markets.show', $market)
                ->with('status', 'Market updated successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.markets.edit', $market)
                ->withInput()
                ->with('error', 'Failed to update market: '.$e->getMessage());
        }
    }

    public function destroy(Market $market): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('loan-products.view'), 403);

        try {
            $market->delete();

            return redirect()
                ->route('admin.markets.index')
                ->with('status', 'Market deleted successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.markets.index')
                ->with('error', 'Failed to delete market: '.$e->getMessage());
        }
    }
}
