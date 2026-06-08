<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sector;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SectorController extends Controller
{
    public function index(): View
    {
        abort_unless(auth('admin')->user()?->can('sectors.view'), 403);

        $sectors = Sector::orderBy('name')->get();

        return view('admin.sectors.index', compact('sectors'));
    }

    public function create(): View
    {
        abort_unless(auth('admin')->user()?->can('sectors.create'), 403);

        return view('admin.sectors.create');
    }

    public function store(): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('sectors.create'), 403);

        try {
            $data = request()->validate([
                'name' => 'required|string|max:255|unique:sectors,name',
                'code' => 'nullable|string|max:255|unique:sectors,code',
                'description' => 'nullable|string',
                'is_active' => 'boolean',
            ]);

            Sector::create($data);

            return redirect()
                ->route('admin.sectors.index')
                ->with('status', 'Sector created successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.sectors.create')
                ->withInput()
                ->with('error', 'Failed to create sector: '.$e->getMessage());
        }
    }

    public function edit(Sector $sector): View
    {
        abort_unless(auth('admin')->user()?->can('sectors.update'), 403);

        return view('admin.sectors.edit', compact('sector'));
    }

    public function update(Sector $sector): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('sectors.update'), 403);

        try {
            $data = request()->validate([
                'name' => 'required|string|max:255|unique:sectors,name,'.$sector->id,
                'code' => 'nullable|string|max:255|unique:sectors,code,'.$sector->id,
                'description' => 'nullable|string',
                'is_active' => 'boolean',
            ]);

            $sector->update($data);

            return redirect()
                ->route('admin.sectors.edit', $sector)
                ->with('status', 'Sector updated successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.sectors.edit', $sector)
                ->withInput()
                ->with('error', 'Failed to update sector: '.$e->getMessage());
        }
    }

    public function destroy(Sector $sector): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('sectors.delete'), 403);

        try {
            $sector->delete();

            return redirect()
                ->route('admin.sectors.index')
                ->with('status', 'Sector deleted successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.sectors.index')
                ->with('error', 'Failed to delete sector: '.$e->getMessage());
        }
    }
}
