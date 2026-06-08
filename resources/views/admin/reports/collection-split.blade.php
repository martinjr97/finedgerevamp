@extends('layouts.admin')

@section('title', 'Collection Split Report | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Collection Split Report',
            'buttons' => [
                [
                    'action' => 'export',
                    'text' => 'Export Excel',
                    'href' => route('admin.reports.collection-split.export', request()->query()),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>'
                ]
            ]
        ])

        {{-- Summary Cards --}}
        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4 shadow-lg">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <p class="text-xs font-medium text-slate-400 mb-1">Total Collections</p>
                        <p class="text-2xl font-bold text-white">{{ number_format($summary['total_repayments']) }}</p>
                    </div>
                    <div class="flex-shrink-0 ml-3">
                        <div class="w-12 h-12 rounded-xl bg-blue-500/20 flex items-center justify-center">
                            <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4 shadow-lg">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <p class="text-xs font-medium text-slate-400 mb-1">Total Amount</p>
                        <p class="text-xl font-bold text-emerald-400">
                            ZMW {{ number_format($summary['total_amount'], 2) }}
                        </p>
                    </div>
                    <div class="flex-shrink-0 ml-3">
                        <div class="w-12 h-12 rounded-xl bg-emerald-500/20 flex items-center justify-center">
                            <svg class="w-6 h-6 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4 shadow-lg">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <p class="text-xs font-medium text-slate-400 mb-1">Total Principal</p>
                        <p class="text-xl font-bold text-green-400">
                            ZMW {{ number_format($summary['total_principal'], 2) }}
                        </p>
                    </div>
                    <div class="flex-shrink-0 ml-3">
                        <div class="w-12 h-12 rounded-xl bg-green-500/20 flex items-center justify-center">
                            <svg class="w-6 h-6 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4 shadow-lg">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <p class="text-xs font-medium text-slate-400 mb-1">Total Interest</p>
                        <p class="text-xl font-bold text-amber-400">
                            ZMW {{ number_format($summary['total_interest'], 2) }}
                        </p>
                    </div>
                    <div class="flex-shrink-0 ml-3">
                        <div class="w-12 h-12 rounded-xl bg-amber-500/20 flex items-center justify-center">
                            <svg class="w-6 h-6 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Split Summary --}}
        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <h2 class="text-xl font-semibold text-white mb-4">Collection Split Summary</h2>
            <div class="grid gap-4 md:grid-cols-3">
                <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                    <div class="text-sm text-slate-400 mb-1">Principal</div>
                    <div class="text-lg font-bold text-green-400">ZMW {{ number_format($summary['total_principal'], 2) }}</div>
                    <div class="text-xs text-slate-400 mt-1">
                        {{ $summary['total_amount'] > 0 ? number_format(($summary['total_principal'] / $summary['total_amount']) * 100, 2) : 0 }}% of total
                    </div>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                    <div class="text-sm text-slate-400 mb-1">Interest</div>
                    <div class="text-lg font-bold text-amber-400">ZMW {{ number_format($summary['total_interest'], 2) }}</div>
                    <div class="text-xs text-slate-400 mt-1">
                        {{ $summary['total_amount'] > 0 ? number_format(($summary['total_interest'] / $summary['total_amount']) * 100, 2) : 0 }}% of total
                    </div>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                    <div class="text-sm text-slate-400 mb-1">Processing Fee</div>
                    <div class="text-lg font-bold text-blue-400">ZMW {{ number_format($summary['total_processing_fee'], 2) }}</div>
                    <div class="text-xs text-slate-400 mt-1">
                        {{ $summary['total_amount'] > 0 ? number_format(($summary['total_processing_fee'] / $summary['total_amount']) * 100, 2) : 0 }}% of total
                    </div>
                </div>
            </div>
        </div>

        {{-- Filters --}}
        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <form method="GET" action="{{ route('admin.reports.collection-split') }}" class="space-y-4">
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    {{-- Search --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Search</label>
                        <input type="text" name="search" value="{{ request('search') }}" 
                               placeholder="Repayment number, customer name..."
                               class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                    </div>

                    {{-- Channel --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Channel</label>
                        <select name="channel_id" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                            <option value="">All Channels</option>
                            @foreach($channels as $channel)
                                <option value="{{ $channel->id }}" @selected(request('channel_id') == $channel->id)>
                                    {{ $channel->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Date From --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Date From</label>
                        <input type="date" name="date_from" value="{{ request('date_from') }}" 
                               class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                    </div>

                    {{-- Date To --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Date To</label>
                        <input type="date" name="date_to" value="{{ request('date_to') }}" 
                               class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button type="submit" class="rounded-2xl bg-cyan-500/20 border border-cyan-500/50 px-6 py-2 text-sm font-medium text-cyan-300 hover:bg-cyan-500/30 transition">
                        Apply Filters
                    </button>
                    <a href="{{ route('admin.reports.collection-split') }}" class="rounded-2xl border border-white/10 px-6 py-2 text-sm font-medium text-white/80 hover:bg-white/10 transition">
                        Clear Filters
                    </a>
                </div>
            </form>
        </div>

        {{-- Collections Table --}}
        <div class="rounded-3xl border border-white/10 bg-white/5 p-4 shadow-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full w-full text-sm text-slate-300">
                    <thead>
                        <tr class="text-sm font-semibold uppercase tracking-[0.25em] text-white/80 text-center border-b border-white/10">
                            <th class="px-4 py-4 text-base">Repayment #</th>
                            <th class="px-4 py-4 text-base">Date</th>
                            <th class="px-4 py-4 text-base">Customer</th>
                            <th class="px-4 py-4 text-base">Total Amount</th>
                            <th class="px-4 py-4 text-base">Principal</th>
                            <th class="px-4 py-4 text-base">Interest</th>
                            <th class="px-4 py-4 text-base">Fee</th>
                            <th class="px-4 py-4 text-base">Principal %</th>
                            <th class="px-4 py-4 text-base">Interest %</th>
                            <th class="px-4 py-4 text-base">Fee %</th>
                            <th class="px-4 py-4 text-base">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($repayments as $repayment)
                            @php
                                $totalPrincipal = $repayment->loanRepayments->sum('principal_amount');
                                $totalInterest = $repayment->loanRepayments->sum('interest_amount');
                                $totalFee = $repayment->loanRepayments->sum('processing_fee_amount');
                                $total = $repayment->total_amount;
                                
                                $principalPercent = $total > 0 ? ($totalPrincipal / $total) * 100 : 0;
                                $interestPercent = $total > 0 ? ($totalInterest / $total) * 100 : 0;
                                $feePercent = $total > 0 ? ($totalFee / $total) * 100 : 0;
                            @endphp
                            <tr class="border-t border-white/5 text-center hover:bg-white/5 transition">
                                <td class="px-4 py-3 font-medium text-white">
                                    {{ $repayment->repayment_number }}
                                </td>
                                <td class="px-4 py-3 text-slate-400">
                                    @if($repayment->processed_at)
                                        {{ $repayment->processed_at->format('d M Y') }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-left">
                                        <div class="font-medium text-white">{{ $repayment->customer->full_name ?? 'N/A' }}</div>
                                        <div class="text-xs text-slate-400">{{ $repayment->customer->email ?? 'N/A' }}</div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 font-bold text-emerald-400">
                                    ZMW {{ number_format($repayment->total_amount, 2) }}
                                </td>
                                <td class="px-4 py-3 text-green-400">
                                    ZMW {{ number_format($totalPrincipal, 2) }}
                                </td>
                                <td class="px-4 py-3 text-amber-400">
                                    ZMW {{ number_format($totalInterest, 2) }}
                                </td>
                                <td class="px-4 py-3 text-blue-400">
                                    ZMW {{ number_format($totalFee, 2) }}
                                </td>
                                <td class="px-4 py-3 text-green-300">
                                    {{ number_format($principalPercent, 2) }}%
                                </td>
                                <td class="px-4 py-3 text-amber-300">
                                    {{ number_format($interestPercent, 2) }}%
                                </td>
                                <td class="px-4 py-3 text-blue-300">
                                    {{ number_format($feePercent, 2) }}%
                                </td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('admin.repayments.show', $repayment) }}" 
                                       class="inline-flex items-center gap-1 rounded-lg bg-cyan-500/20 border border-cyan-500/50 px-3 py-1.5 text-xs font-medium text-cyan-300 hover:bg-cyan-500/30 transition">
                                        View
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="px-4 py-8 text-center text-slate-400">
                                    No collections found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if($repayments->hasPages())
                <div class="mt-6">
                    {{ $repayments->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection

