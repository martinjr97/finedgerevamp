@php
    use App\Migration\Dashboard\MigrationDashboardSupport;

    $preview = $preview ?? [];
    $legacy = $preview['legacy'] ?? [];
    $revamp = $preview['revamp'] ?? [];
    $readiness = $preview['readiness'] ?? [];
    $comparisons = $preview['comparisons'] ?? [];
    $postImport = $preview['post_import'] ?? [];

    $statusStyles = [
        'match' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
        'diff' => 'bg-amber-50 text-amber-900 border-amber-200',
        'critical' => 'bg-rose-50 text-rose-900 border-rose-200',
        'info' => 'bg-slate-50 text-slate-700 border-slate-200',
    ];
    $statusLabels = [
        'match' => 'Match',
        'diff' => 'Diff',
        'critical' => 'Mismatch',
        'info' => 'Info',
    ];
@endphp

@if(!($readiness['can_import'] ?? false))
    <div class="mb-6 rounded-2xl border border-rose-300 bg-rose-50 p-5 text-sm text-rose-950">
        <p class="font-semibold">Import blocked</p>
        <ul class="mt-2 list-disc pl-5 space-y-1">
            @foreach($readiness['blockers'] ?? [] as $blocker)
                <li>{{ $blocker }}</li>
            @endforeach
        </ul>
    </div>
@elseif(($readiness['warnings'] ?? []) !== [])
    <div class="mb-6 rounded-2xl border border-amber-300 bg-amber-50 p-5 text-sm text-amber-950">
        <p class="font-semibold">Review before confirming</p>
        <ul class="mt-2 list-disc pl-5 space-y-1">
            @foreach($readiness['warnings'] as $warning)
                <li>{{ $warning }}</li>
            @endforeach
        </ul>
    </div>
@else
    <div class="mb-6 rounded-2xl border border-emerald-300 bg-emerald-50 p-4 text-sm text-emerald-950">
        <strong>Ready to import.</strong> Legacy and revamp field mapping aligns with the active-loan migrator rules.
    </div>
@endif

<div class="mb-4 flex flex-wrap gap-2 text-xs">
    <span class="rounded-full border px-2 py-1 bg-white">Cohort: <strong>{{ $readiness['cohort'] ?? '—' }}</strong></span>
    <span class="rounded-full border px-2 py-1 bg-white">Legacy source: <strong>{{ $preview['legacy_source'] ?? '—' }}</strong></span>
    @if($readiness['reconciliation_status'] ?? null)
        <span class="rounded-full border px-2 py-1 bg-white">Replay: <strong>{{ $readiness['reconciliation_status'] }}</strong></span>
    @endif
    @if($legacy['is_accrual_loan'] ?? false)
        <span class="rounded-full border border-cyan-300 bg-cyan-50 px-2 py-1 text-cyan-900">MOU/GRZ accrual loan</span>
    @endif
</div>

