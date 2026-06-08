@extends('layouts.admin')

@section('title', 'Cash Flow Statement | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Cash Flow Statement',
            'buttons' => []
        ])

        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <form method="GET" action="{{ route('admin.financial-statements.cash-flow') }}" class="mb-6">
                <div class="flex flex-wrap items-end gap-3">
                    <div class="flex-1 min-w-[200px]">
                        <label class="text-sm font-medium text-slate-300 mb-2 block">Start Date</label>
                        <input type="date" name="start_date" value="{{ $startDate }}" class="w-full rounded-lg bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-purple-500 focus:ring-purple-500">
                    </div>
                    <div class="flex-1 min-w-[200px]">
                        <label class="text-sm font-medium text-slate-300 mb-2 block">End Date</label>
                        <input type="date" name="end_date" value="{{ $endDate }}" class="w-full rounded-lg bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-purple-500 focus:ring-purple-500">
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="rounded-lg bg-purple-600 border-2 border-purple-400 hover:bg-purple-700 hover:border-purple-300 px-4 py-2 text-sm font-medium text-white transition shadow-sm">
                            Generate Report
                        </button>
                        <button type="button" onclick="window.print()" class="rounded-lg bg-emerald-600 border-2 border-emerald-400 hover:bg-emerald-700 hover:border-emerald-300 px-4 py-2 text-sm font-medium text-white transition shadow-sm">
                            Print Report
                        </button>
                        <button type="button" onclick="exportToExcel()" class="rounded-lg bg-white border-2 border-emerald-500 hover:bg-emerald-50 hover:border-emerald-400 px-4 py-2 text-sm font-medium text-emerald-600 transition flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Export Excel
                        </button>
                    </div>
                </div>
            </form>

            @if(request()->has('start_date'))
            <div class="mb-6 p-4 bg-slate-700/30 border border-white/10 rounded-2xl">
                <p class="text-sm text-slate-300">
                    <span class="font-medium">Report Period:</span> 
                    {{ \Carbon\Carbon::parse($startDate)->format('d M Y') }} to {{ \Carbon\Carbon::parse($endDate)->format('d M Y') }}
                </p>
            </div>

            <div class="space-y-8">
                <!-- CASH INFLOWS -->
                <div>
                    <h2 class="text-2xl font-bold text-emerald-400 mb-4">CASH INFLOWS</h2>
                    <div class="rounded-2xl border border-white/10 bg-white/5 overflow-hidden">
                        <table class="min-w-full w-full text-sm">
                            <thead>
                                <tr class="bg-white/5 border-b border-white/10">
                                    <th class="px-4 py-3 text-left text-slate-300 font-semibold">Activity</th>
                                    <th class="px-4 py-3 text-right text-slate-300 font-semibold">Amount (ZMW)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="border-b border-white/5">
                                    <td class="px-4 py-3 text-slate-300">Cash from Loan Repayments</td>
                                    <td class="px-4 py-3 text-right text-white font-medium">{{ number_format($cashFromLoanRepayments, 2) }}</td>
                                </tr>
                                <tr class="border-b border-white/5">
                                    <td class="px-4 py-3 text-slate-300">Cash from Stakeholder Contributions</td>
                                    <td class="px-4 py-3 text-right text-white font-medium">{{ number_format($cashFromStakeholderContributions, 2) }}</td>
                                </tr>
                                <tr class="bg-emerald-500/10 border-t-2 border-emerald-500/30">
                                    <td class="px-4 py-3 font-bold text-white">TOTAL INFLOW</td>
                                    <td class="px-4 py-3 text-right font-bold text-emerald-400 text-lg">{{ number_format($totalInflow, 2) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- CASH OUTFLOWS -->
                <div>
                    <h2 class="text-2xl font-bold text-emerald-400 mb-4">CASH OUTFLOWS</h2>
                    <div class="rounded-2xl border border-white/10 bg-white/5 overflow-hidden">
                        <table class="min-w-full w-full text-sm">
                            <thead>
                                <tr class="bg-white/5 border-b border-white/10">
                                    <th class="px-4 py-3 text-left text-slate-300 font-semibold">Activity</th>
                                    <th class="px-4 py-3 text-right text-slate-300 font-semibold">Amount (ZMW)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="border-b border-white/5">
                                    <td class="px-4 py-3 text-slate-300">Operating Expenses</td>
                                    <td class="px-4 py-3 text-right text-white font-medium">{{ number_format($operatingExpenses, 2) }}</td>
                                </tr>
                                <tr class="border-b border-white/5">
                                    <td class="px-4 py-3 text-slate-300">Loans Disbursed</td>
                                    <td class="px-4 py-3 text-right text-white font-medium">{{ number_format($loansDisbursed, 2) }}</td>
                                </tr>
                                <tr class="bg-rose-500/10 border-t-2 border-rose-500/30">
                                    <td class="px-4 py-3 font-bold text-white">TOTAL OUTFLOW</td>
                                    <td class="px-4 py-3 text-right font-bold text-rose-400 text-lg">{{ number_format($totalOutflow, 2) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- NET CASH FLOW -->
                <div class="rounded-2xl bg-emerald-500/20 border-2 border-emerald-500/50 p-6">
                    <div class="text-center">
                        <h3 class="text-xl font-bold text-emerald-300 mb-2">NET CASH FLOW</h3>
                        <p class="text-3xl font-bold text-white">ZMW {{ number_format($netCashFlow, 2) }}</p>
                    </div>
                </div>

                <!-- CASH FLOW BY BANK -->
                @if($bankCashFlows && count($bankCashFlows) > 0)
                <div>
                    <h2 class="text-xl font-semibold text-white mb-4">CASH FLOW BY BANK</h2>
                    <div class="rounded-2xl border border-white/10 bg-white/5 overflow-hidden">
                        <table class="min-w-full w-full text-sm">
                            <thead>
                                <tr class="bg-white/5 border-b border-white/10">
                                    <th class="px-4 py-3 text-left text-slate-300 font-semibold">Bank</th>
                                    <th class="px-4 py-3 text-right text-slate-300 font-semibold">Inflow (ZMW)</th>
                                    <th class="px-4 py-3 text-right text-slate-300 font-semibold">Outflow (ZMW)</th>
                                    <th class="px-4 py-3 text-right text-slate-300 font-semibold">Net Cash Flow (ZMW)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($bankCashFlows as $flow)
                                    <tr class="border-b border-white/5">
                                        <td class="px-4 py-3 text-slate-300">{{ $flow['bank']->bank_name ?? $flow['bank']->name }}@if($flow['bank']->bank_name && $flow['bank']->name !== $flow['bank']->bank_name) ({{ strtoupper(substr($flow['bank']->bank_name, 0, 4)) }})@endif</td>
                                        <td class="px-4 py-3 text-right text-emerald-400 font-medium">{{ number_format($flow['inflow'], 2) }}</td>
                                        <td class="px-4 py-3 text-right text-rose-400 font-medium">{{ number_format($flow['outflow'], 2) }}</td>
                                        <td class="px-4 py-3 text-right font-semibold {{ $flow['net'] >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">{{ number_format($flow['net'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif

                <!-- CASH FLOW BY MOBILE WALLET -->
                @if($walletCashFlows && count($walletCashFlows) > 0)
                <div>
                    <h2 class="text-xl font-semibold text-white mb-4">CASH FLOW BY MOBILE WALLET</h2>
                    <div class="rounded-2xl border border-white/10 bg-white/5 overflow-hidden">
                        <table class="min-w-full w-full text-sm">
                            <thead>
                                <tr class="bg-white/5 border-b border-white/10">
                                    <th class="px-4 py-3 text-left text-slate-300 font-semibold">Wallet</th>
                                    <th class="px-4 py-3 text-right text-slate-300 font-semibold">Inflow (ZMW)</th>
                                    <th class="px-4 py-3 text-right text-slate-300 font-semibold">Outflow (ZMW)</th>
                                    <th class="px-4 py-3 text-right text-slate-300 font-semibold">Net Cash Flow (ZMW)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($walletCashFlows as $flow)
                                    <tr class="border-b border-white/5">
                                        <td class="px-4 py-3 text-slate-300">{{ $flow['wallet']->name }} ({{ strtoupper($flow['wallet']->provider) }})</td>
                                        <td class="px-4 py-3 text-right text-emerald-400 font-medium">{{ number_format($flow['inflow'], 2) }}</td>
                                        <td class="px-4 py-3 text-right text-rose-400 font-medium">{{ number_format($flow['outflow'], 2) }}</td>
                                        <td class="px-4 py-3 text-right font-semibold {{ $flow['net'] >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">{{ number_format($flow['net'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif

                <!-- INHOUSE TRANSFERS -->
                @if($inhouseTransfers && $inhouseTransfers->count() > 0)
                <div>
                    <h2 class="text-xl font-semibold text-white mb-2">INHOUSE TRANSFERS (Internal Movements)</h2>
                    <div class="p-4 bg-blue-500/10 border border-blue-500/20 rounded-2xl">
                        <p class="text-sm text-blue-300">
                            <strong>Note:</strong> Inhouse transfers are internal movements between accounts and do not affect the net cash flow calculation.
                        </p>
                    </div>
                </div>
                @endif
            </div>
            @endif
        </div>
    </div>

    <script>
        function exportToExcel() {
            // Simple Excel export - you can enhance this later
            const table = document.querySelector('table');
            if (!table) return;
            
            let csv = [];
            const rows = table.querySelectorAll('tr');
            
            for (let i = 0; i < rows.length; i++) {
                const row = [], cols = rows[i].querySelectorAll('td, th');
                
                for (let j = 0; j < cols.length; j++) {
                    row.push(cols[j].innerText);
                }
                
                csv.push(row.join(','));
            }
            
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            
            link.setAttribute('href', url);
            link.setAttribute('download', 'cash-flow-statement-{{ $startDate }}-to-{{ $endDate }}.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
@endsection
