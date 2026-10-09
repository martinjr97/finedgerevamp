@extends('legacy.migration-dashboard.layout')

@section('dashboard-content')
    @include('partials.admin.page-header', [
        'title' => 'Pending Legacy Loans',
        'description' => 'New loans disbursed in legacy during the parallel-run period. Review and confirm import into revamp.',
    ])

    @php $parallel = $summary ?? []; @endphp

    @if(($parallel['pending_loans'] ?? 0) > 0)
        <div class="mb-4 rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950">
            <strong>{{ number_format($parallel['pending_loans']) }}</strong> legacy loan(s) awaiting your confirmation to import.
        </div>
    @endif

    <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 text-sm">
        <div class="rounded-xl border bg-white p-3">
            <p class="text-xs uppercase text-slate-500">Pending customers</p>
            <p class="text-xl font-bold text-primary">{{ number_format($parallel['pending_customers'] ?? 0) }}</p>
        </div>
        <div class="rounded-xl border bg-white p-3">
            <p class="text-xs uppercase text-slate-500">Pending loans</p>
            <p class="text-xl font-bold text-primary">{{ number_format($parallel['pending_loans'] ?? 0) }}</p>
        </div>
        <div class="rounded-xl border bg-white p-3">
            <p class="text-xs uppercase text-slate-500">Pending repayments</p>
            <p class="text-xl font-bold text-primary">{{ number_format($parallel['pending_repayments'] ?? 0) }}</p>
        </div>
        <div class="rounded-xl border bg-white p-3">
            <p class="text-xs uppercase text-slate-500">Pending expenses</p>
            <p class="text-xl font-bold text-primary">{{ number_format($parallel['pending_expenses'] ?? 0) }}</p>
        </div>
        <div class="rounded-xl border bg-white p-3">
            <p class="text-xs uppercase text-slate-500">Treasury sync</p>
            <p class="font-semibold">{{ ($parallel['finance_on_import_enabled'] ?? false) ? 'Enabled' : 'Disabled' }}</p>
        </div>
        <div class="rounded-xl border bg-white p-3">
            <p class="text-xs uppercase text-slate-500">Last loan poll</p>
            <p class="font-semibold">{{ $parallel['last_loan_poll_at'] ?? 'Never' }}</p>
        </div>
        <div class="rounded-xl border bg-white p-3">
            <p class="text-xs uppercase text-slate-500">Last expense sync</p>
            <p class="font-semibold">{{ $parallel['last_expense_sync_at'] ?? 'Never' }}</p>
        </div>
    </div>

    @if(session('status'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">{{ session('error') }}</div>
    @endif

    <form method="GET" class="mb-4 flex flex-wrap gap-2">
        <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Legacy loan / user ID" class="rounded-xl border px-3 py-2 text-sm">
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
                    <th class="px-3 py-2">Legacy Loan</th>
                    <th class="px-3 py-2">User</th>
                    <th class="px-3 py-2">Amount</th>
                    <th class="px-3 py-2">Detected</th>
                    <th class="px-3 py-2">Status</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($loans as $loan)
                    @php
                        $snapshot = json_decode($loan->raw_snapshot ?? '{}', true) ?: [];
                    @endphp
                    <tr class="border-b hover:bg-slate-50">
                        <td class="px-3 py-2 font-semibold">{{ $loan->legacy_loan_id }}</td>
                        <td class="px-3 py-2">{{ $loan->legacy_user_id }}</td>
                        <td class="px-3 py-2">{{ \App\Migration\Dashboard\MigrationDashboardSupport::formatZmw($snapshot['loan_amount'] ?? $snapshot['obtained_amount'] ?? 0) }}</td>
                        <td class="px-3 py-2">{{ $loan->detected_at }}</td>
                        <td class="px-3 py-2">
                            @include('legacy.migration-dashboard.partials.badge', ['label' => $loan->status])
                        </td>
                        <td class="px-3 py-2 text-right">
                            <a href="{{ route('legacy.migration-dashboard.loans.pending.show', $loan->legacy_loan_id) }}" class="font-semibold text-primary hover:underline">Review</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-3 py-8 text-center text-slate-500">No pending legacy loans in the inbox.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $loans->links() }}</div>
    </div>

    @unless($canManage)
        <p class="mt-4 text-xs text-slate-500">Import actions require <code>migration.manage</code> permission.</p>
    @endunless
@endsection
