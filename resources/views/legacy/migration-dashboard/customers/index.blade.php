@extends('legacy.migration-dashboard.layout')

@section('dashboard-content')
    @include('partials.admin.page-header', ['title' => 'Customer Migrations', 'description' => 'Staging customer records and target mappings.'])

    <form method="GET" class="mb-4 flex flex-wrap gap-2">
        <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Legacy user / customer / target ID" class="rounded-xl border px-3 py-2 text-sm">
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
                    <th class="px-3 py-2">Legacy Customer</th>
                    <th class="px-3 py-2">Product</th>
                    <th class="px-3 py-2">Status</th>
                    <th class="px-3 py-2">Target Customer</th>
                    <th class="px-3 py-2">Exception</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($customers as $row)
                    @php $ctx = json_decode($row->raw_context ?? '{}', true) ?: []; @endphp
                    <tr class="border-b hover:bg-slate-50">
                        <td class="px-3 py-2">{{ $row->legacy_user_id }}</td>
                        <td class="px-3 py-2">{{ $row->legacy_customer_id ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $row->target_product_code ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $row->migration_status }}</td>
                        <td class="px-3 py-2">{{ $row->mapped_customer_id ?? '—' }}</td>
                        <td class="px-3 py-2 text-xs">{{ $row->exception ?? '—' }}</td>
                        <td class="px-3 py-2"><a href="{{ route('legacy.migration-dashboard.customers.show', $row->legacy_user_id) }}" class="font-semibold text-primary hover:underline">Detail</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-3 py-6 text-center text-slate-500">No customer migration records.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $customers->withQueryString()->links() }}</div>
    </div>
@endsection
