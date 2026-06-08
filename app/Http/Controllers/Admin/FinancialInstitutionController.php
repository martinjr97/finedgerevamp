<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FinancialInstitution;
use App\Models\FinancialInstitutionBranch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinancialInstitutionController extends Controller
{
    public function index(): View
    {
        abort_unless(auth('admin')->user()?->can('financial-institutions.view'), 403);

        $institutions = FinancialInstitution::query()
            ->withCount('branches')
            ->orderBy('name')
            ->get();

        return view('admin.financial-institutions.index', compact('institutions'));
    }

    public function create(): View
    {
        abort_unless(auth('admin')->user()?->can('financial-institutions.create'), 403);

        return view('admin.financial-institutions.create');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('financial-institutions.create'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', 'unique:financial_institutions,code'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        try {
            $institution = FinancialInstitution::create($data);

            return redirect()
                ->route('admin.financial-institutions.branches', $institution)
                ->with('status', 'Financial institution created. You can add branches below.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.financial-institutions.create')
                ->withInput()
                ->with('error', 'Failed to create financial institution: '.$e->getMessage());
        }
    }

    public function edit(FinancialInstitution $financialInstitution): View
    {
        abort_unless(auth('admin')->user()?->can('financial-institutions.update'), 403);

        return view('admin.financial-institutions.edit', [
            'institution' => $financialInstitution,
        ]);
    }

    public function update(Request $request, FinancialInstitution $financialInstitution): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('financial-institutions.update'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', 'unique:financial_institutions,code,'.$financialInstitution->id],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        try {
            $financialInstitution->update($data);

            return redirect()
                ->route('admin.financial-institutions.edit', $financialInstitution)
                ->with('status', 'Financial institution updated successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.financial-institutions.edit', $financialInstitution)
                ->withInput()
                ->with('error', 'Failed to update financial institution: '.$e->getMessage());
        }
    }

    public function branches(FinancialInstitution $financialInstitution): View
    {
        abort_unless(auth('admin')->user()?->can('financial-institutions.view'), 403);

        $financialInstitution->load(['branches' => fn ($query) => $query->orderBy('name')]);

        return view('admin.financial-institutions.branches', [
            'institution' => $financialInstitution,
        ]);
    }

    public function storeBranch(Request $request, FinancialInstitution $financialInstitution): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('financial-institutions.update'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'sort_code' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        try {
            $financialInstitution->branches()->create($data);

            return redirect()
                ->route('admin.financial-institutions.branches', $financialInstitution)
                ->with('status', 'Branch added successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.financial-institutions.branches', $financialInstitution)
                ->withInput()
                ->with('error', 'Failed to add branch: '.$e->getMessage());
        }
    }

    public function editBranch(FinancialInstitution $financialInstitution, FinancialInstitutionBranch $branch): View
    {
        abort_unless(auth('admin')->user()?->can('financial-institutions.update'), 403);
        $this->ensureBranchBelongsToInstitution($financialInstitution, $branch);

        return view('admin.financial-institutions.edit-branch', [
            'institution' => $financialInstitution,
            'branch' => $branch,
        ]);
    }

    public function updateBranch(Request $request, FinancialInstitution $financialInstitution, FinancialInstitutionBranch $branch): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('financial-institutions.update'), 403);
        $this->ensureBranchBelongsToInstitution($financialInstitution, $branch);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'sort_code' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        try {
            $branch->update($data);

            return redirect()
                ->route('admin.financial-institutions.branches', $financialInstitution)
                ->with('status', 'Branch updated successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.financial-institutions.branches.edit', [$financialInstitution, $branch])
                ->withInput()
                ->with('error', 'Failed to update branch: '.$e->getMessage());
        }
    }

    private function ensureBranchBelongsToInstitution(FinancialInstitution $financialInstitution, FinancialInstitutionBranch $branch): void
    {
        abort_unless((int) $branch->financial_institution_id === (int) $financialInstitution->id, 404);
    }
}
