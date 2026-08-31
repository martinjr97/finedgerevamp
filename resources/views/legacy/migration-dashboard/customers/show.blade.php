@extends('legacy.migration-dashboard.layout')

@section('dashboard-content')
    @php
        $staging = $detail['staging'];
        $target = $detail['target'];
        $identity = $detail['identity'];
        $legacy = $detail['legacy'] ?? [];
        $review = $detail['review'] ?? [];
        $ctx = $staging ? (json_decode($staging->raw_context ?? '{}', true) ?: []) : [];
        $displayName = $legacy['full_name'] ?? trim(($ctx['fname'] ?? '').' '.($ctx['lname'] ?? '')) ?: null;
    @endphp
    @include('partials.admin.page-header', [
        'title' => $displayName ? $displayName.' (Legacy User '.$legacyUserId.')' : 'Customer Legacy User '.$legacyUserId,
        'description' => ($staging->migration_status ?? 'unknown').($staging->target_product_code ? ' · '.$staging->target_product_code : ''),
        'buttons' => [['text' => 'Back', 'href' => $backUrl, 'action' => 'secondary']],
    ])

    @if (session('status'))
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">{{ session('status') }}</div>
    @endif

    @if(!empty($review['is_manual_review']))
        <div class="mb-6 rounded-2xl border border-amber-300 bg-amber-50 p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-amber-800">Manual review required</p>
                    <h2 class="mt-1 text-lg font-semibold text-amber-950">{{ $review['title'] ?? 'Manual review' }}</h2>
                    <p class="mt-2 text-sm text-amber-900">{{ $review['description'] ?? '' }}</p>
                </div>
                @if(!empty($review['migration_run_id']))
                    <a href="{{ route('legacy.migration-dashboard.runs.show', $review['migration_run_id']) }}"
                       class="shrink-0 rounded-lg border border-amber-400 bg-white px-3 py-1.5 text-xs font-semibold text-amber-900 hover:bg-amber-100">
                        Run #{{ $review['migration_run_id'] }}
                    </a>
                @endif
            </div>
            @if(!empty($review['guidance']))
                <p class="mt-3 text-sm text-amber-900"><span class="font-semibold">What to do:</span> {{ $review['guidance'] }}</p>
            @endif
            @if(!empty($review['exception_code']))
                <p class="mt-2 text-xs text-amber-800">Rule code: <code class="rounded bg-amber-100 px-1">{{ $review['exception_code'] }}</code></p>
            @endif
            @if(!empty($review['can_map_to_existing']) && !empty($review['candidate_matches']))
                <p class="mt-3 text-sm text-amber-900">
                    <span class="font-semibold">Quick action:</span>
                    Click <strong>Map to this customer</strong> to review legacy vs revamp fields, apply any cleanup, then confirm the entity map for loan migration.
                </p>
            @elseif(!empty($review['identity_resolve_url']))
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <p class="text-sm text-amber-900">
                        <span class="font-semibold">Duplicate legacy NRC:</span>
                        resolve the identity group first (merge legacy users), then map or promote.
                    </p>
                    @if($canManage ?? false)
                        <a href="{{ $review['identity_resolve_url'] }}"
                           class="inline-flex items-center rounded-lg border border-cyan-400/50 bg-cyan-500/10 px-3 py-1.5 text-xs font-semibold text-cyan-900 hover:bg-cyan-500/20 transition">
                            Resolve identity group
                        </a>
                    @endif
                </div>
            @endif
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-2xl border bg-white p-6 shadow-sm text-sm space-y-3">
            <h2 class="font-semibold text-primary">Legacy person</h2>
            @if($legacy['available'] ?? false)
                <dl class="grid grid-cols-[minmax(7rem,auto)_1fr] gap-x-3 gap-y-2">
                    <dt class="text-slate-500">Full name</dt><dd class="font-semibold text-slate-900">{{ $legacy['full_name'] }}</dd>
                    <dt class="text-slate-500">Legacy user</dt><dd>#{{ $legacyUserId }}</dd>
                    <dt class="text-slate-500">Legacy customer</dt><dd>#{{ $legacy['legacy_customer_id'] ?? ($staging->legacy_customer_id ?? '—') }}</dd>
                    <dt class="text-slate-500">NRC</dt><dd class="font-mono">{{ $legacy['national_id'] ?? '—' }}</dd>
                    <dt class="text-slate-500">Email</dt><dd class="break-all">{{ $legacy['email'] ?? '—' }}</dd>
                    <dt class="text-slate-500">Phone</dt><dd>{{ $legacy['phone'] ?? '—' }}</dd>
                    <dt class="text-slate-500">Employee #</dt><dd>{{ $legacy['employee_number'] ?? '—' }}</dd>
                    <dt class="text-slate-500">Employer</dt><dd>{{ $legacy['employer_name'] ?? '—' }}</dd>
                    <dt class="text-slate-500">Department</dt><dd>{{ $legacy['department'] ?? '—' }}</dd>
                    <dt class="text-slate-500">Product</dt><dd>{{ $staging->target_product_code ?? $legacy['product_type'] ?? '—' }}</dd>
                    <dt class="text-slate-500">Loans</dt><dd>{{ $legacy['active_loan_count'] ?? 0 }} active / {{ $legacy['total_loan_count'] ?? 0 }} total</dd>
                </dl>
            @elseif($staging)
                <p class="text-slate-500 mb-2">Legacy database unavailable — showing staging metadata only.</p>
                <p>User ID: {{ $legacyUserId }}</p>
                <p>Customer ID: {{ $staging->legacy_customer_id ?? '—' }}</p>
                <p>Product: {{ $staging->target_product_code ?? '—' }}</p>
                @if(!empty($ctx['national_id']))
                    <p>NRC: {{ $ctx['national_id'] }}</p>
                @endif
            @else
                <p class="text-slate-500">No staging row — mapped via entity map only.</p>
            @endif
            @if($staging)
                <p class="text-xs text-slate-500 pt-2 border-t">Migration status: <strong>{{ $staging->migration_status }}</strong></p>
            @endif
        </div>
        <div class="rounded-2xl border bg-white p-6 shadow-sm text-sm space-y-3">
            <h2 class="font-semibold text-primary">Revamp target</h2>
            @if($target)
                <dl class="grid grid-cols-[minmax(7rem,auto)_1fr] gap-x-3 gap-y-2">
                    <dt class="text-slate-500">Customer</dt>
                    <dd>
                        <a href="{{ route('admin.customers.show', $target->id) }}" class="font-semibold text-primary hover:underline">
                            #{{ $target->id }} — {{ $target->full_name }}
                        </a>
                    </dd>
                    <dt class="text-slate-500">NRC</dt><dd class="font-mono">{{ $target->national_id ?? '—' }}</dd>
                    <dt class="text-slate-500">Email</dt><dd class="break-all">{{ $target->email ?? '—' }}</dd>
                    <dt class="text-slate-500">Employee #</dt><dd>{{ $target->employee_number ?? '—' }}</dd>
                    <dt class="text-slate-500">Company</dt><dd>{{ $target->company?->name ?? '—' }}</dd>
                </dl>
            @else
                <p class="text-slate-500">Not promoted — no entity map to a revamp customer yet.</p>
            @endif
            @if($detail['map'])
                <p class="text-xs text-slate-500 pt-2 border-t">Map method: {{ $detail['map']->mapping_method }} · confidence {{ $detail['map']->mapping_confidence }}</p>
            @endif
        </div>
    </div>

    @if(!empty($review['candidate_matches']))
        @php
            $mapCandidates = collect($review['candidate_matches'])->map(fn ($candidate) => [
                'id' => $candidate['id'],
                'full_name' => $candidate['full_name'],
                'email' => $candidate['email'],
                'national_id' => $candidate['national_id'],
                'employee_number' => $candidate['employee_number'],
                'company' => $candidate['company'],
                'map_fields' => $candidate['map_fields'] ?? [],
            ])->values()->all();
            $showMapModal = ($canManage ?? false) && !empty($review['can_map_to_existing']) && !$target;
        @endphp
        <div
            class="mt-6 rounded-2xl border border-rose-200 bg-white p-6 shadow-sm"
            @if($showMapModal)
                x-data="customerMapModal({
                    candidates: @js($mapCandidates),
                    legacyUserId: {{ (int) $legacyUserId }},
                    legacyName: @js($displayName ?? 'Legacy user'),
                    mapUrl: @js(route('legacy.migration-dashboard.customers.map', $legacyUserId)),
                    runId: @js(request('run_id')),
                    statusFilter: @js(request('status')),
                })"
            @endif
        >
            <h2 class="font-semibold text-primary mb-2">Conflicting revamp customer(s)</h2>
            <p class="text-sm text-slate-600 mb-4">These existing customers triggered the {{ $review['exception_code'] ?? 'match' }} rule. Compare identity fields before deciding.</p>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b bg-slate-100 text-left text-xs uppercase tracking-wide text-slate-700">
                            <th class="px-3 py-2">ID</th>
                            <th class="px-3 py-2">Name</th>
                            <th class="px-3 py-2">NRC</th>
                            <th class="px-3 py-2">Email</th>
                            <th class="px-3 py-2">Employee #</th>
                            <th class="px-3 py-2">Company</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($review['candidate_matches'] as $candidate)
                            <tr class="border-b hover:bg-slate-50">
                                <td class="px-3 py-2">#{{ $candidate['id'] }}</td>
                                <td class="px-3 py-2 font-semibold">{{ $candidate['full_name'] }}</td>
                                <td class="px-3 py-2 font-mono text-xs">{{ $candidate['national_id'] ?? '—' }}</td>
                                <td class="px-3 py-2 text-xs break-all">{{ $candidate['email'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $candidate['employee_number'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $candidate['company'] ?? '—' }}</td>
                                <td class="px-3 py-2 space-y-1">
                                    <a href="{{ $candidate['admin_url'] }}" class="block font-semibold text-primary hover:underline text-xs">Open in admin</a>
                                    @if($showMapModal)
                                        <button
                                            type="button"
                                            class="rounded-lg border border-emerald-400 bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-900 hover:bg-emerald-100 transition"
                                            @click="openCandidate({{ $candidate['id'] }})"
                                        >
                                            Map to this customer
                                        </button>
                                    @elseif($target && (int) $target->id === (int) $candidate['id'])
                                        <span class="text-xs font-semibold text-emerald-700">Mapped</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($showMapModal)
                @include('legacy.migration-dashboard.customers.partials.map-modal')
            @endif
        </div>
    @endif

    @if(!empty($review['duplicate_legacy_users']))
        <div class="mt-6 rounded-2xl border bg-white p-6 shadow-sm">
            <h2 class="font-semibold text-primary mb-2">Other legacy users with same NRC</h2>
            <ul class="space-y-2 text-sm">
                @foreach($review['duplicate_legacy_users'] as $dup)
                    <li class="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-slate-50 px-3 py-2">
                        <span><strong>{{ $dup['full_name'] }}</strong> · Legacy User #{{ $dup['legacy_user_id'] }} · {{ $dup['email'] ?? '—' }}</span>
                        <a href="{{ $dup['dashboard_url'] }}" class="text-xs font-semibold text-primary hover:underline">View</a>
                    </li>
                @endforeach
            </ul>
            <p class="mt-3 text-xs text-slate-600">
                @if(!empty($review['identity_resolve_url']))
                    <a href="{{ $review['identity_resolve_url'] }}" class="font-semibold text-primary hover:underline">Resolve identity group</a>
                    to merge these legacy users before customer / loan migration.
                @else
                    <a href="{{ route('legacy.migration-dashboard.identity.index') }}" class="font-semibold text-primary hover:underline">Identity resolution</a>
                    may be required if these are duplicate NRC groups.
                @endif
            </p>
        </div>
    @endif

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
                    @forelse($detail['loans'] as $loan)
                        <tr class="border-b">
                            <td class="px-2 py-1"><a href="{{ route('legacy.migration-dashboard.loans.show', $loan->legacy_loan_id) }}" class="text-primary hover:underline">{{ $loan->legacy_loan_id }}</a></td>
                            <td class="px-2 py-1">{{ $loan->migration_status }}</td>
                            <td class="px-2 py-1">{{ $loan->mapped_loan_id ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-4 text-center text-slate-500">No loan staging rows</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="rounded-2xl border bg-white p-6 shadow-sm overflow-x-auto">
            <h2 class="font-semibold text-primary mb-2">Repayments (sample)</h2>
            <table class="min-w-full text-xs">
                <thead><tr class="border-b bg-slate-100"><th class="px-2 py-1 text-left">ID</th><th class="px-2 py-1 text-left">Class</th><th class="px-2 py-1 text-left">Amount</th></tr></thead>
                <tbody>
                    @forelse($detail['repayments'] as $repayment)
                        <tr class="border-b">
                            <td class="px-2 py-1"><a href="{{ route('legacy.migration-dashboard.repayments.show', $repayment->legacy_repayment_id) }}" class="text-primary hover:underline">{{ $repayment->legacy_repayment_id }}</a></td>
                            <td class="px-2 py-1">{{ $repayment->attribution_class }}</td>
                            <td class="px-2 py-1">{{ \App\Migration\Dashboard\MigrationDashboardSupport::formatZmw($repayment->repayment_amount) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-4 text-center text-slate-500">No repayment rows</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
