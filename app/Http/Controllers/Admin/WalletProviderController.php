<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WalletProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class WalletProviderController extends Controller
{
    public function index(): View
    {
        abort_unless(auth('admin')->user()?->can('wallet-providers.view'), 403);

        $providers = WalletProvider::query()
            ->orderBy('name')
            ->get();

        return view('admin.wallet-providers.index', compact('providers'));
    }

    public function create(): View
    {
        abort_unless(auth('admin')->user()?->can('wallet-providers.create'), 403);

        return view('admin.wallet-providers.create');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('wallet-providers.create'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:wallet_providers,name'],
            'code' => ['nullable', 'string', 'max:50', 'unique:wallet_providers,code'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['name'] = Str::upper((string) $data['name']);
        $data['code'] = filled($data['code'] ?? null) ? Str::upper((string) $data['code']) : null;
        $data['is_active'] = $request->boolean('is_active', true);

        try {
            $provider = WalletProvider::create($data);

            return redirect()
                ->route('admin.wallet-providers.edit', $provider)
                ->with('status', 'Wallet provider created successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.wallet-providers.create')
                ->withInput()
                ->with('error', 'Failed to create wallet provider: '.$e->getMessage());
        }
    }

    public function edit(WalletProvider $walletProvider): View
    {
        abort_unless(auth('admin')->user()?->can('wallet-providers.update'), 403);

        return view('admin.wallet-providers.edit', [
            'provider' => $walletProvider,
        ]);
    }

    public function update(Request $request, WalletProvider $walletProvider): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('wallet-providers.update'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:wallet_providers,name,'.$walletProvider->id],
            'code' => ['nullable', 'string', 'max:50', 'unique:wallet_providers,code,'.$walletProvider->id],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['name'] = Str::upper((string) $data['name']);
        $data['code'] = filled($data['code'] ?? null) ? Str::upper((string) $data['code']) : null;
        $data['is_active'] = $request->boolean('is_active', true);

        try {
            $walletProvider->update($data);

            return redirect()
                ->route('admin.wallet-providers.edit', $walletProvider)
                ->with('status', 'Wallet provider updated successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.wallet-providers.edit', $walletProvider)
                ->withInput()
                ->with('error', 'Failed to update wallet provider: '.$e->getMessage());
        }
    }

    public function destroy(WalletProvider $walletProvider): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('wallet-providers.delete'), 403);

        try {
            $walletProvider->delete();

            return redirect()
                ->route('admin.wallet-providers.index')
                ->with('status', 'Wallet provider deleted successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.wallet-providers.index')
                ->with('error', 'Failed to delete wallet provider: '.$e->getMessage());
        }
    }
}

