@extends('layouts.admin')

@section('title', 'Payment Gateways | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Payment Gateways',
            'description' => 'Manage payment provider integrations, credentials, and linked financial accounts.',
            'buttons' => array_filter([
                auth('admin')->user()?->can('payment-gateways.view') || auth('admin')->user()?->can('payment-gateways.manage')
                    ? [
                        'action' => 'secondary',
                        'text' => 'Destination Mappings →',
                        'href' => route('admin.payment-gateway-destination-mappings.index'),
                    ]
                    : null,
                auth('admin')->user()?->can('payment-gateways.view') || auth('admin')->user()?->can('payment-gateways.manage')
                    ? [
                        'action' => 'secondary',
                        'text' => 'Gateway Routing →',
                        'href' => route('admin.payment-gateway-routing.index'),
                    ]
                    : null,
            ]),
        ])

        @if(session('status'))
            <div class="rounded-2xl border border-emerald-400/60 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">
                {{ session('status') }}
            </div>
        @endif

        <div class="admin-data-table">
            <table
                data-datatable="true"
                data-datatable-per-page="10"
                data-datatable-search-placeholder="Search gateways…"
                class="min-w-full w-full"
            >
                    <thead>
                        <tr class="text-xs font-semibold uppercase text-white/80 text-center">
                            <th scope="col" class="px-3">Gateway Name</th>
                            <th scope="col" class="px-3">Code</th>
                            <th scope="col" class="px-3">Status</th>
                            <th scope="col" class="px-3">Type</th>
                            <th scope="col" class="px-3">Collections</th>
                            <th scope="col" class="px-3">Disbursements</th>
                            <th scope="col" class="px-3">Linked Financial Account</th>
                            <th scope="col" class="px-3">Current Balance</th>
                            <th scope="col" class="px-3">Health</th>
                            <th data-sortable="false" scope="col" class="admin-data-table__actions px-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($gateways as $gateway)
                            @php
                                $health = \App\Support\PaymentGatewayRoutingAdminUi::gatewayHealth($gateway);
                            @endphp
                            <tr class="text-center">
                                <td class="px-3 font-medium text-white">{{ $gateway->name }}</td>
                                <td class="px-3">
                                    <span class="font-mono text-cyan-300">{{ $gateway->code }}</span>
                                </td>
                                <td class="px-3">
                                    @include('partials.admin.payment-gateway-status-badge', ['status' => $gateway->status])
                                </td>
                                <td class="px-3">
                                    {{ \App\Support\PaymentGatewayAdminUi::typeLabel($gateway->type) }}
                                </td>
                                <td class="px-3">
                                    <span class="text-sm font-medium {{ $gateway->supports_collections ? 'text-emerald-400' : 'text-slate-500' }}">
                                        {{ $gateway->supports_collections ? 'Enabled' : 'Disabled' }}
                                    </span>
                                </td>
                                <td class="px-3">
                                    <span class="text-sm font-medium {{ $gateway->supports_disbursements ? 'text-emerald-400' : 'text-slate-500' }}">
                                        {{ $gateway->supports_disbursements ? 'Enabled' : 'Disabled' }}
                                    </span>
                                </td>
                                <td class="px-3 text-left">
                                    {{ $gateway->linkedAccountLabel() ?? '—' }}
                                </td>
                                <td class="px-3 font-semibold">
                                    @if ($gateway->linkedAccountBalance() !== null)
                                        ZMW {{ number_format($gateway->linkedAccountBalance(), 2) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-3">
                                    <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold {{ $health['class'] }}">
                                        <span aria-hidden="true">{{ $health['emoji'] }}</span>
                                        {{ $health['label'] }}
                                    </span>
                                </td>
                                <td class="px-3">
                                    <div class="inline-flex flex-wrap items-center justify-center gap-2">
                                        @if(auth('admin')->user()?->can('payment-gateways.view') || auth('admin')->user()?->can('payment-gateways.manage'))
                                            <a href="{{ route('admin.payment-gateways.show', $gateway) }}" class="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-blue-500/40 to-purple-500/40 border-2 border-blue-400/70 px-3 py-1.5 text-sm font-semibold text-blue-200 hover:text-white transition">
                                                View
                                            </a>
                                        @endif
                                        @can('payment-gateways.manage')
                                            <a href="{{ route('admin.payment-gateways.edit', $gateway) }}" class="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-purple-500/40 to-indigo-500/40 border-2 border-purple-400/70 px-3 py-1.5 text-sm font-semibold text-purple-200 hover:text-white transition">
                                                Edit
                                            </a>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="py-8 text-center text-slate-400">No payment gateways configured.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
        </div>
    </div>
@endsection
