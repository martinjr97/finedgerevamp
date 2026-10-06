@extends('layouts.admin')

@section('title', 'Product Gateway Rules | '.config('app.system_name'))

@section('content')
    @php
        $canManage = $canManage ?? false;
    @endphp

    <div class="space-y-8" x-data="{ activeModal: null }">
        @include('partials.admin.page-header', [
            'title' => 'Product Gateway Rules',
            'description' => 'Override the global gateway routing per loan product. When configured, product rules take precedence over global routes.',
            'buttons' => array_filter([
                auth('admin')->user()?->can('payment-gateways.view') || $canManage
                    ? [
                        'action' => 'secondary',
                        'text' => '← Gateway Routing',
                        'href' => route('admin.payment-gateway-routing.index'),
                    ]
                    : null,
            ]),
        ])

        @if(session('status'))
            <div class="rounded-2xl border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        @unless ($canManage)
            <div class="rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                You have view-only access. An administrator with <strong>payment-gateways.manage</strong> permission is required to configure product rules.
            </div>
        @endunless

        <div class="rounded-3xl border border-muted bg-white p-4 shadow-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full w-full text-sm">
                    <thead>
                        <tr class="border-b border-muted text-left text-xs font-semibold uppercase tracking-[0.16em] text-muted">
                            <th class="px-4 py-3">Loan Product</th>
                            <th class="px-4 py-3">Rule</th>
                            <th class="px-4 py-3">Gateway</th>
                            <th class="px-4 py-3">Enabled</th>
                            <th class="px-4 py-3">Automatic</th>
                            <th class="px-4 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($products as $product)
                            @foreach ($product->paymentGatewayProductRules->sortBy('id') as $rule)
                                <tr class="border-t border-muted align-top hover:bg-slate-50/80">
                                    <td class="px-4 py-4 font-medium text-primary">
                                        {{ $product->name }}
                                        <div class="text-xs text-muted">{{ strtoupper($product->code) }}</div>
                                    </td>
                                    <td class="px-4 py-4 text-primary">{{ $rule->displayLabel() }}</td>
                                    <td class="px-4 py-4 text-primary">
                                        {{ $rule->paymentGateway?->name ?? '—' }}
                                    </td>
                                    <td class="px-4 py-4">
                                        <span class="{{ $rule->enabled ? 'text-emerald-700 font-semibold' : 'text-rose-700' }}">
                                            {{ $rule->enabled ? 'Yes' : 'No' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4">
                                        <span class="{{ $rule->auto_process ? 'text-emerald-700 font-semibold' : 'text-slate-500' }}">
                                            {{ $rule->auto_process ? 'Yes' : 'No' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4">
                                        @if ($canManage)
                                            <button
                                                type="button"
                                                class="inline-flex items-center rounded-xl border border-muted bg-white px-3 py-1.5 text-sm font-semibold text-primary hover:bg-slate-50"
                                                @click="activeModal = {{ $rule->id }}"
                                            >
                                                Configure
                                            </button>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-muted">No loan products found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @foreach ($products as $product)
            @foreach ($product->paymentGatewayProductRules as $rule)
                @php
                    $routeKey = $rule->routeKey();
                    $eligibleGateways = $routeKey ? ($gatewaysByRoute[$routeKey->value] ?? collect()) : collect();
                    $modalPrefix = 'product-rule-'.$rule->id;
                @endphp

                <div
                    x-show="activeModal === {{ $rule->id }}"
                    x-cloak
                    class="fixed inset-0 z-50 flex items-center justify-center p-4"
                    role="dialog"
                    aria-modal="true"
                >
                    <div class="absolute inset-0 bg-slate-900/50" @click="activeModal = null"></div>

                    <div class="relative w-full max-w-xl rounded-3xl border border-muted bg-white p-6 shadow-2xl">
                        <div class="flex items-start justify-between gap-4 mb-6">
                            <div>
                                <h2 class="text-xl font-semibold text-primary">{{ $product->name }}</h2>
                                <p class="mt-1 text-sm text-muted">{{ $rule->displayLabel() }}</p>
                            </div>
                            <button type="button" class="text-muted hover:text-primary" @click="activeModal = null">✕</button>
                        </div>

                        <form
                            method="POST"
                            action="{{ route('admin.payment-gateway-product-rules.update', $rule) }}"
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
                                    <option value="" @selected(old('payment_gateway_id', $rule->payment_gateway_id) === null)>— None —</option>
                                    @foreach ($eligibleGateways as $gateway)
                                        <option
                                            value="{{ $gateway->id }}"
                                            @selected((int) old('payment_gateway_id', $rule->payment_gateway_id) === $gateway->id)
                                        >
                                            {{ $gateway->name }} ({{ $gateway->code }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            @include('partials.admin.form-toggle-field', [
                                'name' => 'enabled',
                                'label' => 'Enabled',
                                'checked' => old('enabled', $rule->enabled),
                                'id' => $modalPrefix.'-enabled',
                            ])

                            @include('partials.admin.form-toggle-field', [
                                'name' => 'auto_process',
                                'label' => 'Automatic Processing',
                                'checked' => old('auto_process', $rule->auto_process),
                                'id' => $modalPrefix.'-auto-process',
                            ])

                            <div class="space-y-2">
                                <label for="{{ $modalPrefix }}-notes" class="block text-sm font-medium text-primary">Notes</label>
                                <textarea
                                    id="{{ $modalPrefix }}-notes"
                                    name="notes"
                                    rows="3"
                                    class="w-full rounded-xl border border-muted bg-white px-4 py-3 text-primary"
                                >{{ old('notes', $rule->notes) }}</textarea>
                            </div>

                            <div class="flex justify-end gap-3 pt-2">
                                <button type="button" class="rounded-xl border border-muted px-4 py-2 text-sm font-semibold text-primary" @click="activeModal = null">
                                    Cancel
                                </button>
                                <button type="submit" class="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white">
                                    Save Rule
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endforeach
        @endforeach
    </div>
@endsection
