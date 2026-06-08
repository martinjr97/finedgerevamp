<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GeneralSetting;
use App\Models\LoanProduct;
use App\Support\PublicRegistrationPaths;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomerRegistrationSettingController extends Controller
{
    public function edit(): View
    {
        abort_unless(auth('admin')->user()?->can('settings.view'), 403);

        $setting = GeneralSetting::query()->first() ?? new GeneralSetting([
            'allow_customer_registration' => false,
            'public_registration_paths' => PublicRegistrationPaths::normalize(null),
        ]);

        $paths = PublicRegistrationPaths::fromSetting($setting);

        $governmentProducts = LoanProduct::where('is_active', true)
            ->where('category', 'government')
            ->orderBy('name')
            ->get();

        $collateralProducts = LoanProduct::where('is_active', true)
            ->where('category', 'collateral')
            ->orderBy('name')
            ->get();

        $allProducts = LoanProduct::where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('admin.settings.customer-registration', [
            'setting' => $setting,
            'paths' => $paths,
            'governmentProducts' => $governmentProducts,
            'collateralProducts' => $collateralProducts,
            'allProducts' => $allProducts,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('settings.update'), 403);

        $data = $request->validate([
            'allow_customer_registration' => ['nullable', 'boolean'],
            'government_worker_enabled' => ['nullable', 'boolean'],
            'government_worker_loan_product_id' => [
                'nullable',
                'integer',
                Rule::exists('loan_products', 'id')->where('is_active', true),
            ],
            'collateral_based_enabled' => ['nullable', 'boolean'],
            'collateral_based_loan_product_id' => [
                'nullable',
                'integer',
                Rule::exists('loan_products', 'id')->where('is_active', true),
            ],
        ]);

        $setting = GeneralSetting::query()->first() ?? new GeneralSetting([
            'allow_customer_registration' => false,
        ]);

        $governmentEnabled = $request->boolean('government_worker_enabled');
        $collateralEnabled = $request->boolean('collateral_based_enabled');

        $governmentProductId = $governmentEnabled ? ($data['government_worker_loan_product_id'] ?? null) : null;
        $collateralProductId = $collateralEnabled ? ($data['collateral_based_loan_product_id'] ?? null) : null;

        if ($governmentEnabled && ! $governmentProductId) {
            return back()
                ->withInput()
                ->withErrors(['government_worker_loan_product_id' => 'Select a loan product for Government Worker registration.']);
        }

        if ($collateralEnabled && ! $collateralProductId) {
            return back()
                ->withInput()
                ->withErrors(['collateral_based_loan_product_id' => 'Select a loan product for Collateral-Based registration.']);
        }

        $setting->allow_customer_registration = $request->boolean(
            'allow_customer_registration',
            (bool) ($setting->allow_customer_registration ?? false)
        );

        $setting->public_registration_paths = [
            PublicRegistrationPaths::GOVERNMENT_WORKER => [
                'enabled' => $governmentEnabled,
                'loan_product_id' => $governmentProductId ? (int) $governmentProductId : null,
            ],
            PublicRegistrationPaths::COLLATERAL_BASED => [
                'enabled' => $collateralEnabled,
                'loan_product_id' => $collateralProductId ? (int) $collateralProductId : null,
            ],
        ];

        $setting->save();

        return redirect()
            ->route('admin.settings.customer-registration.edit')
            ->with('status', 'Customer registration settings updated successfully.');
    }
}
