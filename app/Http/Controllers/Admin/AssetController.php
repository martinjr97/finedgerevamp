<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Employee;
use App\Services\AssetTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AssetController extends Controller
{
    public function __construct(
        private readonly AssetTransferService $assetTransferService
    ) {}
    public function index(): View
    {
        abort_unless(auth('admin')->user()?->can('assets.view'), 403);

        $assets = Asset::query()
            ->with('employee')
            ->orderBy('asset_type')
            ->orderBy('name')
            ->get();

        $totalValue = (float) Asset::query()->where('is_active', true)->sum('value');

        $employees = Employee::query()->active()->orderBy('first_name')->orderBy('last_name')->get();

        return view('admin.assets.index', compact('assets', 'totalValue', 'employees'));
    }

    public function create(): View
    {
        abort_unless(auth('admin')->user()?->can('assets.create'), 403);

        $employees = Employee::query()->active()->orderBy('first_name')->orderBy('last_name')->get();

        return view('admin.assets.create', compact('employees'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('assets.create'), 403);

        $validated = $request->validate([
            'asset_type' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'acquisition_date' => ['nullable', 'date'],
            'value' => ['required', 'numeric', 'min:0'],
            'image' => ['nullable', 'image', 'max:2048'],
            'employee_id' => ['nullable', 'exists:employees,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('assets', 'public');
        }

        $asset = Asset::create([
            'asset_type' => $validated['asset_type'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'acquisition_date' => $validated['acquisition_date'] ?? null,
            'value' => $validated['value'],
            'image_path' => $imagePath,
            'employee_id' => $validated['employee_id'] ?? null,
            'is_active' => $request->boolean('is_active', true),
            'created_by' => auth('admin')->id(),
            'updated_by' => auth('admin')->id(),
        ]);

        if ($asset->employee_id) {
            $this->assetTransferService->logOwnerChange(
                $asset,
                null,
                $asset->employee_id,
                'Initial assignment on asset creation',
                auth('admin')->user()
            );
        }

        return redirect()->route('admin.assets.index')
            ->with('status', 'Physical asset added successfully.');
    }

    public function show(Asset $asset): View
    {
        abort_unless(auth('admin')->user()?->can('assets.view'), 403);

        $asset->load([
            'createdBy',
            'updatedBy',
            'employee',
            'transfers.fromEmployee',
            'transfers.toEmployee',
            'transfers.transferredBy',
        ]);

        $employees = Employee::query()
            ->active()
            ->when($asset->employee_id, fn ($query) => $query->where('id', '!=', $asset->employee_id))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        return view('admin.assets.show', compact('asset', 'employees'));
    }

    public function transfer(Request $request, Asset $asset): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('assets.update'), 403);

        try {
            $validated = $request->validate([
                'to_employee_id' => [
                    'required',
                    'integer',
                    Rule::exists('employees', 'id')->where(fn ($query) => $query->where('is_active', true)),
                    Rule::notIn(array_filter([$asset->employee_id])),
                ],
                'reason' => ['nullable', 'string', 'max:1000'],
            ]);
        } catch (ValidationException $exception) {
            session()->flash('open_asset_transfer_id', $asset->id);

            throw $exception;
        }

        try {
            $this->assetTransferService->transfer(
                $asset,
                (int) $validated['to_employee_id'],
                $validated['reason'] ?? null,
                auth('admin')->user()
            );
        } catch (\InvalidArgumentException $exception) {
            session()->flash('open_asset_transfer_id', $asset->id);

            throw ValidationException::withMessages([
                'to_employee_id' => $exception->getMessage(),
            ]);
        }

        return redirect()->back()
            ->with('asset_transfer_success', 'Asset transferred successfully.');
    }

    public function edit(Asset $asset): View
    {
        abort_unless(auth('admin')->user()?->can('assets.update'), 403);

        $employees = Employee::query()
            ->where(function ($query) use ($asset) {
                $query->where('is_active', true);
                if ($asset->employee_id) {
                    $query->orWhere('id', $asset->employee_id);
                }
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        return view('admin.assets.edit', compact('asset', 'employees'));
    }

    public function update(Request $request, Asset $asset): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('assets.update'), 403);

        $validated = $request->validate([
            'asset_type' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'acquisition_date' => ['nullable', 'date'],
            'value' => ['required', 'numeric', 'min:0'],
            'image' => ['nullable', 'image', 'max:2048'],
            'employee_id' => ['nullable', 'exists:employees,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $imagePath = $asset->image_path;
        if ($request->hasFile('image')) {
            if ($imagePath) {
                Storage::disk('public')->delete($imagePath);
            }
            $imagePath = $request->file('image')->store('assets', 'public');
        }

        $previousEmployeeId = $asset->employee_id;

        $asset->update([
            'asset_type' => $validated['asset_type'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'acquisition_date' => $validated['acquisition_date'] ?? null,
            'value' => $validated['value'],
            'image_path' => $imagePath,
            'employee_id' => $validated['employee_id'] ?? null,
            'is_active' => $request->boolean('is_active'),
            'updated_by' => auth('admin')->id(),
        ]);

        $newEmployeeId = $validated['employee_id'] ?? null;

        if ($previousEmployeeId !== $newEmployeeId) {
            $this->assetTransferService->logOwnerChange(
                $asset,
                $previousEmployeeId,
                $newEmployeeId,
                'Updated via asset edit form',
                auth('admin')->user()
            );
        }

        return redirect()->route('admin.assets.index')
            ->with('status', 'Physical asset updated successfully.');
    }

    public function destroy(Asset $asset): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('assets.delete'), 403);

        if ($asset->image_path) {
            Storage::disk('public')->delete($asset->image_path);
        }

        $asset->delete();

        return redirect()->route('admin.assets.index')
            ->with('status', 'Physical asset removed successfully.');
    }
}
