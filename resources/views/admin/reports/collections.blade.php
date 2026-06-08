@extends('layouts.admin')

@section('title', 'Collections Report | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Collections Report',
            'buttons' => [
                [
                    'action' => 'export',
                    'text' => 'Export Details',
                    'href' => route('admin.reports.collections.export', request()->query()),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>'
                ],
                [
                    'action' => 'export',
                    'text' => 'Export Summary',
                    'href' => route('admin.reports.collections.export-summary', request()->query()),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>'
                ]
            ]
        ])

        {{-- Summary Cards --}}
        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4 shadow-lg">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <p class="text-xs font-medium text-slate-400 mb-1">Total Collections</p>
                        <p class="text-2xl font-bold text-white">{{ number_format($summary['total_count']) }}</p>
                    </div>
                    <div class="flex-shrink-0 ml-3">
                        <div class="w-12 h-12 rounded-xl bg-blue-500/20 flex items-center justify-center">
                            <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4 shadow-lg">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <p class="text-xs font-medium text-slate-400 mb-1">Total Amount Collected</p>
                        <p class="text-xl font-bold text-emerald-400">
                            ZMW {{ number_format($summary['total_collections'], 2) }}
                        </p>
                    </div>
                    <div class="flex-shrink-0 ml-3">
                        <div class="w-12 h-12 rounded-xl bg-emerald-500/20 flex items-center justify-center">
                            <svg class="w-6 h-6 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4 shadow-lg">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <p class="text-xs font-medium text-slate-400 mb-1">This Month</p>
                        <p class="text-2xl font-bold text-cyan-400">
                            {{ number_format($collections->filter(fn($repayment) => $repayment->processed_at && $repayment->processed_at->isCurrentMonth())->count()) }}
                        </p>
                    </div>
                    <div class="flex-shrink-0 ml-3">
                        <div class="w-12 h-12 rounded-xl bg-cyan-500/20 flex items-center justify-center">
                            <svg class="w-6 h-6 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4 shadow-lg">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <p class="text-xs font-medium text-slate-400 mb-1">This Month Amount</p>
                        <p class="text-xl font-bold text-blue-400">
                            ZMW {{ number_format($collections->filter(fn($repayment) => $repayment->processed_at && $repayment->processed_at->isCurrentMonth())->sum('total_amount'), 2) }}
                        </p>
                    </div>
                    <div class="flex-shrink-0 ml-3">
                        <div class="w-12 h-12 rounded-xl bg-blue-500/20 flex items-center justify-center">
                            <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Collections by Channel --}}
        @if($summary['by_channel']->isNotEmpty())
            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                <h2 class="text-xl font-semibold text-white mb-4">Collections by Channel</h2>
                <div class="grid gap-4 md:grid-cols-3">
                    @foreach($summary['by_channel'] as $channelData)
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                            <div class="text-sm text-slate-400 mb-1">{{ $channelData['channel'] }}</div>
                            <div class="text-lg font-bold text-white">{{ $channelData['count'] }} payments</div>
                            <div class="text-sm text-emerald-400">ZMW {{ number_format($channelData['total'], 2) }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Filters --}}
        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <form method="GET" action="{{ route('admin.reports.collections') }}" class="space-y-4">
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
                    <a href="{{ route('admin.reports.collections') }}" class="rounded-2xl border border-white/10 px-6 py-2 text-sm font-medium text-white/80 hover:bg-white/10 transition">
                        Clear Filters
                    </a>
                </div>
            </form>
        </div>

        {{-- Collections Table --}}
        <div class="rounded-3xl border border-white/10 bg-white/5 p-4 shadow-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full w-full text-base text-slate-300">
                    <thead>
                        <tr class="text-base font-semibold uppercase tracking-[0.25em] text-white/80 text-center border-b-2 border-white/20">
                            <th class="px-4 py-4 text-lg border-r border-white/10">Repayment #</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Date</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Customer</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Channel</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Amount</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Principal</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Interest</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Fee</th>
                            <th class="px-4 py-4 text-lg">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($collections as $repayment)
                            @php
                                $totalPrincipal = $repayment->loanRepayments->sum('principal_amount');
                                $totalInterest = $repayment->loanRepayments->sum('interest_amount');
                                $totalFee = $repayment->loanRepayments->sum('processing_fee_amount');
                            @endphp
                            <tr class="border-t border-white/40 text-center hover:bg-white/5 transition">
                                <td class="px-4 py-4 font-medium text-white border-r border-white/5">
                                    {{ $repayment->repayment_number }}
                                </td>
                                <td class="px-4 py-4 text-slate-400 border-r border-white/5">
                                    @if($repayment->processed_at)
                                        {{ $repayment->processed_at->format('d M Y') }}
                                        <div class="text-sm text-slate-500">{{ $repayment->processed_at->format('g:i A') }}</div>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-4 border-r border-white/5">
                                    <div class="text-left">
                                        <div class="font-medium text-white">{{ $repayment->customer->full_name ?? 'N/A' }}</div>
                                        <div class="text-sm text-slate-400">{{ $repayment->customer->email ?? 'N/A' }}</div>
                                    </div>
                                </td>
                                <td class="px-4 py-4 border-r border-white/5">
                                    <span class="rounded-full bg-cyan-500/20 px-2 py-1 text-sm text-cyan-300">
                                        {{ $repayment->channel->name ?? '—' }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 font-bold text-emerald-400 border-r border-white/5">
                                    ZMW {{ number_format($repayment->total_amount, 2) }}
                                </td>
                                <td class="px-4 py-4 text-green-400 border-r border-white/5">
                                    ZMW {{ number_format($totalPrincipal, 2) }}
                                </td>
                                <td class="px-4 py-4 text-amber-400 border-r border-white/5">
                                    ZMW {{ number_format($totalInterest, 2) }}
                                </td>
                                <td class="px-4 py-4 text-blue-400 border-r border-white/5">
                                    ZMW {{ number_format($totalFee, 2) }}
                                </td>
                                <td class="px-4 py-4">
                                    <a href="{{ route('admin.repayments.show', $repayment) }}" 
                                       class="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-blue-500/40 to-purple-500/40 border-2 border-blue-400/70 px-4 py-2 text-base font-semibold text-blue-200 hover:from-blue-500/60 hover:to-purple-500/60 hover:border-blue-400 hover:text-white transition shadow-md shadow-blue-500/20">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                        View
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-8 text-center text-slate-400">
                                    No collections found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if($collections->hasPages())
                <div class="mt-6">
                    {{ $collections->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection

