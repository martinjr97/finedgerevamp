@extends('layouts.admin')

@section('title', "Today's Payments | ".config('app.system_name'))

@php
    $currentSortBy = request('sort_by', 'loan_number');
    $currentSortDir = strtolower(request('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';

    $sortLink = function (string $column) use ($currentSortBy, $currentSortDir): string {
        $nextDir = ($currentSortBy === $column && $currentSortDir === 'asc') ? 'desc' : 'asc';

        return route('admin.loans.todays-payments', array_merge(request()->query(), [
            'sort_by' => $column,
            'sort_dir' => $nextDir,
        ]));
    };

    $sortIndicator = function (string $column) use ($currentSortBy, $currentSortDir): string {
        if ($currentSortBy !== $column) {
            return '';
        }

        return $currentSortDir === 'asc' ? ' ↑' : ' ↓';
    };
@endphp

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => "Today's Payments - " . $today->format('M d, Y'),
            'buttons' => [
                [
                    'action' => 'export',
                    'text' => 'Export Excel',
                    'href' => route('admin.loans.todays-payments.export', request()->query()),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
                    'can' => auth('admin')->user()?->can('loans.export')
                ],
                [
                    'action' => 'default',
                    'text' => 'All Loans',
                    'href' => route('admin.loans.index'),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>',
                    'can' => auth('admin')->user()?->can('loans.view')
                ]
            ]
        ])

        <x-admin.collapsible-filters
            panel-id="todays-payments-filters-panel"
            :filter-keys="['search', 'branch_id', 'relationship_manager_id', 'sort_by', 'sort_dir']"
            expanded-hint="Refine today's payment list below."
        >
            <form method="GET" action="{{ route('admin.loans.todays-payments') }}" class="space-y-4">
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Search</label>
                        <input type="text" name="search" value="{{ request('search') }}"
                               placeholder="Loan number, customer name, email, phone..."
                               class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Branch</label>
                        <select name="branch_id" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                            <option value="">All Branches</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" @selected((string) request('branch_id') === (string) $branch->id)>
                                    {{ $branch->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Relationship Manager</label>
                        <select name="relationship_manager_id" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                            <option value="">All Relationship Managers</option>
                            @foreach ($relationshipManagers as $manager)
                                <option value="{{ $manager->id }}" @selected((string) request('relationship_manager_id') === (string) $manager->id)>
                                    {{ trim($manager->first_name.' '.$manager->last_name) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Sort By</label>
                        <select name="sort_by" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                            <option value="loan_number" @selected($currentSortBy === 'loan_number')>Loan Number</option>
                            <option value="customer_name" @selected($currentSortBy === 'customer_name')>Customer Name</option>
                            <option value="expected_amount" @selected($currentSortBy === 'expected_amount')>Expected Amount</option>
                            <option value="amount_paid" @selected($currentSortBy === 'amount_paid')>Amount Paid</option>
                            <option value="remaining_amount" @selected($currentSortBy === 'remaining_amount')>Remaining Amount</option>
                            <option value="payment_status" @selected($currentSortBy === 'payment_status')>Payment Status</option>
                            <option value="outstanding_balance" @selected($currentSortBy === 'outstanding_balance')>Outstanding Balance</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Sort Direction</label>
                        <select name="sort_dir" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                            <option value="asc" @selected($currentSortDir === 'asc')>Ascending</option>
                            <option value="desc" @selected($currentSortDir === 'desc')>Descending</option>
                        </select>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button type="submit" class="rounded-2xl bg-cyan-500/20 border border-cyan-500/50 px-6 py-2 text-sm font-medium text-cyan-300 hover:bg-cyan-500/30 transition">
                        Apply Filters
                    </button>
                    <a href="{{ route('admin.loans.todays-payments') }}" class="rounded-2xl border border-white/10 px-6 py-2 text-sm font-medium text-white/80 hover:bg-white/10 transition">
                        Clear Filters
                    </a>
                </div>
            </form>
        </x-admin.collapsible-filters>

        <div class="rounded-3xl border border-white/10 bg-white/5 p-4 shadow-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full w-full text-base text-slate-300">
                    <thead>
                        <tr class="text-base font-semibold uppercase tracking-[0.25em] text-white/80 text-center border-b-2 border-white/20">
                            <th class="px-4 py-4 text-lg border-r border-white/10">
                                <a href="{{ $sortLink('loan_number') }}" class="inline-flex items-center justify-center gap-1 hover:text-cyan-300 transition">
                                    Loan Number{!! $sortIndicator('loan_number') !!}
                                </a>
                            </th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">
                                <a href="{{ $sortLink('customer_name') }}" class="inline-flex items-center justify-center gap-1 hover:text-cyan-300 transition">
                                    Customer{!! $sortIndicator('customer_name') !!}
                                </a>
                            </th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Product</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">
                                <a href="{{ $sortLink('expected_amount') }}" class="inline-flex items-center justify-center gap-1 hover:text-cyan-300 transition">
                                    Expected Amount{!! $sortIndicator('expected_amount') !!}
                                </a>
                            </th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">
                                <a href="{{ $sortLink('amount_paid') }}" class="inline-flex items-center justify-center gap-1 hover:text-cyan-300 transition">
                                    Amount Paid{!! $sortIndicator('amount_paid') !!}
                                </a>
                            </th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">
                                <a href="{{ $sortLink('remaining_amount') }}" class="inline-flex items-center justify-center gap-1 hover:text-cyan-300 transition">
                                    Remaining{!! $sortIndicator('remaining_amount') !!}
                                </a>
                            </th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">
                                <a href="{{ $sortLink('payment_status') }}" class="inline-flex items-center justify-center gap-1 hover:text-cyan-300 transition">
                                    Payment Status{!! $sortIndicator('payment_status') !!}
                                </a>
                            </th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Period</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">
                                <a href="{{ $sortLink('outstanding_balance') }}" class="inline-flex items-center justify-center gap-1 hover:text-cyan-300 transition">
                                    Outstanding Balance{!! $sortIndicator('outstanding_balance') !!}
                                </a>
                            </th>
                            <th class="px-4 py-4 text-lg">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($loans as $loan)
                            @php
                                $schedule = $loan->todays_schedule;
                                $statusColors = [
                                    'paid' => 'text-green-400',
                                    'paid_early' => 'text-emerald-400',
                                    'partial' => 'text-amber-400',
                                    'upcoming' => 'text-blue-400',
                                    'overdue' => 'text-rose-400',
                                ];
                                $statusColor = $schedule ? ($statusColors[$schedule->status ?? 'upcoming'] ?? 'text-slate-400') : 'text-slate-400';
                            @endphp
                            <tr class="border-t border-white/40 text-center hover:bg-white/5 transition">
                                <td class="px-4 py-4 font-medium text-white border-r border-white/5">
                                    {{ $loan->loan_number }}
                                </td>
                                <td class="px-4 py-4 border-r border-white/5">
                                    <div class="text-left">
                                        <div class="font-medium text-white">{{ $loan->customer->full_name ?? 'N/A' }}</div>
                                        <div class="text-sm text-slate-400">{{ $loan->customer->email ?? 'N/A' }}</div>
                                        <div class="text-xs text-slate-500">{{ $loan->customer->phone ?? 'N/A' }}</div>
                                    </div>
                                </td>
                                <td class="px-4 py-4 border-r border-white/5">
                                    <span class="rounded-full bg-cyan-500/20 px-2 py-1 text-sm text-cyan-300">
                                        {{ $loan->loanProduct->name ?? '—' }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 font-medium text-white border-r border-white/5">
                                    ZMW {{ number_format($schedule->expected_amount ?? 0, 2) }}
                                </td>
                                <td class="px-4 py-4 font-medium text-white border-r border-white/5">
                                    ZMW {{ number_format($schedule->amount_paid ?? 0, 2) }}
                                </td>
                                <td class="px-4 py-4 font-medium text-white border-r border-white/5">
                                    ZMW {{ number_format($schedule->remaining_amount ?? 0, 2) }}
                                </td>
                                <td class="px-4 py-4 border-r border-white/5">
                                    <span class="text-sm font-medium {{ $statusColor }}">
                                        {{ $schedule ? ucfirst(str_replace('_', ' ', $schedule->status)) : 'N/A' }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-slate-400 border-r border-white/5">
                                    {{ $schedule->period_number ?? 'N/A' }}/{{ $loan->tenure_months }}
                                </td>
                                <td class="px-4 py-4 font-medium text-white border-r border-white/5">
                                    ZMW {{ number_format($loan->outstanding_balance, 2) }}
                                </td>
                                <td class="px-4 py-4">
                                    <div class="inline-flex items-center gap-3">
                                        @can('loans.view')
                                        <a href="{{ route('admin.loans.show', $loan) }}"
                                           class="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-blue-500/40 to-blue-600/40 border-2 border-blue-400/70 px-4 py-2 text-base font-semibold text-blue-200 hover:from-blue-500/60 hover:to-blue-600/60 hover:border-blue-400 hover:text-white transition shadow-md shadow-blue-500/20">
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
                                <td colspan="10" class="px-4 py-8 text-center text-slate-400">
                                    No payments scheduled for today.
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
