@extends('layouts.admin')

@section('title', 'Customer Statement | '.config('app.system_name'))

@section('content')
    @php
        $summary = $statement['summary'];
        $rows = $statement['rows'];
        $opening = $statement['opening_balance'];
        $closing = $statement['closing_balance'];
        $filters = $statement['filters'];
    @endphp
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Lifetime Statement — '.$customer->full_name,
            'buttons' => [
                [
                    'action' => 'back',
                    'text' => 'Back to Customer',
                    'href' => route('admin.customers.show', $customer),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>',
                ],
                [
                    'text' => 'Print',
                    'href' => route('admin.customers.statement', array_merge([$customer], request()->only(['from_date', 'to_date', 'loan_id']), ['print' => 1])),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>',
                    'class' => 'inline-flex items-center gap-2 rounded-xl border border-white/20 bg-white/5 px-3 py-2 text-sm font-semibold text-slate-200 hover:bg-white/10 transition',
                ],
            ],
        ])

        @if ($statement['defaulted_date_range'])
            <div class="rounded-2xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-100">
                Showing the last 12 months by default because this customer has a large transaction history. Adjust the date filters to view a different period.
            </div>
        @endif

        <div class="rounded-3xl border border-white/10 bg-gradient-to-br from-slate-900/80 to-slate-800/40 p-6 shadow-lg">
            <div class="flex flex-wrap items-start justify-between gap-4 border-b border-white/10 pb-4 mb-4">
                <div>
                    <p class="text-xs uppercase tracking-[0.35em] text-cyan-300">Account statement</p>
                    <h2 class="text-2xl font-bold text-white mt-1">{{ $customer->full_name }}</h2>
                    <p class="text-sm text-slate-400 mt-1">{{ $customer->email }} · {{ $customer->phone }}</p>
                </div>
                <div class="text-right text-sm text-slate-400">
                    <p>Generated {{ now()->format('d M Y, H:i') }}</p>
                    <p>{{ config('app.system_name') }}</p>
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                    <p class="text-xs text-slate-400 uppercase tracking-wide">Loans collected</p>
                    <p class="text-xl font-bold text-white mt-1">{{ $summary['loans_collected'] }}</p>
                </div>
                <div class="rounded-2xl border border-cyan-500/20 bg-cyan-500/5 p-4">
                    <p class="text-xs text-cyan-300/80 uppercase tracking-wide">Expected settlement</p>
                    <p class="text-lg font-bold text-cyan-200 mt-1">ZMW {{ number_format($summary['total_expected_settlement'], 2) }}</p>
                </div>
                <div class="rounded-2xl border border-emerald-500/20 bg-emerald-500/5 p-4">
                    <p class="text-xs text-emerald-300/80 uppercase tracking-wide">Net paid</p>
                    <p class="text-lg font-bold text-emerald-200 mt-1">ZMW {{ number_format($summary['total_net_paid'], 2) }}</p>
                </div>
                <div class="rounded-2xl border border-rose-500/20 bg-rose-500/5 p-4">
                    <p class="text-xs text-rose-300/80 uppercase tracking-wide">Total refunded</p>
                    <p class="text-lg font-bold text-rose-200 mt-1">ZMW {{ number_format($summary['total_refunded'], 2) }}</p>
                </div>
                <div class="rounded-2xl border border-amber-500/20 bg-amber-500/5 p-4">
                    <p class="text-xs text-amber-300/80 uppercase tracking-wide">Outstanding</p>
                    <p class="text-lg font-bold text-amber-200 mt-1">ZMW {{ number_format($summary['total_outstanding'], 2) }}</p>
                </div>
                @if ($summary['total_suspense'] > 0)
                    <div class="rounded-2xl border border-violet-500/20 bg-violet-500/5 p-4">
                        <p class="text-xs text-violet-300/80 uppercase tracking-wide">Customer credit</p>
                        <p class="text-lg font-bold text-violet-200 mt-1">ZMW {{ number_format($summary['total_suspense'], 2) }}</p>
                    </div>
                @endif
            </div>
        </div>

        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <form method="GET" action="{{ route('admin.customers.statement', $customer) }}" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">From date</label>
                    <input type="date" name="from_date" value="{{ $filters['from_date'] }}"
                           class="w-full rounded-xl border border-white/10 bg-slate-900/50 px-3 py-2 text-sm text-white focus:border-cyan-400 focus:ring-cyan-400/30">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">To date</label>
                    <input type="date" name="to_date" value="{{ $filters['to_date'] }}"
                           class="w-full rounded-xl border border-white/10 bg-slate-900/50 px-3 py-2 text-sm text-white focus:border-cyan-400 focus:ring-cyan-400/30">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-300 mb-1">Loan</label>
                    <select name="loan_id" class="w-full rounded-xl border border-white/10 bg-slate-900/50 px-3 py-2 text-sm text-white focus:border-cyan-400 focus:ring-cyan-400/30">
                        <option value="">All loans</option>
                        @foreach ($statement['loans'] as $loan)
                            <option value="{{ $loan->id }}" @selected($filters['loan_id'] == $loan->id)>
                                {{ $loan->loan_number }} — {{ $loan->loanProduct?->name ?? 'Loan' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="flex-1 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-cyan-500/20 hover:from-cyan-600 hover:to-blue-700 transition">
                        Apply filters
                    </button>
                    @if ($filters['from_date'] || $filters['to_date'] || $filters['loan_id'])
                        <a href="{{ route('admin.customers.statement', $customer) }}" class="rounded-xl border border-white/20 px-4 py-2.5 text-sm font-medium text-slate-300 hover:bg-white/10 transition">
                            Clear
                        </a>
                    @endif
                </div>
            </form>
        </div>

        <div class="rounded-3xl border border-white/10 bg-white/5 shadow-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-white/10 flex flex-wrap items-center justify-between gap-2">
                <h3 class="text-lg font-semibold text-white">Transaction ledger</h3>
                <p class="text-xs text-slate-400">Schedule rows are expected events and do not affect the running balance.</p>
            </div>

            @if ($filters['from_date'] && ($opening['balance_owed'] > 0 || $opening['customer_credit'] > 0))
                <div class="px-6 py-3 bg-slate-800/50 border-b border-white/5 text-sm text-slate-300">
                    <span class="font-semibold text-white">Opening balance</span>
                    @if ($opening['customer_credit'] > 0)
                        — Customer credit ZMW {{ number_format($opening['customer_credit'], 2) }}
                    @else
                        — Balance owed ZMW {{ number_format($opening['balance_owed'], 2) }}
                    @endif
                    <span class="text-slate-500">(before {{ $filters['from_date'] }})</span>
                </div>
            @endif

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-white/10 bg-slate-900/40 text-left text-xs uppercase tracking-wide text-slate-400">
                            <th class="px-4 py-3 font-semibold">Date</th>
                            <th class="px-4 py-3 font-semibold">Loan ref</th>
                            <th class="px-4 py-3 font-semibold">Description</th>
                            <th class="px-4 py-3 font-semibold">Type</th>
                            <th class="px-4 py-3 font-semibold text-right">Debit</th>
                            <th class="px-4 py-3 font-semibold text-right">Credit</th>
                            <th class="px-4 py-3 font-semibold text-right">Running balance</th>
                            <th class="px-4 py-3 font-semibold">Reference</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            @php
                                $isSchedule = $row['transaction_type'] === 'schedule';
                                $isInfo = in_array($row['transaction_type'], ['schedule', 'settlement', 'suspense'], true);
                                $rb = $row['running_balance'] ?? ['balance_owed' => 0, 'customer_credit' => 0];
                            @endphp
                            <tr class="border-b border-white/5 {{ $isInfo ? 'bg-slate-800/30 text-slate-400' : 'text-slate-200' }}">
                                <td class="px-4 py-3 whitespace-nowrap">{{ $row['date']->format('d M Y') }}</td>
                                <td class="px-4 py-3 whitespace-nowrap font-mono text-xs">
                                    @if ($row['loan'])
                                        <a href="{{ route('admin.loans.show', $row['loan']) }}" class="text-cyan-400 hover:text-cyan-300">{{ $row['loan_reference'] }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3">{{ $row['description'] }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide
                                        @if($row['transaction_type'] === 'disbursement') bg-blue-500/20 text-blue-200
                                        @elseif($row['transaction_type'] === 'payment') bg-emerald-500/20 text-emerald-200
                                        @elseif($row['transaction_type'] === 'refund') bg-rose-500/20 text-rose-200
                                        @elseif($row['transaction_type'] === 'schedule') bg-slate-500/20 text-slate-300
                                        @elseif($row['transaction_type'] === 'suspense') bg-violet-500/20 text-violet-200
                                        @else bg-white/10 text-slate-300 @endif">
                                        {{ str_replace('_', ' ', $row['transaction_type']) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right font-medium {{ $row['debit'] ? 'text-rose-300' : 'text-slate-600' }}">
                                    {{ $row['debit'] ? 'ZMW '.number_format($row['debit'], 2) : ($isSchedule ? '—' : '') }}
                                </td>
                                <td class="px-4 py-3 text-right font-medium {{ $row['credit'] ? 'text-emerald-300' : 'text-slate-600' }}">
                                    {{ $row['credit'] ? 'ZMW '.number_format($row['credit'], 2) : ($isSchedule ? '—' : '') }}
                                </td>
                                <td class="px-4 py-3 text-right font-semibold text-white whitespace-nowrap">
                                    @if ($isInfo && ! $row['is_cash'])
                                        <span class="text-slate-500 text-xs">—</span>
                                    @elseif ($rb['customer_credit'] > 0)
                                        <span class="text-violet-300">Credit ZMW {{ number_format($rb['customer_credit'], 2) }}</span>
                                    @else
                                        <span class="text-cyan-300">Owed ZMW {{ number_format($rb['balance_owed'], 2) }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-xs text-slate-400 max-w-[10rem] truncate" title="{{ $row['notes'] }}">
                                    {{ $row['reference'] ?? ($row['notes'] ?? '—') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center text-slate-400">No transactions match the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($rows->isNotEmpty())
                        <tfoot>
                            <tr class="bg-slate-900/60 border-t border-white/10 font-semibold text-white">
                                <td colspan="6" class="px-4 py-3 text-right text-slate-300">Closing balance</td>
                                <td class="px-4 py-3 text-right">
                                    @if ($closing['customer_credit'] > 0)
                                        <span class="text-violet-300">Credit ZMW {{ number_format($closing['customer_credit'], 2) }}</span>
                                    @else
                                        <span class="text-cyan-300">Owed ZMW {{ number_format($closing['balance_owed'], 2) }}</span>
                                    @endif
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>
@endsection
