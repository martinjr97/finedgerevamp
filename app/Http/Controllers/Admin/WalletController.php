<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\ZambianPhoneRules;
use App\Models\Wallet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WalletController extends Controller
{
    /**
     * Display a listing of wallets.
     */
    public function index(): View
    {
        abort_unless(auth('admin')->user()?->can('wallets.view'), 403);
        $wallets = Wallet::orderBy('name')->get();
        return view('admin.wallets.index', compact('wallets'));
    }

    /**
     * Show the form for creating a new wallet.
     */
    public function create(): View
    {
        abort_unless(auth('admin')->user()?->can('wallets.create'), 403);
        return view('admin.wallets.create');
    }

    /**
     * Store a newly created wallet.
     */
    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('wallets.create'), 403);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'wallet_number' => array_merge(ZambianPhoneRules::required(), [Rule::unique('wallets', 'wallet_number')]),
            'provider' => ['required', 'in:mtn,airtel,zamtel,other'],
            'currency' => ['required', 'string', 'size:3'],
            'opening_balance' => ['required', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $wallet = Wallet::create([
            ...$validated,
            'current_balance' => $validated['opening_balance'],
        ]);

        return redirect()->route('admin.wallets.index')
            ->with('status', 'Wallet created successfully.');
    }

    /**
     * Display the specified wallet.
     */
    public function show(Wallet $wallet): View
    {
        abort_unless(auth('admin')->user()?->can('wallets.view'), 403);
        $wallet->load(['sourceTransactions', 'destinationTransactions', 'repayments', 'loans']);
        return view('admin.wallets.show', compact('wallet'));
    }

    /**
     * Show the form for editing the specified wallet.
     */
    public function edit(Wallet $wallet): View
    {
        abort_unless(auth('admin')->user()?->can('wallets.update'), 403);
        return view('admin.wallets.edit', compact('wallet'));
    }

    /**
     * Update the specified wallet.
     */
    public function update(Request $request, Wallet $wallet): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('wallets.update'), 403);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'wallet_number' => array_merge(ZambianPhoneRules::required(), [Rule::unique('wallets', 'wallet_number')->ignore($wallet->id)]),
            'provider' => ['required', 'in:mtn,airtel,zamtel,other'],
            'currency' => ['required', 'string', 'size:3'],
            'opening_balance' => ['required', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $wallet->update($validated);

        return redirect()->route('admin.wallets.index')
            ->with('status', 'Wallet updated successfully.');
    }

    /**
     * Remove the specified wallet.
     */
    public function destroy(Wallet $wallet): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('wallets.delete'), 403);
        // Check if wallet has transactions
        if ($wallet->sourceTransactions()->count() > 0 || $wallet->destinationTransactions()->count() > 0) {
            return redirect()->back()
                ->with('error', 'Cannot delete wallet with existing transactions.');
        }

        $wallet->delete();

        return redirect()->route('admin.wallets.index')
            ->with('status', 'Wallet deleted successfully.');
    }
}
