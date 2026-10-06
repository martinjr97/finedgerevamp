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
            Use filters below, then open the loan list when you need loan-level detail.
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
            @include('admin.reports.partials.loan-book-filters', [
                'formAction' => route('admin.reports.loan-book'),
                'loanProducts' => $loanProducts,
                'customerGroups' => $customerGroups,
            ])
        </div>

        {{-- Open loan list on demand --}}
        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <h2 class="text-xl font-semibold text-white mb-2">Loan-Level Detail</h2>
            <p class="text-sm text-slate-400 mb-4">
                {{ number_format($filteredLoanCount) }} loan(s) match the current filters.
                Open the loan list to browse paginated rows without loading them on this summary page.
            </p>
            @if ($filteredLoanCount > 0)
                <a
                    href="{{ route('admin.reports.loan-book.loans', request()->query()) }}"
                    class="inline-flex items-center gap-2 rounded-2xl bg-cyan-500/20 border border-cyan-500/50 px-6 py-2 text-sm font-medium text-cyan-300 hover:bg-cyan-500/30 transition"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                    </svg>
                    View {{ number_format($filteredLoanCount) }} loans
                </a>
            @else
                <p class="text-sm text-slate-500">No loans match the current filters.</p>
            @endif
        </div>
    </div>
@endsection

