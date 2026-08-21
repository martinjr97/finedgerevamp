@extends('layouts.customer')

@section('title', 'Account Statement')

@section('content')
    @php
        $summary = $statement['summary'];
        $rows = $statement['rows'];
        $opening = $statement['opening_balance'];
        $closing = $statement['closing_balance'];
        $filters = $statement['filters'];
        $filterQuery = array_filter([
            'from_date' => $filters['from_date'] ?? null,
            'to_date' => $filters['to_date'] ?? null,
            'loan_id' => $filters['loan_id'] ?? null,
        ], fn ($value) => filled($value));
    @endphp

    <div class="content-area space-y-6 max-w-6xl mx-auto">
        <div class="bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600 rounded-2xl p-6 shadow-xl border border-blue-500/40">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs uppercase tracking-[0.3em] text-blue-100/80">Account statement</p>
                    <h1 class="text-3xl font-bold text-white mt-1">{{ $customer->full_name }}</h1>
                    <p class="text-blue-100 mt-1 text-sm">{{ $customer->email }} · {{ $customer->phone }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('customer.statement.pdf', $filterQuery) }}"
                       class="inline-flex items-center gap-2 rounded-xl border border-white/30 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white hover:bg-white/20 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Download PDF
                    </a>
                </div>
            </div>
        </div>

        @if ($statement['defaulted_date_range'])
            <div class="rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                Showing the last 12 months by default because you have a large transaction history. Adjust the date filters to view a different period.
            </div>
        @endif

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs text-slate-500 uppercase tracking-wide">Loans</p>
                <p class="text-xl font-bold text-slate-900 mt-1">{{ $summary['loans_collected'] }}</p>
            </div>
            <div class="rounded-2xl border border-cyan-200 bg-cyan-50 p-4 shadow-sm">
                <p class="text-xs text-cyan-700 uppercase tracking-wide">{{ ($filters['loan_id'] ?? null) ? 'Loan amount' : 'Total amount' }}</p>
                <p class="text-lg font-bold text-cyan-900 mt-1">ZMW {{ number_format($summary['total_expected_settlement'], 2) }}</p>
            </div>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm">
                <p class="text-xs text-emerald-700 uppercase tracking-wide">Paid</p>
                <p class="text-lg font-bold text-emerald-900 mt-1">ZMW {{ number_format($summary['total_net_paid'], 2) }}</p>
            </div>
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 shadow-sm">
                <p class="text-xs text-amber-700 uppercase tracking-wide">
                    {{ ($summary['total_suspense'] ?? 0) > 0 ? 'Customer credit' : 'Outstanding' }}
                </p>
                <p class="text-lg font-bold text-amber-900 mt-1">
                    ZMW {{ number_format(($summary['total_suspense'] ?? 0) > 0 ? $summary['total_suspense'] : $summary['total_outstanding'], 2) }}
                </p>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <form method="GET" action="{{ route('customer.statement') }}" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">From date</label>
                    <input type="date" name="from_date" value="{{ $filters['from_date'] }}"
                           class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-500 focus:ring-blue-500/20">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">To date</label>
                    <input type="date" name="to_date" value="{{ $filters['to_date'] }}"
                           class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-500 focus:ring-blue-500/20">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Loan</label>
                    <select name="loan_id" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-500 focus:ring-blue-500/20">
                        <option value="">All loans</option>
                        @foreach ($statement['loans'] as $loan)
                            <option value="{{ $loan->id }}" @selected($filters['loan_id'] == $loan->id)>
                                {{ $loan->loan_number }} — {{ $loan->loanProduct?->name ?? 'Loan' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="flex-1 rounded-xl bg-gradient-to-r from-blue-500 to-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-blue-500/20 hover:from-blue-600 hover:to-indigo-700 transition">
                        Apply filters
                    </button>
                    @if ($filters['from_date'] || $filters['to_date'] || $filters['loan_id'])
                        <a href="{{ route('customer.statement') }}" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 transition">
                            Clear
                        </a>
                    @endif
                </div>
            </form>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 flex flex-wrap items-center justify-between gap-2">
                <h3 class="text-lg font-semibold text-slate-900">Transaction ledger</h3>
                <p class="text-xs text-slate-500">Schedule rows are expected events and do not affect the running balance.</p>
            </div>

            @if ($filters['from_date'] && ($opening['balance_owed'] > 0 || $opening['customer_credit'] > 0))
                <div class="px-6 py-3 bg-slate-50 border-b border-slate-100 text-sm text-slate-700">
                    <span class="font-semibold text-slate-900">Opening balance</span>
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
                        <tr class="border-b border-slate-200 bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
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
                            <tr class="border-b border-slate-100 {{ $isInfo ? 'bg-slate-50 text-slate-500' : 'text-slate-800' }}">
                                <td class="px-4 py-3 whitespace-nowrap">{{ $row['date']->format('d M Y') }}</td>
                                <td class="px-4 py-3 whitespace-nowrap font-mono text-xs text-slate-700">
                                    {{ $row['loan_reference'] ?? '—' }}
                                </td>
                                <td class="px-4 py-3">{{ $row['description'] }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide
                                        @if($row['transaction_type'] === 'disbursement') bg-blue-100 text-blue-800
                                        @elseif($row['transaction_type'] === 'payment') bg-emerald-100 text-emerald-800
                                        @elseif($row['transaction_type'] === 'refund') bg-rose-100 text-rose-800
                                        @elseif($row['transaction_type'] === 'schedule') bg-slate-100 text-slate-600
                                        @elseif($row['transaction_type'] === 'suspense') bg-violet-100 text-violet-800
                                        @else bg-slate-100 text-slate-600 @endif">
                                        {{ str_replace('_', ' ', $row['transaction_type']) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right font-medium {{ $row['debit'] ? 'text-rose-700' : 'text-slate-300' }}">
                                    {{ $row['debit'] ? 'ZMW '.number_format($row['debit'], 2) : ($isSchedule ? '—' : '') }}
                                </td>
                                <td class="px-4 py-3 text-right font-medium {{ $row['credit'] ? 'text-emerald-700' : 'text-slate-300' }}">
                                    {{ $row['credit'] ? 'ZMW '.number_format($row['credit'], 2) : ($isSchedule ? '—' : '') }}
                                </td>
                                <td class="px-4 py-3 text-right font-semibold whitespace-nowrap">
                                    @if ($isInfo && ! $row['is_cash'])
                                        <span class="text-slate-400 text-xs">—</span>
                                    @elseif ($rb['customer_credit'] > 0)
                                        <span class="text-violet-700">Credit ZMW {{ number_format($rb['customer_credit'], 2) }}</span>
                                    @else
                                        <span class="text-cyan-800">Owed ZMW {{ number_format($rb['balance_owed'], 2) }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-xs text-slate-500 max-w-[10rem] truncate" title="{{ $row['notes'] }}">
                                    {{ $row['reference'] ?? ($row['notes'] ?? '—') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center text-slate-500">No transactions match the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($rows->isNotEmpty())
                        <tfoot>
                            <tr class="bg-slate-50 border-t border-slate-200 font-semibold text-slate-900">
                                <td colspan="6" class="px-4 py-3 text-right text-slate-600">Closing balance</td>
                                <td class="px-4 py-3 text-right">
                                    @if ($closing['customer_credit'] > 0)
                                        <span class="text-violet-700">Credit ZMW {{ number_format($closing['customer_credit'], 2) }}</span>
                                    @else
                                        <span class="text-cyan-800">Owed ZMW {{ number_format($closing['balance_owed'], 2) }}</span>
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
