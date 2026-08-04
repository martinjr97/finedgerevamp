@extends('layouts.admin')

@section('title', 'Financial Transactions | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Financial Transactions',
            'buttons' => [
                [
                    'action' => 'create',
                    'text' => 'Record Income',
                    'href' => route('admin.financial-transactions.income.create'),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>',
                    'can' => auth('admin')->user()?->can('financial-transactions.create')
                ],
                [
                    'action' => 'create',
                    'text' => 'Record Expense',
                    'href' => route('admin.financial-transactions.expense.create'),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/></svg>',
                    'can' => auth('admin')->user()?->can('financial-transactions.create')
                ]
            ]
        ])

        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <form method="GET" action="{{ route('admin.financial-transactions.index') }}" class="mb-6 space-y-4">
                <!-- First Row: Type and Category -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm text-slate-300 mb-2 block">Type</label>
                        <select name="type" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                            <option value="">All</option>
                            <option value="income" @selected(request('type') === 'income')>Income</option>
                            <option value="expense" @selected(request('type') === 'expense')>Expense</option>
                            <option value="transfer" @selected(request('type') === 'transfer')>Transfer</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm text-slate-300 mb-2 block">Category</label>
                        <select name="category" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                            <option value="">All</option>
                            <optgroup label="Income">
                                <option value="loan_interest" @selected(request('category') === 'loan_interest')>Loan Interest</option>
                                <option value="loan_processing_fee" @selected(request('category') === 'loan_processing_fee')>Loan Processing Fee</option>
                                <option value="shareholder_contribution" @selected(request('category') === 'shareholder_contribution')>Shareholder Contribution</option>
                                <option value="investment_income" @selected(request('category') === 'investment_income')>Investment Income</option>
                                <option value="donation" @selected(request('category') === 'donation')>Donation</option>
                                <option value="grant" @selected(request('category') === 'grant')>Grant</option>
                                <option value="other_income" @selected(request('category') === 'other_income')>Other Income</option>
                            </optgroup>
                            <optgroup label="Expenses">
                                <option value="operational" @selected(request('category') === 'operational')>Operational</option>
                                <option value="administrative" @selected(request('category') === 'administrative')>Administrative</option>
                                <option value="marketing" @selected(request('category') === 'marketing')>Marketing</option>
                                <option value="salaries" @selected(request('category') === 'salaries')>Salaries</option>
                                <option value="utilities" @selected(request('category') === 'utilities')>Utilities</option>
                                <option value="rent" @selected(request('category') === 'rent')>Rent</option>
                                <option value="other_expense" @selected(request('category') === 'other_expense')>Other Expense</option>
                            </optgroup>
                        </select>
                    </div>
                </div>
                
                <!-- Second Row: Dates and Actions -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="text-sm text-slate-300 mb-2 block">From Date</label>
                        <input type="date" name="date_from" value="{{ request('date_from') }}" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                    </div>
                    <div>
                        <label class="text-sm text-slate-300 mb-2 block">To Date</label>
                        <input type="date" name="date_to" value="{{ request('date_to') }}" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="submit" class="flex-1 rounded-xl bg-cyan-500/20 border border-cyan-500/50 px-3 py-1.5 text-xs font-medium text-cyan-300 hover:bg-cyan-500/30 transition">
                            Filter
                        </button>
                        <a href="{{ route('admin.financial-transactions.index') }}" class="rounded-xl border border-white/10 px-3 py-1.5 text-xs font-medium text-white/80 hover:bg-white/10 transition">
                            Clear
                        </a>
                    </div>
                </div>
            </form>

            <div class="admin-data-table">
            <div class="admin-data-table__scroll">
                <table class="min-w-full w-full">
                    <thead>
                        <tr class="font-semibold uppercase text-white/80 text-center">
                            <th scope="col">Date</th>
                            <th scope="col">Transaction #</th>
                            <th scope="col">Type</th>
                            <th scope="col">Category</th>
                            <th scope="col">Description</th>
                            <th scope="col">Source</th>
                            <th scope="col">Destination</th>
                            <th scope="col">Amount</th>
                            <th scope="col" class="admin-data-table__actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($transactions as $transaction)
                            <tr class="text-center">
                                <td>{{ $transaction->transaction_date->format('M d, Y') }}</td>
                                <td class="font-mono text-sm">{{ $transaction->transaction_number }}</td>
                                <td>
                                    <span class="text-sm font-medium {{ $transaction->type === 'income' ? 'text-emerald-400' : ($transaction->type === 'expense' ? 'text-rose-400' : 'text-blue-400') }}">
                                        {{ ucfirst($transaction->type) }}
                                    </span>
                                </td>
                                <td class="capitalize">{{ str_replace('_', ' ', $transaction->category ?? '—') }}</td>
                                <td class="text-left">{{ $transaction->description }}</td>
                                <td>
                                    @if($transaction->source)
                                        {{ $transaction->source->name ?? '—' }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    @if($transaction->destination)
                                        {{ $transaction->destination->name ?? '—' }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="font-semibold {{ $transaction->type === 'income' ? 'text-emerald-400' : ($transaction->type === 'expense' ? 'text-rose-400' : 'text-blue-400') }}">
                                    {{ $transaction->type === 'expense' ? '-' : ($transaction->type === 'transfer' ? '±' : '+') }}{{ number_format($transaction->amount, 2) }}
                                </td>
                                <td>
                                    <div class="inline-flex items-center gap-3">
                                        @can('financial-transactions.view')
                                        <a href="{{ route('admin.financial-transactions.show', $transaction) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-blue-400/50 bg-blue-500/10 px-2.5 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-500/20 transition">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                            View
                                        </a>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="py-8 text-center text-slate-400">No transactions found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="admin-table-footer">
                {{ $transactions->withQueryString()->links() }}
            </div>
            </div>
        </div>
    </div>
@endsection

