@extends('layouts.admin')

@section('title', 'Balance Sheet | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Balance Sheet',
            'buttons' => []
        ])

        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <form method="GET" action="{{ route('admin.financial-statements.balance-sheet') }}" class="mb-6 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="text-sm font-medium text-slate-300 mb-2 block">As of Date <span class="text-rose-400">*</span></label>
                        <input type="date" name="as_of_date" value="{{ $asOfDate }}" required class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-300 mb-2 block">Include Loans From</label>
                        <input type="date" name="loans_from_date" value="{{ $loansFromDate }}" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                        <p class="text-xs text-slate-400 mt-1">Optional filter to exclude older loans</p>
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="submit" class="flex-1 rounded-2xl bg-gradient-to-r from-purple-500 to-purple-600 px-6 py-2.5 font-semibold text-white shadow-lg shadow-purple-500/40 transition hover:scale-[1.01] hover:shadow-xl hover:shadow-purple-500/50">
                            Generate Report
                        </button>
                        <button type="button" onclick="window.print()" class="flex-1 rounded-2xl bg-gradient-to-r from-emerald-500 to-lime-600 px-6 py-2.5 font-semibold text-white shadow-lg shadow-emerald-500/40 transition hover:scale-[1.01] hover:shadow-xl hover:shadow-emerald-500/50">
                            Print Report
                        </button>
                    </div>
                </div>
            </form>

            @if(request()->has('as_of_date'))
            <div class="mb-6 p-4 bg-blue-500/10 border border-blue-500/20 rounded-2xl">
                <div class="flex items-center gap-6 text-sm">
                    <span class="text-blue-300 font-medium">As of Date: <span class="text-white font-semibold">{{ \Carbon\Carbon::parse($asOfDate)->format('d M Y') }}</span></span>
                    @if($loansFromDate)
                        <span class="text-blue-300 font-medium">Loans Included: <span class="text-white font-semibold">From {{ \Carbon\Carbon::parse($loansFromDate)->format('d M Y') }}</span></span>
                    @else
                        <span class="text-blue-300 font-medium">Loans Included: <span class="text-white font-semibold">Active Loans (Status: Active)</span></span>
                    @endif
                </div>
            </div>

            <div class="grid md:grid-cols-2 gap-8">
                <!-- ASSETS Section -->
                <div class="space-y-4">
                    <h2 class="text-2xl font-bold text-emerald-400">ASSETS</h2>
                    
                    <div class="space-y-3">
                        <h3 class="text-lg font-semibold text-white">Current Assets</h3>
                        
                        <!-- Cash in Banks -->
                        <div class="space-y-2">
                            <h4 class="text-sm font-medium text-slate-300">Cash in Banks</h4>
                            @foreach($banks as $bank)
                                <div class="flex justify-between items-center text-sm pl-4">
                                    <span class="text-slate-300">{{ $bank->bank_name ?? $bank->name }}@if($bank->bank_name && $bank->name !== $bank->bank_name) ({{ strtoupper(substr($bank->bank_name, 0, 4)) }})@endif</span>
                                    <span class="text-white font-medium">{{ number_format($bank->current_balance, 2) }}</span>
                                </div>
                            @endforeach
                            <div class="flex justify-between items-center py-2 border-t border-white/10 mt-2 bg-slate-700/30 px-2 rounded">
                                <span class="text-slate-200 font-semibold">Total Cash in Banks</span>
                                <span class="text-white font-bold">{{ number_format($totalCashInBanks, 2) }}</span>
                            </div>
                        </div>

                        <!-- Cash on Hand -->
                        <div class="space-y-2">
                            <h4 class="text-sm font-medium text-slate-300">Cash on Hand</h4>
                            @forelse($cashRegisters as $register)
                                <div class="flex justify-between items-center text-sm pl-4">
                                    <span class="text-slate-300">{{ $register->name }}</span>
                                    <span class="text-white font-medium">{{ number_format($register->current_balance, 2) }}</span>
                                </div>
                            @empty
                                <div class="pl-4 text-sm text-slate-400">No cash registers configured</div>
                            @endforelse
                            <div class="flex justify-between items-center py-2 border-t border-white/10 mt-2 bg-slate-700/30 px-2 rounded">
                                <span class="text-slate-200 font-semibold">Total Cash on Hand</span>
                                <span class="text-white font-bold">{{ number_format($totalCashOnHand, 2) }}</span>
                            </div>
                        </div>

                        <!-- Cash in Mobile Wallets -->
                        <div class="space-y-2">
                            <h4 class="text-sm font-medium text-slate-300">Cash in Mobile Wallets</h4>
                            @foreach($wallets as $wallet)
                                <div class="flex justify-between items-center text-sm pl-4">
                                    <span class="text-slate-300">{{ $wallet->name }} ({{ strtoupper($wallet->provider) }})</span>
                                    <span class="text-white font-medium">{{ number_format($wallet->current_balance, 2) }}</span>
                                </div>
                            @endforeach
                            <div class="flex justify-between items-center py-2 border-t border-white/10 mt-2 bg-slate-700/30 px-2 rounded">
                                <span class="text-slate-200 font-semibold">Total Cash in Wallets</span>
                                <span class="text-white font-bold">{{ number_format($totalCashInWallets, 2) }}</span>
                            </div>
                        </div>

                        <!-- Total Cash & Cash Equivalents -->
                        <div class="flex justify-between items-center py-3 border-t border-white/40 mt-4 bg-blue-500/20 px-3 rounded">
                            <span class="text-white font-bold text-lg">Total Cash & Cash Equivalents</span>
                            <span class="text-white font-bold text-xl">{{ number_format($cashAndCashEquivalents, 2) }}</span>
                        </div>
                    </div>

                    <!-- Other Assets -->
                    <div class="space-y-3 mt-6">
                        <h3 class="text-lg font-semibold text-white">Other Assets</h3>
                        <div class="flex justify-between items-center py-2">
                            <div class="flex items-center gap-2">
                                <span class="text-slate-300">Loans Receivable</span>
                                <span class="rounded-full bg-blue-500/20 px-2 py-0.5 text-xs text-blue-300 border border-blue-500/30">{{ $loansCount }}</span>
                            </div>
                            <span class="text-white font-semibold text-lg">{{ number_format($loansReceivable, 2) }}</span>
                        </div>
                    </div>

                    <!-- Total Assets -->
                    <div class="flex justify-between items-center py-4 border-t-2 border-white/30 mt-6 bg-slate-800/50 px-4 rounded">
                        <span class="text-white font-bold text-xl">Total Assets</span>
                        <span class="text-white font-bold text-2xl">{{ number_format($totalAssets, 2) }}</span>
                    </div>
                </div>

                <!-- LIABILITIES & EQUITY Section -->
                <div class="space-y-4">
                    <h2 class="text-2xl font-bold text-rose-400">LIABILITIES & EQUITY</h2>
                    
                    <div class="space-y-3">
                        <h3 class="text-lg font-semibold text-white">Liabilities</h3>
                        
                        <!-- Creditors -->
                        <div class="space-y-2">
                            <h4 class="text-sm font-medium text-slate-300">Creditors</h4>
                            @forelse($creditors as $creditor)
                                <div class="pl-4 space-y-1">
                                    <div class="flex justify-between items-center text-sm">
                                        <span class="text-slate-300">{{ $creditor->name }}</span>
                                        <span class="text-white font-medium">{{ number_format($creditor->amount, 2) }}</span>
                                    </div>
                                    @if($creditor->due_date)
                                        <div class="text-xs text-slate-400 pl-4">Due {{ $creditor->due_date->format('d M Y') }}</div>
                                    @endif
                                    @if($creditor->description)
                                        <div class="text-xs text-slate-400 pl-4 italic">{{ $creditor->description }}</div>
                                    @endif
                                </div>
                            @empty
                                <div class="pl-4 text-sm text-slate-400">No creditors</div>
                            @endforelse
                            <div class="flex justify-between items-center py-2 border-t border-white/10 mt-2 bg-slate-700/30 px-2 rounded">
                                <span class="text-slate-200 font-semibold">Total Creditors</span>
                                <span class="text-white font-bold">{{ number_format($creditors->sum('amount'), 2) }}</span>
                            </div>
                        </div>

                        <!-- Total Liabilities -->
                        <div class="flex justify-between items-center py-2 border-t border-white/10 mt-4 bg-slate-700/30 px-2 rounded">
                            <span class="text-slate-200 font-semibold">Total Liabilities</span>
                            <span class="text-white font-bold">{{ number_format($totalLiabilities, 2) }}</span>
                        </div>
                    </div>

                    <!-- Equity -->
                    <div class="space-y-3 mt-6">
                        <h3 class="text-lg font-semibold text-white">Equity</h3>
                        <div class="flex justify-between items-center py-3 border-t border-white/40 bg-slate-700/30 px-3 rounded">
                            <span class="text-white font-bold text-lg">Total Equity</span>
                            <span class="text-white font-bold text-xl {{ $equity >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">{{ number_format($equity, 2) }}</span>
                        </div>
                    </div>

                    <!-- Total Liabilities & Equity -->
                    <div class="flex justify-between items-center py-4 border-t-2 border-white/30 mt-6 bg-purple-500/20 px-4 rounded">
                        <span class="text-white font-bold text-xl">TOTAL LIABILITIES & EQUITY</span>
                        <span class="text-white font-bold text-2xl">{{ number_format($totalLiabilities + $equity, 2) }}</span>
                    </div>
                </div>
            </div>

            <!-- Loans Breakdown by Status -->
            @if($loansBreakdown->isNotEmpty())
            <div class="mt-8 rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                <h3 class="text-lg font-semibold text-white mb-4">Loans Breakdown by Status</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full w-full text-sm text-slate-300">
                        <thead>
                            <tr class="text-xs font-semibold uppercase tracking-[0.25em] text-white/80 text-center border-b border-white/10">
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Count</th>
                                <th class="px-4 py-3">Outstanding (ZMW)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($loansBreakdown as $breakdown)
                                <tr class="border-b border-white/5 text-center">
                                    <td class="px-4 py-3 capitalize">{{ str_replace('_', ' ', $breakdown->status) }}</td>
                                    <td class="px-4 py-3">{{ $breakdown->count }}</td>
                                    <td class="px-4 py-3 font-semibold">{{ number_format($breakdown->outstanding, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif
            @endif
        </div>
    </div>
@endsection
