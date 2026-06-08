<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoanPurpose;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LoanPurposeController extends Controller
{
    public function index(): View
    {
        abort_unless(auth('admin')->user()?->can('loan-purposes.view'), 403);

        $loanPurposes = LoanPurpose::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.loan-purposes.index', compact('loanPurposes'));
    }

    public function create(): View
    {
        abort_unless(auth('admin')->user()?->can('loan-purposes.create'), 403);

        return view('admin.loan-purposes.create');
    }

    public function store(): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('loan-purposes.create'), 403);

        try {
            $data = request()->validate([
                'name' => 'required|string|max:255|unique:loan_purposes,name',
                'description' => 'nullable|string',
                'sort_order' => 'nullable|integer|min:0',
                'is_active' => 'boolean',
            ]);

            $data['sort_order'] = $data['sort_order'] ?? 0;

            LoanPurpose::create($data);

            return redirect()
                ->route('admin.loan-purposes.index')
                ->with('status', 'Loan purpose created successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.loan-purposes.create')
                ->withInput()
                ->with('error', 'Failed to create loan purpose: '.$e->getMessage());
        }
    }

    public function edit(LoanPurpose $loanPurpose): View
    {
        abort_unless(auth('admin')->user()?->can('loan-purposes.update'), 403);

        return view('admin.loan-purposes.edit', compact('loanPurpose'));
    }

    public function update(LoanPurpose $loanPurpose): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('loan-purposes.update'), 403);

        try {
            $data = request()->validate([
                'name' => 'required|string|max:255|unique:loan_purposes,name,'.$loanPurpose->id,
                'description' => 'nullable|string',
                'sort_order' => 'nullable|integer|min:0',
                'is_active' => 'boolean',
            ]);

            $data['sort_order'] = $data['sort_order'] ?? 0;

            $loanPurpose->update($data);

            return redirect()
                ->route('admin.loan-purposes.edit', $loanPurpose)
                ->with('status', 'Loan purpose updated successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.loan-purposes.edit', $loanPurpose)
                ->withInput()
                ->with('error', 'Failed to update loan purpose: '.$e->getMessage());
        }
    }

    public function destroy(LoanPurpose $loanPurpose): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('loan-purposes.delete'), 403);

        if ($loanPurpose->loans()->exists()) {
            return redirect()
                ->route('admin.loan-purposes.index')
                ->with('error', 'This loan purpose cannot be deleted because it is linked to existing loans. Deactivate it instead.');
        }

        try {
            $loanPurpose->delete();

            return redirect()
                ->route('admin.loan-purposes.index')
                ->with('status', 'Loan purpose deleted successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.loan-purposes.index')
                ->with('error', 'Failed to delete loan purpose: '.$e->getMessage());
        }
    }
}
