@extends('layouts.admin')

@section('title', 'Loan Performance Report | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Loan Performance Report',
            'buttons' => [
                [
                    'action' => 'export',
                    'text' => 'Export Excel',
                    'href' => route('admin.reports.loan-performance.export', request()->query()),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>'
                ]
            ]
        ])

        {{-- Filters --}}
        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <form method="GET" action="{{ route('admin.reports.loan-performance') }}" class="space-y-4">
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
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

                    {{-- Company --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Company</label>
                        <select name="company_id" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                            <option value="">All Companies</option>
                            @foreach($companies as $company)
                                <option value="{{ $company->id }}" @selected(request('company_id') == $company->id)>
                                    {{ $company->name }}
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
                </div>

                <div class="flex items-center gap-3">
                    <button type="submit" class="rounded-2xl bg-cyan-500/20 border border-cyan-500/50 px-6 py-2 text-sm font-medium text-cyan-300 hover:bg-cyan-500/30 transition">
                        Apply Filters
                    </button>
                    <a href="{{ route('admin.reports.loan-performance') }}" class="rounded-2xl border border-white/10 px-6 py-2 text-sm font-medium text-white/80 hover:bg-white/10 transition">
                        Clear Filters
                    </a>
                </div>
            </form>
        </div>

        {{-- Performance by Product --}}
        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <h2 class="text-xl font-semibold text-white mb-4">Performance by Product</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full w-full text-base text-slate-300">
                    <thead>
                        <tr class="text-base font-semibold uppercase tracking-[0.25em] text-white/80 text-center border-b-2 border-white/20">
                            <th class="px-4 py-4 text-lg border-r border-white/10">Product</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Total Loans</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Total Principal</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Total Disbursed</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Total Collected</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Total Outstanding</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Active</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Settled</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Defaulted</th>
                            <th class="px-4 py-4 text-lg">Collection Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($performanceByProduct as $item)
                            <tr class="border-t border-white/40 text-center hover:bg-white/5 transition">
                                <td class="px-4 py-4 font-medium text-white border-r border-white/5">
                                    {{ $item->loanProduct->name ?? 'N/A' }}
                                </td>
                                <td class="px-4 py-4 border-r border-white/5">{{ $item->total_loans }}</td>
                                <td class="px-4 py-4 border-r border-white/5">ZMW {{ number_format($item->total_principal, 2) }}</td>
                                <td class="px-4 py-4 border-r border-white/5">ZMW {{ number_format($item->total_disbursed, 2) }}</td>
                                <td class="px-4 py-4 text-emerald-400 border-r border-white/5">ZMW {{ number_format($item->total_collected, 2) }}</td>
                                <td class="px-4 py-4 text-amber-400 border-r border-white/5">ZMW {{ number_format($item->total_outstanding, 2) }}</td>
                                <td class="px-4 py-4 border-r border-white/5">{{ $item->active_loans }}</td>
                                <td class="px-4 py-4 text-teal-400 border-r border-white/5">{{ $item->settled_loans }}</td>
                                <td class="px-4 py-4 text-rose-400 border-r border-white/5">{{ $item->defaulted_loans }}</td>
                                <td class="px-4 py-4">
                                    <span class="text-sm font-medium {{ $item->collection_rate >= 80 ? 'text-emerald-400' : ($item->collection_rate >= 60 ? 'text-amber-400' : 'text-rose-400') }}">
                                        {{ number_format($item->collection_rate, 2) }}%
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-4 py-8 text-center text-slate-400">
                                    No data available.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Performance by Group --}}
        @if($performanceByGroup->isNotEmpty())
            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                <h2 class="text-xl font-semibold text-white mb-4">Performance by Customer Group</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full w-full text-base text-slate-300">
                        <thead>
                            <tr class="text-base font-semibold uppercase tracking-[0.25em] text-white/80 text-center border-b-2 border-white/20">
                                <th class="px-4 py-4 text-lg border-r border-white/10">Group</th>
                                <th class="px-4 py-4 text-lg border-r border-white/10">Total Loans</th>
                                <th class="px-4 py-4 text-lg border-r border-white/10">Total Principal</th>
                                <th class="px-4 py-4 text-lg border-r border-white/10">Total Disbursed</th>
                                <th class="px-4 py-4 text-lg border-r border-white/10">Total Collected</th>
                                <th class="px-4 py-4 text-lg border-r border-white/10">Total Outstanding</th>
                                <th class="px-4 py-4 text-lg border-r border-white/10">Active</th>
                                <th class="px-4 py-4 text-lg border-r border-white/10">Settled</th>
                                <th class="px-4 py-4 text-lg">Collection Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($performanceByGroup as $item)
                                <tr class="border-t border-white/40 text-center hover:bg-white/5 transition">
                                    <td class="px-4 py-4 font-medium text-white border-r border-white/5">
                                        {{ $item->customerGroup->name ?? 'N/A' }}
                                    </td>
                                    <td class="px-4 py-4 border-r border-white/5">{{ $item->total_loans }}</td>
                                    <td class="px-4 py-4 border-r border-white/5">ZMW {{ number_format($item->total_principal, 2) }}</td>
                                    <td class="px-4 py-4 border-r border-white/5">ZMW {{ number_format($item->total_disbursed, 2) }}</td>
                                    <td class="px-4 py-4 text-emerald-400 border-r border-white/5">ZMW {{ number_format($item->total_collected, 2) }}</td>
                                    <td class="px-4 py-4 text-amber-400 border-r border-white/5">ZMW {{ number_format($item->total_outstanding, 2) }}</td>
                                    <td class="px-4 py-4 border-r border-white/5">{{ $item->active_loans }}</td>
                                    <td class="px-4 py-4 text-teal-400 border-r border-white/5">{{ $item->settled_loans }}</td>
                                    <td class="px-4 py-4">
                                        <span class="text-sm font-medium {{ $item->collection_rate >= 80 ? 'text-emerald-400' : ($item->collection_rate >= 60 ? 'text-amber-400' : 'text-rose-400') }}">
                                            {{ number_format($item->collection_rate, 2) }}%
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Performance by Company --}}
        @if($performanceByCompany->isNotEmpty())
            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                <h2 class="text-xl font-semibold text-white mb-4">Performance by Company</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full w-full text-base text-slate-300">
                        <thead>
                            <tr class="text-base font-semibold uppercase tracking-[0.25em] text-white/80 text-center border-b-2 border-white/20">
                                <th class="px-4 py-4 text-lg border-r border-white/10">Company</th>
                                <th class="px-4 py-4 text-lg border-r border-white/10">Relationship Manager</th>
                                <th class="px-4 py-4 text-lg border-r border-white/10">Total Loans</th>
                                <th class="px-4 py-4 text-lg border-r border-white/10">Total Principal</th>
                                <th class="px-4 py-4 text-lg border-r border-white/10">Total Disbursed</th>
                                <th class="px-4 py-4 text-lg border-r border-white/10">Total Collected</th>
                                <th class="px-4 py-4 text-lg border-r border-white/10">Total Outstanding</th>
                                <th class="px-4 py-4 text-lg border-r border-white/10">Active</th>
                                <th class="px-4 py-4 text-lg border-r border-white/10">Settled</th>
                                <th class="px-4 py-4 text-lg">Collection Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($performanceByCompany as $item)
                                <tr class="border-t border-white/40 text-center hover:bg-white/5 transition">
                                    <td class="px-4 py-4 font-medium text-white border-r border-white/5">
                                        {{ $item->company->name ?? 'N/A' }}
                                    </td>
                                    <td class="px-4 py-4 text-slate-400 border-r border-white/5">
                                        @if($item->company && $item->company->relationshipManager)
                                            {{ $item->company->relationshipManager->first_name }} {{ $item->company->relationshipManager->last_name }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 border-r border-white/5">{{ $item->total_loans }}</td>
                                    <td class="px-4 py-4 border-r border-white/5">ZMW {{ number_format($item->total_principal, 2) }}</td>
                                    <td class="px-4 py-4 border-r border-white/5">ZMW {{ number_format($item->total_disbursed, 2) }}</td>
                                    <td class="px-4 py-4 text-emerald-400 border-r border-white/5">ZMW {{ number_format($item->total_collected, 2) }}</td>
                                    <td class="px-4 py-4 text-amber-400 border-r border-white/5">ZMW {{ number_format($item->total_outstanding, 2) }}</td>
                                    <td class="px-4 py-4 border-r border-white/5">{{ $item->active_loans }}</td>
                                    <td class="px-4 py-4 text-teal-400 border-r border-white/5">{{ $item->settled_loans }}</td>
                                    <td class="px-4 py-4">
                                        <span class="text-sm font-medium {{ $item->collection_rate >= 80 ? 'text-emerald-400' : ($item->collection_rate >= 60 ? 'text-amber-400' : 'text-rose-400') }}">
                                            {{ number_format($item->collection_rate, 2) }}%
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
@endsection

