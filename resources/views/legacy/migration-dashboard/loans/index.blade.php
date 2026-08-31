@extends('legacy.migration-dashboard.layout')

@section('dashboard-content')
    @include('partials.admin.page-header', ['title' => 'Active Loan Migration', 'description' => 'Replay and promotion status for legacy active loans.'])

    @if(!empty($filters['run_id']))
        <div class="mb-4 rounded-2xl border border-cyan-200 bg-cyan-50 px-4 py-3 text-sm text-cyan-900 flex flex-wrap items-center justify-between gap-2">
            <span>Filtered to migration run <strong>#{{ $filters['run_id'] }}</strong>@if(!empty($filters['migration_status'])) · status <strong>{{ $filters['migration_status'] }}</strong>@endif</span>
            <a href="{{ route('legacy.migration-dashboard.runs.show', $filters['run_id']) }}" class="font-semibold text-primary hover:underline">← Back to run</a>
        </div>
    @endif

    <form method="GET" class="mb-4 flex flex-wrap gap-2">
        <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Legacy loan / user / target ID" class="rounded-xl border px-3 py-2 text-sm">
        <input type="number" name="run_id" value="{{ $filters['run_id'] ?? '' }}" placeholder="Run ID" min="1" class="rounded-xl border px-3 py-2 text-sm w-28">
        <select name="migration_status" class="rounded-xl border px-3 py-2 text-sm">
            <option value="">Migration status</option>
            @foreach(['promotable', 'manual_review', 'blocked', 'created', 'matched_existing'] as $status)
                <option value="{{ $status }}" @selected(($filters['migration_status'] ?? '') === $status)>{{ $status }}</option>
            @endforeach
        </select>
        <select name="status" class="rounded-xl border px-3 py-2 text-sm">
            <option value="">Reconciliation</option>
            @foreach(['PASS', 'PASS_WITH_MIGRATION_ADJUSTMENT', 'MANUAL_REVIEW', 'FAIL'] as $status)
                <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>
            @endforeach
        </select>
        <button class="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white">Filter</button>
    </form>

    <div class="rounded-2xl border bg-white p-6 shadow-sm overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-b bg-slate-100 text-left text-xs uppercase tracking-wide text-slate-700">
                    <th class="px-3 py-2">Legacy Loan</th>
                    <th class="px-3 py-2">User</th>
                    <th class="px-3 py-2">Product</th>
                    <th class="px-3 py-2">Legacy Out.</th>
                    <th class="px-3 py-2">Target Out.</th>
                    <th class="px-3 py-2">Variance</th>
                    <th class="px-3 py-2">Replay</th>
                    <th class="px-3 py-2">Opening</th>
                    <th class="px-3 py-2">Status</th>
                    <th class="px-3 py-2">Target Loan</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($loans as $loan)
                    <tr class="border-b hover:bg-slate-50">
                        <td class="px-3 py-2">{{ $loan->legacy_loan_id }}</td>
                        <td class="px-3 py-2">{{ $loan->legacy_user_id }}</td>
                        <td class="px-3 py-2">{{ $loan->target_product_code ?? $loan->legacy_product_type }}</td>
                        <td class="px-3 py-2">{{ \App\Migration\Dashboard\MigrationDashboardSupport::formatZmw($loan->legacy_effective_outstanding) }}</td>
                        <td class="px-3 py-2">{{ \App\Migration\Dashboard\MigrationDashboardSupport::formatZmw($loan->target_outstanding) }}</td>
                        <td class="px-3 py-2">{{ \App\Migration\Dashboard\MigrationDashboardSupport::formatZmw($loan->balance_variance) }}</td>
                        <td class="px-3 py-2">{{ $loan->promotion_status ?? '—' }}</td>
                        <td class="px-3 py-2">{{ \App\Migration\Dashboard\MigrationDashboardSupport::formatZmw($loan->migration_opening_adjustment) }}</td>
                        <td class="px-3 py-2">{{ $loan->migration_status }}</td>
                        <td class="px-3 py-2">{{ $loan->mapped_loan_id ?? '—' }}</td>
                        <td class="px-3 py-2"><a href="{{ route('legacy.migration-dashboard.loans.show', $loan->legacy_loan_id) }}" class="font-semibold text-primary hover:underline">Detail</a></td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="px-3 py-6 text-center text-slate-500">No loan migration records.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $loans->withQueryString()->links() }}</div>
    </div>
@endsection
