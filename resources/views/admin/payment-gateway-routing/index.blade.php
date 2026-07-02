@extends('layouts.admin')

@section('title', 'Gateway Routing | '.config('app.system_name'))

@section('content')
    @php
        $canManage = $canManage ?? false;
    @endphp

    <div class="space-y-8" x-data="{ activeModal: null }">
        @include('partials.admin.page-header', [
            'title' => 'Gateway Routing',
            'description' => 'Configure which payment gateway handles each business payment flow. Provider credentials are managed under Payment Gateways.',
            'buttons' => array_filter([
                auth('admin')->user()?->can('payment-gateways.view') || $canManage
                    ? [
                        'action' => 'secondary',
                        'text' => '← Payment Gateways',
                        'href' => route('admin.payment-gateways.index'),
                    ]
                    : null,
            ]),
        ])

        @if(session('status'))
            <div class="rounded-2xl border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-2xl border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-800 space-y-1">
                <p class="font-semibold">Could not save routing configuration:</p>
                <ul class="list-disc pl-5 space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @unless ($canManage)
            <div class="rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                You have view-only access. An administrator with <strong>payment-gateways.manage</strong> permission is required to configure routes.
            </div>
        @endunless

        <div class="rounded-3xl border border-muted bg-white p-4 shadow-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full w-full text-sm">
                    <thead>
                        <tr class="border-b border-muted text-left text-xs font-semibold uppercase tracking-[0.16em] text-muted">
                            <th class="px-4 py-3">Use Case</th>
                            <th class="px-4 py-3">Current Gateway</th>
                            <th class="px-4 py-3">Enabled</th>
                            <th class="px-4 py-3">Automatic Processing</th>
                            <th class="px-4 py-3">Fallback to Manual</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Linked Financial Account</th>
                            <th class="px-4 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tableRows as $row)
                            @php
                                /** @var \App\Models\PaymentGatewayRoute $route */
                                $route = $row['route'];
                                $status = $row['status'];
                            @endphp
                            <tr class="border-t border-muted align-top hover:bg-slate-50/80">
                                <td class="px-4 py-4 font-medium text-primary">
                                    {{ $row['routeKey']->displayLabel() }}
                                </td>
                                <td class="px-4 py-4 text-primary">
                                    {{ \App\Support\PaymentGatewayRoutingAdminUi::gatewayLabel($route) }}
                                </td>
                                <td class="px-4 py-4">
                                    <span class="{{ $route->enabled ? 'text-emerald-700 font-semibold' : 'text-rose-700' }}">
                                        {{ \App\Support\PaymentGatewayRoutingAdminUi::yesNo($route->enabled) }}
                                    </span>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="{{ $route->auto_process ? 'text-emerald-700 font-semibold' : 'text-slate-500' }}">
                                        {{ \App\Support\PaymentGatewayRoutingAdminUi::yesNo($route->auto_process) }}
                                    </span>
                                </td>
                                <td class="px-4 py-4">
                                    {{ \App\Support\PaymentGatewayRoutingAdminUi::yesNo($route->fallback_to_manual) }}
                                </td>
                                <td class="px-4 py-4">
                                    <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold {{ $status['class'] }}">
                                        <span aria-hidden="true">{{ $status['emoji'] }}</span>
                                        {{ $status['label'] }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-primary">
                                    {{ \App\Support\PaymentGatewayRoutingAdminUi::linkedAccountLabel($route) }}
                                </td>
                                <td class="px-4 py-4">
                                    @if ($canManage)
                                        <button
                                            type="button"
                                            class="inline-flex items-center rounded-xl border border-muted bg-white px-3 py-1.5 text-sm font-semibold text-primary hover:bg-slate-50"
                                            @click="activeModal = {{ $route->id }}"
                                        >
                                            Configure
                                        </button>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-muted">No routing use cases found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @foreach ($tableRows as $row)
            @php
                $route = $row['route'];
                $routeKey = $row['routeKey'];
                $eligibleGateways = $row['eligibleGateways'];
                $modalPrefix = 'route-'.$route->id;
            @endphp

            <div
                x-show="activeModal === {{ $route->id }}"
                x-cloak
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
                role="dialog"
                aria-modal="true"
            >
                <div class="absolute inset-0 bg-slate-900/50" @click="activeModal = null"></div>

                <div class="relative w-full max-w-xl rounded-3xl border border-muted bg-white p-6 shadow-2xl">
                    <div class="flex items-start justify-between gap-4 mb-6">
                        <div>
                            <h2 class="text-xl font-semibold text-primary">Configure {{ $routeKey->displayLabel() }}</h2>
                            <p class="mt-1 text-sm text-muted">{{ $routeKey->helpText() }}</p>
                        </div>
                        <button type="button" class="text-muted hover:text-primary" @click="activeModal = null">✕</button>
                    </div>

                    <form
                        method="POST"
                        action="{{ route('admin.payment-gateway-routing.update', $route) }}"
                        class="space-y-5"
                    >
                        @csrf
                        @method('PUT')

                        <div class="space-y-2">
                            <label for="{{ $modalPrefix }}-gateway" class="block text-sm font-medium text-primary">Gateway</label>
                            <select
                                id="{{ $modalPrefix }}-gateway"
                                name="payment_gateway_id"
                                class="w-full rounded-xl border border-muted bg-white px-4 py-3 text-primary"
                            >
                                <option value="" @selected(old('payment_gateway_id', $route->payment_gateway_id) === null)>— None —</option>
                                @foreach ($eligibleGateways as $gateway)
                                    <option
                                        value="{{ $gateway->id }}"
                                        @selected((int) old('payment_gateway_id', $route->payment_gateway_id) === $gateway->id)
                                    >
                                        {{ $gateway->name }} ({{ $gateway->code }})
                                    </option>
                                @endforeach
                            </select>
                            @error('payment_gateway_id')
                                <p class="form-error-text text-sm">{{ $message }}</p>
                            @enderror
                        </div>

                        @include('partials.admin.form-toggle-field', [
                            'inputId' => $modalPrefix.'-enabled',
                            'name' => 'enabled',
                            'label' => 'Enabled',
                            'checked' => old('enabled', $route->enabled),
                        ])

                        @include('partials.admin.form-toggle-field', [
                            'inputId' => $modalPrefix.'-auto-process',
                            'name' => 'auto_process',
                            'label' => 'Automatic Processing',
                            'checked' => old('auto_process', $route->auto_process),
                        ])

                        @include('partials.admin.form-toggle-field', [
                            'inputId' => $modalPrefix.'-fallback',
                            'name' => 'fallback_to_manual',
                            'label' => 'Fallback to Manual',
                            'checked' => old('fallback_to_manual', $route->fallback_to_manual),
                        ])

                        <div class="space-y-2">
                            <label for="{{ $modalPrefix }}-notes" class="block text-sm font-medium text-primary">Notes</label>
                            <textarea
                                id="{{ $modalPrefix }}-notes"
                                name="notes"
                                rows="3"
                                class="w-full rounded-xl border border-muted bg-white px-4 py-3 text-primary"
                                placeholder="Optional operations notes"
                            >{{ old('notes', $route->notes) }}</textarea>
                        </div>

                        <div class="flex flex-wrap justify-end gap-3 pt-2">
                            <button type="button" class="rounded-xl border border-muted px-4 py-2.5 text-sm font-semibold text-primary hover:bg-slate-50" @click="activeModal = null">
                                Cancel
                            </button>
                            <button type="submit" class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:opacity-90">
                                Save Route
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
@endsection
