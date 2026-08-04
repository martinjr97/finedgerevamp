@extends('layouts.admin')

@section('title', 'Customer Groups | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Customer Groups',
            'description' => 'View all customer groups across products and companies',
        ])

        {{-- Filters --}}
        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <form method="GET" action="{{ route('admin.customer-groups.index') }}" class="space-y-4">
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    {{-- Search --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Search</label>
                        <input type="text" name="search" value="{{ request('search') }}" 
                               placeholder="Group name, code, product..."
                               class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                    </div>

                    {{-- Company --}}
                    @if(auth('admin')->user()?->getCompanyFilterId() === null)
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
                    @endif

                    {{-- Loan Product --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Product</label>
                        <select name="loan_product_id" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                            <option value="">All Products</option>
                            @foreach($loanProducts as $product)
                                <option value="{{ $product->id }}" @selected(request('loan_product_id') == $product->id)>
                                    {{ $product->name }} @if($product->company) ({{ $product->company->name }}) @endif
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Status --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Status</label>
                        <select name="is_active" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                            <option value="">All Statuses</option>
                            <option value="1" @selected(request('is_active') === '1')>Active</option>
                            <option value="0" @selected(request('is_active') === '0')>Inactive</option>
                        </select>
                    </div>

                    {{-- Risk Level --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Risk Level</label>
                        <select name="risk_level" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                            <option value="">All Risk Levels</option>
                            <option value="low" @selected(request('risk_level') === 'low')>Low</option>
                            <option value="medium" @selected(request('risk_level') === 'medium')>Medium</option>
                            <option value="high" @selected(request('risk_level') === 'high')>High</option>
                        </select>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button type="submit" class="rounded-2xl bg-cyan-500/20 border border-cyan-500/50 px-6 py-2 text-sm font-medium text-cyan-300 hover:bg-cyan-500/30 transition">
                        Apply Filters
                    </button>
                    <a href="{{ route('admin.customer-groups.index') }}" class="rounded-2xl border border-white/10 px-6 py-2 text-sm font-medium text-white/80 hover:bg-white/10 transition">
                        Clear Filters
                    </a>
                </div>
            </form>
        </div>

        {{-- Customer Groups Table --}}
        <div class="admin-data-table">
            <div class="admin-data-table__scroll">
                <table class="min-w-full w-full text-base text-slate-300">
                    <thead>
                        <tr class="font-semibold uppercase text-white/80 text-center">
                            <th>Group Name</th>
                            <th>Code</th>
                            <th>Product</th>
                            <th>Company</th>
                            <th>Branch</th>
                            <th>Risk Level</th>
                            <th>Customers</th>
                            <th>Status</th>
                            <th scope="col" class="admin-data-table__actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($customerGroups as $group)
                            <tr class="text-center">
                                <td class="font-medium text-white">
                                    {{ $group->name }}
                                </td>
                                <td class="text-slate-400">
                                    <span class="font-mono text-sm">{{ $group->code }}</span>
                                </td>
                                <td>
                                    <div class="text-left">
                                        <div class="font-medium text-white">{{ $group->loanProduct->name ?? 'N/A' }}</div>
                                        <div class="text-xs text-slate-400">{{ $group->loanProduct->code ?? '' }}</div>
                                    </div>
                                </td>
                                <td>
                                    @if($group->loanProduct?->company)
                                        <span class="rounded-full bg-cyan-500/20 px-2 py-1 text-sm text-cyan-300">
                                            {{ $group->loanProduct->company->name }}
                                        </span>
                                    @else
                                        <span class="text-slate-500 text-sm">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($group->branch)
                                        <span class="text-sm text-white">{{ $group->branch->name }}</span>
                                    @else
                                        <span class="text-slate-500 text-sm">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="inline-block rounded-full px-2 py-1 text-xs font-medium
                                        {{ $group->risk_level === 'low' ? 'bg-emerald-500/20 text-emerald-300' : 
                                           ($group->risk_level === 'medium' ? 'bg-amber-500/20 text-amber-300' : 'bg-rose-500/20 text-rose-300') }}">
                                        {{ ucfirst($group->risk_level) }}
                                    </span>
                                </td>
                                <td class="font-medium text-white">
                                    {{ $group->customers_count ?? $group->customers->count() }}
                                </td>
                                <td>
                                    <span class="text-sm font-medium {{ $group->is_active ? 'text-emerald-400' : 'text-slate-400' }}">
                                        {{ $group->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="inline-flex items-center gap-3">
                                        @can('customers.view')
                                        <a href="{{ route('admin.customer-groups.show', $group) }}" 
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
                                <td colspan="9" class="py-8 text-center text-slate-400">
                                    No customer groups found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="admin-table-footer">
                {{ $customerGroups->withQueryString()->links() }}
            </div>
        </div>
    </div>
@endsection

