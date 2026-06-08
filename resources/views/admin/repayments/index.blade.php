@extends('layouts.admin')

@section('title', 'Repayments | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Repayments',
            'buttons' => [
                [
                    'action' => 'export',
                    'text' => 'Export Excel',
                    'href' => route('admin.repayments.export', request()->query()),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
                    'can' => auth('admin')->user()?->can('repayments.export')
                ]
            ]
        ])

        {{-- Filters --}}
        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <form method="GET" action="{{ route('admin.repayments.index') }}" class="space-y-4">
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    {{-- Search --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Search</label>
                        <input type="text" name="search" value="{{ request('search') }}" 
                               placeholder="Repayment number, customer, external ref..."
                               class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                    </div>

                    {{-- Status --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Status</label>
                        <select name="status" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                            <option value="">All Statuses</option>
                            <option value="pending" @selected(request('status') === 'pending')>Pending</option>
                            <option value="processing" @selected(request('status') === 'processing')>Processing</option>
                            <option value="completed" @selected(request('status') === 'completed')>Completed</option>
                            <option value="failed" @selected(request('status') === 'failed')>Failed</option>
                            <option value="cancelled" @selected(request('status') === 'cancelled')>Cancelled</option>
                        </select>
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

                    {{-- Customer --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Customer</label>
                        <select name="customer_id" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                            <option value="">All Customers</option>
                            @foreach($customers->take(100) as $customer)
                                <option value="{{ $customer->id }}" @selected(request('customer_id') == $customer->id)>
                                    {{ $customer->full_name }} ({{ $customer->email }})
                                </option>
                            @endforeach
                        </select>
                        @if($customers->count() > 100)
                            <p class="text-xs text-slate-500 mt-1">Showing first 100 customers. Use search to find others.</p>
                        @endif
                    </div>

                    {{-- Date From (Created) --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Created From</label>
                        <input type="date" name="date_from" value="{{ request('date_from') }}" 
                               class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                    </div>

                    {{-- Date To (Created) --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Created To</label>
                        <input type="date" name="date_to" value="{{ request('date_to') }}" 
                               class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                    </div>

                    {{-- Processed From --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Processed From</label>
                        <input type="date" name="processed_date_from" value="{{ request('processed_date_from') }}" 
                               class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                    </div>

                    {{-- Processed To --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Processed To</label>
                        <input type="date" name="processed_date_to" value="{{ request('processed_date_to') }}" 
                               class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button type="submit" class="rounded-2xl bg-cyan-500/20 border border-cyan-500/50 px-6 py-2 text-sm font-medium text-cyan-300 hover:bg-cyan-500/30 transition">
                        Apply Filters
                    </button>
                    <a href="{{ route('admin.repayments.index') }}" class="rounded-2xl border border-white/10 px-6 py-2 text-sm font-medium text-white/80 hover:bg-white/10 transition">
                        Clear Filters
                    </a>
                </div>
            </form>
        </div>

        {{-- Repayments Table --}}
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
                            <th class="px-4 py-4 text-lg border-r border-white/10">Recovery Method</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Loans</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Status</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">External Ref</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Processed At</th>
                            <th class="px-4 py-4 text-lg">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($repayments as $repayment)
                            <tr class="border-t border-white/40 text-center hover:bg-white/5 transition">
                                <td class="px-4 py-4 font-medium text-white border-r border-white/5">
                                    {{ $repayment->repayment_number }}
                                </td>
                                <td class="px-4 py-4 text-slate-400 border-r border-white/5">
                                    {{ $repayment->created_at->format('M d, Y') }}
                                    <div class="text-sm text-slate-500">{{ $repayment->created_at->format('g:i A') }}</div>
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
                                <td class="px-4 py-4 font-medium text-white border-r border-white/5">
                                    ZMW {{ number_format($repayment->total_amount, 2) }}
                                </td>
                                <td class="px-4 py-4 border-r border-white/5 text-slate-300">
                                    {{ $repayment->recoveryMethodLabel() }}
                                </td>
                                <td class="px-4 py-4 border-r border-white/5">
                                    <div class="text-sm text-slate-400">
                                        @php
                                            $loanCount = $repayment->loanRepayments->count();
                                            $loans = $repayment->loanRepayments->take(2);
                                            $totalPrincipal = $repayment->loanRepayments->sum('principal_amount');
                                            $totalInterest = $repayment->loanRepayments->sum('interest_amount');
                                            $totalFee = $repayment->loanRepayments->sum('processing_fee_amount');
                                        @endphp
                                        @if($loanCount > 0)
                                            @foreach($loans as $loanRepayment)
                                                <div class="mb-1">
                                                    <span class="font-medium">{{ $loanRepayment->loan->loan_number ?? 'N/A' }}</span>
                                                    <div class="text-slate-500 text-sm">
                                                        P: {{ number_format($loanRepayment->principal_amount, 2) }} | 
                                                        I: {{ number_format($loanRepayment->interest_amount, 2) }}
                                                    </div>
                                                </div>
                                            @endforeach
                                            @if($loanCount > 2)
                                                <div class="text-cyan-400">+{{ $loanCount - 2 }} more</div>
                                            @endif
                                            <div class="mt-2 pt-2 border-t border-white/10 text-slate-300">
                                                <div>Total P: ZMW {{ number_format($totalPrincipal, 2) }}</div>
                                                <div>Total I: ZMW {{ number_format($totalInterest, 2) }}</div>
                                                @if($totalFee > 0)
                                                    <div>Total Fee: ZMW {{ number_format($totalFee, 2) }}</div>
                                                @endif
                                            </div>
                                        @else
                                            N/A
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-4 border-r border-white/5">
                                    @php
                                        $statusTextColors = [
                                            'pending' => 'text-amber-400',
                                            'processing' => 'text-blue-400',
                                            'completed' => 'text-emerald-400',
                                            'failed' => 'text-rose-400',
                                            'cancelled' => 'text-slate-400',
                                        ];
                                        $statusTextColor = $statusTextColors[$repayment->status] ?? 'text-slate-400';
                                    @endphp
                                    <span class="text-sm font-medium {{ $statusTextColor }}">
                                        {{ ucfirst($repayment->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 border-r border-white/5">
                                    <div class="text-sm text-slate-400">
                                        @if($repayment->external_reference)
                                            <div class="font-mono">{{ substr($repayment->external_reference, 0, 15) }}...</div>
                                        @else
                                            —
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-slate-400 border-r border-white/5">
                                    @if($repayment->processed_at)
                                        {{ $repayment->processed_at->format('M d, Y') }}
                                        <div class="text-sm text-slate-500">{{ $repayment->processed_at->format('g:i A') }}</div>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-4">
                                    @can('repayments.view')
                                    <a href="{{ route('admin.repayments.show', $repayment) }}" 
                                       class="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-blue-500/40 to-purple-500/40 border-2 border-blue-400/70 px-4 py-2 text-base font-semibold text-blue-200 hover:from-blue-500/60 hover:to-purple-500/60 hover:border-blue-400 hover:text-white transition shadow-md shadow-blue-500/20">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                        View
                                    </a>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-4 py-8 text-center text-slate-400">
                                    No repayments found.
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

