@extends('legacy.migration-dashboard.layout')

@section('dashboard-content')
    @include('partials.admin.page-header', ['title' => 'Reconciliation', 'description' => 'Portfolio-level legacy vs target outstanding comparison.'])

    @php $rec = $summary; @endphp
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 mb-6">
        <div class="rounded-2xl border bg-white p-4 shadow-sm">
            <p class="text-xs uppercase text-slate-500">Legacy Outstanding</p>
            <p class="text-xl font-bold text-primary">{{ \App\Migration\Dashboard\MigrationDashboardSupport::formatZmw($rec['legacy_outstanding'] ?? 0) }}</p>
        </div>
        <div class="rounded-2xl border bg-white p-4 shadow-sm">
            <p class="text-xs uppercase text-slate-500">Target Outstanding</p>
            <p class="text-xl font-bold text-primary">{{ \App\Migration\Dashboard\MigrationDashboardSupport::formatZmw($rec['target_outstanding'] ?? 0) }}</p>
        </div>
        <div class="rounded-2xl border bg-white p-4 shadow-sm">
            <p class="text-xs uppercase text-slate-500">Variance</p>
            <p class="text-xl font-bold text-primary">{{ \App\Migration\Dashboard\MigrationDashboardSupport::formatZmw($rec['variance'] ?? 0) }}</p>
        </div>
        <div class="rounded-2xl border bg-white p-4 shadow-sm"><p class="text-xs uppercase">PASS</p><p class="text-xl font-bold">{{ number_format($rec['pass'] ?? 0) }}</p></div>
        <div class="rounded-2xl border bg-white p-4 shadow-sm"><p class="text-xs uppercase">PASS + Opening</p><p class="text-xl font-bold">{{ number_format($rec['pass_with_opening'] ?? 0) }}</p></div>
        <div class="rounded-2xl border bg-white p-4 shadow-sm"><p class="text-xs uppercase">Manual / Fail</p><p class="text-xl font-bold">{{ number_format(($rec['manual_review'] ?? 0) + ($rec['fail'] ?? 0)) }}</p></div>
    </div>

    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 mb-6">
        Legacy aggregate account balances from <code>loans_accounts.balance</code> are not used as reconciliation truth. Figures above use effective legacy loan outstanding from the migration replay engine.
    </div>

    <div class="rounded-2xl border bg-white p-6 shadow-sm">
        <h2 class="font-semibold text-primary mb-2">Attention summary</h2>
        <dl class="grid gap-2 sm:grid-cols-2 text-sm">
            @foreach($home['attention'] ?? [] as $key => $count)
                <div class="flex justify-between rounded-lg bg-slate-50 px-3 py-2">
                    <dt>{{ str_replace('_', ' ', $key) }}</dt>
                    <dd class="font-bold">{{ number_format($count) }}</dd>
                </div>
            @endforeach
        </dl>
    </div>
@endsection
