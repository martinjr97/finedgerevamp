@extends('layouts.admin')

@section('title', 'Branch Report | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Branch Report',
            'subtitle' => 'Branch-level portfolio, arrears, PAR and staffing overview.',
        ])

        {{-- Filters --}}
        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <form method="GET" action="{{ route('admin.reports.branches') }}" class="space-y-4">
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Branch</label>
                        <select name="branch_id" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                            <option value="">All Branches</option>
                            @foreach($branchOptions as $branch)
                                <option value="{{ $branch->id }}" @selected($selectedBranchId == $branch->id)>
                                    {{ $branch->name }} @if($branch->code) ({{ $branch->code }}) @endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Period</label>
                        <div class="flex flex-wrap gap-2">
                            @php
                                $periodOptions = [
                                    'day' => 'Today',
                                    'week' => 'This Week',
                                    'month' => 'Last 30 Days',
                                    'custom' => 'Custom'
                                ];
                            @endphp
                            @foreach($periodOptions as $key => $label)
                                <label class="cursor-pointer">
                                    <input type="radio" name="period" value="{{ $key }}" class="peer sr-only" @checked($period === $key)>
                                    <span class="inline-flex items-center gap-2 rounded-xl border px-3 py-2 text-sm font-medium transition
                                        {{ $period === $key ? 'border-cyan-400/80 bg-cyan-500/20 text-cyan-100' : 'border-white/10 bg-white/5 text-white/80 hover:border-cyan-400/40 hover:text-white' }}">
                                        {{ $label }}
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        <p class="text-xs text-slate-400 mt-2">Use Custom with dates to target any window.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">From</label>
                        <input type="date" name="date_from" value="{{ request('date_from') }}" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">To</label>
                        <input type="date" name="date_to" value="{{ request('date_to') }}" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <button type="submit" class="rounded-2xl bg-cyan-500/20 border border-cyan-500/50 px-6 py-2 text-sm font-medium text-cyan-300 hover:bg-cyan-500/30 transition">
                        Apply Filters
                    </button>
                    <a href="{{ route('admin.reports.branches') }}" class="rounded-2xl border border-white/10 px-6 py-2 text-sm font-medium text-white/80 hover:bg-white/10 transition">
                        Clear Filters
                    </a>
                </div>
            </form>
        </div>

        {{-- Summary --}}
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-3xl border border-white/10 bg-white/5 p-4 shadow-lg">
                <p class="text-sm text-slate-400">Total Portfolio</p>
                <p class="text-2xl font-semibold text-white mt-2">ZMW {{ number_format($totals['portfolio'], 2) }}</p>
            </div>
            <div class="rounded-3xl border border-white/10 bg-white/5 p-4 shadow-lg">
                <p class="text-sm text-slate-400">Total Arrears</p>
                <p class="text-2xl font-semibold text-amber-300 mt-2">ZMW {{ number_format($totals['arrears'], 2) }}</p>
            </div>
            <div class="rounded-3xl border border-white/10 bg-white/5 p-4 shadow-lg">
                <p class="text-sm text-slate-400">Portfolio at Risk (PAR)</p>
                <p class="text-2xl font-semibold {{ $totals['par'] >= 20 ? 'text-rose-300' : ($totals['par'] >= 10 ? 'text-amber-300' : 'text-emerald-300') }} mt-2">
                    {{ number_format($totals['par'], 2) }}%
                </p>
            </div>
            <div class="rounded-3xl border border-white/10 bg-white/5 p-4 shadow-lg">
                <p class="text-sm text-slate-400">Staff / Customers</p>
                <p class="text-2xl font-semibold text-white mt-2">
                    {{ $totals['staff'] }} <span class="text-slate-400 text-base">staff</span>
                    <span class="mx-2 text-slate-600">•</span>
                    {{ $totals['customers'] }} <span class="text-slate-400 text-base">customers</span>
                </p>
            </div>
        </div>

        {{-- Cashflow KPIs (filtered window) --}}
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            <div class="rounded-3xl border border-white/10 bg-white/5 p-4 shadow-lg">
                <p class="text-xs uppercase tracking-[0.3em] text-cyan-200">Disbursed</p>
                <p class="text-2xl font-semibold text-white mt-2">ZMW {{ number_format($disbursementTotals['amount'], 2) }}</p>
                <p class="text-sm text-slate-400">{{ $disbursementTotals['count'] }} loans • {{ $rangeStart->toDateString() }} → {{ $rangeEnd->toDateString() }}</p>
            </div>
            <div class="rounded-3xl border border-white/10 bg-white/5 p-4 shadow-lg">
                <p class="text-xs uppercase tracking-[0.3em] text-emerald-200">Repaid</p>
                <p class="text-2xl font-semibold text-emerald-200 mt-2">ZMW {{ number_format($repaymentTotals['amount'], 2) }}</p>
                <p class="text-sm text-slate-400">{{ $repaymentTotals['count'] }} repayments • {{ $rangeStart->toDateString() }} → {{ $rangeEnd->toDateString() }}</p>
            </div>
            <div class="rounded-3xl border border-white/10 bg-white/5 p-4 shadow-lg">
                <p class="text-xs uppercase tracking-[0.3em] text-slate-200">Net Flow</p>
                @php $net = $disbursementTotals['amount'] - $repaymentTotals['amount']; @endphp
                <p class="text-2xl font-semibold {{ $net >= 0 ? 'text-amber-200' : 'text-emerald-200' }} mt-2">
                    ZMW {{ number_format($net, 2) }}
                </p>
                <p class="text-sm text-slate-400">Disbursed − Repaid in selected period</p>
            </div>
        </div>

        {{-- Activity chart --}}
        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg overflow-hidden">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-xl font-semibold text-white">Disbursements vs Repayments</h2>
                    <p class="text-sm text-slate-400">Daily totals for the selected window.</p>
                </div>
                <div class="text-xs text-slate-400 bg-white/5 border border-white/10 px-3 py-1 rounded-full">
                    {{ $rangeStart->toDateString() }} → {{ $rangeEnd->toDateString() }}
                </div>
            </div>
            <div class="relative overflow-hidden">
                <canvas
                    id="branchActivityChart"
                    class="block w-full"
                    height="320"
                    style="max-width: 100%;"
                ></canvas>
            </div>
        </div>

        {{-- Branch aggregates --}}
        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <h2 class="text-xl font-semibold text-white mb-4">Branch Portfolio & PAR</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full text-base text-slate-300">
                    <thead>
                        <tr class="text-sm font-semibold uppercase tracking-[0.25em] text-white/80 text-center border-b-2 border-white/20">
                            <th class="px-4 py-3 text-left">Branch</th>
                            <th class="px-4 py-3">Province</th>
                            <th class="px-4 py-3">Manager</th>
                            <th class="px-4 py-3">Groups</th>
                            <th class="px-4 py-3">Staff</th>
                            <th class="px-4 py-3">Customers</th>
                            <th class="px-4 py-3">Loans</th>
                            <th class="px-4 py-3">Portfolio</th>
                            <th class="px-4 py-3">Arrears</th>
                            <th class="px-4 py-3">PAR %</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($branchRows as $row)
                            <tr class="border-t border-white/10 text-center hover:bg-white/5 transition">
                                <td class="px-4 py-4 text-left font-semibold text-white">
                                    {{ $row['branch']->name }} @if($row['branch']->code)<span class="text-slate-400 font-normal">({{ $row['branch']->code }})</span>@endif
                                </td>
                                <td class="px-4 py-4">{{ $row['branch']->province->name ?? '—' }}</td>
                                <td class="px-4 py-4">
                                    @if($row['branch']->manager)
                                        {{ $row['branch']->manager->first_name }} {{ $row['branch']->manager->last_name }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-4">{{ $row['group_count'] }}</td>
                                <td class="px-4 py-4">{{ $row['staff_count'] }}</td>
                                <td class="px-4 py-4">{{ $row['customer_count'] }}</td>
                                <td class="px-4 py-4">{{ $row['loan_count'] }}</td>
                                <td class="px-4 py-4 text-white">ZMW {{ number_format($row['total_portfolio'], 2) }}</td>
                                <td class="px-4 py-4 text-amber-300">ZMW {{ number_format($row['total_arrears'], 2) }}</td>
                                <td class="px-4 py-4">
                                    <span class="text-sm font-medium {{ $row['par'] >= 20 ? 'text-rose-300' : ($row['par'] >= 10 ? 'text-amber-300' : 'text-emerald-300') }}">
                                        {{ number_format($row['par'], 2) }}%
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-4 py-6 text-center text-slate-400">No data found for the selected branch.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Loan-level detail --}}
        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold text-white">Loans by Branch</h2>
                <p class="text-sm text-slate-400">Shows loans linked through customer groups and branches; arrears drive PAR.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-base text-slate-300">
                    <thead>
                        <tr class="text-sm font-semibold uppercase tracking-[0.25em] text-white/80 text-center border-b-2 border-white/20">
                            <th class="px-4 py-3 text-left">Branch</th>
                            <th class="px-4 py-3 text-left">Group</th>
                            <th class="px-4 py-3 text-left">Customer</th>
                            <th class="px-4 py-3">Loan #</th>
                            <th class="px-4 py-3">Product</th>
                            <th class="px-4 py-3">Outstanding</th>
                            <th class="px-4 py-3">Arrears</th>
                            <th class="px-4 py-3">PAR Bucket</th>
                            <th class="px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($loans as $loan)
                            <tr class="border-t border-white/10 hover:bg-white/5 transition">
                                <td class="px-4 py-4 text-left text-white">
                                    {{ $loan->customerGroup?->branch?->name ?? '—' }}
                                </td>
                                <td class="px-4 py-4 text-left">{{ $loan->customerGroup->name ?? '—' }}</td>
                                <td class="px-4 py-4 text-left">
                                    {{ $loan->customer?->full_name ?? '—' }}
                                </td>
                                <td class="px-4 py-4 text-center font-semibold text-white">{{ $loan->loan_number }}</td>
                                <td class="px-4 py-4 text-center">{{ $loan->loanProduct->name ?? '—' }}</td>
                                <td class="px-4 py-4 text-center text-white">ZMW {{ number_format($loan->outstanding_balance, 2) }}</td>
                                <td class="px-4 py-4 text-center text-amber-300">ZMW {{ number_format($loan->arrears_amount, 2) }}</td>
                                <td class="px-4 py-4 text-center">
                                    <span class="text-sm font-medium {{ $loan->par_bucket ? 'text-rose-300' : 'text-emerald-300' }}">
                                        {{ $loan->par_bucket ?? 'Current' }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-center capitalize">{{ str_replace('_', ' ', $loan->status) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-6 text-center text-slate-400">No loans found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        (() => {
            const canvas = document.getElementById('branchActivityChart');
            if (!canvas) return;

            const labels = @json($chartLabels);
            const disb = @json($chartDisb);
            const repay = @json($chartRepay);

            const chart = new Chart(canvas, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [
                        {
                            type: 'bar',
                            label: 'Disbursed',
                            data: disb,
                            backgroundColor: 'rgba(59, 130, 246, 0.4)',
                            borderColor: 'rgba(59, 130, 246, 0.9)',
                            borderWidth: 1.5,
                        },
                        {
                            type: 'bar',
                            label: 'Repaid',
                            data: repay,
                            backgroundColor: 'rgba(16, 185, 129, 0.35)',
                            borderColor: 'rgba(16, 185, 129, 0.9)',
                            borderWidth: 1.5,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    scales: {
                        x: { stacked: false, ticks: { color: '#cbd5e1' } },
                        y: { stacked: false, ticks: { color: '#cbd5e1' }, beginAtZero: true }
                    },
                    plugins: {
                        legend: { labels: { color: '#e2e8f0' } },
                        tooltip: {
                            callbacks: {
                                label: (ctx) => {
                                    const value = ctx.parsed.y ?? 0;
                                    return `${ctx.dataset.label}: ZMW ${value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
                                }
                            }
                        }
                    }
                }
            });

            // Ensure the canvas snaps to its container once the page layout settles
            const resizeToContainer = () => chart.resize();
            if (document.readyState === 'complete') {
                requestAnimationFrame(resizeToContainer);
            } else {
                window.addEventListener('load', () => requestAnimationFrame(resizeToContainer), { once: true });
            }
        })();
    </script>
@endpush
