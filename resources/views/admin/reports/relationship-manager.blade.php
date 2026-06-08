@extends('layouts.admin')

@section('title', 'Relationship Manager Report | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Relationship Manager Report',
            'description' => 'Portfolio performance, disbursement activity, and collections by relationship manager.',
        ])

        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <form method="GET" action="{{ route('admin.reports.relationship-manager') }}" class="space-y-4">
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Branch</label>
                        <select name="branch_id" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                            <option value="">All Branches</option>
                            @foreach($branchOptions as $branch)
                                <option value="{{ $branch->id }}" @selected(($filters['branch_id'] ?? null) == $branch->id)>
                                    {{ $branch->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Relationship Manager</label>
                        <select name="relationship_manager_id" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                            <option value="">All Relationship Managers</option>
                            @foreach($relationshipManagers as $manager)
                                <option value="{{ $manager->id }}" @selected(($filters['relationship_manager_id'] ?? null) == $manager->id)>
                                    {{ $manager->full_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Customer Type</label>
                        <select name="customer_type" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                            <option value="all" @selected(($filters['customer_type'] ?? 'all') === 'all')>All</option>
                            <option value="individual" @selected(($filters['customer_type'] ?? 'all') === 'individual')>Individual</option>
                            <option value="group" @selected(($filters['customer_type'] ?? 'all') === 'group')>Group</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">PAR Bucket</label>
                        <select name="par_bucket" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                            <option value="all" @selected(($filters['par_bucket'] ?? 'all') === 'all')>All Loans</option>
                            <option value="current" @selected(($filters['par_bucket'] ?? 'all') === 'current')>Current Only</option>
                            <option value="at_risk" @selected(($filters['par_bucket'] ?? 'all') === 'at_risk')>At Risk (PAR30+)</option>
                            <option value="par30" @selected(($filters['par_bucket'] ?? 'all') === 'par30')>PAR30 Only</option>
                            <option value="par60" @selected(($filters['par_bucket'] ?? 'all') === 'par60')>PAR60 Only</option>
                            <option value="par90" @selected(($filters['par_bucket'] ?? 'all') === 'par90')>PAR90 Only</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Date From</label>
                        <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Date To</label>
                        <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">View Mode</label>
                        <select name="mode" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                            <option value="summary" @selected(($filters['mode'] ?? 'summary') === 'summary')>Summary</option>
                            <option value="detailed" @selected(($filters['mode'] ?? 'summary') === 'detailed')>Detailed</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Export Data (CSV/PDF)</label>
                        <select name="export_dataset" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                            <option value="all" @selected(($filters['export_dataset'] ?? 'all') === 'all')>Summary (Default)</option>
                            <option value="summary" @selected(($filters['export_dataset'] ?? 'all') === 'summary')>Summary Only</option>
                            <option value="customers" @selected(($filters['export_dataset'] ?? 'all') === 'customers')>Customers Only</option>
                            <option value="loans" @selected(($filters['export_dataset'] ?? 'all') === 'loans')>Loans Only</option>
                            <option value="repayments" @selected(($filters['export_dataset'] ?? 'all') === 'repayments')>Repayments Only</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Excel Tabs to Include</label>
                    <div class="flex flex-wrap gap-3">
                        @php
                            $selectedExcelTabs = $filters['excel_tabs'] ?? ['summary', 'customers', 'loans', 'repayments'];
                        @endphp
                        @foreach ([
                            'summary' => 'Summary',
                            'customers' => 'Customers',
                            'loans' => 'Loans',
                            'repayments' => 'Repayments',
                        ] as $tabValue => $tabLabel)
                            <label class="inline-flex items-center gap-2 rounded-xl border border-white/15 bg-white/5 px-3 py-2 text-sm text-slate-200">
                                <input
                                    type="checkbox"
                                    name="excel_tabs[]"
                                    value="{{ $tabValue }}"
                                    class="rounded border-white/30 bg-white/10 text-cyan-400 focus:ring-cyan-400/40"
                                    @checked(in_array($tabValue, $selectedExcelTabs, true))
                                >
                                <span>{{ $tabLabel }}</span>
                            </label>
                        @endforeach
                    </div>
                    <p class="mt-2 text-xs text-slate-400">
                        Use this for Excel only. You can export all tabs or only selected tabs.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <button type="submit" class="rounded-2xl bg-cyan-500/20 border border-cyan-500/50 px-6 py-2 text-sm font-medium text-cyan-300 hover:bg-cyan-500/30 transition">
                        Apply Filters
                    </button>
                    <a href="{{ route('admin.reports.relationship-manager') }}" class="rounded-2xl border border-white/10 px-6 py-2 text-sm font-medium text-white/80 hover:bg-white/10 transition">
                        Clear Filters
                    </a>
                    <button
                        type="submit"
                        formmethod="GET"
                        formaction="{{ route('admin.reports.relationship-manager.export', ['format' => 'excel']) }}"
                        class="rounded-2xl border border-emerald-400/40 bg-emerald-500/15 px-6 py-2 text-sm font-medium text-emerald-200 hover:bg-emerald-500/25 transition"
                    >
                        Export Excel
                    </button>
                    <button
                        type="submit"
                        formmethod="GET"
                        formaction="{{ route('admin.reports.relationship-manager.export', ['format' => 'csv']) }}"
                        class="rounded-2xl border border-white/20 bg-white/10 px-6 py-2 text-sm font-medium text-white/90 hover:bg-white/15 transition"
                    >
                        Export CSV
                    </button>
                    <button
                        type="submit"
                        formmethod="GET"
                        formaction="{{ route('admin.reports.relationship-manager.export', ['format' => 'pdf']) }}"
                        class="rounded-2xl border border-amber-400/40 bg-amber-500/15 px-6 py-2 text-sm font-medium text-amber-200 hover:bg-amber-500/25 transition"
                    >
                        Export PDF
                    </button>
                </div>
                <p class="text-xs text-slate-400">
                    Tip: Choose Branch, PAR Bucket, and customer type first. For PAR30-only data, set <strong>PAR Bucket = PAR30 Only</strong>.
                </p>
            </form>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4 shadow-lg">
                <p class="text-xs font-medium text-slate-400 mb-1">Portfolio Value</p>
                <p class="text-xl font-bold text-white">ZMW {{ number_format($summary['total_portfolio_value'] ?? 0, 2) }}</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4 shadow-lg">
                <p class="text-xs font-medium text-slate-400 mb-1">Booked Outstanding Balance</p>
                <p class="text-xl font-bold text-amber-300">ZMW {{ number_format($summary['total_outstanding_balance'] ?? 0, 2) }}</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4 shadow-lg">
                <p class="text-xs font-medium text-slate-400 mb-1">Collections (Range)</p>
                <p class="text-xl font-bold text-emerald-300">ZMW {{ number_format($summary['total_collections_amount'] ?? 0, 2) }}</p>
                <p class="text-xs text-slate-400 mt-1">{{ number_format($summary['total_repayments_count'] ?? 0) }} repayments</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4 shadow-lg">
                <p class="text-xs font-medium text-slate-400 mb-1">PAR Ratio</p>
                <p class="text-xl font-bold text-rose-300">{{ number_format($summary['par_ratio'] ?? 0, 2) }}%</p>
                <p class="text-xs text-slate-400 mt-1">At Risk: ZMW {{ number_format($summary['total_par_amount'] ?? 0, 2) }}</p>
            </div>
        </div>

        <div class="rounded-3xl border border-white/10 bg-white/5 p-4 shadow-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full w-full text-sm text-slate-300">
                    <thead>
                        <tr class="text-xs font-semibold uppercase tracking-[0.2em] text-white/80 text-center border-b-2 border-white/20">
                            <th class="px-4 py-3 text-left">Relationship Manager</th>
                            <th class="px-4 py-3">Portfolio Value</th>
                            <th class="px-4 py-3">Booked Outstanding</th>
                            <th class="px-4 py-3">PAR</th>
                            <th class="px-4 py-3">Customers</th>
                            <th class="px-4 py-3">Groups</th>
                            <th class="px-4 py-3">Disbursements</th>
                            <th class="px-4 py-3">Pending Balances</th>
                            <th class="px-4 py-3">Collections</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($reportRows as $row)
                            <tr class="border-b border-white/10 hover:bg-white/5 transition">
                                <td class="px-4 py-3 align-top">
                                    <div class="text-white font-semibold">{{ $row['manager']->full_name }}</div>
                                    <div class="text-xs text-slate-400">{{ $row['manager']->branch?->name ?? 'No Branch' }}</div>
                                </td>
                                <td class="px-4 py-3 text-center font-semibold text-white">ZMW {{ number_format($row['total_portfolio_value'], 2) }}</td>
                                <td class="px-4 py-3 text-center text-amber-300 font-semibold">ZMW {{ number_format($row['total_outstanding_balance'], 2) }}</td>
                                <td class="px-4 py-3 text-center">
                                    <div class="text-rose-300 font-semibold">ZMW {{ number_format($row['par_amount'], 2) }}</div>
                                    <div class="text-xs text-slate-400">{{ number_format($row['par_ratio'], 2) }}% ({{ $row['par_status'] }})</div>
                                </td>
                                <td class="px-4 py-3 text-center">{{ number_format($row['individual_customers_count']) }}</td>
                                <td class="px-4 py-3 text-center">{{ number_format($row['groups_count']) }}</td>
                                <td class="px-4 py-3 text-center">
                                    <div>{{ number_format($row['loans_disbursed_count']) }} loans</div>
                                    <div class="text-xs text-slate-400">ZMW {{ number_format($row['loans_disbursed_amount'], 2) }}</div>
                                </td>
                                <td class="px-4 py-3 text-center text-amber-300 font-semibold">ZMW {{ number_format($row['pending_loan_balances'], 2) }}</td>
                                <td class="px-4 py-3 text-center">
                                    <div>{{ number_format($row['repayments_count']) }} repayments</div>
                                    <div class="text-xs text-slate-400">ZMW {{ number_format($row['collections_amount'], 2) }}</div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-8 text-center text-slate-400">
                                    No relationship manager data found for the selected filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if(($filters['mode'] ?? 'summary') === 'detailed')
            <div class="space-y-6">
                @foreach($reportRows as $row)
                    <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                            <h2 class="text-lg font-semibold text-white">{{ $row['manager']->full_name }}</h2>
                            <span class="inline-flex items-center rounded-full border border-cyan-400/30 bg-cyan-500/10 px-3 py-1 text-xs font-semibold text-cyan-200">
                                Detailed Mode
                            </span>
                        </div>

                        <div class="grid gap-6 xl:grid-cols-2">
                            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                                <h3 class="text-sm font-semibold text-white mb-3">Customers Linked</h3>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full text-xs text-slate-300">
                                        <thead>
                                            <tr class="border-b border-white/10 text-slate-200">
                                                <th class="py-2 text-left">Customer</th>
                                                <th class="py-2 text-left">Type</th>
                                                <th class="py-2 text-left">Group</th>
                                                <th class="py-2 text-left">Company</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($row['details']['customers'] as $customer)
                                                <tr class="border-b border-white/5">
                                                    <td class="py-2">{{ $customer['name'] }}</td>
                                                    <td class="py-2">{{ ucfirst($customer['portfolio_type']) }}</td>
                                                    <td class="py-2">{{ $customer['group_name'] ?? '—' }}</td>
                                                    <td class="py-2">{{ $customer['company_name'] ?? '—' }}</td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="4" class="py-3 text-slate-400">No customers found.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                                <h3 class="text-sm font-semibold text-white mb-3">Groups Linked</h3>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full text-xs text-slate-300">
                                        <thead>
                                            <tr class="border-b border-white/10 text-slate-200">
                                                <th class="py-2 text-left">Group</th>
                                                <th class="py-2 text-left">Code</th>
                                                <th class="py-2 text-left">Customers</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($row['details']['groups'] as $group)
                                                <tr class="border-b border-white/5">
                                                    <td class="py-2">{{ $group['name'] }}</td>
                                                    <td class="py-2">{{ $group['code'] ?? '—' }}</td>
                                                    <td class="py-2">{{ number_format($group['customers_count']) }}</td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="3" class="py-3 text-slate-400">No groups found.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="rounded-2xl border border-white/10 bg-white/5 p-4 xl:col-span-2">
                                <h3 class="text-sm font-semibold text-white mb-3">Loans Linked</h3>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full text-xs text-slate-300">
                                        <thead>
                                            <tr class="border-b border-white/10 text-slate-200">
                                                <th class="py-2 text-left">Loan #</th>
                                                <th class="py-2 text-left">Customer</th>
                                                <th class="py-2 text-left">Status</th>
                                                <th class="py-2 text-left">Outstanding</th>
                                                <th class="py-2 text-left">Overdue</th>
                                                <th class="py-2 text-left">PAR</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($row['details']['loans'] as $loan)
                                                <tr class="border-b border-white/5">
                                                    <td class="py-2">{{ $loan['loan_number'] }}</td>
                                                    <td class="py-2">{{ $loan['customer_name'] }}</td>
                                                    <td class="py-2">{{ ucfirst(str_replace('_', ' ', $loan['status'])) }}</td>
                                                    <td class="py-2">ZMW {{ number_format($loan['outstanding_balance'], 2) }}</td>
                                                    <td class="py-2">ZMW {{ number_format($loan['overdue_amount'], 2) }}</td>
                                                    <td class="py-2">{{ $loan['par_status'] }}</td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="6" class="py-3 text-slate-400">No loans found.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="rounded-2xl border border-white/10 bg-white/5 p-4 xl:col-span-2">
                                <h3 class="text-sm font-semibold text-white mb-3">Repayment History</h3>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full text-xs text-slate-300">
                                        <thead>
                                            <tr class="border-b border-white/10 text-slate-200">
                                                <th class="py-2 text-left">Repayment #</th>
                                                <th class="py-2 text-left">Loan #</th>
                                                <th class="py-2 text-left">Customer</th>
                                                <th class="py-2 text-left">Date</th>
                                                <th class="py-2 text-left">Amount</th>
                                                <th class="py-2 text-left">Channel</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($row['details']['repayments'] as $repayment)
                                                <tr class="border-b border-white/5">
                                                    <td class="py-2">{{ $repayment['repayment_number'] }}</td>
                                                    <td class="py-2">{{ $repayment['loan_number'] }}</td>
                                                    <td class="py-2">{{ $repayment['customer_name'] }}</td>
                                                    <td class="py-2">{{ $repayment['processed_at']?->format('d M Y H:i') ?? '—' }}</td>
                                                    <td class="py-2">ZMW {{ number_format($repayment['amount'], 2) }}</td>
                                                    <td class="py-2">{{ $repayment['channel_name'] }}</td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="6" class="py-3 text-slate-400">No repayments found.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection
