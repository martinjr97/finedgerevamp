@extends('layouts.admin')

@section('title', 'Creditors Report | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Creditors Report',
            'description' => 'Outstanding creditor balances, payment activity in a date range, and payment detail.',
            'buttons' => [
                [
                    'action' => 'export',
                    'text' => 'Export Excel',
                    'href' => route('admin.reports.creditors.export', request()->query()),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
                ],
                [
                    'action' => 'back',
                    'text' => 'All Reports',
                    'href' => route('admin.reports.index'),
                ],
            ],
        ])

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4 shadow-lg">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Creditors</p>
                <p class="mt-2 text-2xl font-bold text-white">{{ number_format($summary['creditor_count']) }}</p>
                <p class="text-xs text-slate-500 mt-1">{{ number_format($summary['creditors_with_balance']) }} with balance</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4 shadow-lg">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Total outstanding</p>
                <p class="mt-2 text-2xl font-bold text-amber-300">ZMW {{ number_format($summary['total_outstanding'], 2) }}</p>
                <p class="text-xs text-slate-500 mt-1">Current liability snapshot</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4 shadow-lg xl:col-span-1">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Paid in period</p>
                <p class="mt-2 text-2xl font-bold text-emerald-300">ZMW {{ number_format($summary['total_paid_in_period'], 2) }}</p>
                <p class="text-xs text-slate-500 mt-1">{{ \Carbon\Carbon::parse($filters['date_from'])->format('M j, Y') }} – {{ \Carbon\Carbon::parse($filters['date_to'])->format('M j, Y') }}</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4 shadow-lg">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Payments in period</p>
                <p class="mt-2 text-2xl font-bold text-cyan-300">{{ number_format($summary['payment_count_in_period']) }}</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4 shadow-lg">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Manage creditors</p>
                <a href="{{ route('admin.creditors.index') }}" class="mt-2 inline-flex text-sm font-semibold text-cyan-300 hover:text-cyan-200">Open creditor list →</a>
            </div>
        </div>

        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <form method="GET" action="{{ route('admin.reports.creditors') }}" class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Period from</label>
                    <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Period to</label>
                    <input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Creditor status</label>
                    <select name="status" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2">
                        <option value="active" @selected($filters['status'] === 'active')>Active only</option>
                        <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive only</option>
                        <option value="all" @selected($filters['status'] === 'all')>All</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Search creditor</label>
                    <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Name or description…" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2">
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="rounded-xl bg-cyan-500/20 border border-cyan-500/50 px-4 py-2 text-sm font-semibold text-cyan-200">Apply</button>
                    <a href="{{ route('admin.reports.creditors') }}" class="rounded-xl border border-white/10 px-4 py-2 text-sm text-slate-300">Reset</a>
                </div>
            </form>
        </div>

        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
            <h2 class="text-lg font-semibold text-white">Creditor balances</h2>
            <div class="admin-data-table">
                <table class="min-w-full w-full text-sm" data-datatable="true" data-datatable-per-page="15" data-datatable-search-placeholder="Search table…">
                    <thead>
                        <tr class="text-xs uppercase tracking-wide text-slate-400 text-left border-b border-white/10">
                            <th class="py-2 pr-3">Creditor</th>
                            <th class="py-2 pr-3">Status</th>
                            <th class="py-2 pr-3 text-right">Outstanding</th>
                            <th class="py-2 pr-3 text-right">Paid in period</th>
                            <th class="py-2 pr-3 text-right">Payments</th>
                            <th class="py-2 pr-3">Due date</th>
                            <th class="py-2 pr-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($creditors as $creditor)
                            <tr class="border-b border-white/5">
                                <td class="py-3 pr-3 font-medium text-white">{{ $creditor->name }}</td>
                                <td class="py-3 pr-3">
                                    <span class="{{ $creditor->is_active ? 'text-emerald-400' : 'text-rose-400' }}">{{ $creditor->is_active ? 'Active' : 'Inactive' }}</span>
                                </td>
                                <td class="py-3 pr-3 text-right font-semibold text-amber-200">ZMW {{ number_format($creditor->outstanding, 2) }}</td>
                                <td class="py-3 pr-3 text-right text-emerald-200">ZMW {{ number_format($creditor->paid_in_period, 2) }}</td>
                                <td class="py-3 pr-3 text-right text-slate-300">{{ number_format($creditor->payment_count_in_period) }}</td>
                                <td class="py-3 pr-3 text-slate-300">{{ $creditor->due_date?->format('M d, Y') ?? '—' }}</td>
                                <td class="py-3 pr-3">
                                    @can('creditors.view')
                                        <a href="{{ route('admin.creditors.show', $creditor->id) }}" class="text-cyan-300 hover:text-cyan-200 text-xs font-semibold">View</a>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-8 text-center text-slate-400">No creditors match your filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
            <h2 class="text-lg font-semibold text-white">Payments in period</h2>
            <div class="admin-data-table overflow-x-auto">
                <table class="min-w-full w-full text-sm">
                    <thead>
                        <tr class="text-xs uppercase tracking-wide text-slate-400 text-left border-b border-white/10">
                            <th class="py-2 pr-3">Date</th>
                            <th class="py-2 pr-3">Transaction</th>
                            <th class="py-2 pr-3">Creditor</th>
                            <th class="py-2 pr-3 text-right">Amount</th>
                            <th class="py-2 pr-3">Description</th>
                            <th class="py-2 pr-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($payments as $payment)
                            <tr class="border-b border-white/5">
                                <td class="py-3 pr-3 text-slate-300">{{ $payment->transaction_date->format('M d, Y') }}</td>
                                <td class="py-3 pr-3 font-mono text-xs text-slate-400">{{ $payment->transaction_number }}</td>
                                <td class="py-3 pr-3 text-white">{{ $payment->creditor?->name ?? '—' }}</td>
                                <td class="py-3 pr-3 text-right font-semibold text-rose-200">ZMW {{ number_format($payment->amount, 2) }}</td>
                                <td class="py-3 pr-3 text-slate-300">{{ Str::limit($payment->description, 60) }}</td>
                                <td class="py-3 pr-3">
                                    @can('financial-transactions.view')
                                        <a href="{{ route('admin.financial-transactions.show', $payment) }}" class="text-cyan-300 hover:text-cyan-200 text-xs font-semibold">View</a>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-8 text-center text-slate-400">No creditor payments in this period.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($payments->hasPages())
                <div class="pt-4">{{ $payments->links() }}</div>
            @endif
        </div>
    </div>
@endsection
