@extends('layouts.admin')

@section('title', 'Record Income | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        <div class="space-y-2 text-left">
            <p class="text-xs uppercase tracking-[0.4em] text-cyan-300">Financial Management</p>
            <h1 class="text-3xl font-bold">Record Income</h1>
        </div>

        <form action="{{ route('admin.financial-transactions.income.store') }}" method="POST" class="space-y-6">
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
                        <option value="loan_interest" @selected(old('category') === 'loan_interest')>Loan Interest</option>
                        <option value="loan_processing_fee" @selected(old('category') === 'loan_processing_fee')>Loan Processing Fee</option>
                        <option value="shareholder_contribution" @selected(old('category') === 'shareholder_contribution')>Shareholder Contribution</option>
                        <option value="investment_income" @selected(old('category') === 'investment_income')>Investment Income</option>
                        <option value="donation" @selected(old('category') === 'donation')>Donation</option>
                        <option value="grant" @selected(old('category') === 'grant')>Grant</option>
                        <option value="other_income" @selected(old('category') === 'other_income')>Other Income</option>
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
                    <label class="text-sm font-medium text-slate-300">Receive Into <span class="text-rose-400">*</span></label>
                    <div class="mt-2 space-y-3">
                        <div>
                            <label class="text-sm text-slate-300 mb-2 block">Type</label>
                            <select name="destination_type" id="destination_type" required class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                                <option value="bank" @selected(old('destination_type') === 'bank')>Bank</option>
                                <option value="wallet" @selected(old('destination_type') === 'wallet')>Wallet</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-sm text-slate-300 mb-2 block">Account</label>
                            <select name="destination_id" id="destination_id" required class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                                <option value="">Select Account</option>
                                @foreach($banks as $bank)
                                    <option value="{{ $bank->id }}" data-type="bank" @selected(old('destination_id') == $bank->id)>{{ $bank->name }} - {{ $bank->account_number }}</option>
                                @endforeach
                                @foreach($wallets as $wallet)
                                    <option value="{{ $wallet->id }}" data-type="wallet" @selected(old('destination_id') == $wallet->id)>{{ $wallet->name }} - {{ $wallet->wallet_number }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    @error('destination_type')
                        <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
                    @enderror
                    @error('destination_id')
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
                <button type="submit" class="rounded-2xl bg-gradient-to-r from-emerald-500 to-lime-600 px-6 py-3 font-semibold text-white shadow-lg shadow-emerald-500/40 transition hover:scale-[1.01] hover:shadow-xl hover:shadow-emerald-500/50">
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

