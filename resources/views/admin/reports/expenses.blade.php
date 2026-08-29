@extends('layouts.admin')

@section('title', 'Expenses Report | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Expenses Report',
            'description' => 'Analyse spending by category, subcategory, receiver, and period.',
            'buttons' => [
                [
                    'action' => 'export',
                    'text' => 'Export CSV',
                    'href' => route('admin.reports.expenses.export', request()->query()),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
                ],
            ],
        ])

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4 shadow-lg">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Total Spent</p>
                <p class="mt-2 text-2xl font-bold text-rose-300">ZMW {{ number_format($summary['total_amount'], 2) }}</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4 shadow-lg">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Transactions</p>
                <p class="mt-2 text-2xl font-bold text-white">{{ number_format($summary['transaction_count']) }}</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4 shadow-lg">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Average Expense</p>
                <p class="mt-2 text-2xl font-bold text-cyan-300">ZMW {{ number_format($summary['average_amount'], 2) }}</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4 shadow-lg">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Top Category</p>
                <p class="mt-2 text-lg font-bold text-amber-300">{{ $summary['top_category_name'] ?? '—' }}</p>
                @if ($summary['top_category_name'])
                    <p class="text-sm text-slate-400">ZMW {{ number_format($summary['top_category_amount'], 2) }}</p>
                @endif
            </div>
        </div>

        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <form method="GET" action="{{ route('admin.reports.expenses') }}" class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">From date</label>
                    <input type="date" name="date_from" value="{{ request('date_from') }}" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">To date</label>
                    <input type="date" name="date_to" value="{{ request('date_to') }}" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Category</label>
                    <select name="expense_category_id" id="filter_expense_category" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2">
                        <option value="">All categories</option>
                        @foreach ($expenseCategories as $category)
                            <option value="{{ $category->id }}" @selected((int) request('expense_category_id') === $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Subcategory</label>
                    <select name="expense_subcategory_id" id="filter_expense_subcategory" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2">
                        <option value="">All subcategories</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Employee</label>
                    <select name="employee_id" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2">
                        <option value="">All employees</option>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}" @selected((int) request('employee_id') === $employee->id)>{{ $employee->full_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Receiver name</label>
                    <input type="text" name="receiver_name" value="{{ request('receiver_name') }}" placeholder="Search receiver…" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Description, reference…" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2">
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="rounded-xl bg-cyan-500/20 border border-cyan-500/50 px-4 py-2 text-sm font-semibold text-cyan-200">Apply</button>
                    <a href="{{ route('admin.reports.expenses') }}" class="rounded-xl border border-white/10 px-4 py-2 text-sm text-slate-300">Clear</a>
                </div>
            </form>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                <h2 class="text-lg font-semibold text-white mb-4">Spending by category</h2>
                <div class="admin-data-table">
                    <table class="min-w-full w-full text-sm">
                        <thead>
                            <tr class="text-xs uppercase tracking-wide text-slate-400 text-left border-b border-white/10">
                                <th class="py-2 pr-3">Category</th>
                                <th class="py-2 pr-3 text-right">Count</th>
                                <th class="py-2 pr-3 text-right">Amount</th>
                                <th class="py-2 text-right">Share</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($categoryBreakdown as $row)
                                <tr class="border-b border-white/5 text-slate-200">
                                    <td class="py-3 pr-3 font-medium text-white">{{ $row->category_name }}</td>
                                    <td class="py-3 pr-3 text-right">{{ number_format($row->transaction_count) }}</td>
                                    <td class="py-3 pr-3 text-right text-rose-300">ZMW {{ number_format($row->total_amount, 2) }}</td>
                                    <td class="py-3 text-right">{{ number_format($row->share_percentage, 1) }}%</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="py-6 text-center text-slate-400">No expenses in this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="space-y-6">
                <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                    <h2 class="text-lg font-semibold text-white mb-4">Top subcategories</h2>
                    <ul class="space-y-3">
                        @forelse ($subcategoryBreakdown as $row)
                            <li class="flex items-center justify-between gap-3 text-sm">
                                <span class="text-slate-200">{{ $row->subcategory_name }}</span>
                                <span class="font-semibold text-rose-300">ZMW {{ number_format($row->total_amount, 2) }}</span>
                            </li>
                        @empty
                            <li class="text-slate-400 text-sm">No subcategory data.</li>
                        @endforelse
                    </ul>
                </div>

                <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                    <h2 class="text-lg font-semibold text-white mb-4">Top receivers</h2>
                    <ul class="space-y-3">
                        @forelse ($topReceivers as $row)
                            <li class="flex items-center justify-between gap-3 text-sm">
                                <span class="text-slate-200">{{ $row->receiver_name }}</span>
                                <span class="font-semibold text-amber-300">ZMW {{ number_format($row->total_amount, 2) }}</span>
                            </li>
                        @empty
                            <li class="text-slate-400 text-sm">No receiver names recorded yet.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>

        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <h2 class="text-lg font-semibold text-white mb-4">Largest individual expenses</h2>
            <div class="admin-data-table">
                <table class="min-w-full w-full text-sm">
                    <thead>
                        <tr class="text-xs uppercase tracking-wide text-slate-400 text-left border-b border-white/10">
                            <th class="py-2 pr-3">Date</th>
                            <th class="py-2 pr-3">Description</th>
                            <th class="py-2 pr-3">Category</th>
                            <th class="py-2 pr-3">Receiver</th>
                            <th class="py-2 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($topExpenses as $expense)
                            <tr class="border-b border-white/5 text-slate-200">
                                <td class="py-3 pr-3 whitespace-nowrap">{{ $expense->transaction_date->format('d M Y') }}</td>
                                <td class="py-3 pr-3">
                                    <a href="{{ route('admin.financial-transactions.show', $expense) }}" class="text-cyan-300 hover:text-cyan-200">{{ $expense->description }}</a>
                                </td>
                                <td class="py-3 pr-3">{{ $expense->expenseCategory?->name ?? '—' }}</td>
                                <td class="py-3 pr-3">{{ $expense->receiver_name ?? ($expense->employee?->full_name ?? '—') }}</td>
                                <td class="py-3 text-right font-semibold text-rose-300">ZMW {{ number_format($expense->amount, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-6 text-center text-slate-400">No expenses found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-3xl border border-white/10 bg-white/5 shadow-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-white/10">
                <h2 class="text-lg font-semibold text-white">Expense ledger</h2>
            </div>
            <div class="admin-data-table">
                <table class="min-w-full w-full text-sm">
                    <thead>
                        <tr class="text-xs uppercase tracking-wide text-slate-400 text-left border-b border-white/10">
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Transaction #</th>
                            <th class="px-4 py-3">Category</th>
                            <th class="px-4 py-3">Description</th>
                            <th class="px-4 py-3">Receiver</th>
                            <th class="px-4 py-3">Source</th>
                            <th class="px-4 py-3 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($expenses as $expense)
                            <tr class="border-b border-white/5 text-slate-200">
                                <td class="px-4 py-3 whitespace-nowrap">{{ $expense->transaction_date->format('d M Y') }}</td>
                                <td class="px-4 py-3 font-mono text-xs">
                                    <a href="{{ route('admin.financial-transactions.show', $expense) }}" class="text-cyan-300">{{ $expense->transaction_number }}</a>
                                </td>
                                <td class="px-4 py-3">{{ $expense->expenseCategory?->name ?? '—' }}</td>
                                <td class="px-4 py-3">{{ $expense->description }}</td>
                                <td class="px-4 py-3">{{ $expense->receiver_name ?? ($expense->employee?->full_name ?? '—') }}</td>
                                <td class="px-4 py-3">{{ $expense->sourceBank?->name ?? $expense->sourceWallet?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-rose-300">ZMW {{ number_format($expense->amount, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">No expenses match the selected filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($expenses->hasPages())
                <div class="px-6 py-4 border-t border-white/10">{{ $expenses->links() }}</div>
            @endif
        </div>
    </div>

    <script>
        const filterSubcategories = @json($expenseCategories->mapWithKeys(fn ($category) => [
            $category->id => $category->subcategories->map(fn ($sub) => ['id' => $sub->id, 'name' => $sub->name])->values(),
        ]));
        const selectedCategoryId = @json((int) request('expense_category_id'));
        const selectedSubcategoryId = @json((int) request('expense_subcategory_id'));

        function refreshFilterSubcategories() {
            const categoryId = document.getElementById('filter_expense_category').value;
            const select = document.getElementById('filter_expense_subcategory');
            const options = filterSubcategories[categoryId] || [];

            select.innerHTML = '<option value="">All subcategories</option>';
            options.forEach((subcategory) => {
                const option = document.createElement('option');
                option.value = subcategory.id;
                option.textContent = subcategory.name;
                if (String(selectedSubcategoryId) === String(subcategory.id)) {
                    option.selected = true;
                }
                select.appendChild(option);
            });
        }

        document.getElementById('filter_expense_category').addEventListener('change', refreshFilterSubcategories);
        if (selectedCategoryId) {
            document.getElementById('filter_expense_category').value = String(selectedCategoryId);
        }
        refreshFilterSubcategories();
    </script>
@endsection
