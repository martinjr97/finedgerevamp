@extends('layouts.admin')

@section('title', 'Customers | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Customers',
            'buttons' => [
                [
                    'action' => 'export',
                    'text' => 'Export to Excel',
                    'href' => route('admin.customers.export', request()->all()),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>'
                ],
                [
                    'action' => 'create',
                    'text' => 'Create Customer',
                    'href' => route('admin.customers.select-product-type'),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>',
                    'can' => auth('admin')->user()?->can('customers.create')
                ]
            ]
        ])

        {{-- Filters --}}
        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <form method="GET" action="{{ route('admin.customers.index') }}" class="space-y-4">
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    {{-- Search --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Search</label>
                        <input type="text" name="search" value="{{ request('search') }}" 
                               placeholder="Name, email, phone, national ID..."
                               class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                    </div>

                    {{-- Status --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Account Status</label>
                        <select name="status" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                            <option value="">All Statuses</option>
                            <option value="active" @selected(request('status') === 'active')>Active</option>
                            <option value="pending" @selected(request('status') === 'pending')>Pending</option>
                            <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                            <option value="suspended" @selected(request('status') === 'suspended')>Suspended</option>
                        </select>
                    </div>

                    {{-- Approval Status --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Approval Status</label>
                        <select name="approval_status" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                            <option value="">All Approval Statuses</option>
                            <option value="pending" @selected(request('approval_status') === 'pending')>Pending</option>
                            <option value="approved" @selected(request('approval_status') === 'approved')>Approved</option>
                            <option value="rejected" @selected(request('approval_status') === 'rejected')>Rejected</option>
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
                    <a href="{{ route('admin.customers.index') }}" class="rounded-2xl border border-white/10 px-6 py-2 text-sm font-medium text-white/80 hover:bg-white/10 transition">
                        Clear Filters
                    </a>
                </div>
            </form>
        </div>

        <div class="rounded-3xl border border-white/10 bg-white/5 p-4 shadow-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full w-full text-base text-slate-300">
                    <thead>
                        <tr class="text-base font-semibold uppercase tracking-[0.25em] text-white/80 text-center border-b-2 border-white/20">
                            <th class="px-4 py-4 text-lg border-r border-white/10">Name</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Email</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Phone</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">National ID</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Product</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Company / Group</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Account Status</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Approval Status</th>
                            <th class="px-4 py-4 text-lg">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($customers as $customer)
                            <tr class="border-t border-white/40 text-center hover:bg-white/5 transition">
                                <td class="px-4 py-4 font-medium text-white border-r border-white/5">
                                    {{ $customer->full_name }}
                                </td>
                                <td class="px-4 py-4 border-r border-white/5">{{ $customer->email }}</td>
                                <td class="px-4 py-4 border-r border-white/5">{{ $customer->phone ?? '—' }}</td>
                                <td class="px-4 py-4 border-r border-white/5">{{ $customer->national_id ?? '—' }}</td>
                                <td class="px-4 py-4 border-r border-white/5">
                                    <span class="rounded-full bg-cyan-500/20 px-2 py-1 text-sm text-cyan-300">
                                        {{ $customer->loanProduct->name ?? '—' }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 border-r border-white/5">
                                    @if($customer->loanProduct && ($customer->loanProduct->category === 'character' || $customer->loanProduct->category === 'collateral'))
                                        <span class="rounded-full bg-purple-500/20 px-2 py-1 text-sm text-purple-300 font-normal">
                                            {{ $customer->customerGroup->name ?? '—' }}
                                        </span>
                                    @else
                                        {{ $customer->company->name ?? '—' }}
                                    @endif
                                </td>
                                <td class="px-4 py-4 border-r border-white/5">
                                    <span class="text-sm font-medium {{ $customer->status === 'active' ? 'text-emerald-400' : ($customer->status === 'pending' ? 'text-amber-400' : 'text-rose-400') }}">
                                        {{ ucfirst($customer->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 border-r border-white/5">
                                    <span class="text-sm font-medium {{ $customer->approval_status === 'approved' ? 'text-emerald-400' : ($customer->approval_status === 'pending' ? 'text-amber-400' : 'text-rose-400') }}">
                                        {{ ucfirst($customer->approval_status ?? 'unknown') }}
                                    </span>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="inline-flex items-center gap-3">
                                        @can('customers.view')
                                        <a href="{{ route('admin.customers.show', $customer) }}" class="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-blue-500/40 to-blue-600/40 border-2 border-blue-400/70 px-4 py-2 text-base font-semibold text-blue-200 hover:from-blue-500/60 hover:to-blue-600/60 hover:border-blue-400 hover:text-white transition shadow-md shadow-blue-500/20">
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
                                <td colspan="9" class="px-4 py-8 text-center text-slate-400">No customers found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            {{-- Pagination --}}
            @if($customers->hasPages())
                <div class="mt-6 flex items-center justify-center">
                    {{ $customers->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
