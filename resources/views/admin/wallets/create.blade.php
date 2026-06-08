@extends('layouts.admin')

@section('title', 'Add Wallet | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        <div class="space-y-2 text-left">
            <p class="text-xs uppercase tracking-[0.4em] text-cyan-300">Financial Management</p>
            <h1 class="text-3xl font-bold">Add Wallet</h1>
        </div>

        <form action="{{ route('admin.wallets.store') }}" method="POST" class="space-y-6">
            @csrf

            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
                <div>
                    <label class="text-sm font-medium text-slate-300">Name <span class="text-rose-400">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                    @error('name')
                        <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-300">Wallet Number <span class="text-rose-400">*</span></label>
                    <input type="text" name="wallet_number" value="{{ old('wallet_number') }}" required placeholder="e.g., MTN-26000936165" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                    @error('wallet_number')
                        <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-medium text-slate-300">Provider <span class="text-rose-400">*</span></label>
                        <select name="provider" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                            <option value="mtn" @selected(old('provider') === 'mtn')>MTN</option>
                            <option value="airtel" @selected(old('provider') === 'airtel')>Airtel</option>
                            <option value="zamtel" @selected(old('provider') === 'zamtel')>Zamtel</option>
                            <option value="other" @selected(old('provider') === 'other')>Other</option>
                        </select>
                        @error('provider')
                            <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="text-sm font-medium text-slate-300">Currency <span class="text-rose-400">*</span></label>
                        <select name="currency" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                            <option value="ZMW" @selected(old('currency', 'ZMW') === 'ZMW')>ZMW</option>
                            <option value="USD" @selected(old('currency') === 'USD')>USD</option>
                            <option value="EUR" @selected(old('currency') === 'EUR')>EUR</option>
                        </select>
                        @error('currency')
                            <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-300">Opening Balance <span class="text-rose-400">*</span></label>
                    <input type="number" name="opening_balance" value="{{ old('opening_balance', 0) }}" step="0.01" min="0" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                    @error('opening_balance')
                        <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="inline-flex items-center gap-2 text-sm text-slate-300">
                        <input type="checkbox" name="is_active" value="1" class="rounded border-white/20 bg-white/10 text-cyan-400 focus:ring-cyan-500/30" @checked(old('is_active', true))>
                        Active
                    </label>
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-300">Notes</label>
                    <textarea name="notes" rows="3" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">{{ old('notes') }}</textarea>
                    @error('notes')
                        <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('admin.wallets.index') }}" class="inline-flex items-center gap-2 rounded-2xl border border-white/20 bg-white/5 px-4 py-3 text-base font-medium text-slate-300 hover:bg-white/10 hover:border-white/30 transition">
                    Cancel
                </a>
                <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-blue-500 to-blue-600 px-4 py-3 text-base font-semibold text-white shadow-lg shadow-blue-500/30 hover:from-blue-600 hover:to-blue-700 transition">
                    Create Wallet
                </button>
            </div>
        </form>
    </div>
@endsection

