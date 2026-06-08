@extends('layouts.admin')

@section('title', 'Collateral Types | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Collateral Types - ' . $loanProduct->name,
            'description' => 'Manage collateral types and value ranges for this loan product',
            'buttons' => [
                [
                    'action' => 'secondary',
                    'text' => 'Back to Product',
                    'href' => route('admin.loan-products.show', $loanProduct),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>'
                ],
                [
                    'action' => 'primary',
                    'text' => 'Add Collateral Type',
                    'href' => route('admin.loan-products.collateral-types.create', $loanProduct),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>'
                ]
            ]
        ])

        @if($collateralTypes->count() > 0)
            <div class="rounded-3xl border border-white/10 bg-white/5 p-4 shadow-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full w-full text-sm text-slate-300">
                        <thead>
                            <tr class="text-sm font-semibold uppercase tracking-[0.25em] text-white/80 text-center border-b border-white/10">
                                <th class="px-4 py-4 text-base">Name</th>
                                <th class="px-4 py-4 text-base">Code</th>
                                <th class="px-4 py-4 text-base">Category</th>
                                <th class="px-4 py-4 text-base">Value Range</th>
                                <th class="px-4 py-4 text-base">Loan to Value</th>
                                <th class="px-4 py-4 text-base">Status</th>
                                <th class="px-4 py-4 text-base">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($collateralTypes as $collateralType)
                                <tr class="border-t border-white/5 text-center hover:bg-white/5 transition">
                                    <td class="px-4 py-3 font-medium text-white">{{ $collateralType->name }}</td>
                                    <td class="px-4 py-3 text-slate-400 font-mono">{{ $collateralType->code }}</td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full bg-cyan-500/20 px-2 py-1 text-xs text-cyan-300">
                                            {{ $collateralType->category }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        @if($collateralType->min_value || $collateralType->max_value)
                                            <div class="text-xs">
                                                @if($collateralType->min_value)
                                                    <div>Min: ZMW {{ number_format($collateralType->min_value, 2) }}</div>
                                                @endif
                                                @if($collateralType->max_value)
                                                    <div>Max: ZMW {{ number_format($collateralType->max_value, 2) }}</div>
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-slate-500">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @if($collateralType->loan_to_value_ratio)
                                            <span class="text-amber-300">{{ number_format($collateralType->loan_to_value_ratio, 2) }}%</span>
                                        @else
                                            <span class="text-slate-500">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full px-2 py-1 text-xs {{ $collateralType->is_active ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300' }}">
                                            {{ $collateralType->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="inline-flex items-center gap-2">
                                            <a href="{{ route('admin.loan-products.collateral-types.show', [$loanProduct, $collateralType]) }}" 
                                               class="inline-flex items-center justify-center rounded-full bg-blue-500/20 border border-blue-500/50 px-3 py-1.5 text-xs font-medium text-blue-300 hover:bg-blue-500/30 hover:border-blue-500 transition">
                                                View
                                            </a>
                                            <a href="{{ route('admin.loan-products.collateral-types.edit', [$loanProduct, $collateralType]) }}" 
                                               class="inline-flex items-center justify-center rounded-full bg-amber-500/20 border border-amber-500/50 px-3 py-1.5 text-xs font-medium text-amber-300 hover:bg-amber-500/30 hover:border-amber-500 transition">
                                                Edit
                                            </a>
                                            <form method="POST" 
                                                  action="{{ route('admin.loan-products.collateral-types.destroy', [$loanProduct, $collateralType]) }}" 
                                                  class="inline"
                                                  onsubmit="return confirm('Are you sure you want to delete this collateral type?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" 
                                                        class="inline-flex items-center justify-center rounded-full bg-rose-500/20 border border-rose-500/50 px-3 py-1.5 text-xs font-medium text-rose-300 hover:bg-rose-500/30 hover:border-rose-500 transition">
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <div class="rounded-3xl border border-white/10 bg-white/5 p-8 shadow-lg text-center">
                <p class="text-slate-400 mb-4">No collateral types have been created for this product yet.</p>
                <a href="{{ route('admin.loan-products.collateral-types.create', $loanProduct) }}" 
                   class="inline-flex items-center gap-2 rounded-2xl border border-emerald-500/40 bg-emerald-500/10 px-4 py-2 text-sm font-semibold text-emerald-300 hover:bg-emerald-500/20 transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Create First Collateral Type
                </a>
            </div>
        @endif
    </div>
@endsection

