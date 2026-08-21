<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AssetController extends Controller
{
    public function index(): View
    {
        abort_unless(auth('admin')->user()?->can('assets.view'), 403);

        $assets = Asset::query()
            ->orderBy('asset_type')
            ->orderBy('name')
            ->get();

        $totalValue = (float) Asset::query()->where('is_active', true)->sum('value');

        return view('admin.assets.index', compact('assets', 'totalValue'));
    }

    public function create(): View
    {
        abort_unless(auth('admin')->user()?->can('assets.create'), 403);

        return view('admin.assets.create');
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
            'is_active' => ['nullable', 'boolean'],
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('assets', 'public');
        }

        Asset::create([
            'asset_type' => $validated['asset_type'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'acquisition_date' => $validated['acquisition_date'] ?? null,
            'value' => $validated['value'],
            'image_path' => $imagePath,
            'is_active' => $request->boolean('is_active', true),
            'created_by' => auth('admin')->id(),
            'updated_by' => auth('admin')->id(),
        ]);

        return redirect()->route('admin.assets.index')
            ->with('status', 'Physical asset added successfully.');
    }

    public function show(Asset $asset): View
    {
        abort_unless(auth('admin')->user()?->can('assets.view'), 403);

        $asset->load(['createdBy', 'updatedBy']);

        return view('admin.assets.show', compact('asset'));
    }

    public function edit(Asset $asset): View
    {
        abort_unless(auth('admin')->user()?->can('assets.update'), 403);

        return view('admin.assets.edit', compact('asset'));
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
            'is_active' => ['nullable', 'boolean'],
        ]);

        $imagePath = $asset->image_path;
        if ($request->hasFile('image')) {
            if ($imagePath) {
                Storage::disk('public')->delete($imagePath);
            }
            $imagePath = $request->file('image')->store('assets', 'public');
        }

        $asset->update([
            'asset_type' => $validated['asset_type'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'acquisition_date' => $validated['acquisition_date'] ?? null,
            'value' => $validated['value'],
            'image_path' => $imagePath,
            'is_active' => $request->boolean('is_active'),
            'updated_by' => auth('admin')->id(),
        ]);

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
