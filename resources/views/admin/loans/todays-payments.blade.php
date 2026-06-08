@extends('layouts.admin')

@section('title', "Today's Payments | ".config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => "Today's Payments - " . $today->format('M d, Y'),
            'buttons' => [
                [
                    'action' => 'export',
                    'text' => 'Export Excel',
                    'href' => route('admin.loans.todays-payments.export', request()->query()),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
                    'can' => auth('admin')->user()?->can('loans.export')
                ],
                [
                    'action' => 'default',
                    'text' => 'All Loans',
                    'href' => route('admin.loans.index'),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>',
                    'can' => auth('admin')->user()?->can('loans.view')
                ]
            ]
        ])

        {{-- Filters --}}
        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <form method="GET" action="{{ route('admin.loans.todays-payments') }}" class="space-y-4">
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {{-- Search --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Search</label>
                        <input type="text" name="search" value="{{ request('search') }}" 
                               placeholder="Loan number, customer name, email, phone..."
                               class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button type="submit" class="rounded-2xl bg-cyan-500/20 border border-cyan-500/50 px-6 py-2 text-sm font-medium text-cyan-300 hover:bg-cyan-500/30 transition">
                        Apply Filters
                    </button>
                    <a href="{{ route('admin.loans.todays-payments') }}" class="rounded-2xl border border-white/10 px-6 py-2 text-sm font-medium text-white/80 hover:bg-white/10 transition">
                        Clear Filters
                    </a>
                </div>
            </form>
        </div>

        {{-- Loans Table --}}
        <div class="rounded-3xl border border-white/10 bg-white/5 p-4 shadow-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full w-full text-base text-slate-300">
                    <thead>
                        <tr class="text-base font-semibold uppercase tracking-[0.25em] text-white/80 text-center border-b-2 border-white/20">
                            <th class="px-4 py-4 text-lg border-r border-white/10">Loan Number</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Customer</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Product</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Expected Amount</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Amount Paid</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Remaining</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Payment Status</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Period</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Outstanding Balance</th>
                            <th class="px-4 py-4 text-lg">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($loans as $loan)
                            @php
                                $schedule = $loan->todays_schedule;
                                $statusColors = [
                                    'paid' => 'text-green-400',
                                    'paid_early' => 'text-emerald-400',
                                    'partial' => 'text-amber-400',
                                    'upcoming' => 'text-blue-400',
                                    'overdue' => 'text-rose-400',
                                ];
                                $statusColor = $schedule ? ($statusColors[$schedule->status ?? 'upcoming'] ?? 'text-slate-400') : 'text-slate-400';
                            @endphp
                            <tr class="border-t border-white/40 text-center hover:bg-white/5 transition">
                                <td class="px-4 py-4 font-medium text-white border-r border-white/5">
                                    {{ $loan->loan_number }}
                                </td>
                                <td class="px-4 py-4 border-r border-white/5">
                                    <div class="text-left">
                                        <div class="font-medium text-white">{{ $loan->customer->full_name ?? 'N/A' }}</div>
                                        <div class="text-sm text-slate-400">{{ $loan->customer->email ?? 'N/A' }}</div>
                                        <div class="text-xs text-slate-500">{{ $loan->customer->phone ?? 'N/A' }}</div>
                                    </div>
                                </td>
                                <td class="px-4 py-4 border-r border-white/5">
                                    <span class="rounded-full bg-cyan-500/20 px-2 py-1 text-sm text-cyan-300">
                                        {{ $loan->loanProduct->name ?? '—' }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 font-medium text-white border-r border-white/5">
                                    ZMW {{ number_format($schedule->expected_amount ?? 0, 2) }}
                                </td>
                                <td class="px-4 py-4 font-medium text-white border-r border-white/5">
                                    ZMW {{ number_format($schedule->amount_paid ?? 0, 2) }}
                                </td>
                                <td class="px-4 py-4 font-medium text-white border-r border-white/5">
                                    ZMW {{ number_format($schedule->remaining_amount ?? 0, 2) }}
                                </td>
                                <td class="px-4 py-4 border-r border-white/5">
                                    <span class="text-sm font-medium {{ $statusColor }}">
                                        {{ $schedule ? ucfirst(str_replace('_', ' ', $schedule->status)) : 'N/A' }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-slate-400 border-r border-white/5">
                                    {{ $schedule->period_number ?? 'N/A' }}/{{ $loan->tenure_months }}
                                </td>
                                <td class="px-4 py-4 font-medium text-white border-r border-white/5">
                                    ZMW {{ number_format($loan->outstanding_balance, 2) }}
                                </td>
                                <td class="px-4 py-4">
                                    <div class="inline-flex items-center gap-3">
                                        @can('loans.view')
                                        <a href="{{ route('admin.loans.show', $loan) }}" 
                                           class="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-blue-500/40 to-blue-600/40 border-2 border-blue-400/70 px-4 py-2 text-base font-semibold text-blue-200 hover:from-blue-500/60 hover:to-blue-600/60 hover:border-blue-400 hover:text-white transition shadow-md shadow-blue-500/20">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                                <td colspan="10" class="px-4 py-8 text-center text-slate-400">
                                    No payments scheduled for today.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if($loans->hasPages())
                <div class="mt-6">
                    {{ $loans->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection

