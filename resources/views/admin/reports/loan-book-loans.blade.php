@extends('layouts.admin')

@section('title', 'Loan Book — Loan List | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Loan Book — Loan List',
            'description' => 'Loan-level detail for the current portfolio filters.',
            'buttons' => [
                [
                    'action' => 'secondary',
                    'text' => 'Back to Summary',
                    'href' => route('admin.reports.loan-book', request()->query()),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>',
                ],
                [
                    'action' => 'export',
                    'text' => 'Export Details',
                    'href' => route('admin.reports.loan-book.export', request()->query()),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
                ],
            ],
        ])

        <p class="text-sm text-slate-400">
            Showing {{ number_format($loans->total()) }} loan(s) matching your filters.
            Active portfolio: {{ number_format($stats['active_loans']) }} disbursed loan(s).
        </p>

        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            @include('admin.reports.partials.loan-book-filters', [
                'formAction' => route('admin.reports.loan-book.loans'),
                'loanProducts' => $loanProducts,
                'customerGroups' => $customerGroups,
            ])
        </div>

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
                                    <span class="text-white">
                                        {{ $loan->resolvedRelationshipManager()?->full_name ?? '—' }}
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
                                <td colspan="12" class="px-4 py-8 text-center text-slate-400">
                                    No loans found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($loans->hasPages())
                <div class="mt-6">
                    {{ $loans->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
