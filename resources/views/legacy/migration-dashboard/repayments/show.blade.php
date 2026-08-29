@extends('legacy.migration-dashboard.layout')

@section('dashboard-content')
    @php $staging = $detail['staging']; $allocations = $detail['allocations']; @endphp
    @include('partials.admin.page-header', [
        'title' => 'Repayment '.$legacyRepaymentId,
        'description' => \App\Migration\Dashboard\MigrationDashboardSupport::formatZmw($staging->repayment_amount),
        'buttons' => [['text' => 'Back', 'href' => route('legacy.migration-dashboard.repayments.index'), 'action' => 'secondary']],
    ])

    <div class="grid gap-4 lg:grid-cols-2 mb-6">
        <div class="rounded-2xl border bg-white p-6 shadow-sm text-sm space-y-2">
            <p>User: {{ $staging->legacy_user_id }}</p>
            <p>Class: {{ $staging->attribution_class }}</p>
            <p>Status: {{ $staging->migration_status }}</p>
            <p>Confidence: {{ $staging->confidence }}</p>
            @if($staging->exception)<p class="text-amber-800">Exception: {{ $staging->exception }}</p>@endif
        </div>
        <div class="rounded-2xl border bg-white p-6 shadow-sm text-sm">
            <p>Target repayment: {{ $staging->mapped_repayment_id ?? '—' }}</p>
        </div>
    </div>

    <div class="rounded-2xl border bg-white p-6 shadow-sm">
        <h2 class="font-semibold text-primary mb-3">Allocations</h2>
        @if($allocations->isEmpty())
            @php $jsonAlloc = json_decode($staging->allocations ?? '[]', true) ?: []; @endphp
            @if($jsonAlloc !== [])
                <ul class="text-sm space-y-1">
                    @foreach($jsonAlloc as $alloc)
                        <li>Loan {{ $alloc['legacy_loan_id'] ?? '—' }} — {{ \App\Migration\Dashboard\MigrationDashboardSupport::formatZmw($alloc['amount'] ?? 0) }}</li>
                    @endforeach
                </ul>
            @else
                <p class="text-slate-500 text-sm">No allocations recorded.</p>
            @endif
        @else
            <table class="min-w-full text-sm">
                <thead><tr class="border-b bg-slate-100 text-xs uppercase"><th class="px-3 py-2 text-left">Loan</th><th class="px-3 py-2 text-left">Amount</th><th class="px-3 py-2 text-left">Principal</th><th class="px-3 py-2 text-left">Interest</th><th class="px-3 py-2 text-left">Fees</th><th class="px-3 py-2 text-left">Rule</th></tr></thead>
                <tbody>
                    @foreach($allocations as $alloc)
                        <tr class="border-b">
                            <td class="px-3 py-2">{{ $alloc->legacy_loan_id }}</td>
                            <td class="px-3 py-2">{{ \App\Migration\Dashboard\MigrationDashboardSupport::formatZmw($alloc->allocated_amount) }}</td>
                            <td class="px-3 py-2">{{ \App\Migration\Dashboard\MigrationDashboardSupport::formatZmw($alloc->principal_amount ?? null) }}</td>
                            <td class="px-3 py-2">{{ \App\Migration\Dashboard\MigrationDashboardSupport::formatZmw($alloc->interest_amount ?? null) }}</td>
                            <td class="px-3 py-2">{{ \App\Migration\Dashboard\MigrationDashboardSupport::formatZmw($alloc->fee_amount ?? null) }}</td>
                            <td class="px-3 py-2 text-xs">{{ $alloc->rule_used ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
