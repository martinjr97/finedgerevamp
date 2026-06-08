@extends('layouts.admin')

@section('title', 'Payment Due Report Results | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        <div class="space-y-2 text-left">
            <p class="text-xs uppercase tracking-[0.4em] text-cyan-300">Payment Reports</p>
            <h1 class="text-3xl font-bold">Payment Due Report - {{ $company->name }}</h1>
            <p class="text-sm text-slate-400">
                Month: <span class="font-semibold text-white">{{ $selectedMonth->format('F Y') }}</span> | 
                Due Date: <span class="font-semibold text-white">{{ $dueDate->format('d M Y') }}</span> | 
                Pay Day: <span class="font-semibold text-white">{{ $company->pay_day }}</span>
            </p>
        </div>

        <!-- Summary Cards -->
        <div class="flex flex-row gap-4 overflow-x-auto">
            <div class="flex-1 min-w-0 rounded-3xl border border-white/10 bg-gradient-to-br from-blue-500/20 to-blue-600/10 p-4 lg:p-6 shadow-lg flex-shrink-0">
                <p class="text-xs uppercase tracking-wide text-slate-400 mb-2">Total Customers</p>
                <p class="text-2xl lg:text-3xl font-bold text-white">{{ $totalCustomers }}</p>
            </div>
            <div class="flex-1 min-w-0 rounded-3xl border border-white/10 bg-gradient-to-br from-purple-500/20 to-purple-600/10 p-4 lg:p-6 shadow-lg flex-shrink-0">
                <p class="text-xs uppercase tracking-wide text-slate-400 mb-2">Total Loans</p>
                <p class="text-2xl lg:text-3xl font-bold text-white">{{ $totalLoans }}</p>
            </div>
            <div class="flex-1 min-w-0 rounded-3xl border border-white/10 bg-gradient-to-br from-emerald-500/20 to-emerald-600/10 p-4 lg:p-6 shadow-lg flex-shrink-0">
                <p class="text-xs uppercase tracking-wide text-slate-400 mb-2">Total Expected</p>
                <p class="text-2xl lg:text-3xl font-bold text-white">{{ number_format($totalExpected, 2) }}</p>
            </div>
            <div class="flex-1 min-w-0 rounded-3xl border border-white/10 bg-gradient-to-br from-amber-500/20 to-amber-600/10 p-4 lg:p-6 shadow-lg flex-shrink-0">
                <p class="text-xs uppercase tracking-wide text-slate-400 mb-2">Total Remaining</p>
                <p class="text-2xl lg:text-3xl font-bold text-white">{{ number_format($totalRemaining, 2) }}</p>
            </div>
        </div>

        <!-- Export Button -->
        <div class="flex items-center justify-end">
            <form action="{{ route('admin.companies.payment-due-report.export', $company) }}" method="GET" class="inline">
                <input type="hidden" name="month" value="{{ $selectedMonth->format('Y-m') }}">
                <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-emerald-500 to-teal-600 px-4 py-3 text-base font-semibold text-white shadow-lg shadow-emerald-500/30 hover:from-emerald-600 hover:to-teal-700 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Export to Excel
                </button>
            </form>
        </div>

        <!-- Payment Schedules Table -->
        <div class="rounded-3xl border border-white/10 bg-white/5 p-4 shadow-lg">
            <div class="overflow-x-auto">
                <table data-datatable="true" data-datatable-per-page="25" class="min-w-full w-full text-base text-slate-300">
                    <thead>
                        <tr class="text-base font-semibold uppercase tracking-[0.25em] text-white/80 text-center border-b-2 border-white/20">
                            <th class="px-4 py-4 text-lg border-r border-white/10">Customer</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Loan Number</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Product</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Period</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Due Date</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Expected Amount</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Amount Paid</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Remaining</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($paymentSchedules as $schedule)
                            <tr class="border-t border-white/40 text-center hover:bg-white/5 transition">
                                <td class="px-4 py-4 border-r border-white/5 text-left">
                                    <div class="font-medium text-white">{{ $schedule->loan->customer->full_name ?? 'N/A' }}</div>
                                    <div class="text-sm text-slate-400">{{ $schedule->loan->customer->email ?? 'N/A' }}</div>
                                </td>
                                <td class="px-4 py-4 border-r border-white/5 font-mono text-sm">{{ $schedule->loan->loan_number ?? 'N/A' }}</td>
                                <td class="px-4 py-4 border-r border-white/5">{{ $schedule->loan->loanProduct->name ?? 'N/A' }}</td>
                                <td class="px-4 py-4 border-r border-white/5">{{ $schedule->period_number }}</td>
                                <td class="px-4 py-4 border-r border-white/5">{{ $schedule->due_date->format('d M Y') }}</td>
                                <td class="px-4 py-4 border-r border-white/5 font-semibold">{{ number_format($schedule->expected_amount, 2) }}</td>
                                <td class="px-4 py-4 border-r border-white/5">{{ number_format($schedule->amount_paid, 2) }}</td>
                                <td class="px-4 py-4 border-r border-white/5 font-semibold text-amber-300">{{ number_format($schedule->remaining_amount, 2) }}</td>
                                <td class="px-4 py-4 border-r border-white/5">
                                    @php
                                        $statusColors = [
                                            'paid' => 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30',
                                            'partial' => 'bg-amber-500/20 text-amber-300 border-amber-500/30',
                                            'overdue' => 'bg-rose-500/20 text-rose-300 border-rose-500/30',
                                            'upcoming' => 'bg-blue-500/20 text-blue-300 border-blue-500/30',
                                        ];
                                        $statusColor = $statusColors[$schedule->status] ?? 'bg-slate-500/20 text-slate-300 border-slate-500/30';
                                    @endphp
                                    <span class="inline-block rounded-full px-3 py-1 text-xs font-medium border {{ $statusColor }}">
                                        {{ ucfirst(str_replace('_', ' ', $schedule->status)) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-8 text-center text-slate-400">
                                    No payment schedules found for this month and pay day.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Totals Summary -->
        @if($paymentSchedules->count() > 0)
        <div class="rounded-3xl border border-white/10 bg-gradient-to-br from-cyan-500/10 via-transparent to-blue-500/5 p-6 shadow-lg">
            <h2 class="text-lg font-semibold text-white mb-4">Summary</h2>
            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <p class="text-xs uppercase text-slate-400 mb-1">Total Expected Amount</p>
                    <p class="text-2xl font-bold text-white">{{ number_format($totalExpected, 2) }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase text-slate-400 mb-1">Total Amount Paid</p>
                    <p class="text-2xl font-bold text-emerald-300">{{ number_format($totalPaid, 2) }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase text-slate-400 mb-1">Total Remaining Amount</p>
                    <p class="text-2xl font-bold text-amber-300">{{ number_format($totalRemaining, 2) }}</p>
                </div>
            </div>
        </div>
        @endif

        <div class="flex items-center justify-start">
            <a href="{{ route('admin.companies.payment-due-report', $company) }}" class="inline-flex items-center gap-2 rounded-2xl border border-white/20 bg-white/5 px-4 py-3 text-base font-medium text-slate-300 hover:bg-white/10 hover:border-white/30 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to Month Selection
            </a>
        </div>
    </div>
@endsection

