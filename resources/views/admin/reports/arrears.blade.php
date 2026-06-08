@extends('layouts.admin')

@section('title', 'Arrears Report | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Arrears Report',
            'buttons' => [
                [
                    'action' => 'export',
                    'text' => 'Export Details',
                    'href' => route('admin.reports.arrears.export', request()->query()),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>'
                ],
                [
                    'action' => 'export',
                    'text' => 'Export Summary',
                    'href' => route('admin.reports.arrears.export-summary', request()->query()),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>'
                ]
            ]
        ])

        {{-- Summary Cards --}}
        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4 shadow-lg">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <p class="text-xs font-medium text-slate-400 mb-1">Total Overdue Loans</p>
                        <p class="text-2xl font-bold text-white">{{ number_format($arrearsData->total()) }}</p>
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
                        <p class="text-xs font-medium text-slate-400 mb-1">Total Overdue Amount</p>
                        <p class="text-xl font-bold text-rose-400">
                            ZMW {{ number_format($arrearsData->sum('overdue_amount'), 2) }}
                        </p>
                    </div>
                    <div class="flex-shrink-0 ml-3">
                        <div class="w-12 h-12 rounded-xl bg-rose-500/20 flex items-center justify-center">
                            <svg class="w-6 h-6 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4 shadow-lg">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <p class="text-xs font-medium text-slate-400 mb-1">Avg Days Overdue</p>
                        <p class="text-2xl font-bold text-amber-400">
                            {{ $arrearsData->count() > 0 ? round($arrearsData->avg('days_overdue'), 1) : 0 }}
                        </p>
                        <p class="text-xs text-slate-500 mt-0.5">days</p>
                    </div>
                    <div class="flex-shrink-0 ml-3">
                        <div class="w-12 h-12 rounded-xl bg-amber-500/20 flex items-center justify-center">
                            <svg class="w-6 h-6 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4 shadow-lg">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <p class="text-xs font-medium text-slate-400 mb-1">PAR30+ Loans</p>
                        <p class="text-2xl font-bold text-orange-400">
                            {{ number_format($arrearsData->where('par_status', '!=', null)->where('days_overdue', '>=', 30)->count()) }}
                        </p>
                    </div>
                    <div class="flex-shrink-0 ml-3">
                        <div class="w-12 h-12 rounded-xl bg-orange-500/20 flex items-center justify-center">
                            <svg class="w-6 h-6 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Filters --}}
        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <form method="GET" action="{{ route('admin.reports.arrears') }}" class="space-y-4">
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    {{-- Search --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Search</label>
                        <input type="text" name="search" value="{{ request('search') }}" 
                               placeholder="Loan number, customer name..."
                               class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
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

                    {{-- Days Overdue Min --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Min Days Overdue</label>
                        <input type="number" name="days_overdue_min" value="{{ request('days_overdue_min') }}" 
                               placeholder="e.g. 30"
                               class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button type="submit" class="rounded-2xl bg-cyan-500/20 border border-cyan-500/50 px-6 py-2 text-sm font-medium text-cyan-300 hover:bg-cyan-500/30 transition">
                        Apply Filters
                    </button>
                    <a href="{{ route('admin.reports.arrears') }}" class="rounded-2xl border border-white/10 px-6 py-2 text-sm font-medium text-white/80 hover:bg-white/10 transition">
                        Clear Filters
                    </a>
                </div>
            </form>
        </div>

        {{-- Arrears Table --}}
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
                            <th class="px-4 py-4 text-lg border-r border-white/10">Outstanding</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Overdue Amount</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Overdue Installments</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Days Overdue</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">PAR Status</th>
                            <th class="px-4 py-4 text-lg">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($arrearsData as $item)
                            @php
                                $loan = $item['loan'];
                            @endphp
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
                                    ZMW {{ number_format($loan->outstanding_balance, 2) }}
                                </td>
                                <td class="px-4 py-4 font-bold text-rose-400 border-r border-white/5">
                                    ZMW {{ number_format($item['overdue_amount'], 2) }}
                                </td>
                                <td class="px-4 py-4 border-r border-white/5">
                                    <span class="text-sm font-medium text-amber-400">
                                        {{ $item['overdue_installments_count'] ?? 0 }} installment(s)
                                    </span>
                                </td>
                                <td class="px-4 py-4 border-r border-white/5">
                                    <span class="text-sm font-medium {{ $item['days_overdue'] >= 90 ? 'text-rose-400' : ($item['days_overdue'] >= 60 ? 'text-red-400' : ($item['days_overdue'] >= 30 ? 'text-orange-400' : 'text-amber-400')) }}">
                                        {{ $item['days_overdue'] }} days
                                    </span>
                                </td>
                                <td class="px-4 py-4 border-r border-white/5">
                                    @if($item['par_status'])
                                        <span class="text-sm font-medium {{ $item['par_status'] === 'PAR30' ? 'text-orange-400' : ($item['par_status'] === 'PAR60' ? 'text-red-400' : 'text-rose-400') }}">
                                            {{ $item['par_status'] }}
                                        </span>
                                    @else
                                        <span class="text-slate-500">—</span>
                                    @endif
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
                                <td colspan="12" class="px-4 py-8 text-center text-slate-400">
                                    No loans with overdue installments found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if($arrearsData->hasPages())
                <div class="mt-6">
                    {{ $arrearsData->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection

