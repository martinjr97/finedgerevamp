@extends('legacy.migration-dashboard.layout')

@section('dashboard-content')
    @php
        $staging = $detail['staging'];
        $target = $detail['target'];
        $identity = $detail['identity'];
        $ctx = $staging ? (json_decode($staging->raw_context ?? '{}', true) ?: []) : [];
    @endphp
    @include('partials.admin.page-header', [
        'title' => 'Customer Legacy User '.$legacyUserId,
        'description' => 'Migration audit detail',
        'buttons' => [['text' => 'Back', 'href' => route('legacy.migration-dashboard.customers.index'), 'action' => 'secondary']],
    ])

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-2xl border bg-white p-6 shadow-sm text-sm space-y-2">
            <h2 class="font-semibold text-primary">Legacy identity</h2>
            <p>User ID: {{ $legacyUserId }}</p>
            @if($staging)
                <p>Customer ID: {{ $staging->legacy_customer_id ?? '—' }}</p>
                <p>Product: {{ $staging->target_product_code ?? '—' }}</p>
                <p>Status: {{ $staging->migration_status }}</p>
            @else
                <p class="text-slate-500">No staging row — mapped via entity map only.</p>
            @endif
            @if(!empty($ctx['national_id']))
                <p>NRC: {{ \App\Migration\Dashboard\MigrationDashboardSupport::maskNrc($ctx['national_id']) }} <span class="text-xs text-slate-500">(masked in list views)</span></p>
            @endif
        </div>
        <div class="rounded-2xl border bg-white p-6 shadow-sm text-sm space-y-2">
            <h2 class="font-semibold text-primary">Target identity</h2>
            @if($target)
                <p>Customer #{{ $target->id }} — {{ $target->full_name }}</p>
                <p>Company: {{ $target->company?->name ?? '—' }}</p>
            @else
                <p class="text-slate-500">Not promoted yet</p>
            @endif
            @if($detail['map'])
                <p class="text-xs">Map method: {{ $detail['map']->mapping_method }}</p>
            @endif
        </div>
    </div>

    @if($identity)
        <div class="mt-6 rounded-2xl border bg-white p-6 shadow-sm">
            <h2 class="font-semibold text-primary mb-3">Identity alias resolution</h2>
            <p class="text-sm">Target Customer {{ $identity['target_customer_id'] ?? ($target->id ?? '—') }}</p>
            <ul class="mt-2 text-sm list-disc pl-5">
                <li>← Legacy User {{ $identity['primary_legacy_user_id'] ?? '—' }} (primary)</li>
                @foreach($identity['alias_legacy_user_ids'] ?? [] as $aliasId)
                    <li>← Legacy User {{ $aliasId }}</li>
                @endforeach
            </ul>
            <p class="mt-2 text-xs text-slate-600">{{ $identity['reason'] ?? '' }}</p>
        </div>
    @endif

    <div class="mt-6 grid gap-4 lg:grid-cols-2">
        <div class="rounded-2xl border bg-white p-6 shadow-sm overflow-x-auto">
            <h2 class="font-semibold text-primary mb-2">Active loans ({{ $detail['loans']->count() }})</h2>
            <table class="min-w-full text-xs">
                <thead><tr class="border-b bg-slate-100"><th class="px-2 py-1 text-left">Legacy Loan</th><th class="px-2 py-1 text-left">Status</th><th class="px-2 py-1 text-left">Target</th></tr></thead>
                <tbody>
                    @foreach($detail['loans'] as $loan)
                        <tr class="border-b">
                            <td class="px-2 py-1"><a href="{{ route('legacy.migration-dashboard.loans.show', $loan->legacy_loan_id) }}" class="text-primary hover:underline">{{ $loan->legacy_loan_id }}</a></td>
                            <td class="px-2 py-1">{{ $loan->migration_status }}</td>
                            <td class="px-2 py-1">{{ $loan->mapped_loan_id ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="rounded-2xl border bg-white p-6 shadow-sm overflow-x-auto">
            <h2 class="font-semibold text-primary mb-2">Repayments (sample)</h2>
            <table class="min-w-full text-xs">
                <thead><tr class="border-b bg-slate-100"><th class="px-2 py-1 text-left">ID</th><th class="px-2 py-1 text-left">Class</th><th class="px-2 py-1 text-left">Amount</th></tr></thead>
                <tbody>
                    @foreach($detail['repayments'] as $repayment)
                        <tr class="border-b">
                            <td class="px-2 py-1"><a href="{{ route('legacy.migration-dashboard.repayments.show', $repayment->legacy_repayment_id) }}" class="text-primary hover:underline">{{ $repayment->legacy_repayment_id }}</a></td>
                            <td class="px-2 py-1">{{ $repayment->attribution_class }}</td>
                            <td class="px-2 py-1">{{ \App\Migration\Dashboard\MigrationDashboardSupport::formatZmw($repayment->repayment_amount) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
