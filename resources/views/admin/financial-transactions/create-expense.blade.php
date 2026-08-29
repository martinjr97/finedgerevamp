@extends('layouts.admin')

@section('title', 'Record Expense | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Record Expense',
            'description' => 'Capture expense details, payee information, and payment source.',
            'buttons' => [[
                'action' => 'back',
                'text' => 'Back',
                'href' => route('admin.financial-transactions.index'),
            ]],
        ])

        <form action="{{ route('admin.financial-transactions.expense.store') }}" method="POST" class="space-y-6">
            @csrf

            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-6">
                <div>
                    <h2 class="text-sm font-semibold uppercase tracking-[0.25em] text-slate-400">Expense details</h2>
                    <div class="mt-4 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                        <div>
                            <label class="text-sm font-medium text-slate-300">Transaction Date <span class="text-rose-400">*</span></label>
                            <input type="date" name="transaction_date" value="{{ old('transaction_date', now()->toDateString()) }}" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                            @error('transaction_date')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-300">Category <span class="text-rose-400">*</span></label>
                            <select name="category" id="expense_category" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                                @foreach ($expenseCategories as $category)
                                    <option value="{{ $category->code }}" @selected(old('category') === $category->code)>{{ $category->name }}</option>
                                @endforeach
                            </select>
                            @error('category')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-300">Subcategory</label>
                            <select name="expense_subcategory_id" id="expense_subcategory_id" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                                <option value="">Unclassified</option>
                            </select>
                            @error('expense_subcategory_id')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-300">Amount <span class="text-rose-400">*</span></label>
                            <input type="number" name="amount" value="{{ old('amount') }}" step="0.01" min="0.01" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                            @error('amount')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
                        </div>

                        <div class="md:col-span-2 xl:col-span-4">
                            <label class="text-sm font-medium text-slate-300">Description <span class="text-rose-400">*</span></label>
                            <input type="text" name="description" value="{{ old('description') }}" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                            @error('description')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>

                <div>
                    <h2 class="text-sm font-semibold uppercase tracking-[0.25em] text-slate-400">Payee</h2>
                    <div class="mt-4 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                        <div>
                            <label class="text-sm font-medium text-slate-300">Employee (optional)</label>
                            <select name="employee_id" id="employee_id" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                                <option value="">Not an employee</option>
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}" data-name="{{ $employee->full_name }}" @selected(old('employee_id') == $employee->id)>{{ $employee->full_name }}</option>
                                @endforeach
                            </select>
                            @error('employee_id')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
                        </div>

                        <div class="md:col-span-2 xl:col-span-3">
                            <label class="text-sm font-medium text-slate-300">Receiver name</label>
                            <input type="text" name="receiver_name" id="receiver_name" value="{{ old('receiver_name') }}" placeholder="Person or organisation paid" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                            <p class="mt-1 text-xs text-slate-500">Select an employee to pre-fill, or enter any payee name manually.</p>
                            @error('receiver_name')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>

                <div>
                    <h2 class="text-sm font-semibold uppercase tracking-[0.25em] text-slate-400">Payment source</h2>
                    <div class="mt-4 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                        <div>
                            <label class="text-sm font-medium text-slate-300">Pay from type <span class="text-rose-400">*</span></label>
                            <select name="source_type" id="source_type" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                                <option value="bank" @selected(old('source_type') === 'bank')>Bank</option>
                                <option value="wallet" @selected(old('source_type') === 'wallet')>Wallet</option>
                            </select>
                            @error('source_type')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
                        </div>

                        <div class="md:col-span-2 xl:col-span-3">
                            <label class="text-sm font-medium text-slate-300">Account <span class="text-rose-400">*</span></label>
                            <select name="source_id" id="source_id" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                                <option value="">Select account</option>
                                @foreach($banks as $bank)
                                    <option value="{{ $bank->id }}" data-type="bank" @selected(old('source_id') == $bank->id)>{{ $bank->name }} - {{ $bank->account_reference }} (Balance: {{ number_format($bank->current_balance, 2) }})</option>
                                @endforeach
                                @foreach($wallets as $wallet)
                                    <option value="{{ $wallet->id }}" data-type="wallet" @selected(old('source_id') == $wallet->id)>{{ $wallet->name }} - {{ $wallet->wallet_number }} (Balance: {{ number_format($wallet->current_balance, 2) }})</option>
                                @endforeach
                            </select>
                            @error('source_id')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
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
                <button type="submit" class="rounded-2xl bg-gradient-to-r from-rose-500 to-pink-600 px-6 py-3 font-semibold text-white shadow-lg shadow-rose-500/40 transition hover:scale-[1.01]">
                    Record Expense
                </button>
                <a href="{{ route('admin.financial-transactions.index') }}" class="rounded-2xl border border-white/10 px-6 py-3 text-sm font-medium text-white/80 hover:bg-white/10 transition">
                    Cancel
                </a>
            </div>
        </form>
    </div>

    <script>
        const expenseSubcategories = @json($expenseCategories->mapWithKeys(fn ($category) => [
            $category->code => $category->subcategories->map(fn ($sub) => ['id' => $sub->id, 'name' => $sub->name])->values(),
        ]));

        function refreshExpenseSubcategories() {
            const categoryCode = document.getElementById('expense_category').value;
            const select = document.getElementById('expense_subcategory_id');
            const options = expenseSubcategories[categoryCode] || [];
            const selected = @json(old('expense_subcategory_id'));

            select.innerHTML = '<option value="">Unclassified</option>';
            options.forEach((subcategory) => {
                const option = document.createElement('option');
                option.value = subcategory.id;
                option.textContent = subcategory.name;
                if (String(selected) === String(subcategory.id)) {
                    option.selected = true;
                }
                select.appendChild(option);
            });
        }

        document.getElementById('expense_category').addEventListener('change', refreshExpenseSubcategories);
        refreshExpenseSubcategories();

        document.getElementById('employee_id').addEventListener('change', function () {
            const option = this.options[this.selectedIndex];
            const receiverInput = document.getElementById('receiver_name');
            if (option.value && option.dataset.name && !receiverInput.value) {
                receiverInput.value = option.dataset.name;
            }
        });

        document.getElementById('source_type').addEventListener('change', function() {
            const type = this.value;
            const select = document.getElementById('source_id');
            select.querySelectorAll('option').forEach(option => {
                if (option.value === '') {
                    option.hidden = false;
                    return;
                }
                option.hidden = option.getAttribute('data-type') !== type;
            });
            select.value = '';
        });

        document.getElementById('source_type').dispatchEvent(new Event('change'));
    </script>
@endsection