<div class="grid gap-4 lg:grid-cols-2 mb-6">
    <div class="rounded-2xl border bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-primary mb-1">Legacy (source)</h2>
        <p class="text-xs text-slate-500 mb-4">As recorded in legacy at review time{{ ($preview['legacy_source'] ?? '') === 'live' ? ' (live DB)' : ' (inbox snapshot)' }}.</p>
        @if($legacy['available'] ?? false)
            <dl class="grid grid-cols-[minmax(8rem,auto)_1fr] gap-x-3 gap-y-2 text-sm">
                <dt class="text-slate-500">Loan ID</dt><dd class="font-semibold">#{{ $legacy['loan_id'] ?? '—' }}</dd>
                <dt class="text-slate-500">Status</dt><dd>{{ $legacy['status_code'] ?? '—' }}</dd>
                <dt class="text-slate-500">Customer</dt><dd>{{ $legacy['customer']['full_name'] ?? '—' }} <span class="text-slate-500">(user #{{ $legacy['customer']['legacy_user_id'] ?? '—' }})</span></dd>
                <dt class="text-slate-500">NRC</dt><dd class="font-mono text-xs">{{ $legacy['customer']['national_id'] ?? '—' }}</dd>
                <dt class="text-slate-500">Phone</dt><dd>{{ $legacy['customer']['phone'] ?? '—' }}</dd>
                <dt class="text-slate-500">Product</dt><dd>{{ $legacy['product_code'] ?? '—' }}</dd>
                <dt class="text-slate-500">Principal</dt><dd class="font-semibold">{{ MigrationDashboardSupport::formatZmw($legacy['financials']['principal_obtained'] ?? 0) }}</dd>
                <dt class="text-slate-500">Total repayment</dt><dd>{{ MigrationDashboardSupport::formatZmw($legacy['financials']['total_repayment_amount'] ?? 0) }}</dd>
                <dt class="text-slate-500">Paid</dt><dd>{{ MigrationDashboardSupport::formatZmw($legacy['financials']['amount_paid'] ?? 0) }}</dd>
                <dt class="text-slate-500">Outstanding</dt><dd class="font-semibold text-primary">{{ MigrationDashboardSupport::formatZmw($legacy['financials']['effective_outstanding'] ?? 0) }}</dd>
                <dt class="text-slate-500">Tenure</dt><dd>{{ $legacy['tenure_months'] ?? '—' }} month(s)</dd>
                <dt class="text-slate-500">Disbursed</dt><dd>{{ $legacy['dates']['created_at'] ?? '—' }}</dd>
                <dt class="text-slate-500">Due date</dt><dd>{{ $legacy['dates']['due_date'] ?? '—' }}</dd>
                <dt class="text-slate-500">First repayment</dt><dd>{{ $legacy['dates']['first_repayment_date'] ?? '—' }}</dd>
                @if($legacy['is_accrual_loan'] ?? false)
                    <dt class="text-slate-500">Legacy principal</dt><dd>{{ MigrationDashboardSupport::formatZmw($legacy['financials']['principle_amount'] ?? 0) }}</dd>
                    <dt class="text-slate-500">Accrued interest</dt><dd>{{ MigrationDashboardSupport::formatZmw($legacy['financials']['accrued_interest'] ?? 0) }}</dd>
                    <dt class="text-slate-500">Accrued days</dt><dd>{{ $legacy['financials']['accrued_days'] ?? '—' }}</dd>
                    <dt class="text-slate-500">Daily interest</dt><dd>{{ MigrationDashboardSupport::formatZmw($legacy['financials']['daily_added_interest'] ?? 0) }}</dd>
                    <dt class="text-slate-500">Current balance</dt><dd>{{ MigrationDashboardSupport::formatZmw($legacy['financials']['current_loan_amount'] ?? 0) }}</dd>
                @else
                    <dt class="text-slate-500">Fixed balance</dt><dd>{{ MigrationDashboardSupport::formatZmw($legacy['financials']['fixed_balance'] ?? 0) }}</dd>
                    <dt class="text-slate-500">Installment</dt><dd>{{ MigrationDashboardSupport::formatZmw($legacy['financials']['current_installment'] ?? $legacy['financials']['initial_installment'] ?? 0) }}</dd>
                @endif
            </dl>
        @else
            <p class="text-sm text-slate-500">Legacy loan data unavailable.</p>
        @endif
    </div>

    <div class="rounded-2xl border bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-primary mb-1">Revamp (after import)</h2>
        <p class="text-xs text-slate-500 mb-4">Preview of fields written by the active-loan migrator on confirm.</p>
        <dl class="grid grid-cols-[minmax(8rem,auto)_1fr] gap-x-3 gap-y-2 text-sm">
            <dt class="text-slate-500">Loan number</dt><dd class="font-mono font-semibold">{{ $revamp['loan_number'] ?? '—' }}</dd>
            <dt class="text-slate-500">Customer</dt>
            <dd>
                @if($revamp['customer_id'] ?? null)
                    {{ $revamp['customer_name'] ?? 'Customer' }} <span class="text-slate-500">(#{{ $revamp['customer_id'] }})</span>
                @else
                    <span class="text-rose-700 font-semibold">Not mapped</span>
                @endif
            </dd>
            <dt class="text-slate-500">Product</dt><dd>{{ $revamp['loan_product_code'] ?? '—' }}@if($revamp['loan_product_name'] ?? null) <span class="text-slate-500">({{ $revamp['loan_product_name'] }})</span>@endif</dd>
            <dt class="text-slate-500">Principal</dt><dd class="font-semibold">{{ MigrationDashboardSupport::formatZmw($revamp['principal_amount'] ?? 0) }}</dd>
            <dt class="text-slate-500">Total booked</dt><dd>{{ MigrationDashboardSupport::formatZmw($revamp['total_amount'] ?? 0) }}</dd>
            <dt class="text-slate-500">Paid</dt><dd>{{ MigrationDashboardSupport::formatZmw($revamp['amount_paid'] ?? 0) }}</dd>
            <dt class="text-slate-500">Outstanding</dt><dd class="font-semibold text-primary">{{ MigrationDashboardSupport::formatZmw($revamp['outstanding_balance'] ?? 0) }}</dd>
            <dt class="text-slate-500">Tenure</dt><dd>{{ $revamp['tenure_months'] ?? '—' }} month(s)</dd>
            <dt class="text-slate-500">Start date</dt><dd>{{ $revamp['loan_start_date'] ?? '—' }}</dd>
            <dt class="text-slate-500">Disbursed at</dt><dd>{{ $revamp['disbursed_at'] ?? '—' }}</dd>
            <dt class="text-slate-500">End date</dt><dd>{{ $revamp['loan_end_date'] ?? '—' }}</dd>
            <dt class="text-slate-500">First payment</dt><dd>{{ $revamp['first_payment_date'] ?? '—' }}</dd>
            <dt class="text-slate-500">Accrual</dt><dd>{{ ucfirst(str_replace('_', ' ', $revamp['accrual_type'] ?? '—')) }} / {{ ucfirst(str_replace('_', ' ', $revamp['interest_behavior'] ?? '—')) }}</dd>
            <dt class="text-slate-500">Schedule</dt><dd>{{ ucfirst($revamp['repayment_structure'] ?? '—') }} installments</dd>
            <dt class="text-slate-500">Status</dt><dd>{{ ucfirst($revamp['status'] ?? '—') }} · {{ ucfirst($revamp['disbursement_status'] ?? '—') }}</dd>
        </dl>

        @if($postImport !== [])
            <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs text-slate-700">
                <p class="font-semibold text-slate-900 mb-1">On confirm import</p>
                <ul class="list-disc pl-4 space-y-0.5">
                    @if($postImport['repayments_synced'] ?? false)
                        <li>Legacy repayments for this customer will be synced and allocated.</li>
                    @endif
                    @if($postImport['accrual_catch_up'] ?? false)
                        <li>Daily interest accrual will be caught up from loan start through yesterday.</li>
                    @endif
                    @if($postImport['schedule_refresh'] ?? false)
                        <li>Payment schedule aging will be refreshed.</li>
                    @endif
                </ul>
            </div>
        @endif
    </div>
</div>

<div class="rounded-2xl border bg-white p-6 shadow-sm overflow-x-auto">
    <h2 class="text-lg font-semibold text-primary mb-1">Field comparison</h2>
    <p class="text-xs text-slate-500 mb-4">Side-by-side check — mismatches on principal, dates, or outstanding should be resolved before confirming.</p>
    <table class="min-w-full text-sm">
        <thead>
            <tr class="border-b bg-slate-100 text-left text-xs uppercase tracking-wide text-slate-700">
                <th class="px-3 py-2">Field</th>
                <th class="px-3 py-2">Legacy</th>
                <th class="px-3 py-2">Revamp (import)</th>
                <th class="px-3 py-2">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($comparisons as $row)
                @php $style = $statusStyles[$row['status'] ?? 'info'] ?? $statusStyles['info']; @endphp
                <tr class="border-b hover:bg-slate-50">
                    <td class="px-3 py-2 font-medium">{{ $row['label'] }}</td>
                    <td class="px-3 py-2">{{ $row['legacyValue'] }}</td>
                    <td class="px-3 py-2">{{ $row['revampValue'] }}</td>
                    <td class="px-3 py-2">
                        <span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-semibold {{ $style }}">
                            {{ $statusLabels[$row['status'] ?? 'info'] ?? 'Info' }}
                        </span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="px-3 py-6 text-center text-slate-500">No comparison rows available.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
