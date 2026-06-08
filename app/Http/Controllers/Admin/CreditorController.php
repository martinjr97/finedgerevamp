<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Creditor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CreditorController extends Controller
{
    /**
     * Display a listing of creditors.
     */
    public function index(): View
    {
        abort_unless(auth('admin')->user()?->can('creditors.view'), 403);
        $creditors = Creditor::where('is_active', true)->orderBy('due_date')->get();
        return view('admin.creditors.index', compact('creditors'));
    }

    /**
     * Show the form for creating a new creditor.
     */
    public function create(): View
    {
        abort_unless(auth('admin')->user()?->can('creditors.create'), 403);
        return view('admin.creditors.create');
    }

    /**
     * Store a newly created creditor.
     */
    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('creditors.create'), 403);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'amount' => ['required', 'numeric', 'min:0'],
            'due_date' => ['nullable', 'date'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        Creditor::create($validated);

        return redirect()->route('admin.creditors.index')
            ->with('status', 'Creditor created successfully.');
    }

    /**
     * Display the specified creditor.
     */
    public function show(Creditor $creditor): View
    {
        abort_unless(auth('admin')->user()?->can('creditors.view'), 403);
        return view('admin.creditors.show', compact('creditor'));
    }

    /**
     * Show the form for editing the specified creditor.
     */
    public function edit(Creditor $creditor): View
    {
        abort_unless(auth('admin')->user()?->can('creditors.update'), 403);
        return view('admin.creditors.edit', compact('creditor'));
    }

    /**
     * Update the specified creditor.
     */
    public function update(Request $request, Creditor $creditor): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('creditors.update'), 403);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'amount' => ['required', 'numeric', 'min:0'],
            'due_date' => ['nullable', 'date'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $creditor->update($validated);

        return redirect()->route('admin.creditors.index')
            ->with('status', 'Creditor updated successfully.');
    }

    /**
     * Remove the specified creditor.
     */
    public function destroy(Creditor $creditor): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('creditors.delete'), 403);
        $creditor->delete();

        return redirect()->route('admin.creditors.index')
            ->with('status', 'Creditor deleted successfully.');
    }
}
