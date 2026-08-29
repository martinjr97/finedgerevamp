@extends('legacy.migration-dashboard.layout')

@section('dashboard-content')
    @include('partials.admin.page-header', [
        'title' => 'Legacy Migration Dashboard',
        'description' => 'Read-only monitoring of phased migration runs, mappings, and reconciliation.',
    ])

    @php
        $cards = $summary['cards'] ?? [];
        $phases = $summary['phases'] ?? [];
        $repayments = $summary['repayments'] ?? [];
        $attention = $summary['attention'] ?? [];
    @endphp

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border bg-white p-4 shadow-sm">
            <p class="text-xs uppercase tracking-wide text-slate-500">True Customers</p>
            <p class="mt-1 text-2xl font-bold text-primary">{{ number_format($cards['true_customers'] ?? 0) }}</p>
            <p class="text-xs text-slate-500">Legacy users {{ number_format($cards['legacy_users'] ?? 0) }} · excluded {{ number_format($cards['excluded_admin_staff'] ?? 0) }}</p>
        </div>
        <div class="rounded-2xl border bg-white p-4 shadow-sm">
            <p class="text-xs uppercase tracking-wide text-slate-500">Active Loans</p>
            <p class="mt-1 text-2xl font-bold text-primary">{{ number_format($cards['active_loans'] ?? 0) }}</p>
            <p class="text-xs text-slate-500">Promotable {{ number_format($cards['promotable'] ?? 0) }} · manual {{ number_format($cards['manual_review_loans'] ?? 0) }}</p>
        </div>
        <div class="rounded-2xl border bg-white p-4 shadow-sm">
            <p class="text-xs uppercase tracking-wide text-slate-500">Marketeers</p>
            <p class="mt-1 text-2xl font-bold text-primary">{{ number_format($cards['marketeer_customers'] ?? 0) }}</p>
            <p class="text-xs text-slate-500">Markets mapped {{ number_format($cards['marketeer_markets'] ?? 0) }}</p>
        </div>
        <div class="rounded-2xl border bg-white p-4 shadow-sm">
            <p class="text-xs uppercase tracking-wide text-slate-500">Needs Attention</p>
            <p class="mt-1 text-2xl font-bold text-primary">{{ number_format(array_sum($attention)) }}</p>
            <p class="text-xs text-slate-500">Ambiguous repayments {{ number_format($attention['ambiguous_repayments'] ?? 0) }}</p>
        </div>
    </div>

    <div class="mt-6 rounded-2xl border bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-primary mb-4">Migration Phase Progress</h2>
        <div class="space-y-4">
            @foreach([
                'reference_data' => 'Reference Data',
                'customers' => 'Customers',
                'active_loans' => 'Active Loans',
                'repayments' => 'Repayments',
                'reconciliation' => 'Reconciliation',
            ] as $key => $label)
                @php $phase = $phases[$key] ?? ['status' => 'NOT_STARTED', 'label' => 'Not started', 'percent' => 0]; @endphp
                <div>
                    <div class="mb-1 flex items-center justify-between text-sm">
                        <span class="font-medium text-primary">{{ $label }}</span>
                        <span class="text-xs font-semibold uppercase text-slate-600">{{ $phase['status'] }} — {{ $phase['label'] }}</span>
                    </div>
                    <div class="h-2 rounded-full bg-slate-200">
                        <div class="h-2 rounded-full bg-primary" style="width: {{ $phase['percent'] }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="mt-6 grid gap-4 lg:grid-cols-2">
        <div class="rounded-2xl border bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-primary mb-4">Repayment Classification</h2>
            <dl class="grid grid-cols-2 gap-3 text-sm">
                @foreach(['A_DIRECT', 'B_RECONSTRUCTED', 'C_AMBIGUOUS', 'D_MANUAL'] as $class)
                    <div class="rounded-xl bg-slate-50 p-3">
                        <dt class="text-xs uppercase text-slate-500">{{ $class }}</dt>
                        <dd class="text-xl font-bold text-primary">{{ number_format($repayments[$class] ?? 0) }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
        <div class="rounded-2xl border bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-primary mb-4">Reconciliation Snapshot</h2>
            @php $rec = $summary['reconciliation'] ?? []; @endphp
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt>Legacy outstanding</dt><dd class="font-semibold">{{ \App\Migration\Dashboard\MigrationDashboardSupport::formatZmw($rec['legacy_outstanding'] ?? 0) }}</dd></div>
                <div class="flex justify-between"><dt>Target outstanding</dt><dd class="font-semibold">{{ \App\Migration\Dashboard\MigrationDashboardSupport::formatZmw($rec['target_outstanding'] ?? 0) }}</dd></div>
                <div class="flex justify-between"><dt>Variance</dt><dd class="font-semibold">{{ \App\Migration\Dashboard\MigrationDashboardSupport::formatZmw($rec['variance'] ?? 0) }}</dd></div>
                <div class="flex justify-between"><dt>PASS</dt><dd>{{ number_format($rec['pass'] ?? 0) }}</dd></div>
                <div class="flex justify-between"><dt>Manual review</dt><dd>{{ number_format($rec['manual_review'] ?? 0) }}</dd></div>
                <div class="flex justify-between"><dt>FAIL</dt><dd>{{ number_format($rec['fail'] ?? 0) }}</dd></div>
            </dl>
        </div>
    </div>

    @if(!empty($summary['latest_runs']) && count($summary['latest_runs']))
        <div class="mt-6 rounded-2xl border bg-white p-6 shadow-sm">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-primary">Recent Runs</h2>
                <a href="{{ route('legacy.migration-dashboard.runs.index') }}" class="text-sm font-semibold text-primary hover:underline">View all</a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b bg-slate-100 text-left text-xs uppercase tracking-wide text-slate-700">
                            <th class="px-3 py-2">Phase</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">Started</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach(collect($summary['latest_runs'])->take(5) as $run)
                            <tr class="border-b">
                                <td class="px-3 py-2">{{ $run->phase ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $run->status ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $run->started_at ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
