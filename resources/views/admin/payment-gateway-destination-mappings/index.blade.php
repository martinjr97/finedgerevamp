@extends('layouts.admin')

@section('title', 'Payment Gateway Destination Mappings | '.config('app.system_name'))

@section('content')
    @php
        use App\Support\PaymentGatewayDestinationMappingAdminUi as MappingUi;
        $selectedGatewayId = (int) ($filters['gateway_id'] ?? $filters['default_gateway_id'] ?? 0);
        $selectedGateway = $gateways->firstWhere('id', $selectedGatewayId) ?? $gateways->first();
        $showCgrateSync = $selectedGateway?->code === 'cgrate';
    @endphp

    <div
        class="space-y-8"
        x-data="destinationMappingsPage({
            activeModal: null,
            editing: null,
            form: {
                payment_gateway_id: @js($selectedGateway?->id),
                destination_type: 'bank',
                financial_institution_id: '',
                channel_id: '',
                gateway_key: @js($selectedGateway ? MappingUi::defaultGatewayKey($selectedGateway) : 'issuerName'),
                gateway_value: '',
                environment: '',
                status: 'active',
                notes: '',
            },
            gateways: @js($gateways->map(fn ($g) => [
                'id' => $g->id,
                'name' => $g->name,
                'code' => $g->code,
                'default_key' => MappingUi::defaultGatewayKey($g),
            ])),
        })"
    >
        @include('partials.admin.page-header', [
            'title' => 'Payment Gateway Destination Mappings',
            'description' => 'Configure how FineEdge destinations translate into gateway-specific identifiers.',
            'buttons' => array_filter([
                auth('admin')->user()?->can('payment-gateways.view') || $canManage ? [
                    'action' => 'secondary',
                    'text' => '← Payment Gateways',
                    'href' => route('admin.payment-gateways.index'),
                ] : null,
            ]),
        ])

        @if ($canManage)
            <div class="flex justify-end -mt-4">
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-2xl bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-lg hover:opacity-90"
                    @click="openCreate()"
                >
                    + New Mapping
                </button>
            </div>
        @endif

        @if(session('status'))
            <div class="rounded-2xl border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-2xl border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-800 space-y-1">
                <p class="font-semibold">Could not complete this action:</p>
                <ul class="list-disc pl-5 space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @unless ($canManage)
            <div class="rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                You have view-only access. An administrator with <strong>payment-gateways.manage</strong> permission is required to create, edit, or delete mappings.
            </div>
        @endunless

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
            @foreach ([
                ['label' => 'Total Gateways', 'value' => $summary['total_gateways'], 'class' => 'text-primary'],
                ['label' => 'Configured Mappings', 'value' => $summary['configured_mappings'], 'class' => 'text-primary'],
                ['label' => 'Missing Mappings', 'value' => $summary['missing_mappings'], 'class' => 'text-rose-700'],
                ['label' => 'Verification Required', 'value' => $summary['verification_required'], 'class' => 'text-amber-700'],
                ['label' => 'Active Mappings', 'value' => $summary['active_mappings'], 'class' => 'text-emerald-700'],
                ['label' => 'Discovered Issuers', 'value' => $summary['discovered_issuers'], 'class' => 'text-cyan-700'],
            ] as $card)
                <div class="rounded-2xl border border-muted bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-muted">{{ $card['label'] }}</p>
                    <p class="mt-2 text-2xl font-semibold {{ $card['class'] }}">{{ number_format($card['value']) }}</p>
                </div>
            @endforeach
        </div>

        <div class="rounded-3xl border border-muted bg-white p-4 shadow-lg space-y-4">
            <form method="GET" action="{{ route('admin.payment-gateway-destination-mappings.index') }}" class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                <div class="space-y-1">
                    <label for="filter-gateway" class="block text-xs font-semibold uppercase tracking-[0.14em] text-muted">Gateway</label>
                    <select id="filter-gateway" name="gateway_id" class="w-full rounded-xl border border-muted bg-white px-3 py-2.5 text-sm text-primary">
                        <option value="">All gateways</option>
                        @foreach ($gateways as $gateway)
                            <option value="{{ $gateway->id }}" @selected((int) ($filters['gateway_id'] ?? 0) === $gateway->id)>{{ $gateway->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="space-y-1">
                    <label for="filter-environment" class="block text-xs font-semibold uppercase tracking-[0.14em] text-muted">Environment</label>
                    <select id="filter-environment" name="environment" class="w-full rounded-xl border border-muted bg-white px-3 py-2.5 text-sm text-primary">
                        <option value="">All environments</option>
                        <option value="global" @selected(($filters['environment'] ?? '') === 'global')>Global</option>
                        @foreach (['local', 'uat', 'production'] as $env)
                            <option value="{{ $env }}" @selected(($filters['environment'] ?? '') === $env)>{{ strtoupper($env) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="space-y-1">
                    <label for="filter-destination-type" class="block text-xs font-semibold uppercase tracking-[0.14em] text-muted">Destination Type</label>
                    <select id="filter-destination-type" name="destination_type" class="w-full rounded-xl border border-muted bg-white px-3 py-2.5 text-sm text-primary">
                        <option value="">All types</option>
                        <option value="bank" @selected(($filters['destination_type'] ?? '') === 'bank')>Bank</option>
                        <option value="mobile_money" @selected(($filters['destination_type'] ?? '') === 'mobile_money')>Mobile Money</option>
                    </select>
                </div>
                <div class="space-y-1">
                    <label for="filter-status" class="block text-xs font-semibold uppercase tracking-[0.14em] text-muted">Status</label>
                    <select id="filter-status" name="status" class="w-full rounded-xl border border-muted bg-white px-3 py-2.5 text-sm text-primary">
                        <option value="">All statuses</option>
                        @foreach (['active', 'verification_required', 'inactive'] as $status)
                            <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ MappingUi::statusBadge($status)['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="space-y-1 xl:col-span-2">
                    <label for="filter-search" class="block text-xs font-semibold uppercase tracking-[0.14em] text-muted">Search</label>
                    <div class="flex gap-2">
                        <input
                            id="filter-search"
                            type="search"
                            name="search"
                            value="{{ $filters['search'] ?? '' }}"
                            placeholder="Bank name, gateway identifier, gateway value…"
                            class="w-full rounded-xl border border-muted bg-white px-3 py-2.5 text-sm text-primary"
                        >
                        <button type="submit" class="rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90">Filter</button>
                    </div>
                </div>
            </form>

            @if ($canManage && $showCgrateSync)
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-muted pt-4">
                    <p class="text-sm text-muted">Refresh the latest issuer list from cGrate to validate UAT and production mappings.</p>
                    <form method="POST" action="{{ route('admin.payment-gateway-destination-mappings.sync-cgrate-issuers') }}">
                        @csrf
                        <button type="submit" class="rounded-xl border border-cyan-300 bg-cyan-50 px-4 py-2 text-sm font-semibold text-cyan-900 hover:bg-cyan-100">
                            Sync cGrate Issuers
                        </button>
                    </form>
                </div>
            @endif
        </div>

        <div class="admin-data-table">
            <div class="admin-data-table__scroll">
                <table class="min-w-full w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase text-muted">
                            <th>Gateway</th>
                            <th>Destination Type</th>
                            <th>FineEdge Destination</th>
                            <th>Gateway Field</th>
                            <th>Gateway Value</th>
                            <th>Environment</th>
                            <th>Status</th>
                            <th>Last Verified</th>
                            <th>Loans</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($mappings as $mapping)
                            @php
                                $gateway = $mapping->paymentGateway;
                                $statusBadge = MappingUi::statusBadge($mapping->status);
                                $loanCount = $mapping->destination_type === 'bank'
                                    ? ($loanCountsByBank[$mapping->financial_institution_id] ?? 0)
                                    : 0;
                            @endphp
                            <tr class="align-top hover:bg-slate-50/80">
                                <td class="font-medium text-primary">{{ $gateway?->name ?? '—' }}</td>
                                <td>{{ MappingUi::destinationTypeLabel($mapping->destination_type) }}</td>
                                <td class="text-primary">{{ MappingUi::fineEdgeDestinationLabel($mapping) }}</td>
                                <td>
                                    @if ($gateway)
                                        {{ MappingUi::gatewayFieldLabel($gateway, $mapping->gateway_key) }}
                                    @else
                                        {{ $mapping->gateway_key }}
                                    @endif
                                </td>
                                <td class="font-mono text-primary">{{ $mapping->gateway_value }}</td>
                                <td>{{ MappingUi::environmentLabel($mapping->environment) }}</td>
                                <td>
                                    <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold {{ $statusBadge['class'] }}">
                                        <span aria-hidden="true">{{ $statusBadge['emoji'] }}</span>
                                        {{ $statusBadge['label'] }}
                                    </span>
                                </td>
                                <td class="text-muted">{{ MappingUi::lastVerifiedLabel($mapping->last_verified_at) }}</td>
                                <td class="text-muted">{{ $loanCount > 0 ? number_format($loanCount) : '—' }}</td>
                                <td>
                                    <div class="flex flex-wrap gap-2">
                                        @if ($canManage)
                                            @php
                                                $mappingPayload = [
                                                    'id' => $mapping->id,
                                                    'payment_gateway_id' => $mapping->payment_gateway_id,
                                                    'destination_type' => $mapping->destination_type,
                                                    'financial_institution_id' => $mapping->financial_institution_id,
                                                    'channel_id' => $mapping->channel_id,
                                                    'gateway_key' => $mapping->gateway_key,
                                                    'gateway_value' => $mapping->gateway_value,
                                                    'environment' => $mapping->environment,
                                                    'status' => $mapping->status,
                                                    'notes' => $mapping->notes,
                                                    'gateway_code' => $gateway?->code,
                                                ];
                                            @endphp
                                            <button
                                                type="button"
                                                class="inline-flex items-center rounded-xl border border-muted bg-white px-3 py-1.5 text-sm font-semibold text-primary hover:bg-slate-50"
                                                @click="openEdit({{ \Illuminate\Support\Js::from($mappingPayload) }})"
                                            >
                                                Configure
                                            </button>
                                            <form method="POST" action="{{ route('admin.payment-gateway-destination-mappings.destroy', $mapping) }}" onsubmit="return confirm('Delete this destination mapping?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="inline-flex items-center rounded-xl border border-rose-200 bg-rose-50 px-3 py-1.5 text-sm font-semibold text-rose-700 hover:bg-rose-100">
                                                    Delete
                                                </button>
                                            </form>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="py-8 text-center text-muted">No destination mappings match your filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($mappings->hasPages())
                <div class="admin-table-footer">
                    {{ $mappings->withQueryString()->links() }}
                </div>
            @endif
        </div>

        @if ($showCgrateSync)
            <section class="rounded-3xl border border-muted bg-white p-4 shadow-lg space-y-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-primary">Latest cGrate Issuers</h2>
                        <p class="text-sm text-muted">Values discovered from getAvailableCashDepositIssuers(). Numeric issuer codes such as 543 are preserved.</p>
                    </div>
                    @if (! empty($discoveredIssuers['discovered_at']))
                        <span class="text-xs text-muted">Discovered {{ \Carbon\Carbon::parse($discoveredIssuers['discovered_at'])->diffForHumans() }}</span>
                    @endif
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs font-semibold uppercase text-muted">
                                <th>Issuer Value</th>
                                <th>Type</th>
                                <th>Discovered At</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse (($discoveredIssuers['issuers'] ?? []) as $issuer)
                                <tr>
                                    <td class="font-mono text-primary">{{ $issuer }}</td>
                                    <td>{{ MappingUi::issuerValueType((string) $issuer) }}</td>
                                    <td class="text-muted">
                                        @if (! empty($discoveredIssuers['discovered_at']))
                                            {{ \Carbon\Carbon::parse($discoveredIssuers['discovered_at'])->isToday() ? 'Today' : \Carbon\Carbon::parse($discoveredIssuers['discovered_at'])->diffForHumans() }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>
                                        <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-800">Available</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-6 text-center text-muted">No issuer discovery snapshot yet. Use <strong>Sync cGrate Issuers</strong> to fetch the latest list.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        <section class="rounded-3xl border border-muted bg-white p-4 shadow-lg space-y-4">
            <div>
                <h2 class="text-lg font-semibold text-primary">Bank Mapping Coverage</h2>
                <p class="text-sm text-muted">See which FineEdge banks still need a gateway mapping for {{ $selectedGateway?->name ?? 'the selected gateway' }}.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase text-muted">
                            <th>FineEdge Bank</th>
                            <th>Mapping Exists</th>
                            <th>Gateway Value</th>
                            <th>Status</th>
                            <th>Environment</th>
                            <th>Last Verified</th>
                            <th>Loans Using Bank</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($coverageRows as $row)
                            @php $statusBadge = MappingUi::statusBadge($row['status']); @endphp
                            <tr class="align-top hover:bg-slate-50/80">
                                <td class="font-medium text-primary">{{ $row['bank_name'] }}</td>
                                <td>{{ $row['mapping_exists'] ? 'Yes' : 'No' }}</td>
                                <td class="font-mono">{{ $row['gateway_value'] ?? '—' }}</td>
                                <td>
                                    <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold {{ $statusBadge['class'] }}">
                                        <span aria-hidden="true">{{ $statusBadge['emoji'] }}</span>
                                        {{ $statusBadge['label'] }}
                                    </span>
                                </td>
                                <td>{{ is_string($row['environment']) ? MappingUi::environmentLabel($row['environment'] === '—' ? null : $row['environment']) : strtoupper((string) $row['environment']) }}</td>
                                <td class="text-muted">{{ MappingUi::lastVerifiedLabel($row['last_verified_at']) }}</td>
                                <td class="text-muted">{{ $row['loan_count'] > 0 ? number_format($row['loan_count']) : '—' }}</td>
                                <td>
                                    @if ($canManage && $selectedGateway)
                                        @php
                                            $coveragePayload = $row['mapping_payload']
                                                ? ['mapping' => $row['mapping_payload']]
                                                : [
                                                    'payment_gateway_id' => $selectedGateway->id,
                                                    'destination_type' => 'bank',
                                                    'financial_institution_id' => $row['bank_id'],
                                                    'gateway_code' => $selectedGateway->code,
                                                    'gateway_value' => $row['gateway_value'] ?? '',
                                                ];
                                        @endphp
                                        <button
                                            type="button"
                                            class="inline-flex items-center rounded-xl border border-muted bg-white px-3 py-1.5 text-sm font-semibold text-primary hover:bg-slate-50"
                                            @click="openCoverageConfigure({{ \Illuminate\Support\Js::from($coveragePayload) }})"
                                        >
                                            {{ $row['mapping_exists'] ? 'Configure' : 'Create Mapping' }}
                                        </button>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <div
            x-show="activeModal !== null"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
        >
            <div class="absolute inset-0 bg-slate-900/50" @click="closeModal()"></div>

            <div class="relative w-full max-w-2xl rounded-3xl border border-muted bg-white p-6 shadow-2xl max-h-[90vh] overflow-y-auto">
                <div class="flex items-start justify-between gap-4 mb-6">
                    <div>
                        <h2 class="text-xl font-semibold text-primary" x-text="editing ? 'Configure Destination Mapping' : 'New Destination Mapping'"></h2>
                        <p class="mt-1 text-sm text-muted">Map a FineEdge destination to the identifier required by the payment gateway.</p>
                    </div>
                    <button type="button" class="text-muted hover:text-primary" @click="closeModal()">✕</button>
                </div>

                <form
                    :method="editing ? 'POST' : 'POST'"
                    :action="editing ? `{{ url('/admin/payment-gateway-destination-mappings') }}/${editing.id}` : '{{ route('admin.payment-gateway-destination-mappings.store') }}'"
                    class="space-y-5"
                >
                    @csrf
                    <template x-if="editing">
                        <input type="hidden" name="_method" value="PUT">
                    </template>

                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-primary" for="modal-gateway">Gateway</label>
                        <select
                            id="modal-gateway"
                            name="payment_gateway_id"
                            class="w-full rounded-xl border border-muted bg-white px-4 py-3 text-primary"
                            x-model="form.payment_gateway_id"
                            @change="onGatewayChange()"
                            :disabled="editing !== null"
                            required
                        >
                            <option value="">Select gateway</option>
                            @foreach ($gateways as $gateway)
                                <option value="{{ $gateway->id }}">{{ $gateway->name }} ({{ $gateway->code }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-primary" for="modal-destination-type">Destination Type</label>
                            <select id="modal-destination-type" name="destination_type" class="w-full rounded-xl border border-muted bg-white px-4 py-3 text-primary" x-model="form.destination_type" required>
                                <option value="bank">Bank</option>
                                <option value="mobile_money">Mobile Money</option>
                            </select>
                        </div>
                        <div class="space-y-2" x-show="form.destination_type === 'bank'">
                            <label class="block text-sm font-medium text-primary" for="modal-bank">FineEdge Bank</label>
                            <select id="modal-bank" name="financial_institution_id" class="w-full rounded-xl border border-muted bg-white px-4 py-3 text-primary" x-model="form.financial_institution_id">
                                <option value="">Select bank</option>
                                @foreach ($financialInstitutions as $fi)
                                    <option value="{{ $fi->id }}">{{ $fi->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="space-y-2" x-show="form.destination_type === 'mobile_money'">
                            <label class="block text-sm font-medium text-primary" for="modal-channel">Mobile Money Channel</label>
                            <select id="modal-channel" name="channel_id" class="w-full rounded-xl border border-muted bg-white px-4 py-3 text-primary" x-model="form.channel_id">
                                <option value="">Select channel</option>
                                @foreach ($channels as $channel)
                                    <option value="{{ $channel->id }}">{{ $channel->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-primary" for="modal-gateway-key">
                                <span x-text="gatewayFieldLabel()"></span>
                            </label>
                            <input id="modal-gateway-key" name="gateway_key" type="text" class="w-full rounded-xl border border-muted bg-white px-4 py-3 text-primary font-mono" x-model="form.gateway_key" required>
                            <p class="text-xs text-muted" x-show="gatewayFieldHelp()" x-text="gatewayFieldHelp()"></p>
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-primary" for="modal-gateway-value">Gateway Value</label>
                            <input id="modal-gateway-value" name="gateway_value" type="text" class="w-full rounded-xl border border-muted bg-white px-4 py-3 text-primary font-mono" x-model="form.gateway_value" required>
                            <p class="text-xs text-muted" x-show="gatewayValueHelp()" x-text="gatewayValueHelp()"></p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-primary" for="modal-environment">Environment</label>
                            <select id="modal-environment" name="environment" class="w-full rounded-xl border border-muted bg-white px-4 py-3 text-primary" x-model="form.environment">
                                <option value="">Global (any environment)</option>
                                <option value="local">Local</option>
                                <option value="uat">UAT</option>
                                <option value="production">Production</option>
                            </select>
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-primary" for="modal-status">Status</label>
                            <select id="modal-status" name="status" class="w-full rounded-xl border border-muted bg-white px-4 py-3 text-primary" x-model="form.status" required>
                                <option value="active">Active</option>
                                <option value="verification_required">Verification Required</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-primary" for="modal-notes">Notes</label>
                        <textarea id="modal-notes" name="notes" rows="3" class="w-full rounded-xl border border-muted bg-white px-4 py-3 text-primary" x-model="form.notes" placeholder="Optional operations notes"></textarea>
                    </div>

                    <div class="flex flex-wrap justify-end gap-3 pt-2">
                        <button type="button" class="rounded-xl border border-muted px-4 py-2.5 text-sm font-semibold text-primary hover:bg-slate-50" @click="closeModal()">
                            Cancel
                        </button>
                        <button type="submit" class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:opacity-90">
                            Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            function destinationMappingsPage(config) {
                return {
                    activeModal: config.activeModal,
                    editing: config.editing,
                    form: config.form,
                    gateways: config.gateways,
                    selectedGateway() {
                        return this.gateways.find((gateway) => String(gateway.id) === String(this.form.payment_gateway_id)) ?? null;
                    },
                    gatewayFieldLabel() {
                        const gateway = this.selectedGateway();
                        if (gateway?.code === 'cgrate' && this.form.gateway_key === 'issuerName') {
                            return 'cGrate issuerName';
                        }
                        return 'Gateway Field';
                    },
                    gatewayFieldHelp() {
                        const gateway = this.selectedGateway();
                        if (gateway?.code === 'cgrate' && this.form.gateway_key === 'issuerName') {
                            return 'This is the issuerName value that will be sent to processCashDeposit().';
                        }
                        return '';
                    },
                    gatewayValueHelp() {
                        const gateway = this.selectedGateway();
                        if (gateway?.code === 'cgrate') {
                            return 'In UAT this may be 543. In production this should match the value supplied by cGrate.';
                        }
                        return '';
                    },
                    onGatewayChange() {
                        const gateway = this.selectedGateway();
                        if (gateway) {
                            this.form.gateway_key = gateway.default_key;
                        }
                    },
                    openCreate() {
                        this.editing = null;
                        this.form = {
                            payment_gateway_id: config.form.payment_gateway_id ?? '',
                            destination_type: 'bank',
                            financial_institution_id: '',
                            channel_id: '',
                            gateway_key: this.selectedGateway()?.default_key ?? 'issuerName',
                            gateway_value: '',
                            environment: '',
                            status: 'active',
                            notes: '',
                        };
                        this.activeModal = 'create';
                    },
                    openEdit(mapping) {
                        this.editing = mapping;
                        this.form = {
                            payment_gateway_id: mapping.payment_gateway_id,
                            destination_type: mapping.destination_type,
                            financial_institution_id: mapping.financial_institution_id ?? '',
                            channel_id: mapping.channel_id ?? '',
                            gateway_key: mapping.gateway_key,
                            gateway_value: mapping.gateway_value,
                            environment: mapping.environment ?? '',
                            status: mapping.status,
                            notes: mapping.notes ?? '',
                        };
                        this.activeModal = 'edit';
                    },
                    openCoverageConfigure(payload) {
                        if (payload.mapping) {
                            this.openEdit(payload.mapping);
                            return;
                        }

                        this.editing = null;
                        this.form = {
                            payment_gateway_id: payload.payment_gateway_id,
                            destination_type: 'bank',
                            financial_institution_id: payload.financial_institution_id,
                            channel_id: '',
                            gateway_key: payload.gateway_code === 'cgrate' ? 'issuerName' : 'identifier',
                            gateway_value: payload.gateway_value ?? '',
                            environment: '',
                            status: 'active',
                            notes: '',
                        };
                        this.activeModal = 'create';
                    },
                    closeModal() {
                        this.activeModal = null;
                        this.editing = null;
                    },
                };
            }
        </script>
    @endpush
@endsection
