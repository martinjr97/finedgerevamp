@extends('layouts.admin')

@section('title', 'Risk Heatmap Dashboard | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Risk Heatmap Dashboard',
            'description' => 'Visual dashboard showing high-risk areas across borrowers, branches, regions, and loan officers'
        ])

        {{-- Summary Cards --}}
        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-3xl border border-red-500/30 bg-red-500/10 p-6 shadow-lg">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-sm font-medium text-red-300">High-Risk Borrowers</h3>
                    <svg class="w-6 h-6 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <p class="text-3xl font-bold text-red-400">{{ $highRiskBorrowers->count() }}</p>
                <p class="text-xs text-red-300/70 mt-1">Borrowers with risk score ≥ 30</p>
            </div>

            <div class="rounded-3xl border border-orange-500/30 bg-orange-500/10 p-6 shadow-lg">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-sm font-medium text-orange-300">High-Risk Branches</h3>
                    <svg class="w-6 h-6 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                </div>
                <p class="text-3xl font-bold text-orange-400">{{ $highRiskBranches->count() }}</p>
                <p class="text-xs text-orange-300/70 mt-1">Branches with elevated risk</p>
            </div>

            <div class="rounded-3xl border border-yellow-500/30 bg-yellow-500/10 p-6 shadow-lg">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-sm font-medium text-yellow-300">Regions with Delinquency</h3>
                    <svg class="w-6 h-6 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                    </svg>
                </div>
                <p class="text-3xl font-bold text-yellow-400">{{ $delinquencyByRegion->count() }}</p>
                <p class="text-xs text-yellow-300/70 mt-1">Provinces with active loans</p>
            </div>

            <div class="rounded-3xl border border-purple-500/30 bg-purple-500/10 p-6 shadow-lg">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-sm font-medium text-purple-300">Loan Officers</h3>
                    <svg class="w-6 h-6 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                </div>
                <p class="text-3xl font-bold text-purple-400">{{ $loanOfficerRisk->count() }}</p>
                <p class="text-xs text-purple-300/70 mt-1">Active relationship managers</p>
            </div>
        </div>

        {{-- High-Risk Borrowers --}}
        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <h2 class="text-xl font-semibold text-white mb-4 flex items-center gap-2">
                <span class="inline-flex items-center rounded-full bg-red-500/20 px-2.5 py-1 text-xs font-semibold text-red-400 border border-red-500/50">
                    High Risk
                </span>
                High-Risk Borrowers
            </h2>
            <div class="overflow-x-auto">
                <table class="min-w-full w-full text-sm text-slate-300">
                    <thead>
                        <tr class="text-xs font-semibold uppercase tracking-[0.25em] text-white/80 text-center border-b-2 border-white/20">
                            <th class="px-4 py-3 text-left">Customer</th>
                            <th class="px-4 py-3">Credit Score</th>
                            <th class="px-4 py-3">Total Loans</th>
                            <th class="px-4 py-3">Loan Amount</th>
                            <th class="px-4 py-3">Overdue Amount</th>
                            <th class="px-4 py-3">Overdue %</th>
                            <th class="px-4 py-3">Risk Score</th>
                            <th class="px-4 py-3">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($highRiskBorrowers as $item)
                            @php
                                $riskColor = $item['risk_score'] >= 70 ? 'red' : ($item['risk_score'] >= 50 ? 'orange' : 'yellow');
                                $riskBg = $item['risk_score'] >= 70 ? 'bg-red-500/20 border-red-500/50 text-red-400' : ($item['risk_score'] >= 50 ? 'bg-orange-500/20 border-orange-500/50 text-orange-400' : 'bg-yellow-500/20 border-yellow-500/50 text-yellow-400');
                                $creditScoreColor = $item['credit_score'] !== null && $item['credit_score'] < 40 ? 'text-red-400' : ($item['credit_score'] !== null && $item['credit_score'] < 60 ? 'text-orange-400' : 'text-green-400');
                            @endphp
                            <tr class="border-b border-white/10 hover:bg-white/5 transition">
                                <td class="px-4 py-3">
                                    <div>
                                        <p class="font-medium text-white">{{ $item['customer']->first_name }} {{ $item['customer']->last_name }}</p>
                                        <p class="text-xs text-slate-400">{{ $item['customer']->phone }}</p>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="{{ $creditScoreColor }} font-semibold">
                                        {{ $item['credit_score'] !== null ? number_format($item['credit_score'], 1) : 'N/A' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">{{ $item['total_loans'] }}</td>
                                <td class="px-4 py-3 text-center">{{ number_format($item['total_loan_amount'], 2) }}</td>
                                <td class="px-4 py-3 text-center text-red-400 font-semibold">{{ number_format($item['overdue_amount'], 2) }}</td>
                                <td class="px-4 py-3 text-center text-red-400 font-semibold">{{ number_format($item['overdue_percentage'], 1) }}%</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $riskBg }} border">
                                        {{ number_format($item['risk_score'], 0) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <a href="{{ route('admin.customers.show', $item['customer']->id) }}" 
                                       class="inline-flex items-center gap-1.5 rounded-xl bg-cyan-500/20 border border-cyan-500/50 px-3 py-1.5 text-xs font-semibold text-cyan-300 hover:bg-cyan-500/30 transition">
                                        View
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-slate-400">No high-risk borrowers found</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- High-Risk Branches --}}
        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <h2 class="text-xl font-semibold text-white mb-4 flex items-center gap-2">
                <span class="inline-flex items-center rounded-full bg-orange-500/20 px-2.5 py-1 text-xs font-semibold text-orange-400 border border-orange-500/50">
                    Medium-High Risk
                </span>
                High-Risk Branches
            </h2>
            <div class="overflow-x-auto">
                <table class="min-w-full w-full text-sm text-slate-300">
                    <thead>
                        <tr class="text-xs font-semibold uppercase tracking-[0.25em] text-white/80 text-center border-b-2 border-white/20">
                            <th class="px-4 py-3 text-left">Branch</th>
                            <th class="px-4 py-3">Location</th>
                            <th class="px-4 py-3">Total Loans</th>
                            <th class="px-4 py-3">Loan Amount</th>
                            <th class="px-4 py-3">Overdue Amount</th>
                            <th class="px-4 py-3">Delinquency Rate</th>
                            <th class="px-4 py-3">Default Rate</th>
                            <th class="px-4 py-3">Risk Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($highRiskBranches as $item)
                            @php
                                $riskColor = $item['risk_score'] >= 70 ? 'red' : ($item['risk_score'] >= 50 ? 'orange' : 'yellow');
                                $riskBg = $item['risk_score'] >= 70 ? 'bg-red-500/20 border-red-500/50 text-red-400' : ($item['risk_score'] >= 50 ? 'bg-orange-500/20 border-orange-500/50 text-orange-400' : 'bg-yellow-500/20 border-yellow-500/50 text-yellow-400');
                            @endphp
                            <tr class="border-b border-white/10 hover:bg-white/5 transition">
                                <td class="px-4 py-3">
                                    <div>
                                        <p class="font-medium text-white">{{ $item['branch']->name }}</p>
                                        <p class="text-xs text-slate-400">{{ $item['branch']->code }}</p>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <p class="text-sm">{{ $item['branch']->province?->name ?? 'N/A' }}</p>
                                    <p class="text-xs text-slate-400">{{ $item['branch']->district?->name ?? '' }}</p>
                                </td>
                                <td class="px-4 py-3 text-center">{{ $item['total_loans'] }}</td>
                                <td class="px-4 py-3 text-center">{{ number_format($item['total_loan_amount'], 2) }}</td>
                                <td class="px-4 py-3 text-center text-red-400 font-semibold">{{ number_format($item['overdue_amount'], 2) }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="font-semibold {{ $item['delinquency_rate'] > 20 ? 'text-red-400' : ($item['delinquency_rate'] > 10 ? 'text-orange-400' : 'text-yellow-400') }}">
                                        {{ number_format($item['delinquency_rate'], 1) }}%
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="font-semibold {{ $item['default_rate'] > 10 ? 'text-red-400' : ($item['default_rate'] > 5 ? 'text-orange-400' : 'text-yellow-400') }}">
                                        {{ number_format($item['default_rate'], 1) }}%
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $riskBg }} border">
                                        {{ number_format($item['risk_score'], 0) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-slate-400">No high-risk branches found</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Delinquency by Region --}}
        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <h2 class="text-xl font-semibold text-white mb-4 flex items-center gap-2">
                <span class="inline-flex items-center rounded-full bg-yellow-500/20 px-2.5 py-1 text-xs font-semibold text-yellow-400 border border-yellow-500/50">
                    Regional Analysis
                </span>
                Delinquency by Region
            </h2>
            <div class="overflow-x-auto">
                <table class="min-w-full w-full text-sm text-slate-300">
                    <thead>
                        <tr class="text-xs font-semibold uppercase tracking-[0.25em] text-white/80 text-center border-b-2 border-white/20">
                            <th class="px-4 py-3 text-left">Province</th>
                            <th class="px-4 py-3">Total Loans</th>
                            <th class="px-4 py-3">Loan Amount</th>
                            <th class="px-4 py-3">Overdue Amount</th>
                            <th class="px-4 py-3">Delinquency Rate</th>
                            <th class="px-4 py-3">Default Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($delinquencyByRegion as $item)
                            <tr class="border-b border-white/10 hover:bg-white/5 transition">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-white">{{ $item['province']->name }}</p>
                                    <p class="text-xs text-slate-400">{{ $item['province']->code }}</p>
                                </td>
                                <td class="px-4 py-3 text-center">{{ $item['total_loans'] }}</td>
                                <td class="px-4 py-3 text-center">{{ number_format($item['total_loan_amount'], 2) }}</td>
                                <td class="px-4 py-3 text-center text-red-400 font-semibold">{{ number_format($item['overdue_amount'], 2) }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="font-semibold {{ $item['delinquency_rate'] > 20 ? 'text-red-400' : ($item['delinquency_rate'] > 10 ? 'text-orange-400' : 'text-yellow-400') }}">
                                        {{ number_format($item['delinquency_rate'], 1) }}%
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="font-semibold {{ $item['default_rate'] > 10 ? 'text-red-400' : ($item['default_rate'] > 5 ? 'text-orange-400' : 'text-yellow-400') }}">
                                        {{ number_format($item['default_rate'], 1) }}%
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-slate-400">No regional data available</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Loan Officer Performance Risk --}}
        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <h2 class="text-xl font-semibold text-white mb-4 flex items-center gap-2">
                <span class="inline-flex items-center rounded-full bg-purple-500/20 px-2.5 py-1 text-xs font-semibold text-purple-400 border border-purple-500/50">
                    Performance Risk
                </span>
                Loan Officer Performance Risk
            </h2>
            <div class="overflow-x-auto">
                <table class="min-w-full w-full text-sm text-slate-300">
                    <thead>
                        <tr class="text-xs font-semibold uppercase tracking-[0.25em] text-white/80 text-center border-b-2 border-white/20">
                            <th class="px-4 py-3 text-left">Loan Officer</th>
                            <th class="px-4 py-3">Customers</th>
                            <th class="px-4 py-3">Total Loans</th>
                            <th class="px-4 py-3">Loan Amount</th>
                            <th class="px-4 py-3">Overdue Amount</th>
                            <th class="px-4 py-3">Avg Credit Score</th>
                            <th class="px-4 py-3">Delinquency Rate</th>
                            <th class="px-4 py-3">Default Rate</th>
                            <th class="px-4 py-3">Risk Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($loanOfficerRisk as $item)
                            @php
                                $riskColor = $item['risk_score'] >= 70 ? 'red' : ($item['risk_score'] >= 50 ? 'orange' : 'yellow');
                                $riskBg = $item['risk_score'] >= 70 ? 'bg-red-500/20 border-red-500/50 text-red-400' : ($item['risk_score'] >= 50 ? 'bg-orange-500/20 border-orange-500/50 text-orange-400' : 'bg-yellow-500/20 border-yellow-500/50 text-yellow-400');
                            @endphp
                            <tr class="border-b border-white/10 hover:bg-white/5 transition">
                                <td class="px-4 py-3">
                                    <div>
                                        <p class="font-medium text-white">{{ $item['officer']->full_name }}</p>
                                        <p class="text-xs text-slate-400">{{ $item['officer']->email }}</p>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center">{{ $item['customer_count'] }}</td>
                                <td class="px-4 py-3 text-center">{{ $item['total_loans'] }}</td>
                                <td class="px-4 py-3 text-center">{{ number_format($item['total_loan_amount'], 2) }}</td>
                                <td class="px-4 py-3 text-center text-red-400 font-semibold">{{ number_format($item['overdue_amount'], 2) }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="font-semibold {{ $item['avg_credit_score'] < 40 ? 'text-red-400' : ($item['avg_credit_score'] < 60 ? 'text-orange-400' : 'text-green-400') }}">
                                        {{ number_format($item['avg_credit_score'], 1) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="font-semibold {{ $item['delinquency_rate'] > 25 ? 'text-red-400' : ($item['delinquency_rate'] > 15 ? 'text-orange-400' : 'text-yellow-400') }}">
                                        {{ number_format($item['delinquency_rate'], 1) }}%
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="font-semibold {{ $item['default_rate'] > 15 ? 'text-red-400' : ($item['default_rate'] > 10 ? 'text-orange-400' : 'text-yellow-400') }}">
                                        {{ number_format($item['default_rate'], 1) }}%
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $riskBg }} border">
                                        {{ number_format($item['risk_score'], 0) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-8 text-center text-slate-400">No loan officer data available</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

