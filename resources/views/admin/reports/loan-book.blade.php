@extends('layouts.admin')

@section('title', 'Loan Book Report | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Loan Book Report',
            'buttons' => [
                [
                    'action' => 'export',
                    'text' => 'Export Details',
                    'href' => route('admin.reports.loan-book.export', request()->query()),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>'
                ],
                [
                    'action' => 'export',
                    'text' => 'Export Summary',
                    'href' => route('admin.reports.loan-book.export-summary', request()->query()),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>'
                ]
            ]
        ])

        <p class="text-sm text-slate-400">
            Live portfolio totals use <strong class="text-slate-200">active, disbursed</strong> loans ({{ number_format($stats['active_loans']) }} of {{ number_format($stats['total_loans']) }} total).
            The list below defaults to that portfolio; use filters or “Show all loans” to include approved awaiting disbursement.
        </p>

        {{-- Portfolio Statistics --}}
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-2xl border border-emerald-400/20 bg-emerald-500/5 p-4 shadow-lg">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <p class="text-xs font-medium text-slate-400 mb-1">Active Loans</p>
                        <p class="text-2xl font-bold text-emerald-300">{{ number_format($stats['active_loans']) }}</p>
                        <p class="text-xs text-slate-500 mt-1">Disbursed &amp; in repayment</p>
                    </div>
                    <div class="flex-shrink-0 ml-3">
                        <div class="w-12 h-12 rounded-xl bg-emerald-500/20 flex items-center justify-center">
                            <svg class="w-6 h-6 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4 shadow-lg">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <p class="text-xs font-medium text-slate-400 mb-1">Active Portfolio Principal</p>
                        <p class="text-xl font-bold text-cyan-400">
                            ZMW {{ number_format($stats['active_principal'], 2) }}
                        </p>
                    </div>
                    <div class="flex-shrink-0 ml-3">
                        <div class="w-12 h-12 rounded-xl bg-cyan-500/20 flex items-center justify-center">
                            <svg class="w-6 h-6 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4 shadow-lg">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <p class="text-xs font-medium text-slate-400 mb-1">Active Portfolio Outstanding</p>
                        <p class="text-xl font-bold text-amber-400">
                            ZMW {{ number_format($stats['total_outstanding'], 2) }}
                        </p>
                    </div>
                    <div class="flex-shrink-0 ml-3">
                        <div class="w-12 h-12 rounded-xl bg-amber-500/20 flex items-center justify-center">
                            <svg class="w-6 h-6 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4 shadow-lg">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <p class="text-xs font-medium text-slate-400 mb-1">Total Disbursed</p>
                        <p class="text-xl font-bold text-emerald-400">
                            ZMW {{ number_format($stats['total_disbursed'], 2) }}
                        </p>
                    </div>
                    <div class="flex-shrink-0 ml-3">
                        <div class="w-12 h-12 rounded-xl bg-emerald-500/20 flex items-center justify-center">
                            <svg class="w-6 h-6 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Statistics by Status --}}
        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <h2 class="text-xl font-semibold text-white mb-4">Portfolio by Status</h2>
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                @foreach($stats['by_status'] as $statusStat)
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <div class="text-sm text-slate-400 mb-1">{{ ucfirst(str_replace('_', ' ', $statusStat->status)) }}</div>
                        <div class="text-lg font-bold text-white">{{ number_format($statusStat->count) }} loans</div>
                        <div class="text-sm text-cyan-400">ZMW {{ number_format($statusStat->total_principal, 2) }}</div>
                        <div class="text-xs text-amber-400">Outstanding: ZMW {{ number_format($statusStat->total_outstanding, 2) }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Statistics by Product --}}
        @if($stats['by_product']->isNotEmpty())
            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                <h2 class="text-xl font-semibold text-white mb-4">Portfolio by Product</h2>
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    @foreach($stats['by_product'] as $productStat)
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                            <div class="text-sm text-slate-400 mb-1">{{ $productStat->loanProduct->name ?? 'N/A' }}</div>
                            <div class="text-lg font-bold text-white">{{ number_format($productStat->count) }} loans</div>
                            <div class="text-sm text-cyan-400">ZMW {{ number_format($productStat->total_principal, 2) }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Filters --}}
        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <form method="GET" action="{{ route('admin.reports.loan-book') }}" class="space-y-4">
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    {{-- Search --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Search</label>
                        <input type="text" name="search" value="{{ request('search') }}" 
                               placeholder="Loan number, customer name..."
                               class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                    </div>

                    {{-- Status --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Status</label>
                        <select name="status" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                            <option value="">All Statuses</option>
                            <option value="pending_approval" @selected(request('status') === 'pending_approval')>Pending Approval</option>
                            <option value="approved" @selected(request('status') === 'approved')>Approved</option>
                            <option value="active" @selected(request('status') === 'active')>Active</option>
                            <option value="settled" @selected(request('status') === 'settled')>Settled</option>
                            <option value="defaulted" @selected(request('status') === 'defaulted')>Defaulted</option>
                            <option value="cancelled" @selected(request('status') === 'cancelled')>Cancelled</option>
                        </select>
                    </div>

                    {{-- Product --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Product</label>
                        <select name="loan_product_id" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                            <option value="">All Products</option>
                            @foreach($loanProducts as $product)
                                <option value="{{ $product->id }}" @selected(request('loan_product_id') == $product->id)>
                                    {{ $product->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Customer Group --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Customer Group</label>
                        <select name="customer_group_id" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                            <option value="">All Groups</option>
                            @foreach($customerGroups as $group)
                                <option value="{{ $group->id }}" @selected(request('customer_group_id') == $group->id)>
                                    {{ $group->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Date From --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Start Date From</label>
                        <input type="date" name="date_from" value="{{ request('date_from') }}" 
                               class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                    </div>

                    {{-- Date To --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Start Date To</label>
                        <input type="date" name="date_to" value="{{ request('date_to') }}" 
                               class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                    </div>

                    {{-- Disbursement Status --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Disbursement</label>
                        <select name="disbursement_status" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                            <option value="">Any</option>
                            <option value="pending" @selected(request('disbursement_status') === 'pending')>Pending</option>
                            <option value="completed" @selected(request('disbursement_status') === 'completed')>Completed</option>
                        </select>
                    </div>
                </div>

                <label class="inline-flex items-center gap-2 text-sm text-slate-300 cursor-pointer">
                    <input type="checkbox" name="show_all" value="1" @checked(request()->boolean('show_all'))
                           class="rounded border-white/20 bg-white/10 text-cyan-500 focus:ring-cyan-400/40">
                    Show all loans (include approved awaiting disbursement)
                </label>

                <div class="flex items-center gap-3">
                    <button type="submit" class="rounded-2xl bg-cyan-500/20 border border-cyan-500/50 px-6 py-2 text-sm font-medium text-cyan-300 hover:bg-cyan-500/30 transition">
                        Apply Filters
                    </button>
                    <a href="{{ route('admin.reports.loan-book') }}" class="rounded-2xl border border-white/10 px-6 py-2 text-sm font-medium text-white/80 hover:bg-white/10 transition">
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
                            <th class="px-4 py-4 text-lg border-r border-white/10">Loan #</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Customer</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Product</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Company</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Relationship Manager</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Principal</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Booked Total</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10" title="Booked balance owed">Booked Outstanding</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Start Date</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Disbursement</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Status</th>
                            <th class="px-4 py-4 text-lg">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($loans as $loan)
                            <tr class="border-t border-white/40 text-center hover:bg-white/5 transition">
                                <td class="px-4 py-4 font-medium text-white border-r border-white/5">
                                    {{ $loan->loan_number }}
                                </td>
                                <td class="px-4 py-4 border-r border-white/5">
                                    <div class="text-left">
                                        <div class="font-medium text-white">{{ $loan->customer->full_name ?? 'N/A' }}</div>
                                        <div class="text-sm text-slate-400">{{ $loan->customer->email ?? 'N/A' }}</div>
                                    </div>
                                </td>
                                <td class="px-4 py-4 border-r border-white/5">
                                    <span class="text-white">{{ $loan->loanProduct->name ?? '—' }}</span>
                                </td>
                                <td class="px-4 py-4 border-r border-white/5">
                                    <span class="text-white">{{ $loan->customer->company->name ?? '—' }}</span>
                                </td>
                                <td class="px-4 py-4 border-r border-white/5">
                                    @php
                                        $relationshipManager = $loan->customer->company->relationshipManager ?? null;
                                    @endphp
                                    <span class="text-white">
                                        {{ $relationshipManager ? ($relationshipManager->first_name . ' ' . $relationshipManager->last_name) : '—' }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 font-medium text-white border-r border-white/5">
                                    ZMW {{ number_format($loan->principal_amount, 2) }}
                                </td>
                                <td class="px-4 py-4 font-medium text-white border-r border-white/5">
                                    ZMW {{ number_format($loan->total_amount, 2) }}
                                </td>
                                <td class="px-4 py-4 font-medium text-amber-400 border-r border-white/5">
                                    ZMW {{ number_format($loan->outstanding_balance, 2) }}
                                </td>
                                <td class="px-4 py-4 text-slate-400 border-r border-white/5">
                                    {{ $loan->loan_start_date->format('d M Y') }}
                                </td>
                                <td class="px-4 py-4 border-r border-white/5 text-left">
                                    <div class="text-sm text-white">{{ $loan->channel->name ?? '—' }}</div>
                                    <div class="text-xs text-slate-400">{{ $loan->disbursementChannelTypeLabel() }}</div>
                                    <div class="text-xs text-slate-300 mt-0.5">{{ \Illuminate\Support\Str::limit($loan->disbursementDestinationSummary() ?: '—', 40) }}</div>
                                </td>
                                <td class="px-4 py-4 border-r border-white/5">
                                    @php
                                        $statusTextColors = [
                                            'pending_approval' => 'text-amber-400',
                                            'approved' => 'text-blue-400',
                                            'active' => 'text-emerald-400',
                                            'settled' => 'text-teal-400',
                                            'defaulted' => 'text-rose-400',
                                            'cancelled' => 'text-slate-400',
                                        ];
                                        $statusTextColor = $statusTextColors[$loan->status] ?? 'text-slate-400';
                                    @endphp
                                    <span class="text-sm font-medium {{ $statusTextColor }}">
                                        {{ ucfirst(str_replace('_', ' ', $loan->status)) }}
                                    </span>
                                </td>
                                <td class="px-4 py-4">
                                    <a href="{{ route('admin.loans.show', $loan) }}" 
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
                                <td colspan="11" class="px-4 py-8 text-center text-slate-400">
                                    No loans found.
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

