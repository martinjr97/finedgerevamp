@extends('layouts.admin')

@section('title', 'Record Income | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Record Income',
            'description' => 'Record treasury income and deposit into a bank or wallet account.',
            'buttons' => [[
                'action' => 'back',
                'text' => 'Back',
                'href' => route('admin.financial-transactions.index'),
            ]],
        ])

        <form action="{{ route('admin.financial-transactions.income.store') }}" method="POST" class="space-y-6">
            @csrf

            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-6">
                <div>
                    <h2 class="text-sm font-semibold uppercase tracking-[0.25em] text-slate-400">Income details</h2>
                    <div class="mt-4 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                        <div>
                            <label class="text-sm font-medium text-slate-300">Transaction Date <span class="text-rose-400">*</span></label>
                            <input type="date" name="transaction_date" value="{{ old('transaction_date', now()->toDateString()) }}" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                            @error('transaction_date')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-300">Category <span class="text-rose-400">*</span></label>
                            <select name="category" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                                @foreach ($incomeCategories as $category)
                                    <option value="{{ $category->code }}" @selected(old('category') === $category->code)>{{ $category->name }}</option>
                                @endforeach
                            </select>
                            @error('category')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-300">Amount <span class="text-rose-400">*</span></label>
                            <input type="number" name="amount" value="{{ old('amount') }}" step="0.01" min="0.01" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                            @error('amount')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
                        </div>

                        <div class="md:col-span-2 xl:col-span-3">
                            <label class="text-sm font-medium text-slate-300">Description <span class="text-rose-400">*</span></label>
                            <input type="text" name="description" value="{{ old('description') }}" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                            @error('description')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>

                <div>
                    <h2 class="text-sm font-semibold uppercase tracking-[0.25em] text-slate-400">Deposit destination</h2>
                    <div class="mt-4 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                        <div>
                            <label class="text-sm font-medium text-slate-300">Account type <span class="text-rose-400">*</span></label>
                            <select name="destination_type" id="destination_type" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                                <option value="bank" @selected(old('destination_type') === 'bank')>Bank</option>
                                <option value="wallet" @selected(old('destination_type') === 'wallet')>Wallet</option>
                            </select>
                            @error('destination_type')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
                        </div>

                        <div class="md:col-span-2 xl:col-span-2">
                            <label class="text-sm font-medium text-slate-300">Account <span class="text-rose-400">*</span></label>
                            <select name="destination_id" id="destination_id" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                                <option value="">Select account</option>
                                @foreach($banks as $bank)
                                    <option value="{{ $bank->id }}" data-type="bank" @selected(old('destination_id') == $bank->id)>{{ $bank->name }} - {{ $bank->account_number }}</option>
                                @endforeach
                                @foreach($wallets as $wallet)
                                    <option value="{{ $wallet->id }}" data-type="wallet" @selected(old('destination_id') == $wallet->id)>{{ $wallet->name }} - {{ $wallet->wallet_number }}</option>
                                @endforeach
                            </select>
                            @error('destination_id')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-300">Reference number</label>
                            <input type="text" name="reference_number" value="{{ old('reference_number') }}" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                            @error('reference_number')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
                        </div>

                        <div class="md:col-span-2 xl:col-span-3">
                            <label class="text-sm font-medium text-slate-300">Notes</label>
                            <textarea name="notes" rows="3" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">{{ old('notes') }}</textarea>
                            @error('notes')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-2xl bg-gradient-to-r from-emerald-500 to-lime-600 px-6 py-3 font-semibold text-white shadow-lg shadow-emerald-500/40 transition hover:scale-[1.01]">
                    Record Income
                </button>
                <a href="{{ route('admin.financial-transactions.index') }}" class="rounded-2xl border border-white/10 px-6 py-3 text-sm font-medium text-white/80 hover:bg-white/10 transition">
                    Cancel
                </a>
            </div>
        </form>
    </div>

    <script>
        document.getElementById('destination_type').addEventListener('change', function() {
            const type = this.value;
            const select = document.getElementById('destination_id');
            select.querySelectorAll('option').forEach(option => {
                if (option.value === '') {
                    option.hidden = false;
                    return;
                }
                option.hidden = option.getAttribute('data-type') !== type;
            });
            select.value = '';
        });
        document.getElementById('destination_type').dispatchEvent(new Event('change'));
    </script>
@endsection
