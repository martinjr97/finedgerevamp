@extends('legacy.migration-dashboard.layout')

@section('dashboard-content')
    @php $staging = $detail['staging']; $replay = $detail['replay']; $target = $detail['target']; @endphp
    @include('partials.admin.page-header', [
        'title' => 'Loan '.$legacyLoanId,
        'description' => 'Migration and reconciliation detail',
        'buttons' => [['text' => 'Back', 'href' => route('legacy.migration-dashboard.loans.index'), 'action' => 'secondary']],
    ])

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-2xl border bg-white p-6 shadow-sm text-sm space-y-2">
            <h2 class="font-semibold text-primary">Legacy</h2>
            <p>Loan ID: {{ $legacyLoanId }}</p>
            <p>User: {{ $staging->legacy_user_id }}</p>
            <p>Product: {{ $staging->target_product_code ?? $staging->legacy_product_type }}</p>
            <p>Legacy outstanding: {{ \App\Migration\Dashboard\MigrationDashboardSupport::formatZmw($staging->legacy_effective_outstanding) }}</p>
            <p>Status: {{ $staging->migration_status }}</p>
            @if($staging->exception)
                <p class="text-amber-800">Exception: {{ $staging->exception }}</p>
            @endif
        </div>
        <div class="rounded-2xl border bg-white p-6 shadow-sm text-sm space-y-2">
            <h2 class="font-semibold text-primary">Target</h2>
            @if($target)
                <p>Loan #{{ $target->id }} — {{ $target->loan_number ?? '' }}</p>
                <p>Outstanding: {{ \App\Migration\Dashboard\MigrationDashboardSupport::formatZmw($target->outstanding_balance) }}</p>
            @else
                <p class="text-slate-500">Not promoted</p>
            @endif
            @if($replay)
                <p>Replay: {{ $replay->promotion_status ?? '—' }}</p>
                <p>Reconciliation: {{ $replay->reconciliation_status ?? '—' }}</p>
                <p>Opening adjustment: {{ \App\Migration\Dashboard\MigrationDashboardSupport::formatZmw($replay->migration_opening_adjustment) }}</p>
            @endif
        </div>
    </div>

    @if($staging->migration_status === 'manual_review' || ($replay && ($replay->promotion_status ?? '') === 'MANUAL_REVIEW'))
        <div class="mt-6 rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
            <strong>Manual review required.</strong>
            {{ $staging->exception ?? 'See replay reconciliation notes.' }}
        </div>
    @endif
@endsection
