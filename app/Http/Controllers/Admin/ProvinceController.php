<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Province;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProvinceController extends Controller
{
    public function index(): View
    {
        abort_unless(auth('admin')->user()?->can('provinces.view'), 403);

        $provinces = Province::orderBy('name')->get();

        return view('admin.provinces.index', compact('provinces'));
    }

    public function create(): View
    {
        abort_unless(auth('admin')->user()?->can('provinces.create'), 403);

        return view('admin.provinces.create');
    }

    public function store(): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('provinces.create'), 403);

        try {
            $data = request()->validate([
                'name' => 'required|string|max:255',
                'code' => 'required|string|max:255|unique:provinces,code',
                'country' => 'nullable|string|max:255',
                'is_active' => 'boolean',
            ]);

            Province::create($data);

            return redirect()
                ->route('admin.provinces.index')
                ->with('status', 'Province created successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.provinces.create')
                ->withInput()
                ->with('error', 'Failed to create province: '.$e->getMessage());
        }
    }

    public function edit(Province $province): View
    {
        abort_unless(auth('admin')->user()?->can('provinces.update'), 403);

        return view('admin.provinces.edit', compact('province'));
    }

    public function update(Province $province): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('provinces.update'), 403);

        try {
            $data = request()->validate([
                'name' => 'required|string|max:255',
                'code' => 'required|string|max:255|unique:provinces,code,'.$province->id,
                'country' => 'nullable|string|max:255',
                'is_active' => 'boolean',
            ]);

            $province->update($data);

            return redirect()
                ->route('admin.provinces.edit', $province)
                ->with('status', 'Province updated successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.provinces.edit', $province)
                ->withInput()
                ->with('error', 'Failed to update province: '.$e->getMessage());
        }
    }

    public function destroy(Province $province): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('provinces.delete'), 403);

        try {
            $province->delete();

            return redirect()
                ->route('admin.provinces.index')
                ->with('status', 'Province deleted successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.provinces.index')
                ->with('error', 'Failed to delete province: '.$e->getMessage());
        }
    }
}
