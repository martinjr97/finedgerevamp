@extends('layouts.admin')

@section('title', $product->name . ' | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => $product->name,
            'description' => $product->description ?? 'Loan product details and information',
            'buttons' => [
                [
                    'action' => 'secondary',
                    'text' => 'Back to Products',
                    'href' => route('admin.loan-products.index'),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>'
                ],
                [
                    'action' => 'edit',
                    'text' => 'Edit Product',
                    'href' => route('admin.loan-products.edit', $product),
                    'can' => auth('admin')->user()?->can('loan-products.update'),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>'
                ]
            ]
        ])

        {{-- Product Information --}}
        <div class="rounded-3xl border-2 border-blue-500/30 bg-blue-950/30 p-6 shadow-lg">
            <h2 class="mb-6 text-xl font-semibold text-white flex items-center gap-2">
                <span class="w-1 h-6 rounded-full bg-blue-500"></span>Product Information
            </h2>
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Code</p>
                    <p class="text-sm font-medium text-white">{{ $product->code }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Category</p>
                    <span class="inline-block rounded-full bg-cyan-500/20 px-2 py-1 text-xs text-cyan-300">
                        {{ ucwords(str_replace('_', ' ', $product->category)) }}
                    </span>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Status</p>
                    <span class="inline-block rounded-full px-2 py-1 text-xs {{ $product->is_active ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300' }}">
                        {{ $product->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>
                @if($product->tenure_months)
                    <div>
                        <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Tenure</p>
                        <p class="text-sm font-medium text-white">{{ $product->tenure_months }} months</p>
                    </div>
                @endif
                @if($product->max_amount)
                    <div>
                        <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Maximum Amount</p>
                        <p class="text-sm font-medium text-white">ZMW {{ number_format($product->max_amount, 2) }}</p>
                    </div>
                @endif
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Requires Collateral</p>
                    <span class="inline-block rounded-full px-2 py-1 text-xs {{ $product->requires_collateral ? 'bg-amber-500/20 text-amber-300' : 'bg-slate-500/20 text-slate-300' }}">
                        {{ $product->requires_collateral ? 'Yes' : 'No' }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Category-Specific Content --}}
        @if($product->category === 'mou' && isset($companies))
            {{-- MOU: Show Companies --}}
            <div class="rounded-3xl border-2 border-blue-500/30 bg-blue-950/30 p-6 shadow-lg">
                <div class="mb-6 flex items-center justify-between">
                    <h2 class="text-xl font-semibold text-white flex items-center gap-2">
                        <span class="w-1 h-6 rounded-full bg-blue-500"></span>Partner Companies
                    </h2>
                    <a href="{{ route('admin.companies.create') }}" class="inline-flex items-center gap-2 rounded-2xl border border-emerald-500/40 bg-emerald-500/10 px-4 py-2 text-sm font-semibold text-emerald-300 hover:bg-emerald-500/20 transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Add New Company
                    </a>
                </div>
                @if($companies->count() > 0)
                    <div class="overflow-x-auto">
                        <table data-datatable="true" data-datatable-per-page="5" class="min-w-full w-full text-sm text-slate-300">
                            <thead>
                                <tr class="text-sm font-semibold uppercase tracking-[0.25em] text-white/80 text-center border-b border-white/10">
                                    <th class="px-4 py-4 text-base">Company Name</th>
                                    <th class="px-4 py-4 text-base">Code</th>
                                    <th class="px-4 py-4 text-base">Type</th>
                                    <th class="px-4 py-4 text-base">Customers</th>
                                    <th class="px-4 py-4 text-base">Status</th>
                                    <th class="px-4 py-4 text-base">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($companies as $company)
                                    <tr class="border-t border-white/5 text-center">
                                        <td class="px-4 py-3 font-medium text-white">{{ $company->name }}</td>
                                        <td class="px-4 py-3">{{ $company->code }}</td>
                                        <td class="px-4 py-3">
                                            <span class="rounded-full bg-purple-500/20 px-2 py-1 text-xs text-purple-300">
                                                {{ ucfirst($company->type) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">{{ $company->customers_count }}</td>
                                        <td class="px-4 py-3">
                                            <span class="rounded-full px-2 py-1 text-xs {{ $company->status === 'active' ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300' }}">
                                                {{ ucfirst($company->status) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <a href="{{ route('admin.companies.show', $company) }}" class="inline-flex items-center justify-center rounded-full bg-blue-500/20 border border-blue-500/50 px-3 py-1.5 text-xs font-medium text-blue-300 hover:bg-blue-500/30 hover:border-blue-500 transition">View</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-slate-400 text-center py-8">No companies found for this product.</p>
                @endif
            </div>
        @elseif(($product->category === 'character' || $product->category === 'collateral' || $product->category === 'group_loans') && isset($customerGroups))
            {{-- Character/Collateral: Show Customer Groups --}}
            <div class="rounded-3xl border-2 border-blue-500/30 bg-blue-950/30 p-6 shadow-lg">
                <div class="mb-6 flex items-center justify-between">
                    <h2 class="text-xl font-semibold text-white flex items-center gap-2">
                        <span class="w-1 h-6 rounded-full bg-blue-500"></span>Customer Groups
                    </h2>
                    <a href="{{ route('admin.customer-groups.create', ['loan_product_id' => $product->id]) }}" class="inline-flex items-center gap-2 rounded-2xl border border-emerald-500/40 bg-emerald-500/10 px-4 py-2 text-sm font-semibold text-emerald-300 hover:bg-emerald-500/20 transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Add New Group
                    </a>
                </div>
                @if($customerGroups->count() > 0)
                    <div class="overflow-x-auto">
                        <table data-datatable="true" data-datatable-per-page="5" class="min-w-full w-full text-sm text-slate-300">
                            <thead>
                                <tr class="text-sm font-semibold uppercase tracking-[0.25em] text-white/80 text-center border-b border-white/10">
                                    <th class="px-4 py-4 text-base">Group Name</th>
                                    <th class="px-4 py-4 text-base">Code</th>
                                    <th class="px-4 py-4 text-base">Risk Level</th>
                                    <th class="px-4 py-4 text-base">Rate Type</th>
                                    <th class="px-4 py-4 text-base">Customers</th>
                                    <th class="px-4 py-4 text-base">Max Loan Amount</th>
                                    <th class="px-4 py-4 text-base">Status</th>
                                    <th class="px-4 py-4 text-base">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($customerGroups as $group)
                                    <tr class="border-t border-white/5 text-center">
                                        <td class="px-4 py-3 font-medium text-white">{{ $group->name }}</td>
                                        <td class="px-4 py-3">{{ $group->code }}</td>
                                        <td class="px-4 py-3">
                                            <span class="rounded-full px-2 py-1 text-xs {{ $group->risk_level === 'low' ? 'bg-emerald-500/20 text-emerald-300' : ($group->risk_level === 'medium' ? 'bg-amber-500/20 text-amber-300' : 'bg-rose-500/20 text-rose-300') }}">
                                                {{ ucfirst($group->risk_level) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            @if($group->loanRateType)
                                                <div class="flex flex-col items-center gap-1">
                                                    <span class="text-xs font-medium text-white">{{ $group->loanRateType->name }}</span>
                                                    <span class="text-xs text-slate-400 font-mono">{{ $group->loanRateType->code }}</span>
                                                </div>
                                            @else
                                                <span class="text-slate-500 text-xs">Not assigned</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">{{ $group->customers_count }}</td>
                                        <td class="px-4 py-3">
                                            @if($group->max_loan_amount)
                                                ZMW {{ number_format($group->max_loan_amount, 2) }}
                                            @else
                                                <span class="text-slate-500">—</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="rounded-full px-2 py-1 text-xs {{ $group->is_active ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300' }}">
                                                {{ $group->is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <a href="{{ route('admin.customer-groups.show', $group) }}" 
                                               class="inline-flex items-center justify-center rounded-full bg-blue-500/20 border border-blue-500/50 px-3 py-1.5 text-xs font-medium text-blue-300 hover:bg-blue-500/30 hover:border-blue-500 transition">
                                                View
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-slate-400 text-center py-8">No customer groups found for this product.</p>
                @endif
            </div>
        @elseif($product->category === 'marketeer' && isset($markets))
            {{-- Marketeer: Show Markets --}}
            <div class="rounded-3xl border-2 border-blue-500/30 bg-blue-950/30 p-6 shadow-lg">
                <div class="mb-6 flex items-center justify-between">
                    <h2 class="text-xl font-semibold text-white flex items-center gap-2">
                        <span class="w-1 h-6 rounded-full bg-blue-500"></span>Markets
                    </h2>
                    <a href="{{ route('admin.markets.create') }}" class="inline-flex items-center gap-2 rounded-2xl border border-emerald-500/40 bg-emerald-500/10 px-4 py-2 text-sm font-semibold text-emerald-300 hover:bg-emerald-500/20 transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Add New Market
                    </a>
                </div>
                @if($markets->count() > 0)
                    <div class="overflow-x-auto">
                        <table data-datatable="true" data-datatable-per-page="5" class="min-w-full w-full text-sm text-slate-300">
                            <thead>
                                <tr class="text-sm font-semibold uppercase tracking-[0.25em] text-white/80 text-center border-b border-white/10">
                                    <th class="px-4 py-4 text-base">Market Name</th>
                                    <th class="px-4 py-4 text-base">Code</th>
                                    <th class="px-4 py-4 text-base">Location</th>
                                    <th class="px-4 py-4 text-base">Contact Person</th>
                                    <th class="px-4 py-4 text-base">Portfolio Manager</th>
                                    <th class="px-4 py-4 text-base">Customers</th>
                                    <th class="px-4 py-4 text-base">Status</th>
                                    <th class="px-4 py-4 text-base">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($markets as $market)
                                    <tr class="border-t border-white/5 text-center">
                                        <td class="px-4 py-3 font-medium text-white">{{ $market->name }}</td>
                                        <td class="px-4 py-3">{{ $market->code }}</td>
                                        <td class="px-4 py-3">
                                            <span class="text-xs">{{ $market->province->name }}, {{ $market->district->name }}</span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="text-xs">
                                                <div class="font-medium">{{ $market->contact_person_name }}</div>
                                                <div class="text-slate-400">{{ $market->contact_person_phone }}</div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            @if($market->portfolioManager)
                                                <span class="text-xs">{{ $market->portfolioManager->first_name }} {{ $market->portfolioManager->last_name }}</span>
                                            @else
                                                <span class="text-slate-500 text-xs">—</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">{{ $market->marketeer_customer_details_count ?? 0 }}</td>
                                        <td class="px-4 py-3">
                                            <span class="rounded-full px-2 py-1 text-xs {{ $market->is_active ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300' }}">
                                                {{ $market->is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <a href="{{ route('admin.markets.show', $market) }}" class="inline-flex items-center justify-center rounded-full bg-blue-500/20 border border-blue-500/50 px-3 py-1.5 text-xs font-medium text-blue-300 hover:bg-blue-500/30 hover:border-blue-500 transition">View</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-slate-400 text-center py-8">No markets found for this product.</p>
                @endif
            </div>
        @elseif($product->category === 'government' && isset($stats))
            {{-- Government: Show Stats --}}
            <div class="rounded-3xl border-2 border-blue-500/30 bg-blue-950/30 p-6 shadow-lg">
                <h2 class="mb-6 text-xl font-semibold text-white flex items-center gap-2">
                    <span class="w-1 h-6 rounded-full bg-blue-500"></span>Statistics
                </h2>
                <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="text-xs uppercase tracking-wide text-slate-400 mb-2">Total Customers</p>
                        <p class="text-2xl font-bold text-white">{{ number_format($stats['total_customers']) }}</p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="text-xs uppercase tracking-wide text-slate-400 mb-2">Active Customers</p>
                        <p class="text-2xl font-bold text-emerald-300">{{ number_format($stats['active_customers']) }}</p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="text-xs uppercase tracking-wide text-slate-400 mb-2">Pending Customers</p>
                        <p class="text-2xl font-bold text-amber-300">{{ number_format($stats['pending_customers']) }}</p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="text-xs uppercase tracking-wide text-slate-400 mb-2">Suspended Customers</p>
                        <p class="text-2xl font-bold text-rose-300">{{ number_format($stats['suspended_customers']) }}</p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="text-xs uppercase tracking-wide text-slate-400 mb-2">Total Loan Capacity</p>
                        <p class="text-2xl font-bold text-white">ZMW {{ number_format($stats['total_loan_amount'], 2) }}</p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="text-xs uppercase tracking-wide text-slate-400 mb-2">Average Loan Capacity</p>
                        <p class="text-2xl font-bold text-white">ZMW {{ number_format($stats['average_loan_amount'] ?? 0, 2) }}</p>
                    </div>
                </div>
            </div>

            {{-- Government: Show Customer Groups --}}
            @if(isset($customerGroups))
                <div class="rounded-3xl border-2 border-blue-500/30 bg-blue-950/30 p-6 shadow-lg">
                    <div class="mb-6 flex items-center justify-between">
                        <h2 class="text-xl font-semibold text-white flex items-center gap-2">
                            <span class="w-1 h-6 rounded-full bg-blue-500"></span>Customer Groups
                        </h2>
                    </div>
                    @if($customerGroups->count() > 0)
                        <div class="overflow-x-auto">
                            <table data-datatable="true" data-datatable-per-page="5" class="min-w-full w-full text-sm text-slate-300">
                                <thead>
                                    <tr class="text-sm font-semibold uppercase tracking-[0.25em] text-white/80 text-center border-b border-white/10">
                                        <th class="px-4 py-4 text-base">Group Name</th>
                                        <th class="px-4 py-4 text-base">Code</th>
                                        <th class="px-4 py-4 text-base">Risk Level</th>
                                        <th class="px-4 py-4 text-base">Rate Type</th>
                                        <th class="px-4 py-4 text-base">Customers</th>
                                        <th class="px-4 py-4 text-base">Max Loan Amount</th>
                                        <th class="px-4 py-4 text-base">Status</th>
                                        <th class="px-4 py-4 text-base">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($customerGroups as $group)
                                        <tr class="border-t border-white/5 text-center">
                                            <td class="px-4 py-3 font-medium text-white">{{ $group->name }}</td>
                                            <td class="px-4 py-3">{{ $group->code }}</td>
                                            <td class="px-4 py-3">
                                                <span class="rounded-full px-2 py-1 text-xs {{ $group->risk_level === 'low' ? 'bg-emerald-500/20 text-emerald-300' : ($group->risk_level === 'medium' ? 'bg-amber-500/20 text-amber-300' : 'bg-rose-500/20 text-rose-300') }}">
                                                    {{ ucfirst($group->risk_level) }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3">
                                                @if($group->loanRateType)
                                                    <div class="flex flex-col items-center gap-1">
                                                        <span class="text-xs font-medium text-white">{{ $group->loanRateType->name }}</span>
                                                        <span class="text-xs text-slate-400 font-mono">{{ $group->loanRateType->code }}</span>
                                                    </div>
                                                @else
                                                    <span class="text-slate-500 text-xs">Not assigned</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3">{{ $group->customers_count }}</td>
                                            <td class="px-4 py-3">
                                                @if($group->max_loan_amount)
                                                    ZMW {{ number_format($group->max_loan_amount, 2) }}
                                                @else
                                                    <span class="text-slate-500">—</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3">
                                                <span class="rounded-full px-2 py-1 text-xs {{ $group->is_active ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300' }}">
                                                    {{ $group->is_active ? 'Active' : 'Inactive' }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="inline-flex items-center gap-2">
                                                    <a href="{{ route('admin.customer-groups.show', $group) }}" 
                                                       class="rounded-full bg-blue-500/20 border border-blue-500/50 px-3 py-1.5 text-xs font-medium text-blue-300 hover:bg-blue-500/30 hover:border-blue-500 transition">
                                                        View
                                                    </a>
                                                    <a href="{{ route('admin.customers.index', ['customer_group_id' => $group->id]) }}" 
                                                       class="rounded-full bg-cyan-500/20 border border-cyan-500/50 px-3 py-1.5 text-xs font-medium text-cyan-300 hover:bg-cyan-500/30 hover:border-cyan-500 transition">
                                                        View Customers
                                                    </a>
                                                    @if(auth('admin')->user()?->can('loan-rate-types.view'))
                                                        <a href="{{ route('admin.customer-groups.manage-rate-type', $group) }}" 
                                                           class="rounded-full bg-indigo-500/20 border border-indigo-500/50 px-3 py-1.5 text-xs font-medium text-indigo-300 hover:bg-indigo-500/30 hover:border-indigo-500 transition">
                                                            Manage Rates
                                                        </a>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-slate-400 text-center py-8">No customer groups found for this product.</p>
                    @endif
                </div>
            @endif
        @endif

        {{-- Actions Section --}}
        <div class="rounded-3xl border-2 border-blue-500/30 bg-blue-950/30 p-6 shadow-lg">
            <h2 class="mb-6 text-xl font-semibold text-white flex items-center gap-2">
                <span class="w-1 h-6 rounded-full bg-blue-500"></span>Actions
            </h2>
            <div class="flex flex-wrap gap-3">
                @if($product->category === 'government' && auth('admin')->user()?->can('loan-rate-types.view'))
                    <a href="{{ route('admin.loan-rate-types.index', ['product_id' => $product->id]) }}" class="inline-flex items-center gap-2 rounded-2xl border border-indigo-500/40 bg-indigo-500/10 px-4 py-3 text-sm font-semibold text-indigo-300 hover:bg-indigo-500/20 transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Manage Interest Rates
                    </a>
                @endif
                @if($product->category === 'collateral' && auth('admin')->user()?->can('loan-products.update'))
                    <a href="{{ route('admin.loan-products.collateral-types.index', $product) }}" class="inline-flex items-center gap-2 rounded-2xl border border-purple-500/40 bg-purple-500/10 px-4 py-3 text-sm font-semibold text-purple-300 hover:bg-purple-500/20 transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Manage Collateral Types
                    </a>
                @endif
                <a href="{{ route('admin.customers.create', ['product_id' => $product->id]) }}" class="inline-flex items-center gap-2 rounded-2xl border border-emerald-500/40 bg-emerald-500/10 px-4 py-3 text-sm font-semibold text-emerald-300 hover:bg-emerald-500/20 transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add New Customer
                </a>
                <button disabled class="inline-flex items-center gap-2 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-semibold text-slate-400 opacity-50 cursor-not-allowed">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    View Reports
                </button>
                <button disabled class="inline-flex items-center gap-2 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-semibold text-slate-400 opacity-50 cursor-not-allowed">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Configure Settings
                </button>
            </div>
        </div>
    </div>
@endsection
