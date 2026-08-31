@extends('legacy.migration-dashboard.layout')

@section('dashboard-content')
    @include('partials.admin.page-header', ['title' => 'Customer Migrations', 'description' => 'Staging customer records and target mappings.'])

    @if(!empty($filters['run_id']))
        <div class="mb-4 rounded-2xl border border-cyan-200 bg-cyan-50 px-4 py-3 text-sm text-cyan-900 flex flex-wrap items-center justify-between gap-2">
            <span>Filtered to migration run <strong>#{{ $filters['run_id'] }}</strong>@if(!empty($filters['status'])) · status <strong>{{ $filters['status'] }}</strong>@endif</span>
            <a href="{{ route('legacy.migration-dashboard.runs.show', $filters['run_id']) }}" class="font-semibold text-primary hover:underline">← Back to run</a>
        </div>
    @endif

    <form method="GET" class="mb-4 flex flex-wrap gap-2">
        <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Legacy user / customer / target ID" class="rounded-xl border px-3 py-2 text-sm">
        <input type="number" name="run_id" value="{{ $filters['run_id'] ?? '' }}" placeholder="Run ID" min="1" class="rounded-xl border px-3 py-2 text-sm w-28">
        <select name="status" class="rounded-xl border px-3 py-2 text-sm">
            <option value="">All statuses</option>
            @foreach(['would_create', 'created', 'matched_existing', 'manual_review', 'alias'] as $status)
                <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>
            @endforeach
        </select>
        <button class="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white">Filter</button>
    </form>

    <div class="rounded-2xl border bg-white p-6 shadow-sm overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-b bg-slate-100 text-left text-xs uppercase tracking-wide text-slate-700">
                    <th class="px-3 py-2">Legacy User</th>
                    <th class="px-3 py-2">Name</th>
                    <th class="px-3 py-2">Product</th>
                    <th class="px-3 py-2">Status</th>
                    <th class="px-3 py-2">Issue</th>
                    <th class="px-3 py-2">Target</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($customers as $row)
                    <tr class="border-b hover:bg-slate-50 {{ $row->migration_status === 'manual_review' ? 'bg-amber-50/60' : '' }}">
                        <td class="px-3 py-2">{{ $row->legacy_user_id }}</td>
                        <td class="px-3 py-2 font-medium">{{ $row->legacy_name ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $row->target_product_code ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $row->migration_status }}</td>
                        <td class="px-3 py-2 text-xs">
                            @if($row->exception)
                                <span class="font-semibold text-amber-900">{{ $row->exception_label ?? $row->exception }}</span>
                                <span class="block text-slate-500">{{ $row->exception }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-3 py-2">{{ $row->mapped_customer_id ?? '—' }}</td>
                        <td class="px-3 py-2">
                            <a href="{{ route('legacy.migration-dashboard.customers.show', array_filter(['legacyUserId' => $row->legacy_user_id, 'run_id' => $filters['run_id'] ?? null, 'status' => $filters['status'] ?? null])) }}" class="font-semibold text-primary hover:underline">Detail</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-3 py-6 text-center text-slate-500">No customer migration records.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $customers->withQueryString()->links() }}</div>
    </div>
@endsection
