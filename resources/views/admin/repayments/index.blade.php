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

        <x-admin.collapsible-filters
            panel-id="repayment-filters-panel"
            :filter-keys="['search', 'status', 'channel_id', 'customer_id', 'date_from', 'date_to', 'processed_date_from', 'processed_date_to']"
            expanded-hint="Refine the repayment list below."
        >
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
        </x-admin.collapsible-filters>

        {{-- Repayments Table --}}
        <div class="admin-data-table">
            <div class="admin-data-table__scroll">
                <table class="min-w-full w-full text-base text-slate-300">
                    <thead>
                        <tr class="font-semibold uppercase text-white/80 text-center">
                            <th>Repayment #</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Channel</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>External Ref</th>
                            <th scope="col" class="admin-data-table__actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($repayments as $repayment)
                            <tr class="text-center">
                                <td class="font-medium text-white">
                                    {{ $repayment->repayment_number }}
                                </td>
                                <td class="text-slate-400">
                                    {{ $repayment->created_at->format('M d, Y') }}
                                    <div class="text-sm text-slate-500">{{ $repayment->created_at->format('g:i A') }}</div>
                                </td>
                                <td>
                                    <div class="text-left">
                                        <div class="font-medium text-white">{{ $repayment->customer->full_name ?? 'N/A' }}</div>
                                        <div class="text-sm text-slate-400">{{ $repayment->customer->email ?? 'N/A' }}</div>
                                    </div>
                                </td>
                                <td>
                                    <span class="rounded-full bg-cyan-500/20 px-2 py-1 text-sm text-cyan-300">
                                        {{ $repayment->channel->name ?? '—' }}
                                    </span>
                                </td>
                                <td class="font-medium text-white">
                                    ZMW {{ number_format($repayment->total_amount, 2) }}
                                </td>
                                <td>
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
                                <td>
                                    <div class="text-sm text-slate-400">
                                        @if($repayment->external_reference)
                                            <div class="font-mono">{{ substr($repayment->external_reference, 0, 15) }}...</div>
                                        @else
                                            —
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    @can('repayments.view')
                                    <a href="{{ route('admin.repayments.show', $repayment) }}" 
                                       class="inline-flex items-center gap-1.5 rounded-lg border border-blue-400/50 bg-blue-500/10 px-2.5 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-500/20 transition">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                                <td colspan="8" class="py-8 text-center text-slate-400">
                                    No repayments found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="admin-table-footer">
                {{ $repayments->withQueryString()->links() }}
            </div>
        </div>
    </div>
@endsection

