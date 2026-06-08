<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Branch;
use App\Models\District;
use App\Models\Loan;
use App\Models\Province;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Carbon\Carbon;

class BranchController extends Controller
{
    public function index(): View
    {
        abort_unless(auth('admin')->user()?->can('branches.view'), 403);

        $branches = Branch::with(['province', 'district', 'manager', 'admins' => fn ($q) => $q->where('is_active', true), 'customerGroups'])
            ->orderBy('name')
            ->get();

        // Pre-calculate disbursement stats per branch
        $branchIds = $branches->pluck('id')->all();
        $today = Carbon::today();
        $weekStart = $today->copy()->subDays(6);
        $monthStart = $today->copy()->subDays(29);

        $loanDisbursements = Loan::with('customerGroup:id,branch_id')
            ->where('disbursement_status', 'completed')
            ->whereNotNull('disbursed_at')
            ->whereHas('customerGroup', function ($q) use ($branchIds) {
                $q->whereIn('branch_id', $branchIds);
            })
            ->get(['id', 'customer_group_id', 'principal_amount', 'disbursed_at']);

        $branchStats = [];
        foreach ($loanDisbursements as $loan) {
            $branchId = $loan->customerGroup?->branch_id;
            if (!$branchId) {
                continue;
            }

            $branchStats[$branchId] ??= [
                'total' => 0,
                'monthly' => 0,
                'weekly' => 0,
                'daily' => 0,
                'loan_count' => 0,
            ];

            $amount = (float) $loan->principal_amount;
            $branchStats[$branchId]['total'] += $amount;
            $branchStats[$branchId]['loan_count'] += 1;

            $disbursedAt = Carbon::parse($loan->disbursed_at);
            if ($disbursedAt->greaterThanOrEqualTo($monthStart)) {
                $branchStats[$branchId]['monthly'] += $amount;
            }
            if ($disbursedAt->greaterThanOrEqualTo($weekStart)) {
                $branchStats[$branchId]['weekly'] += $amount;
            }
            if ($disbursedAt->greaterThanOrEqualTo($today)) {
                $branchStats[$branchId]['daily'] += $amount;
            }
        }

        return view('admin.branches.index', [
            'branches' => $branches,
            'branchStats' => $branchStats,
        ]);
    }

    public function create(): View
    {
        abort_unless(auth('admin')->user()?->can('branches.create'), 403);

        return view('admin.branches.create', [
            'provinces' => Province::where('is_active', true)->orderBy('name')->get(),
            'districts' => District::where('is_active', true)->orderBy('name')->get(),
            'managers' => Admin::where('is_active', true)->orderBy('first_name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('branches.create'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255', 'unique:branches,code'],
            'province_id' => ['nullable', 'exists:provinces,id'],
            'district_id' => ['nullable', 'exists:districts,id'],
            'branch_manager_id' => ['nullable', 'exists:admins,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        try {
            Branch::create([
                'name' => $data['name'],
                'code' => $data['code'],
                'province_id' => $data['province_id'] ?? null,
                'district_id' => $data['district_id'] ?? null,
                'branch_manager_id' => $data['branch_manager_id'] ?? null,
                'is_active' => $request->boolean('is_active', true),
            ]);

            return redirect()
                ->route('admin.branches.index')
                ->with('status', 'Branch created successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.branches.create')
                ->withInput()
                ->with('error', 'Failed to create branch: '.$e->getMessage());
        }
    }

    public function edit(Branch $branch): View
    {
        abort_unless(auth('admin')->user()?->can('branches.update'), 403);

        return view('admin.branches.edit', [
            'branch' => $branch,
            'provinces' => Province::where('is_active', true)->orderBy('name')->get(),
            'districts' => District::where('is_active', true)->orderBy('name')->get(),
            'managers' => Admin::where('is_active', true)->orderBy('first_name')->get(),
        ]);
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('branches.update'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255', 'unique:branches,code,'.$branch->id],
            'province_id' => ['nullable', 'exists:provinces,id'],
            'district_id' => ['nullable', 'exists:districts,id'],
            'branch_manager_id' => ['nullable', 'exists:admins,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        try {
            $branch->update([
                'name' => $data['name'],
                'code' => $data['code'],
                'province_id' => $data['province_id'] ?? null,
                'district_id' => $data['district_id'] ?? null,
                'branch_manager_id' => $data['branch_manager_id'] ?? null,
                'is_active' => $request->boolean('is_active', true),
            ]);

            return redirect()
                ->route('admin.branches.edit', $branch)
                ->with('status', 'Branch updated successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.branches.edit', $branch)
                ->withInput()
                ->with('error', 'Failed to update branch: '.$e->getMessage());
        }
    }

    public function destroy(Branch $branch): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('branches.delete'), 403);

        try {
            $branch->delete();

            return redirect()
                ->route('admin.branches.index')
                ->with('status', 'Branch deleted successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.branches.index')
                ->with('error', 'Failed to delete branch: '.$e->getMessage());
        }
    }
}

