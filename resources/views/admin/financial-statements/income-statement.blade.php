@extends('layouts.admin')

@section('title', 'Income Statement | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        <div class="flex justify-between items-center">
            <h1 class="text-2xl font-bold text-white">Income Statement</h1>
        </div>

        <!-- Date Filters and Action Buttons -->
        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <form method="GET" action="{{ route('admin.financial-statements.income-statement') }}" id="incomeStatementForm" class="mb-6">
                <div class="flex flex-wrap items-end gap-4">
                    <div class="flex-1 min-w-[200px]">
                        <label class="text-sm text-slate-300 mb-2 block font-medium">Start Date</label>
                        <div class="relative">
                            <input type="date" name="start_date" value="{{ $startDate }}" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 pr-10 focus:border-purple-500 focus:ring-purple-500/40">
                            <svg class="absolute right-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="flex-1 min-w-[200px]">
                        <label class="text-sm text-slate-300 mb-2 block font-medium">End Date</label>
                        <div class="relative">
                            <input type="date" name="end_date" value="{{ $endDate }}" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 pr-10 focus:border-purple-500 focus:ring-purple-500/40">
                            <svg class="absolute right-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <button type="submit" class="rounded-2xl bg-purple-600 hover:bg-purple-700 px-6 py-2 text-sm font-medium text-white transition">
                            Generate Report
                        </button>
                        <button type="button" onclick="window.print()" class="rounded-2xl bg-emerald-600 hover:bg-emerald-700 px-6 py-2 text-sm font-medium text-white transition">
                            Print Report
                        </button>
                        <button type="button" onclick="exportToExcel()" class="rounded-2xl bg-white/10 border-2 border-emerald-500/50 hover:bg-emerald-500/20 px-6 py-2 text-sm font-medium text-emerald-300 transition flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Export Excel
                        </button>
                    </div>
                </div>
            </form>

            <!-- Report Period -->
            <div class="mb-6">
                <p class="text-sm text-slate-400">
                    Report Period: <span class="text-white font-medium">{{ \Carbon\Carbon::parse($startDate)->format('d M Y') }} to {{ \Carbon\Carbon::parse($endDate)->format('d M Y') }}</span>
                </p>
            </div>

            <!-- INCOME Section -->
            <div class="mb-8">
                <h2 class="text-lg font-semibold text-white mb-4 uppercase tracking-wide">INCOME</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full w-full rounded-lg border border-white/10 bg-white/5">
                        <thead>
                            <tr class="bg-gradient-to-r from-cyan-500/20 to-blue-500/20 border-b-2 border-cyan-500/30">
                                <th class="px-6 py-3 text-left text-xs font-semibold text-white uppercase tracking-wider">Source</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold text-white uppercase tracking-wider">Amount (ZMW)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @forelse($incomeSources as $source => $amount)
                                <tr>
                                    <td class="px-6 py-3 text-sm text-white">{{ $source }}</td>
                                    <td class="px-6 py-3 text-sm text-white text-right">{{ number_format($amount, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="px-6 py-3 text-sm text-slate-400 text-center">No income recorded for this period</td>
                                </tr>
                            @endforelse
                            <tr class="bg-white/10 font-semibold border-t-2 border-cyan-500/30">
                                <td class="px-6 py-3 text-sm text-white">TOTAL INCOME</td>
                                <td class="px-6 py-3 text-sm text-white text-right">{{ number_format($totalRevenue, 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- EXPENSES Section -->
            <div class="mb-8">
                <h2 class="text-lg font-semibold text-white mb-4 uppercase tracking-wide">EXPENSES</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full w-full rounded-lg border border-white/10 bg-white/5">
                        <thead>
                            <tr class="bg-gradient-to-r from-cyan-500/20 to-blue-500/20 border-b-2 border-cyan-500/30">
                                <th class="px-6 py-3 text-left text-xs font-semibold text-white uppercase tracking-wider">Category</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold text-white uppercase tracking-wider">Amount (ZMW)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @forelse($expenses as $category => $amount)
                                <tr>
                                    <td class="px-6 py-3 text-sm text-white">{{ $category }}</td>
                                    <td class="px-6 py-3 text-sm text-white text-right">{{ number_format($amount, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="px-6 py-3 text-sm text-slate-400 text-center">No expenses recorded for this period</td>
                                </tr>
                            @endforelse
                            <tr class="bg-white/10 font-semibold border-t-2 border-cyan-500/30">
                                <td class="px-6 py-3 text-sm text-white">TOTAL EXPENSES</td>
                                <td class="px-6 py-3 text-sm text-white text-right">{{ number_format($totalExpenses, 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Net Income Summary -->
            <div class="mb-8">
                <div class="bg-emerald-500 rounded-lg p-6 text-white">
                    <div class="text-sm mb-2">Net Income</div>
                    <div class="text-3xl font-bold mb-2">ZMW {{ number_format($netIncome, 2) }}</div>
                    <div class="text-sm">Profit Margin: {{ number_format($profitMargin, 2) }}%</div>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-purple-500/20 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-purple-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm text-slate-400">Total Income</p>
                            <p class="text-xl font-bold text-purple-300">ZMW {{ number_format($totalRevenue, 2) }}</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-rose-500/20 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-rose-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm text-slate-400">Total Expenses</p>
                            <p class="text-xl font-bold text-rose-300">ZMW {{ number_format($totalExpenses, 2) }}</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-emerald-500/20 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-emerald-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm text-slate-400">Net Income</p>
                            <p class="text-xl font-bold text-emerald-300">ZMW {{ number_format($netIncome, 2) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function exportToExcel() {
            // Redirect to the same route with export parameter
            const url = new URL('{{ route("admin.financial-statements.income-statement") }}', window.location.origin);
            url.searchParams.set('export', 'excel');
            url.searchParams.set('start_date', '{{ $startDate }}');
            url.searchParams.set('end_date', '{{ $endDate }}');
            window.location.href = url.toString();
        }

        // Print styles
        const style = document.createElement('style');
        style.textContent = `
            @media print {
                body * {
                    visibility: hidden;
                }
                .rounded-3xl, .rounded-3xl * {
                    visibility: visible;
                }
                .rounded-3xl {
                    position: absolute;
                    left: 0;
                    top: 0;
                    width: 100%;
                }
                button {
                    display: none !important;
                }
            }
        `;
        document.head.appendChild(style);
    </script>
@endsection
