@extends('legacy.migration-dashboard.layout')

@section('dashboard-content')
    @include('partials.admin.page-header', [
        'title' => 'Pending Legacy Customers',
        'description' => 'Borrowers with new parallel-run loans who are not yet mapped in revamp. Poll legacy or promote individually before importing loans.',
    ])

    @php $parallel = $summary ?? []; @endphp

    @if(($parallel['pending_customers'] ?? 0) > 0)
        <div class="mb-4 rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950">
            <strong>{{ number_format($parallel['pending_customers']) }}</strong> legacy customer(s) awaiting promotion before their loans can import.
        </div>
    @endif

    <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 text-sm">
        <div class="rounded-xl border bg-white p-3">
            <p class="text-xs uppercase text-slate-500">Pending customers</p>
            <p class="text-xl font-bold text-primary">{{ number_format($parallel['pending_customers'] ?? 0) }}</p>
        </div>
        <div class="rounded-xl border bg-white p-3">
            <p class="text-xs uppercase text-slate-500">Pending loans</p>
            <p class="text-xl font-bold text-primary">{{ number_format($parallel['pending_loans'] ?? 0) }}</p>
        </div>
        <div class="rounded-xl border bg-white p-3">
            <p class="text-xs uppercase text-slate-500">Last customer poll</p>
            <p class="font-semibold">{{ $parallel['last_customer_poll_at'] ?? 'Never' }}</p>
        </div>
        <div class="rounded-xl border bg-white p-3">
            <p class="text-xs uppercase text-slate-500">Last customer sync</p>
            <p class="font-semibold">{{ $parallel['last_customer_sync_at'] ?? 'Never' }}</p>
        </div>
    </div>

    @if(session('status'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">{{ session('error') }}</div>
    @endif

    @if($canManage)
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <form method="POST" action="{{ route('legacy.migration-dashboard.parallel-run.poll-customers') }}" onsubmit="return confirm('Poll legacy for unmapped borrowers and attempt automatic promotion?');">
                @csrf
                <button type="submit" class="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white hover:opacity-90">
                    Poll &amp; sync customers now
                </button>
            </form>
            @unless($parallel['customer_polling_enabled'] ?? false)
                <p class="text-xs text-slate-500">Cron scheduling requires <code>LEGACY_CUSTOMER_POLLING_ENABLED=true</code> in <code>.env</code>.</p>
            @endunless
        </div>
    @endif

    <form method="GET" class="mb-4 flex flex-wrap gap-2">
        <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Legacy user ID, name, phone" class="rounded-xl border px-3 py-2 text-sm">
        <select name="status" class="rounded-xl border px-3 py-2 text-sm">
            <option value="">Status</option>
            @foreach(['pending_review', 'blocked'] as $status)
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
                    <th class="px-3 py-2">Phone</th>
                    <th class="px-3 py-2">Detected</th>
                    <th class="px-3 py-2">Status</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($customers as $row)
                    @php
                        $snapshot = json_decode($row->raw_snapshot ?? '{}', true) ?: [];
                        $legacyUser = $snapshot['legacy_user'] ?? [];
                        $name = trim(($legacyUser['first_name'] ?? '').' '.($legacyUser['last_name'] ?? '')) ?: '—';
                        $phone = $legacyUser['phone'] ?? $snapshot['phone'] ?? '—';
                    @endphp
                    <tr class="border-b hover:bg-slate-50">
                        <td class="px-3 py-2 font-semibold">{{ $row->legacy_user_id }}</td>
                        <td class="px-3 py-2">{{ $name }}</td>
                        <td class="px-3 py-2">{{ $phone }}</td>
                        <td class="px-3 py-2">{{ $row->detected_at }}</td>
                        <td class="px-3 py-2">
                            @include('legacy.migration-dashboard.partials.badge', ['label' => $row->status])
                        </td>
                        <td class="px-3 py-2 text-right">
                            <a href="{{ route('legacy.migration-dashboard.customers.pending.show', $row->legacy_user_id) }}" class="font-semibold text-primary hover:underline">Review</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-3 py-8 text-center text-slate-500">No pending legacy customers in the inbox.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $customers->links() }}</div>
    </div>

    @unless($canManage)
        <p class="mt-4 text-xs text-slate-500">Promote and poll actions require <code>migration.manage</code> permission.</p>
    @endunless
@endsection
