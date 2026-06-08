@extends('layouts.admin')

@section('title', 'Customer Repayments | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Customer Repayments - ' . $customer->full_name,
            'buttons' => [
                [
                    'action' => 'create',
                    'text' => 'Initiate Repayment',
                    'href' => route('admin.customers.repayments.create', $customer),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>',
                    'can' => auth('admin')->user()?->can('repayments.create')
                ],
                [
                    'action' => 'back',
                    'text' => 'Back to Customer',
                    'href' => route('admin.customers.show', $customer),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>'
                ]
            ]
        ])

        {{-- Summary Statistics --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                <p class="text-sm text-slate-400 mb-2">Total Repayments</p>
                <p class="text-3xl font-bold text-white">{{ $summary['total_repayments'] }}</p>
            </div>
            <div class="rounded-3xl border border-emerald-500/30 bg-emerald-500/10 p-6 shadow-lg">
                <p class="text-sm text-emerald-300 mb-2">Total Amount</p>
                <p class="text-3xl font-bold text-emerald-400">ZMW {{ number_format($summary['total_amount'], 2) }}</p>
            </div>
            <div class="rounded-3xl border border-green-500/30 bg-green-500/10 p-6 shadow-lg">
                <p class="text-sm text-green-300 mb-2">Principal Paid</p>
                <p class="text-3xl font-bold text-green-400">ZMW {{ number_format($summary['total_principal'], 2) }}</p>
            </div>
            <div class="rounded-3xl border border-amber-500/30 bg-amber-500/10 p-6 shadow-lg">
                <p class="text-sm text-amber-300 mb-2">Interest Paid</p>
                <p class="text-3xl font-bold text-amber-400">ZMW {{ number_format($summary['total_interest'], 2) }}</p>
            </div>
            <div class="rounded-3xl border border-blue-500/30 bg-blue-500/10 p-6 shadow-lg">
                <p class="text-sm text-blue-300 mb-2">Fees Paid</p>
                <p class="text-3xl font-bold text-blue-400">ZMW {{ number_format($summary['total_fees'], 2) }}</p>
            </div>
        </div>

        {{-- Repayments Table --}}
        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <h2 class="text-xl font-semibold text-white mb-6 flex items-center gap-2">
                <span class="w-1 h-6 rounded-full bg-teal-500"></span>All Repayments
            </h2>
            @if($repayments->count() > 0)
                <div class="overflow-x-auto">
                    <table data-datatable="true" data-datatable-per-page="20" class="min-w-full w-full text-sm text-slate-300">
                        <thead>
                            <tr class="text-sm font-semibold uppercase tracking-[0.25em] text-white/80 text-center border-b border-white/10">
                                <th class="px-4 py-4 text-base text-white">Repayment Number</th>
                                <th class="px-4 py-4 text-base text-white">Date</th>
                                <th class="px-4 py-4 text-base text-white">Channel</th>
                                <th class="px-4 py-4 text-base text-white">Total Amount</th>
                                <th class="px-4 py-4 text-base text-white">Recovery Method</th>
                                <th class="px-4 py-4 text-base text-white">Principal</th>
                                <th class="px-4 py-4 text-base text-white">Interest</th>
                                <th class="px-4 py-4 text-base text-white">Fees</th>
                                <th class="px-4 py-4 text-base text-white">Status</th>
                                <th class="px-4 py-4 text-base text-white">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($repayments as $repayment)
                                <tr class="border-t border-white/5 text-center hover:bg-white/5 transition">
                                    <td class="px-4 py-3 font-medium text-white">
                                        {{ $repayment->repayment_number }}
                                    </td>
                                    <td class="px-4 py-3 text-white">
                                        {{ $repayment->created_at->format('d M Y') }}
                                        <div class="text-xs text-slate-400">{{ $repayment->created_at->format('g:i A') }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full bg-purple-500/20 px-2 py-1 text-xs text-purple-300">
                                            {{ $repayment->channel->name ?? '—' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 font-medium text-white">
                                        ZMW {{ number_format($repayment->total_amount, 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-slate-300">
                                        {{ $repayment->recoveryMethodLabel() }}
                                    </td>
                                    <td class="px-4 py-3 text-green-400">
                                        ZMW {{ number_format($repayment->loanRepayments->sum('principal_amount'), 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-amber-400">
                                        ZMW {{ number_format($repayment->loanRepayments->sum('interest_amount'), 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-blue-400">
                                        ZMW {{ number_format($repayment->loanRepayments->sum('processing_fee_amount'), 2) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        @php
                                            $statusColors = [
                                                'pending' => 'bg-amber-500/20 text-amber-300',
                                                'processing' => 'bg-blue-500/20 text-blue-300',
                                                'completed' => 'bg-emerald-500/20 text-emerald-300',
                                                'failed' => 'bg-rose-500/20 text-rose-300',
                                                'cancelled' => 'bg-slate-500/20 text-slate-300',
                                            ];
                                            $statusColor = $statusColors[$repayment->status] ?? 'bg-slate-500/20 text-slate-300';
                                        @endphp
                                        <span class="inline-block rounded-full px-2 py-1 text-xs {{ $statusColor }}">
                                            {{ ucfirst($repayment->status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <a href="{{ route('admin.repayments.show', $repayment) }}" class="rounded-full bg-blue-500/20 border border-blue-500/50 px-3 py-1.5 text-xs font-medium text-blue-300 hover:bg-blue-500/30 transition">
                                            View
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-6">
                    {{ $repayments->links() }}
                </div>
            @else
                <p class="text-center text-slate-400 py-12">This customer has no repayments yet.</p>
            @endif
        </div>
    </div>
@endsection
