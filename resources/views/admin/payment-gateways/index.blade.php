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

        <div class="rounded-3xl border border-white/10 bg-white/5 p-4 shadow-lg">
            <div class="overflow-x-auto">
                <table data-datatable="true" data-datatable-per-page="10" class="min-w-full w-full text-sm text-slate-300">
                    <thead>
                        <tr class="text-xs font-semibold uppercase tracking-[0.2em] text-white/80 text-center border-b-2 border-white/20">
                            <th class="px-3 py-4 border-r border-white/10">Gateway Name</th>
                            <th class="px-3 py-4 border-r border-white/10">Code</th>
                            <th class="px-3 py-4 border-r border-white/10">Status</th>
                            <th class="px-3 py-4 border-r border-white/10">Type</th>
                            <th class="px-3 py-4 border-r border-white/10">Collections</th>
                            <th class="px-3 py-4 border-r border-white/10">Disbursements</th>
                            <th class="px-3 py-4 border-r border-white/10">Linked Financial Account</th>
                            <th class="px-3 py-4 border-r border-white/10">Current Balance</th>
                            <th class="px-3 py-4 border-r border-white/10">Health</th>
                            <th class="px-3 py-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($gateways as $gateway)
                            @php
                                $health = \App\Support\PaymentGatewayRoutingAdminUi::gatewayHealth($gateway);
                            @endphp
                            <tr class="border-t border-white/40 text-center hover:bg-white/5 transition">
                                <td class="px-3 py-4 font-medium text-white border-r border-white/5">{{ $gateway->name }}</td>
                                <td class="px-3 py-4 border-r border-white/5">
                                    <span class="font-mono text-cyan-300">{{ $gateway->code }}</span>
                                </td>
                                <td class="px-3 py-4 border-r border-white/5">
                                    @include('partials.admin.payment-gateway-status-badge', ['status' => $gateway->status])
                                </td>
                                <td class="px-3 py-4 border-r border-white/5">
                                    {{ \App\Support\PaymentGatewayAdminUi::typeLabel($gateway->type) }}
                                </td>
                                <td class="px-3 py-4 border-r border-white/5">
                                    <span class="text-sm font-medium {{ $gateway->supports_collections ? 'text-emerald-400' : 'text-slate-500' }}">
                                        {{ $gateway->supports_collections ? 'Enabled' : 'Disabled' }}
                                    </span>
                                </td>
                                <td class="px-3 py-4 border-r border-white/5">
                                    <span class="text-sm font-medium {{ $gateway->supports_disbursements ? 'text-emerald-400' : 'text-slate-500' }}">
                                        {{ $gateway->supports_disbursements ? 'Enabled' : 'Disabled' }}
                                    </span>
                                </td>
                                <td class="px-3 py-4 border-r border-white/5 text-left">
                                    {{ $gateway->linkedAccountLabel() ?? '—' }}
                                </td>
                                <td class="px-3 py-4 border-r border-white/5 font-semibold">
                                    @if ($gateway->linkedAccountBalance() !== null)
                                        ZMW {{ number_format($gateway->linkedAccountBalance(), 2) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-3 py-4 border-r border-white/5">
                                    <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold {{ $health['class'] }}">
                                        <span aria-hidden="true">{{ $health['emoji'] }}</span>
                                        {{ $health['label'] }}
                                    </span>
                                </td>
                                <td class="px-3 py-4">
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
                                <td colspan="10" class="px-4 py-8 text-center text-slate-400">No payment gateways configured.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
