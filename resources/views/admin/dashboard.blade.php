@extends('layouts.admin')

@section('title', 'Dashboard | '.config('app.system_name'))
@section('page-title', 'Dashboard')

@section('content')
    @php
        $adminUser = auth('admin')->user();
        $canViewCompanies = (bool) $adminUser?->can('companies.view');
        $canViewLoanProducts = (bool) $adminUser?->can('loan-products.view');
        $canViewCustomers = (bool) $adminUser?->can('customers.view');
        $canViewLoans = (bool) $adminUser?->can('loans.view');
        $canViewApprovals = (bool) $adminUser?->can('approvals.view');
        $canViewRepayments = (bool) $adminUser?->can('repayments.view');
        $canDisburseLoans = (bool) $adminUser?->can('loans.disburse');

        $canViewOverviewStats = $canViewCompanies
            || $canViewLoanProducts
            || $canViewCustomers
            || $canViewLoans
            || $canViewApprovals
            || $canViewRepayments;

        $canViewActivityStats = $canViewLoans || $canViewRepayments || $canViewCustomers;
        $canViewTrendChart = $canViewLoans || $canViewRepayments;
    @endphp

    <style>
        .dashboard-period-btn {
            border-radius: 0.75rem;
            border-width: 1px;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            font-weight: 700;
            transition: all 0.2s ease;
        }

        .dashboard-period-btn-active {
            background: #dbeafe;
            color: #0a2540;
            border-color: #93c5fd;
            box-shadow: 0 8px 18px rgba(59, 130, 246, 0.18);
        }

        .dashboard-period-btn-inactive {
            background: #ffffff;
            color: #0a2540;
            border-color: rgba(148, 163, 184, 0.7);
        }

        .dashboard-period-btn-inactive:hover {
            background: #f1f5f9;
            border-color: #94a3b8;
        }

        .dashboard-welcome-card {
            background: linear-gradient(90deg, #f8fafc 0%, #e2e8f0 100%);
            border: 1px solid #cbd5e1;
            color: #0f172a;
        }

        .dashboard-welcome-eyebrow {
            color: #64748b;
        }

        .dashboard-welcome-title {
            color: #0f172a;
        }

        .dashboard-welcome-text {
            color: #334155;
        }

        .dashboard-legend {
            color: #334155;
            font-weight: 600;
        }

        .dashboard-legend-dot {
            width: 0.65rem;
            height: 0.65rem;
            border-radius: 9999px;
            display: inline-block;
            flex-shrink: 0;
        }

        .dashboard-legend-dot-loans {
            background: #2563eb;
        }

        .dashboard-legend-dot-repayments {
            background: #10b981;
        }
    </style>

    <div class="space-y-8" x-data="{ activeTab: 'today' }">
        <section class="dashboard-welcome-card rounded-3xl p-6 shadow-lg">
            <p class="dashboard-welcome-eyebrow text-xs uppercase tracking-[0.25em]">Welcome</p>
            <h2 class="dashboard-welcome-title text-2xl font-semibold mt-2">
                {{ $adminGreeting['greeting'] ?? 'Welcome' }}, {{ $adminGreeting['name'] ?? 'Admin' }}
            </h2>
            @if(!empty($adminGreeting['branch']))
                <p class="dashboard-welcome-text text-sm mt-2">Branch: <span class="font-semibold">{{ $adminGreeting['branch'] }}</span></p>
            @endif
            <p class="dashboard-welcome-text text-sm mt-2">{{ $adminGreeting['note'] ?? 'Here is your latest dashboard activity.' }}</p>
        </section>

        {{-- Overall Stats --}}
        @if ($canViewOverviewStats)
            <section id="dashboard-stats">
                <h2 class="text-xl font-semibold mb-4 text-slate-300">Overview</h2>
                <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                    @if ($canViewCompanies)
                        <a href="{{ route('admin.companies.index') }}" class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg hover:bg-white/10 hover:border-white/20 transition-all cursor-pointer group">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-sm text-slate-400 group-hover:text-slate-300 transition">Active Companies</p>
                                <svg class="w-5 h-5 text-slate-400 group-hover:text-slate-300 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </div>
                            <p class="text-4xl font-semibold mt-2">{{ number_format($overallStats['active_companies']) }}</p>
                            <p class="text-slate-500 text-sm mt-3 group-hover:text-slate-400 transition">Partners &amp; operator nodes currently synced.</p>
                        </a>
                    @endif

                    @if ($canViewLoanProducts)
                        <a href="{{ route('admin.loan-products.index') }}" class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg hover:bg-white/10 hover:border-white/20 transition-all cursor-pointer group">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-sm text-slate-400 group-hover:text-slate-300 transition">Loan Products</p>
                                <svg class="w-5 h-5 text-slate-400 group-hover:text-slate-300 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </div>
                            <p class="text-4xl font-semibold mt-2">{{ number_format($overallStats['loan_products']) }}</p>
                            <p class="text-slate-500 text-sm mt-3 group-hover:text-slate-400 transition">Available offerings across all pipelines.</p>
                        </a>
                    @endif

                    @if ($canViewCustomers)
                        <a href="{{ route('admin.customers.index') }}" class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg hover:bg-white/10 hover:border-white/20 transition-all cursor-pointer group">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-sm text-slate-400 group-hover:text-slate-300 transition">Registered Customers</p>
                                <svg class="w-5 h-5 text-slate-400 group-hover:text-slate-300 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </div>
                            <p class="text-4xl font-semibold mt-2">{{ number_format($overallStats['total_customers']) }}</p>
                            <p class="text-slate-500 text-sm mt-3 group-hover:text-slate-400 transition">Borrowers onboarded across products.</p>
                        </a>
                    @endif

                    @if ($canViewLoans)
                        <a href="{{ route('admin.loans.index', ['status' => 'active', 'disbursement_status' => 'completed']) }}" class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg hover:bg-white/10 hover:border-white/20 transition-all cursor-pointer group">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-sm text-slate-400 group-hover:text-slate-300 transition">Active Loans</p>
                                <svg class="w-5 h-5 text-slate-400 group-hover:text-slate-300 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </div>
                            <p class="text-4xl font-semibold mt-2">{{ number_format($overallStats['active_loans']) }}</p>
                            <p class="text-slate-500 text-sm mt-3 group-hover:text-slate-400 transition">Approved and disbursed loans currently in repayment (excludes approved awaiting disbursement).</p>
                        </a>
                    @endif

                    @if ($canViewApprovals)
                        <a href="{{ route('admin.approvals.index') }}" class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg hover:bg-white/10 hover:border-white/20 transition-all cursor-pointer group">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-sm text-slate-400 group-hover:text-slate-300 transition">Pending Approvals</p>
                                <svg class="w-5 h-5 text-slate-400 group-hover:text-slate-300 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </div>
                            <p class="text-4xl font-semibold mt-2">{{ number_format($overallStats['pending_approvals']) }}</p>
                            <p class="text-slate-500 text-sm mt-3 group-hover:text-slate-400 transition">Admins, companies, customers, loans, repayments, and transfers awaiting action.</p>
                        </a>
                    @endif

                    @if ($canViewLoans)
                        <article class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                            <p class="text-sm text-slate-400">Total Outstanding</p>
                            <p class="text-4xl font-semibold mt-2">{{ number_format($overallStats['total_outstanding'], 2) }}</p>
                            <p class="text-slate-500 text-sm mt-3">Outstanding balance on disbursed active loans only (excludes approved loans awaiting disbursement).</p>
                        </article>
                    @endif

                    @if ($canViewRepayments || $canViewLoans)
                        <a href="{{ route('admin.loans.todays-payments') }}" class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg hover:bg-white/10 hover:border-white/20 transition-all cursor-pointer group">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-sm text-slate-400 group-hover:text-slate-300 transition">Repayments Due Today</p>
                                <div class="flex items-center gap-2">
                                    <svg class="w-5 h-5 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                    <svg class="w-5 h-5 text-slate-400 group-hover:text-slate-300 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </div>
                            </div>
                            <p class="text-4xl font-semibold mt-2">ZMW {{ number_format($overallStats['total_repayments_due_today'], 2) }}</p>
                            <p class="text-slate-500 text-sm mt-3 group-hover:text-slate-400 transition">Total amount due in repayments scheduled for today.</p>
                        </a>
                    @endif
                </div>
            </section>
        @endif

        {{-- Pending Disbursement Queue (only when there are approved loans awaiting disbursement) --}}
        @if ($canDisburseLoans && $pendingDisbursementCount > 0)
            <section>
                <article class="rounded-3xl border border-amber-300/30 bg-amber-500/10 p-6 shadow-lg">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h2 class="text-xl font-semibold text-amber-100">Pending Disbursement Queue</h2>
                            <p class="text-sm text-amber-200/80 mt-1">Approved loans awaiting disbursement. Prioritize loans where the start date has already passed.</p>
                        </div>
                        <a href="{{ route('admin.loans.index', ['status' => 'approved', 'disbursement_status' => 'pending']) }}"
                           class="inline-flex items-center gap-2 rounded-xl border border-amber-300/40 bg-amber-500/20 px-4 py-2 text-sm font-semibold text-amber-100 hover:bg-amber-500/30 transition">
                            View Full Queue
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                    </div>

                    <div class="mt-5 grid gap-4 md:grid-cols-3">
                        <article class="rounded-2xl border border-white/10 bg-white/10 p-4">
                            <p class="text-xs uppercase tracking-[0.2em] text-amber-200/80">Pending Loans</p>
                            <p class="text-2xl font-semibold text-white mt-2">{{ number_format($pendingDisbursementCount) }}</p>
                        </article>
                        <article class="rounded-2xl border border-rose-300/30 bg-rose-500/15 p-4">
                            <p class="text-xs uppercase tracking-[0.2em] text-rose-100/90">Start Date Passed</p>
                            <p class="text-2xl font-semibold text-rose-100 mt-2">{{ number_format($overduePendingDisbursementCount) }}</p>
                        </article>
                        <article class="rounded-2xl border border-white/10 bg-white/10 p-4">
                            <p class="text-xs uppercase tracking-[0.2em] text-amber-200/80">Pending Principal</p>
                            <p class="text-2xl font-semibold text-white mt-2">ZMW {{ number_format($pendingDisbursementAmount, 2) }}</p>
                        </article>
                    </div>

                    <div class="mt-6 overflow-x-auto">
                            <table class="min-w-full text-left text-sm text-slate-200">
                                <thead>
                                    <tr class="border-b border-white/20 text-xs uppercase tracking-[0.15em] text-amber-100/80">
                                        <th class="px-3 py-3">Loan</th>
                                        <th class="px-3 py-3">Customer</th>
                                        <th class="px-3 py-3">Product</th>
                                        <th class="px-3 py-3">Start Date</th>
                                        <th class="px-3 py-3">Principal</th>
                                        <th class="px-3 py-3">Priority</th>
                                        <th class="px-3 py-3 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($pendingDisbursementLoans as $loan)
                                        @php
                                            $isOverdue = $loan->loan_start_date?->lt($dashboardToday);
                                            $daysLate = $isOverdue ? $loan->loan_start_date->diffInDays($dashboardToday) : 0;
                                            $customerName = $loan->customer?->full_name
                                                ?: trim(($loan->customer?->first_name ?? '').' '.($loan->customer?->last_name ?? ''))
                                                ?: 'N/A';
                                        @endphp
                                        <tr class="border-b border-white/10 {{ $isOverdue ? 'bg-rose-500/10' : 'hover:bg-white/5' }}">
                                            <td class="px-3 py-3 font-medium text-white">{{ $loan->loan_number }}</td>
                                            <td class="px-3 py-3">
                                                <div class="font-medium text-white">{{ $customerName }}</div>
                                                <div class="text-xs text-slate-400">{{ $loan->customer?->email ?? 'No email' }}</div>
                                            </td>
                                            <td class="px-3 py-3">{{ $loan->loanProduct?->name ?? 'N/A' }}</td>
                                            <td class="px-3 py-3">{{ $loan->loan_start_date?->format('M d, Y') ?? 'N/A' }}</td>
                                            <td class="px-3 py-3 font-medium text-white">ZMW {{ number_format($loan->principal_amount, 2) }}</td>
                                            <td class="px-3 py-3">
                                                @if ($isOverdue)
                                                    <span class="inline-flex rounded-full bg-rose-500/20 px-2 py-1 text-xs font-semibold text-rose-200">
                                                        {{ $daysLate }} {{ $daysLate === 1 ? 'day' : 'days' }} late
                                                    </span>
                                                @else
                                                    <span class="inline-flex rounded-full bg-emerald-500/20 px-2 py-1 text-xs font-semibold text-emerald-200">
                                                        Upcoming
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-3 text-right">
                                                <a href="{{ route('admin.loans.show', $loan) }}"
                                                   class="inline-flex items-center gap-1 rounded-lg border border-white/20 bg-white/10 px-3 py-1.5 text-xs font-semibold text-white hover:bg-white/20 transition">
                                                    Open Loan
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                    </div>
                </article>
            </section>
        @endif

        {{-- Time Period Tabs --}}
        @if ($canViewActivityStats)
            <section>
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-semibold text-slate-300">Activity Statistics</h2>
                <div class="flex gap-2 bg-white/5 rounded-lg p-1 border border-white/10">
                    <button 
                        @click="activeTab = 'today'" 
                        :class="activeTab === 'today' ? 'dashboard-period-btn dashboard-period-btn-active' : 'dashboard-period-btn dashboard-period-btn-inactive'"
                        class="dashboard-period-btn"
                        type="button">
                        Today
                    </button>
                    <button 
                        @click="activeTab = 'week'" 
                        :class="activeTab === 'week' ? 'dashboard-period-btn dashboard-period-btn-active' : 'dashboard-period-btn dashboard-period-btn-inactive'"
                        class="dashboard-period-btn"
                        type="button">
                        This Week
                    </button>
                </div>
            </div>

            {{-- Today's Stats --}}
            <div x-show="activeTab === 'today'" x-transition class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
                @if ($canViewLoans)
                    <article class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-sm text-slate-400">Loans Created</p>
                            <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <p class="text-3xl font-semibold mt-2">{{ number_format($todayStats['loans_created']) }}</p>
                        <p class="text-slate-500 text-xs mt-2">New loan applications today</p>
                    </article>

                    <article class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-sm text-slate-400">Loans Approved</p>
                            <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <p class="text-3xl font-semibold mt-2">{{ number_format($todayStats['loans_approved']) }}</p>
                        <p class="text-slate-500 text-xs mt-2">Loans approved today</p>
                    </article>

                    <article class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-sm text-slate-400">Loans Disbursed</p>
                            <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <p class="text-3xl font-semibold mt-2">{{ number_format($todayStats['loans_disbursed']) }}</p>
                        <p class="text-slate-500 text-xs mt-2">Loans disbursed today</p>
                    </article>

                    <article class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-sm text-slate-400">Amount Disbursed</p>
                            <svg class="w-5 h-5 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <p class="text-3xl font-semibold mt-2">{{ number_format($todayStats['total_disbursed'], 2) }}</p>
                        <p class="text-slate-500 text-xs mt-2">Total disbursed today</p>
                    </article>
                @endif

                @if ($canViewRepayments)
                    <article class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-sm text-slate-400">Repayments Received</p>
                            <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                        </div>
                        <p class="text-3xl font-semibold mt-2">{{ number_format($todayStats['repayments_received']) }}</p>
                        <p class="text-slate-500 text-xs mt-2">Repayments processed today</p>
                    </article>

                    <article class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-sm text-slate-400">Repayment Amount</p>
                            <svg class="w-5 h-5 text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                        </div>
                        <p class="text-3xl font-semibold mt-2">{{ number_format($todayStats['total_repayments'], 2) }}</p>
                        <p class="text-slate-500 text-xs mt-2">Total repayments today</p>
                    </article>
                @endif

                @if ($canViewCustomers)
                    <article class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-sm text-slate-400">New Customers</p>
                            <svg class="w-5 h-5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                            </svg>
                        </div>
                        <p class="text-3xl font-semibold mt-2">{{ number_format($todayStats['new_customers']) }}</p>
                        <p class="text-slate-500 text-xs mt-2">New registrations today</p>
                    </article>
                @endif
            </div>

            {{-- This Week's Stats --}}
            <div x-show="activeTab === 'week'" x-transition class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
                @if ($canViewLoans)
                    <article class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-sm text-slate-400">Loans Created</p>
                            <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <p class="text-3xl font-semibold mt-2">{{ number_format($weekStats['loans_created']) }}</p>
                        <p class="text-slate-500 text-xs mt-2">New loan applications this week</p>
                    </article>

                    <article class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-sm text-slate-400">Loans Approved</p>
                            <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <p class="text-3xl font-semibold mt-2">{{ number_format($weekStats['loans_approved']) }}</p>
                        <p class="text-slate-500 text-xs mt-2">Loans approved this week</p>
                    </article>

                    <article class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-sm text-slate-400">Loans Disbursed</p>
                            <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <p class="text-3xl font-semibold mt-2">{{ number_format($weekStats['loans_disbursed']) }}</p>
                        <p class="text-slate-500 text-xs mt-2">Loans disbursed this week</p>
                    </article>

                    <article class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-sm text-slate-400">Amount Disbursed</p>
                            <svg class="w-5 h-5 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <p class="text-3xl font-semibold mt-2">{{ number_format($weekStats['total_disbursed'], 2) }}</p>
                        <p class="text-slate-500 text-xs mt-2">Total disbursed this week</p>
                    </article>
                @endif

                @if ($canViewRepayments)
                    <article class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-sm text-slate-400">Repayments Received</p>
                            <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                        </div>
                        <p class="text-3xl font-semibold mt-2">{{ number_format($weekStats['repayments_received']) }}</p>
                        <p class="text-slate-500 text-xs mt-2">Repayments processed this week</p>
                    </article>

                    <article class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-sm text-slate-400">Repayment Amount</p>
                            <svg class="w-5 h-5 text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                        </div>
                        <p class="text-3xl font-semibold mt-2">{{ number_format($weekStats['total_repayments'], 2) }}</p>
                        <p class="text-slate-500 text-xs mt-2">Total repayments this week</p>
                    </article>
                @endif

                @if ($canViewCustomers)
                    <article class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-sm text-slate-400">New Customers</p>
                            <svg class="w-5 h-5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                            </svg>
                        </div>
                        <p class="text-3xl font-semibold mt-2">{{ number_format($weekStats['new_customers']) }}</p>
                        <p class="text-slate-500 text-xs mt-2">New registrations this week</p>
                    </article>
                @endif
            </div>
            </section>
        @endif

        {{-- Loan / Repayment Trend --}}
        @if ($canViewTrendChart)
            <section>
            @php
                $trendLabels = $monthlyTrend['labels'] ?? [];
                $loanTakeoutSeries = $canViewLoans ? ($monthlyTrend['loan_takeouts'] ?? []) : [];
                $repaymentSeries = $canViewRepayments ? ($monthlyTrend['repayments'] ?? []) : [];

                $compactCurrency = function (float $amount): string {
                    if ($amount >= 1000000) {
                        return 'ZMW '.number_format($amount / 1000000, 1).'M';
                    }

                    if ($amount >= 1000) {
                        return 'ZMW '.number_format($amount / 1000, 0).'K';
                    }

                    return 'ZMW '.number_format($amount, 0);
                };

                $seriesValues = array_merge($loanTakeoutSeries, $repaymentSeries);
                $maxSeriesValue = max($seriesValues ?: [1]);
                if ($maxSeriesValue <= 0) {
                    $yAxisMax = 100;
                } else {
                    $magnitude = pow(10, max(0, (int) floor(log10($maxSeriesValue))));
                    $normalized = $maxSeriesValue / $magnitude;
                    $niceNormalized = $normalized <= 1 ? 1 : ($normalized <= 2 ? 2 : ($normalized <= 5 ? 5 : 10));
                    $yAxisMax = $niceNormalized * $magnitude;
                }

                $ySteps = 4;
                $chartWidth = 960;
                $chartHeight = 320;
                $paddingLeft = 96;
                $paddingRight = 28;
                $paddingTop = 24;
                $paddingBottom = 66;
                $plotWidth = $chartWidth - $paddingLeft - $paddingRight;
                $plotHeight = $chartHeight - $paddingTop - $paddingBottom;
                $pointCount = count($trendLabels);
                $xStep = $pointCount > 1 ? $plotWidth / ($pointCount - 1) : 0;

                $loanPoints = [];
                $repaymentPoints = [];
                $loanPolyline = [];
                $repaymentPolyline = [];

                for ($i = 0; $i < $pointCount; $i++) {
                    $x = $paddingLeft + ($pointCount > 1 ? ($xStep * $i) : ($plotWidth / 2));
                    $loanValue = (float) ($loanTakeoutSeries[$i] ?? 0);
                    $repaymentValue = (float) ($repaymentSeries[$i] ?? 0);
                    $loanY = $paddingTop + ($plotHeight * (1 - ($loanValue / $yAxisMax)));
                    $repaymentY = $paddingTop + ($plotHeight * (1 - ($repaymentValue / $yAxisMax)));

                    $loanPoints[] = ['x' => $x, 'y' => $loanY, 'value' => $loanValue];
                    $repaymentPoints[] = ['x' => $x, 'y' => $repaymentY, 'value' => $repaymentValue];
                    $loanPolyline[] = number_format($x, 2, '.', '').','.number_format($loanY, 2, '.', '');
                    $repaymentPolyline[] = number_format($x, 2, '.', '').','.number_format($repaymentY, 2, '.', '');
                }
            @endphp

            <article class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
                    <div>
                        <h2 class="text-xl font-semibold text-slate-300">Loan Takeouts vs Repayments Trend</h2>
                        <p class="text-sm text-slate-500 mt-1">Current month and previous 6 months (amounts in ZMW).</p>
                    </div>
                    <div class="dashboard-legend flex items-center gap-4 text-sm">
                        @if ($canViewLoans)
                            <span class="inline-flex items-center gap-2">
                                <span class="dashboard-legend-dot dashboard-legend-dot-loans"></span>
                                Loan Takeouts
                            </span>
                        @endif
                        @if ($canViewRepayments)
                            <span class="inline-flex items-center gap-2">
                                <span class="dashboard-legend-dot dashboard-legend-dot-repayments"></span>
                                Repayments
                            </span>
                        @endif
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <svg viewBox="0 0 {{ $chartWidth }} {{ $chartHeight }}" class="min-w-[760px] w-full h-auto">
                        @for ($step = 0; $step <= $ySteps; $step++)
                            @php
                                $lineY = $paddingTop + (($plotHeight / $ySteps) * $step);
                                $axisValue = $yAxisMax - (($yAxisMax / $ySteps) * $step);
                            @endphp
                            <line x1="{{ $paddingLeft }}" y1="{{ $lineY }}" x2="{{ $chartWidth - $paddingRight }}" y2="{{ $lineY }}" stroke="rgba(148, 163, 184, 0.4)" stroke-width="1" />
                            <text x="{{ $paddingLeft - 12 }}" y="{{ $lineY + 4 }}" text-anchor="end" fill="#475569" font-size="12">
                                {{ $compactCurrency((float) $axisValue) }}
                            </text>
                        @endfor

                        <line x1="{{ $paddingLeft }}" y1="{{ $chartHeight - $paddingBottom }}" x2="{{ $chartWidth - $paddingRight }}" y2="{{ $chartHeight - $paddingBottom }}" stroke="rgba(71, 85, 105, 0.7)" stroke-width="1.2" />

                        @if ($canViewLoans && !empty($loanPolyline))
                            <polyline points="{{ implode(' ', $loanPolyline) }}" fill="none" stroke="#2563EB" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                        @endif
                        @if ($canViewRepayments && !empty($repaymentPolyline))
                            <polyline points="{{ implode(' ', $repaymentPolyline) }}" fill="none" stroke="#10B981" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                        @endif

                        @if ($canViewLoans)
                            @foreach ($loanPoints as $point)
                                <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="4" fill="#2563EB" />
                            @endforeach
                        @endif
                        @if ($canViewRepayments)
                            @foreach ($repaymentPoints as $point)
                                <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="4" fill="#10B981" />
                            @endforeach
                        @endif

                        @foreach ($trendLabels as $index => $label)
                            @php
                                $x = $paddingLeft + ($pointCount > 1 ? ($xStep * $index) : ($plotWidth / 2));
                            @endphp
                            <text x="{{ $x }}" y="{{ $chartHeight - 32 }}" text-anchor="middle" fill="#334155" font-size="12" font-weight="600">
                                {{ $label }}
                            </text>
                        @endforeach
                    </svg>
                </div>
            </article>
            </section>
        @endif
    </div>
@endsection
