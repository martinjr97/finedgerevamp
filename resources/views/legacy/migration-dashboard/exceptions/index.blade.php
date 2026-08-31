@extends('legacy.migration-dashboard.layout')

@section('dashboard-content')
    @include('partials.admin.page-header', ['title' => 'Migration Exceptions', 'description' => 'Manual review and rule violations across staging tables.'])

    <div class="mb-4 rounded-2xl border bg-white p-4 shadow-sm text-sm">
        Total flagged items (sample): <strong>{{ $summary['total'] ?? 0 }}</strong>
    </div>

    <form method="GET" class="mb-4 flex flex-wrap gap-2">
        <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Rule code or legacy ID" class="rounded-xl border px-3 py-2 text-sm">
        <input type="number" name="run_id" value="{{ $filters['run_id'] ?? '' }}" placeholder="Run ID" min="1" class="rounded-xl border px-3 py-2 text-sm w-28">
        <select name="entity" class="rounded-xl border px-3 py-2 text-sm">
            <option value="">All entities</option>
            @foreach(['loan', 'customer', 'repayment', 'company'] as $entity)
                <option value="{{ $entity }}" @selected(($filters['entity'] ?? '') === $entity)>{{ $entity }}</option>
            @endforeach
        </select>
        <button class="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white">Filter</button>
    </form>

    <div class="rounded-2xl border bg-white p-6 shadow-sm overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-b bg-slate-100 text-left text-xs uppercase tracking-wide text-slate-700">
                    <th class="px-3 py-2">Rule</th>
                    <th class="px-3 py-2">Legacy Loan</th>
                    <th class="px-3 py-2">Legacy User</th>
                    <th class="px-3 py-2">Status</th>
                    <th class="px-3 py-2">Message</th>
                    <th class="px-3 py-2">Run</th>
                </tr>
            </thead>
            <tbody>
                @forelse($exceptions as $row)
                    <tr class="border-b hover:bg-slate-50">
                        <td class="px-3 py-2">{{ $row->exception ?? 'MANUAL_REVIEW' }}</td>
                        <td class="px-3 py-2">{{ $row->legacy_loan_id ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $row->legacy_user_id ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $row->migration_status }}</td>
                        <td class="px-3 py-2 text-xs">{{ $row->exception ?? 'Requires manual review' }}</td>
                        <td class="px-3 py-2">
                            @if($row->migration_run_id)
                                <a href="{{ route('legacy.migration-dashboard.runs.show', $row->migration_run_id) }}" class="text-primary hover:underline">{{ $row->migration_run_id }}</a>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-3 py-6 text-center text-slate-500">No exceptions match filters.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $exceptions->withQueryString()->links() }}</div>
    </div>
@endsection
