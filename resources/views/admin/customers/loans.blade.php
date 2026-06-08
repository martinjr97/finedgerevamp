@extends('layouts.admin')

@section('title', 'Customer Loans | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Customer Loans - ' . $customer->full_name,
            'buttons' => [
                [
                    'action' => 'back',
                    'text' => 'Back to Customer',
                    'href' => route('admin.customers.show', $customer),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>'
                ]
            ]
        ])

        {{-- Summary Statistics --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                <p class="text-sm text-slate-400 mb-2">Total Loans</p>
                <p class="text-3xl font-bold text-white">{{ $summary['total_loans'] }}</p>
            </div>
            <div class="rounded-3xl border border-emerald-500/30 bg-emerald-500/10 p-6 shadow-lg">
                <p class="text-sm text-emerald-300 mb-2">Total Principal</p>
                <p class="text-3xl font-bold text-emerald-400">ZMW {{ number_format($summary['total_principal'], 2) }}</p>
            </div>
            <div class="rounded-3xl border border-blue-500/30 bg-blue-500/10 p-6 shadow-lg">
                <p class="text-sm text-blue-300 mb-2">Booked Loan Total</p>
                <p class="text-3xl font-bold text-blue-400">ZMW {{ number_format($summary['total_amount'], 2) }}</p>
            </div>
            <div class="rounded-3xl border border-rose-500/30 bg-rose-500/10 p-6 shadow-lg">
                <p class="text-sm text-rose-300 mb-2">Booked Outstanding</p>
                <p class="text-3xl font-bold text-rose-400">ZMW {{ number_format($summary['total_outstanding'], 2) }}</p>
            </div>
        </div>

        {{-- Loan Status Summary --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="rounded-3xl border border-emerald-500/30 bg-emerald-500/10 p-4 shadow-lg">
                <p class="text-sm text-emerald-300 mb-1">Active Loans</p>
                <p class="text-2xl font-bold text-emerald-400">{{ $summary['active_loans'] }}</p>
            </div>
            <div class="rounded-3xl border border-green-500/30 bg-green-500/10 p-4 shadow-lg">
                <p class="text-sm text-green-300 mb-1">Completed Loans</p>
                <p class="text-2xl font-bold text-green-400">{{ $summary['completed_loans'] }}</p>
            </div>
            <div class="rounded-3xl border border-rose-500/30 bg-rose-500/10 p-4 shadow-lg">
                <p class="text-sm text-rose-300 mb-1">Defaulted Loans</p>
                <p class="text-2xl font-bold text-rose-400">{{ $summary['defaulted_loans'] }}</p>
            </div>
        </div>

        {{-- Loans Table --}}
        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <h2 class="text-xl font-semibold text-white mb-6 flex items-center gap-2">
                <span class="w-1 h-6 rounded-full bg-green-500"></span>All Loans
            </h2>
            @if($loans->count() > 0)
                <div class="overflow-x-auto">
                    <table data-datatable="true" data-datatable-per-page="10" class="min-w-full w-full text-sm text-slate-300">
                        <thead>
                            <tr class="text-sm font-semibold uppercase tracking-[0.25em] text-white/80 text-center border-b border-white/10">
                                <th class="px-4 py-4 text-base text-white">Loan Number</th>
                                <th class="px-4 py-4 text-base text-white">Product</th>
                                <th class="px-4 py-4 text-base text-white">Principal Amount</th>
                                <th class="px-4 py-4 text-base text-white">Booked Total</th>
                                <th class="px-4 py-4 text-base text-white">Booked Outstanding</th>
                                <th class="px-4 py-4 text-base text-white">Tenure</th>
                                <th class="px-4 py-4 text-base text-white">Start Date</th>
                                <th class="px-4 py-4 text-base text-white">Status</th>
                                <th class="px-4 py-4 text-base text-white">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($loans as $loan)
                                <tr class="border-t border-white/5 text-center hover:bg-white/5 transition">
                                    <td class="px-4 py-3 font-medium text-white">
                                        {{ $loan->loan_number }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full bg-cyan-500/20 px-2 py-1 text-xs text-cyan-300">
                                            {{ $loan->loanProduct->name ?? '—' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 font-medium text-white">
                                        ZMW {{ number_format($loan->principal_amount, 2) }}
                                    </td>
                                    <td class="px-4 py-3 font-medium text-white">
                                        ZMW {{ number_format($loan->total_amount, 2) }}
                                    </td>
                                    <td class="px-4 py-3 font-medium text-white">
                                        ZMW {{ number_format($loan->outstanding_balance, 2) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ $loan->tenure_months }} {{ $loan->tenure_months == 1 ? 'Month' : 'Months' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ $loan->loan_start_date->format('d M Y') }}
                                    </td>
                                    <td class="px-4 py-3">
                                        @php
                                            $statusColors = [
                                                'pending_approval' => 'bg-amber-500/20 text-amber-300',
                                                'approved' => 'bg-blue-500/20 text-blue-300',
                                                'active' => 'bg-emerald-500/20 text-emerald-300',
                                                'completed' => 'bg-green-500/20 text-green-300',
                                                'defaulted' => 'bg-rose-500/20 text-rose-300',
                                                'cancelled' => 'bg-slate-500/20 text-slate-300',
                                            ];
                                            $statusColor = $statusColors[$loan->status] ?? 'bg-slate-500/20 text-slate-300';
                                        @endphp
                                        <span class="inline-block rounded-full px-2 py-1 text-xs {{ $statusColor }}">
                                            {{ ucfirst(str_replace('_', ' ', $loan->status)) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <a href="{{ route('admin.loans.show', $loan) }}" class="rounded-full bg-blue-500/20 border border-blue-500/50 px-3 py-1.5 text-xs font-medium text-blue-300 hover:bg-blue-500/30 transition">
                                            View
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-center text-slate-400 py-12">This customer has no loans yet.</p>
            @endif
        </div>
    </div>
@endsection

