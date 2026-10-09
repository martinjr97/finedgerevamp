@extends('legacy.migration-dashboard.layout')

@section('dashboard-content')
    @php
        $inbox = $detail['inbox'];
        $preview = $detail['preview'] ?? [];
        $readiness = $preview['readiness'] ?? [];
    @endphp

    @include('partials.admin.page-header', [
        'title' => 'Review Legacy Loan #'.$legacyLoanId,
        'description' => 'Compare legacy vs revamp before confirming import (repayments sync + accrual catch-up).',
    ])

    <div class="mb-4">
        <a href="{{ route('legacy.migration-dashboard.loans.pending') }}" class="text-sm font-semibold text-primary hover:underline">← Back to pending loans</a>
    </div>

    @if(session('error'))
        <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">{{ session('error') }}</div>
    @endif

    <div class="mb-6 rounded-2xl border bg-white p-4 shadow-sm">
        <dl class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 text-sm">
            <div>
                <dt class="text-xs uppercase text-slate-500">Inbox status</dt>
                <dd class="mt-1">@include('legacy.migration-dashboard.partials.badge', ['label' => $inbox->status])</dd>
            </div>
            <div>
                <dt class="text-xs uppercase text-slate-500">Legacy user</dt>
                <dd class="mt-1 font-semibold">#{{ $inbox->legacy_user_id }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase text-slate-500">Detected</dt>
                <dd class="mt-1">{{ $inbox->detected_at }}</dd>
            </div>
            @if($inbox->block_reason)
                <div class="sm:col-span-2">
                    <dt class="text-xs uppercase text-slate-500">Block reason</dt>
                    <dd class="mt-1 text-rose-700">{{ $inbox->block_reason }}</dd>
                </div>
            @endif
        </dl>
    </div>

    @include('legacy.migration-dashboard.loans.partials.import-preview-comparison', ['preview' => $preview])

    @if($canManage && in_array($inbox->status, ['pending_review', 'blocked'], true))
        <div class="mt-6 flex flex-wrap gap-3">
            <form method="POST" action="{{ route('legacy.migration-dashboard.loans.pending.import', $legacyLoanId) }}" onsubmit="return confirm('Import this loan into revamp? This will promote the loan, sync repayments, and catch up daily accrual.');">
                @csrf
                <button
                    type="submit"
                    @disabled(!($readiness['can_import'] ?? false))
                    class="rounded-xl px-5 py-2.5 text-sm font-semibold text-white {{ ($readiness['can_import'] ?? false) ? 'bg-primary hover:opacity-90' : 'bg-slate-300 cursor-not-allowed' }}"
                >
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
        @unless($readiness['can_import'] ?? false)
            <p class="mt-2 text-xs text-slate-500">Confirm import is disabled until blockers above are resolved.</p>
            @if(collect($readiness['blockers'] ?? [])->contains(fn ($b) => str_contains((string) $b, 'Customer not mapped')))
                <a href="{{ route('legacy.migration-dashboard.customers.pending.show', $inbox->legacy_user_id) }}" class="mt-2 inline-block text-sm font-semibold text-primary hover:underline">
                    Promote legacy customer #{{ $inbox->legacy_user_id }} →
                </a>
            @endif
        @endunless
    @endif
@endsection
