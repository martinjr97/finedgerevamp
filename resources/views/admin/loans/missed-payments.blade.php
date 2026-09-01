@extends('layouts.admin')

@section('title', 'Due Last 14 Days | '.config('app.system_name'))

@php
    $currentSortBy = request('sort_by', 'days_overdue');
    $currentSortDir = strtolower(request('sort_dir', 'desc')) === 'desc' ? 'desc' : 'asc';

    $sortLink = function (string $column) use ($currentSortBy, $currentSortDir): string {
        $nextDir = ($currentSortBy === $column && $currentSortDir === 'asc') ? 'desc' : 'asc';

        return route('admin.loans.missed-payments', array_merge(request()->query(), [
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
            'title' => 'Due Last 14 Days',
            'description' => 'Unsettled installments due from '.$windowStart->format('M d, Y').' to '.$windowEnd->format('M d, Y').' (today excluded).',
            'buttons' => [
                [
                    'action' => 'export',
                    'text' => 'Export Excel',
                    'href' => route('admin.loans.missed-payments.export', request()->query()),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
                    'can' => auth('admin')->user()?->can('loans.export')
                ],
                [
                    'action' => 'default',
                    'text' => "Today's Payments",
                    'href' => route('admin.loans.todays-payments'),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>',
                    'can' => auth('admin')->user()?->can('loans.view')
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

        <div class="rounded-2xl border border-amber-400/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-100">
            Loans with at least one <strong class="text-white">unpaid installment</strong> whose due date fell in the last 14 days before today.
            Use branch or relationship manager filters to divide follow-up work, then export for your team.
        </div>

        <x-admin.collapsible-filters
            panel-id="missed-payments-filters-panel"
            :filter-keys="['search', 'branch_id', 'relationship_manager_id', 'sort_by', 'sort_dir']"
            expanded-hint="Refine the missed payment list below."
        >
            <form method="GET" action="{{ route('admin.loans.missed-payments') }}" class="space-y-4">
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
                            <option value="days_overdue" @selected($currentSortBy === 'days_overdue')>Days Overdue</option>
                            <option value="due_date" @selected($currentSortBy === 'due_date')>Oldest Due Date</option>
                            <option value="missed_amount" @selected($currentSortBy === 'missed_amount')>Missed Amount</option>
                            <option value="missed_installments" @selected($currentSortBy === 'missed_installments')>Missed Installments</option>
                            <option value="loan_number" @selected($currentSortBy === 'loan_number')>Loan Number</option>
                            <option value="customer_name" @selected($currentSortBy === 'customer_name')>Customer Name</option>
                            <option value="outstanding_balance" @selected($currentSortBy === 'outstanding_balance')>Outstanding Balance</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Sort Direction</label>
                        <select name="sort_dir" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                            <option value="desc" @selected($currentSortDir === 'desc')>Descending</option>
                            <option value="asc" @selected($currentSortDir === 'asc')>Ascending</option>
                        </select>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button type="submit" class="rounded-2xl bg-cyan-500/20 border border-cyan-500/50 px-6 py-2 text-sm font-medium text-cyan-300 hover:bg-cyan-500/30 transition">
                        Apply Filters
                    </button>
                    <a href="{{ route('admin.loans.missed-payments') }}" class="rounded-2xl border border-white/10 px-6 py-2 text-sm font-medium text-white/80 hover:bg-white/10 transition">
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
                                <a href="{{ $sortLink('due_date') }}" class="inline-flex items-center justify-center gap-1 hover:text-cyan-300 transition">
                                    Oldest Due{!! $sortIndicator('due_date') !!}
                                </a>
                            </th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">
                                <a href="{{ $sortLink('days_overdue') }}" class="inline-flex items-center justify-center gap-1 hover:text-cyan-300 transition">
                                    Days Overdue{!! $sortIndicator('days_overdue') !!}
                                </a>
                            </th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">
                                <a href="{{ $sortLink('missed_amount') }}" class="inline-flex items-center justify-center gap-1 hover:text-cyan-300 transition">
                                    Missed Amount{!! $sortIndicator('missed_amount') !!}
                                </a>
                            </th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">
                                <a href="{{ $sortLink('missed_installments') }}" class="inline-flex items-center justify-center gap-1 hover:text-cyan-300 transition">
                                    Installments{!! $sortIndicator('missed_installments') !!}
                                </a>
                            </th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">
                                <a href="{{ $sortLink('outstanding_balance') }}" class="inline-flex items-center justify-center gap-1 hover:text-cyan-300 transition">
                                    Outstanding{!! $sortIndicator('outstanding_balance') !!}
                                </a>
                            </th>
                            <th class="px-4 py-4 text-lg">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($loans as $loan)
                            @php
                                $schedule = $loan->primary_missed_schedule;
                                $relationshipManager = $loan->customerGroup?->relationshipManager
                                    ?? $loan->customer?->company?->relationshipManager
                                    ?? $loan->customer?->customerGroup?->relationshipManager;
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
                                        @if($relationshipManager)
                                            <div class="text-xs text-cyan-300/80 mt-1">RM: {{ trim($relationshipManager->first_name.' '.$relationshipManager->last_name) }}</div>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-4 border-r border-white/5">
                                    <span class="rounded-full bg-cyan-500/20 px-2 py-1 text-sm text-cyan-300">
                                        {{ $loan->loanProduct->name ?? '—' }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 font-medium text-white border-r border-white/5 whitespace-nowrap">
                                    {{ $schedule?->due_date?->format('M d, Y') ?? 'N/A' }}
                                </td>
                                <td class="px-4 py-4 border-r border-white/5">
                                    <span class="text-sm font-semibold {{ ($loan->missed_days_overdue ?? 0) >= 30 ? 'text-rose-400' : (($loan->missed_days_overdue ?? 0) >= 7 ? 'text-amber-400' : 'text-orange-300') }}">
                                        {{ $loan->missed_days_overdue ?? 0 }} days
                                    </span>
                                </td>
                                <td class="px-4 py-4 font-medium text-rose-200 border-r border-white/5">
                                    ZMW {{ number_format($loan->missed_amount_total ?? 0, 2) }}
                                </td>
                                <td class="px-4 py-4 text-slate-300 border-r border-white/5">
                                    {{ $loan->missed_installments_count ?? 0 }}
                                </td>
                                <td class="px-4 py-4 font-medium text-white border-r border-white/5">
                                    ZMW {{ number_format($loan->outstanding_balance, 2) }}
                                </td>
                                <td class="px-4 py-4">
                                    <div class="inline-flex items-center gap-3">
                                        @can('loans.view')
                                        <a href="{{ route('admin.loans.show', $loan) }}"
                                           class="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-blue-500/40 to-blue-600/40 border-2 border-blue-400/70 px-4 py-2 text-base font-semibold text-blue-200 hover:from-blue-500/60 hover:to-blue-600/60 hover:border-blue-400 hover:text-white transition shadow-md shadow-blue-500/20">
                                            View
                                        </a>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-8 text-center text-slate-400">
                                    No missed payments in the last 14 days (excluding today).
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
