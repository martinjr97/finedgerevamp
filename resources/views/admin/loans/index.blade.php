@extends('layouts.admin')

@section('title', 'Loans | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Loans',
            'buttons' => [
                [
                    'action' => 'export',
                    'text' => 'Export Excel',
                    'href' => route('admin.loans.export', request()->query()),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
                    'can' => auth('admin')->user()?->can('loans.export')
                ],
                [
                    'action' => 'default',
                    'text' => "Today's Payments",
                    'href' => route('admin.loans.todays-payments'),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>',
                    'can' => auth('admin')->user()?->can('loans.view')
                ]
            ]
        ])

        {{-- Filters --}}
        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <form method="GET" action="{{ route('admin.loans.index') }}" class="space-y-4">
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    {{-- Search --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Search</label>
                        <input type="text" name="search" value="{{ request('search') }}" 
                               placeholder="Loan number, customer name, email, phone..."
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
                            <option value="completed" @selected(request('status') === 'completed')>Completed</option>
                            <option value="settled" @selected(request('status') === 'settled')>Settled</option>
                            <option value="defaulted" @selected(request('status') === 'defaulted')>Defaulted</option>
                            <option value="cancelled" @selected(request('status') === 'cancelled')>Cancelled</option>
                        </select>
                    </div>

                    {{-- Disbursement Status --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Disbursement</label>
                        <select name="disbursement_status" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                            <option value="">All</option>
                            <option value="pending" @selected(request('disbursement_status') === 'pending')>Pending</option>
                            <option value="processing" @selected(request('disbursement_status') === 'processing')>Processing</option>
                            <option value="completed" @selected(request('disbursement_status') === 'completed')>Completed</option>
                            <option value="failed" @selected(request('disbursement_status') === 'failed')>Failed</option>
                        </select>
                    </div>

                    {{-- Loan Product --}}
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
                                    {{ $group->name }} ({{ $group->loanProduct->name ?? 'N/A' }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Accrual Type --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Accrual Type</label>
                        <select name="accrual_type" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                            <option value="">All Types</option>
                            <option value="daily" @selected(request('accrual_type') === 'daily')>Daily</option>
                            <option value="at_beginning" @selected(request('accrual_type') === 'at_beginning')>At Beginning</option>
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
                    <a href="{{ route('admin.loans.index') }}" class="rounded-2xl border border-white/10 px-6 py-2 text-sm font-medium text-white/80 hover:bg-white/10 transition">
                        Clear Filters
                    </a>
                </div>
            </form>
        </div>

        {{-- Loans Table --}}
        <div class="admin-data-table">
            <div class="admin-data-table__scroll">
                <table class="min-w-full w-full text-base text-slate-300">
                    <thead>
                        <tr class="font-semibold uppercase text-white/80 text-center">
                            <th>Loan Number</th>
                            <th>Customer</th>
                            <th>Product</th>
                            <th>Principal</th>
                            <th>Booked Total</th>
                            <th title="Booked accounting balance owed">Booked Outstanding</th>
                            <th>Tenure</th>
                            <th>Start Date</th>
                            <th>Status</th>
                            <th scope="col" class="admin-data-table__actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($loans as $loan)
                            <tr class="text-center">
                                <td class="font-medium text-white">
                                    {{ $loan->loan_number }}
                                </td>
                                <td>
                                    <div class="text-left">
                                        <div class="font-medium text-white">{{ $loan->customer->full_name ?? 'N/A' }}</div>
                                        <div class="text-sm text-slate-400">{{ $loan->customer->email ?? 'N/A' }}</div>
                                    </div>
                                </td>
                                <td>
                                    <span class="rounded-full bg-cyan-500/20 px-2 py-1 text-sm text-cyan-300">
                                        {{ $loan->loanProduct->name ?? '—' }}
                                    </span>
                                </td>
                                <td class="font-medium text-white">
                                    ZMW {{ number_format($loan->principal_amount, 2) }}
                                </td>
                                <td class="font-medium text-white">
                                    ZMW {{ number_format($loan->total_amount, 2) }}
                                </td>
                                <td class="font-medium text-white">
                                    ZMW {{ number_format($loan->outstanding_balance, 2) }}
                                </td>
                                <td>
                                    {{ $loan->tenure_months }} {{ $loan->tenure_months === 1 ? 'Month' : 'Months' }}
                                </td>
                                <td class="text-slate-400">
                                    {{ $loan->loan_start_date->format('M d, Y') }}
                                </td>
                                <td>
                                    @php
                                        $statusTextColors = [
                                            'pending_approval' => 'text-amber-400',
                                            'approved' => 'text-blue-400',
                                            'active' => 'text-emerald-400',
                                            'completed' => 'text-green-400',
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
                                <td>
                                    <div class="inline-flex items-center gap-3">
                                        @can('loans.view')
                                        <a href="{{ route('admin.loans.show', $loan) }}" 
                                           class="inline-flex items-center gap-1.5 rounded-lg border border-blue-400/50 bg-blue-500/10 px-2.5 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-500/20 transition">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                                <td colspan="10" class="py-8 text-center text-slate-400">
                                    No loans found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="admin-table-footer">
                {{ $loans->withQueryString()->links() }}
            </div>
        </div>
    </div>
@endsection
