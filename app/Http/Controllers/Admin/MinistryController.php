<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ministry;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MinistryController extends Controller
{
    public function index(): View
    {
        abort_unless(auth('admin')->user()?->can('ministries.view'), 403);

        $ministries = Ministry::orderBy('name')->get();

        return view('admin.ministries.index', compact('ministries'));
    }

    public function create(): View
    {
        abort_unless(auth('admin')->user()?->can('ministries.create'), 403);

        return view('admin.ministries.create');
    }

    public function store(): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('ministries.create'), 403);

        try {
            $data = request()->validate([
                'name' => 'required|string|max:255|unique:ministries,name',
                'code' => 'required|string|max:255|unique:ministries,code',
                'description' => 'nullable|string',
                'is_active' => 'boolean',
            ]);

            Ministry::create($data);

            return redirect()
                ->route('admin.ministries.index')
                ->with('status', 'Ministry created successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.ministries.create')
                ->withInput()
                ->with('error', 'Failed to create ministry: '.$e->getMessage());
        }
    }

    public function edit(Ministry $ministry): View
    {
        abort_unless(auth('admin')->user()?->can('ministries.update'), 403);

        return view('admin.ministries.edit', compact('ministry'));
    }

    public function update(Ministry $ministry): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('ministries.update'), 403);

        try {
            $data = request()->validate([
                'name' => 'required|string|max:255|unique:ministries,name,'.$ministry->id,
                'code' => 'required|string|max:255|unique:ministries,code,'.$ministry->id,
                'description' => 'nullable|string',
                'is_active' => 'boolean',
            ]);

            $ministry->update($data);

            return redirect()
                ->route('admin.ministries.edit', $ministry)
                ->with('status', 'Ministry updated successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.ministries.edit', $ministry)
                ->withInput()
                ->with('error', 'Failed to update ministry: '.$e->getMessage());
        }
    }

    public function destroy(Ministry $ministry): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('ministries.delete'), 403);

        try {
            $ministry->delete();

            return redirect()
                ->route('admin.ministries.index')
                ->with('status', 'Ministry deleted successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.ministries.index')
                ->with('error', 'Failed to delete ministry: '.$e->getMessage());
        }
    }
}
