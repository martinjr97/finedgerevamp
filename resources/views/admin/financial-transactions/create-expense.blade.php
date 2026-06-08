@extends('layouts.admin')

@section('title', 'Record Expense | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        <div class="space-y-2 text-left">
            <p class="text-xs uppercase tracking-[0.4em] text-cyan-300">Financial Management</p>
            <h1 class="text-3xl font-bold">Record Expense</h1>
        </div>

        <form action="{{ route('admin.financial-transactions.expense.store') }}" method="POST" class="space-y-6">
            @csrf

            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
                <div>
                    <label class="text-sm font-medium text-slate-300">Transaction Date <span class="text-rose-400">*</span></label>
                    <input type="date" name="transaction_date" value="{{ old('transaction_date', now()->toDateString()) }}" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                    @error('transaction_date')
                        <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-300">Category <span class="text-rose-400">*</span></label>
                    <select name="category" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                        <option value="operational" @selected(old('category') === 'operational')>Operational</option>
                        <option value="administrative" @selected(old('category') === 'administrative')>Administrative</option>
                        <option value="marketing" @selected(old('category') === 'marketing')>Marketing</option>
                        <option value="salaries" @selected(old('category') === 'salaries')>Salaries</option>
                        <option value="utilities" @selected(old('category') === 'utilities')>Utilities</option>
                        <option value="rent" @selected(old('category') === 'rent')>Rent</option>
                        <option value="other_expense" @selected(old('category') === 'other_expense')>Other Expense</option>
                    </select>
                    @error('category')
                        <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-300">Description <span class="text-rose-400">*</span></label>
                    <input type="text" name="description" value="{{ old('description') }}" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                    @error('description')
                        <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-300">Amount <span class="text-rose-400">*</span></label>
                    <input type="number" name="amount" value="{{ old('amount') }}" step="0.01" min="0.01" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                    @error('amount')
                        <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-300">Pay From <span class="text-rose-400">*</span></label>
                    <div class="mt-2 space-y-3">
                        <div>
                            <label class="text-sm text-slate-300 mb-2 block">Type</label>
                            <select name="source_type" id="source_type" required class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                                <option value="bank" @selected(old('source_type') === 'bank')>Bank</option>
                                <option value="wallet" @selected(old('source_type') === 'wallet')>Wallet</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-sm text-slate-300 mb-2 block">Account</label>
                            <select name="source_id" id="source_id" required class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                                <option value="">Select Account</option>
                                @foreach($banks as $bank)
                                    <option value="{{ $bank->id }}" data-type="bank" data-balance="{{ $bank->current_balance }}" @selected(old('source_id') == $bank->id)>{{ $bank->name }} - {{ $bank->account_number }} (Balance: {{ number_format($bank->current_balance, 2) }})</option>
                                @endforeach
                                @foreach($wallets as $wallet)
                                    <option value="{{ $wallet->id }}" data-type="wallet" data-balance="{{ $wallet->current_balance }}" @selected(old('source_id') == $wallet->id)>{{ $wallet->name }} - {{ $wallet->wallet_number }} (Balance: {{ number_format($wallet->current_balance, 2) }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    @error('source_type')
                        <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
                    @enderror
                    @error('source_id')
                        <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-300">Reference Number</label>
                    <input type="text" name="reference_number" value="{{ old('reference_number') }}" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                    @error('reference_number')
                        <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-300">Notes</label>
                    <textarea name="notes" rows="3" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">{{ old('notes') }}</textarea>
                    @error('notes')
                        <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-2xl bg-gradient-to-r from-rose-500 to-pink-600 px-6 py-3 font-semibold text-white shadow-lg shadow-rose-500/40 transition hover:scale-[1.01] hover:shadow-xl hover:shadow-rose-500/50">
                    Record Expense
                </button>
                <a href="{{ route('admin.financial-transactions.index') }}" class="rounded-2xl border border-white/10 px-6 py-3 text-sm font-medium text-white/80 hover:bg-white/10 transition">
                    Cancel
                </a>
            </div>
        </form>
    </div>

    <script>
        document.getElementById('source_type').addEventListener('change', function() {
            const type = this.value;
            const select = document.getElementById('source_id');
            const options = select.querySelectorAll('option');
            
            options.forEach(option => {
                if (option.value === '') {
                    option.style.display = 'block';
                } else {
                    const optionType = option.getAttribute('data-type');
                    option.style.display = optionType === type ? 'block' : 'none';
                }
            });
            
            select.value = '';
        });
    </script>
@endsection

