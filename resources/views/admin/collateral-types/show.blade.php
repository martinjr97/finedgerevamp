@extends('layouts.admin')

@section('title', 'Collateral Type: '.$collateralType->name.' | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => $collateralType->name,
            'description' => 'Collateral Type Details',
            'buttons' => [
                [
                    'action' => 'secondary',
                    'text' => 'Back to Collateral Types',
                    'href' => route('admin.loan-products.collateral-types.index', $loanProduct),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>'
                ],
                [
                    'action' => 'edit',
                    'text' => 'Edit',
                    'href' => route('admin.loan-products.collateral-types.edit', [$loanProduct, $collateralType]),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>'
                ]
            ]
        ])

        <div class="grid gap-6 md:grid-cols-2">
            {{-- Collateral Type Information --}}
            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
                <h2 class="text-xl font-semibold text-white">Collateral Type Information</h2>
                <div class="space-y-3 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Name:</span>
                        <span class="font-medium text-white">{{ $collateralType->name }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Code:</span>
                        <span class="text-xs text-slate-400 font-mono">{{ $collateralType->code }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Product:</span>
                        <a href="{{ route('admin.loan-products.show', $loanProduct) }}" class="font-medium text-cyan-400 hover:text-cyan-300 hover:underline transition">
                            {{ $loanProduct->name }}
                        </a>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Category:</span>
                        <span class="rounded-full bg-cyan-500/20 px-2 py-1 text-xs text-cyan-300">
                            {{ $collateralType->category }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Status:</span>
                        <span class="rounded-full px-2 py-1 text-xs {{ $collateralType->is_active ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300' }}">
                            {{ $collateralType->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- Value Information --}}
            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
                <h2 class="text-xl font-semibold text-white">Value Information</h2>
                <div class="space-y-3 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Minimum Value:</span>
                        <span class="font-medium text-white">
                            @if($collateralType->min_value)
                                ZMW {{ number_format($collateralType->min_value, 2) }}
                            @else
                                <span class="text-slate-500">—</span>
                            @endif
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Maximum Value:</span>
                        <span class="font-medium text-white">
                            @if($collateralType->max_value)
                                ZMW {{ number_format($collateralType->max_value, 2) }}
                            @else
                                <span class="text-slate-500">—</span>
                            @endif
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Loan to Value Ratio:</span>
                        <span class="font-medium text-amber-300">
                            @if($collateralType->loan_to_value_ratio)
                                {{ number_format($collateralType->loan_to_value_ratio, 2) }}%
                            @else
                                <span class="text-slate-500">—</span>
                            @endif
                        </span>
                    </div>
                    @if($collateralType->min_value && $collateralType->max_value && $collateralType->loan_to_value_ratio)
                        <div class="mt-4 pt-4 border-t border-white/10">
                            <div class="flex items-center justify-between">
                                <span class="text-slate-400">Max Loan Amount:</span>
                                <span class="font-bold text-emerald-300">
                                    ZMW {{ number_format(($collateralType->max_value * $collateralType->loan_to_value_ratio) / 100, 2) }}
                                </span>
                            </div>
                            <p class="text-xs text-slate-400 mt-1">Based on max value and LTV ratio</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        @if($collateralType->description)
            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                <h2 class="text-xl font-semibold text-white mb-4">Description</h2>
                <p class="text-slate-300">{{ $collateralType->description }}</p>
            </div>
        @endif
    </div>
@endsection

