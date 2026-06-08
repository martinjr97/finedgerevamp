<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\FinancialInstitution;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class BankController extends Controller
{
    /**
     * Display a listing of banks.
     */
    public function index(): View
    {
        abort_unless(auth('admin')->user()?->can('banks.view'), 403);
        $banks = Bank::orderBy('name')->get();
        return view('admin.banks.index', compact('banks'));
    }

    /**
     * Show the form for creating a new bank.
     */
    public function create(): View
    {
        abort_unless(auth('admin')->user()?->can('banks.create'), 403);

        return view('admin.banks.create', [
            'financialInstitutions' => $this->financialInstitutionsForForm(),
        ]);
    }

    /**
     * Store a newly created bank.
     */
    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('banks.create'), 403);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'max:50', 'unique:banks,account_number'],
            'account_name' => ['required', 'string', 'max:255'],
            'bank_name' => ['required', 'string', 'max:255'],
            'branch' => ['nullable', 'string', 'max:255'],
            'currency' => ['required', 'string', 'size:3'],
            'opening_balance' => ['required', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $bank = Bank::create([
            ...$validated,
            'current_balance' => $validated['opening_balance'],
        ]);

        return redirect()->route('admin.banks.index')
            ->with('status', 'Bank created successfully.');
    }

    /**
     * Display the specified bank.
     */
    public function show(Bank $bank): View
    {
        abort_unless(auth('admin')->user()?->can('banks.view'), 403);
        $bank->load(['sourceTransactions', 'destinationTransactions', 'repayments', 'loans']);
        return view('admin.banks.show', compact('bank'));
    }

    /**
     * Show the form for editing the specified bank.
     */
    public function edit(Bank $bank): View
    {
        abort_unless(auth('admin')->user()?->can('banks.update'), 403);

        return view('admin.banks.edit', [
            'bank' => $bank,
            'financialInstitutions' => $this->financialInstitutionsForForm(),
        ]);
    }

    /**
     * Update the specified bank.
     */
    public function update(Request $request, Bank $bank): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('banks.update'), 403);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'max:50', 'unique:banks,account_number,' . $bank->id],
            'account_name' => ['required', 'string', 'max:255'],
            'bank_name' => ['required', 'string', 'max:255'],
            'branch' => ['nullable', 'string', 'max:255'],
            'currency' => ['required', 'string', 'size:3'],
            'opening_balance' => ['required', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $bank->update($validated);

        return redirect()->route('admin.banks.index')
            ->with('status', 'Bank updated successfully.');
    }

    /**
     * Remove the specified bank.
     */
    public function destroy(Bank $bank): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('banks.delete'), 403);
        // Check if bank has transactions
        if ($bank->sourceTransactions()->count() > 0 || $bank->destinationTransactions()->count() > 0) {
            return redirect()->back()
                ->with('error', 'Cannot delete bank with existing transactions.');
        }

        $bank->delete();

        return redirect()->route('admin.banks.index')
            ->with('status', 'Bank deleted successfully.');
    }

    /**
     * @return Collection<int, FinancialInstitution>
     */
    private function financialInstitutionsForForm(): Collection
    {
        return FinancialInstitution::query()
            ->active()
            ->with(['branches' => fn ($query) => $query->active()->orderBy('name')])
            ->orderBy('name')
            ->get();
    }
}
