@extends('legacy.migration-dashboard.layout')

@section('dashboard-content')
    @php
        $inbox = $detail['inbox'];
        $snapshot = $detail['snapshot'] ?? [];
        $live = $detail['live'] ?? [];
    @endphp

    @include('partials.admin.page-header', [
        'title' => 'Review Legacy Loan #'.$legacyLoanId,
        'description' => 'Confirm import into revamp shadow portfolio (includes repayments + accrual catch-up).',
    ])

    <div class="mb-4">
        <a href="{{ route('legacy.migration-dashboard.loans.pending') }}" class="text-sm font-semibold text-primary hover:underline">← Back to pending loans</a>
    </div>

    @if(session('error'))
        <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">{{ session('error') }}</div>
    @endif

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-2xl border bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-primary mb-4">Inbox</h2>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt>Status</dt><dd>@include('legacy.migration-dashboard.partials.badge', ['label' => $inbox->status])</dd></div>
                <div class="flex justify-between"><dt>Legacy user</dt><dd>{{ $inbox->legacy_user_id }}</dd></div>
                <div class="flex justify-between"><dt>Detected</dt><dd>{{ $inbox->detected_at }}</dd></div>
                @if($inbox->block_reason)
                    <div class="flex justify-between"><dt>Block reason</dt><dd class="text-rose-700">{{ $inbox->block_reason }}</dd></div>
                @endif
            </dl>
        </div>

        <div class="rounded-2xl border bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-primary mb-4">Legacy snapshot</h2>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt>Loan amount</dt><dd>{{ \App\Migration\Dashboard\MigrationDashboardSupport::formatZmw($snapshot['loan_amount'] ?? 0) }}</dd></div>
                <div class="flex justify-between"><dt>Repaid</dt><dd>{{ \App\Migration\Dashboard\MigrationDashboardSupport::formatZmw($snapshot['repaid_amount'] ?? 0) }}</dd></div>
                <div class="flex justify-between"><dt>Due date</dt><dd>{{ $snapshot['due_date'] ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt>Created</dt><dd>{{ $snapshot['created_at'] ?? '—' }}</dd></div>
            </dl>
        </div>
    </div>

    @if($live !== [])
        <div class="mt-4 rounded-2xl border bg-slate-50 p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-primary mb-2">Live legacy row</h2>
            <p class="text-xs text-slate-500 mb-3">Read-only check against legacy DB at review time.</p>
            <dl class="grid gap-2 sm:grid-cols-2 text-sm">
                <div><dt class="text-slate-500">Status code</dt><dd class="font-semibold">{{ $live['status_code'] ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">Current balance</dt><dd class="font-semibold">{{ \App\Migration\Dashboard\MigrationDashboardSupport::formatZmw($live['current_loan_amount'] ?? 0) }}</dd></div>
            </dl>
        </div>
    @endif

    @if($canManage && in_array($inbox->status, ['pending_review', 'blocked'], true))
        <div class="mt-6 flex flex-wrap gap-3">
            <form method="POST" action="{{ route('legacy.migration-dashboard.loans.pending.import', $legacyLoanId) }}" onsubmit="return confirm('Import this loan into revamp? This will promote the loan, sync repayments, and catch up daily accrual.');">
                @csrf
                <button type="submit" class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:opacity-90">
                    Confirm import
                </button>
            </form>

            <form method="POST" action="{{ route('legacy.migration-dashboard.loans.pending.dismiss', $legacyLoanId) }}" class="flex items-end gap-2" onsubmit="return confirm('Dismiss this loan from the import queue?');">
                @csrf
                <input type="text" name="notes" placeholder="Optional reason" class="rounded-xl border px-3 py-2 text-sm">
                <button type="submit" class="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Dismiss
                </button>
            </form>
        </div>
    @endif
@endsection
