@extends('layouts.admin')

@section('title', $gateway->name.' | Payment Gateways')

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => $gateway->name,
            'description' => 'Payment provider configuration, operational status, and recent activity.',
            'buttons' => array_filter([
                auth('admin')->user()?->can('payment-gateways.view') || auth('admin')->user()?->can('payment-gateways.manage')
                    ? [
                        'action' => 'secondary',
                        'text' => 'Gateway Routing →',
                        'href' => route('admin.payment-gateway-routing.index'),
                    ]
                    : null,
                auth('admin')->user()?->can('payment-gateways.manage')
                    ? [
                        'action' => 'edit',
                        'text' => 'Edit Gateway',
                        'href' => route('admin.payment-gateways.edit', $gateway),
                    ]
                    : null,
            ]),
        ])

        @if(session('status'))
            <div class="rounded-2xl border border-emerald-400/60 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">
                {{ session('status') }}
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
                <h2 class="text-xl font-semibold text-white">Provider Information</h2>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <p class="text-sm text-slate-400">Name</p>
                        <p class="text-white font-medium">{{ $gateway->name }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-400">Code</p>
                        <p class="text-cyan-300 font-mono">{{ $gateway->code }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-400">Provider</p>
                        <p class="text-white font-medium">{{ \App\Support\PaymentGatewayAdminUi::providerDisplayName($gateway) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-400">Status</p>
                        <p class="mt-1">@include('partials.admin.payment-gateway-status-badge', ['status' => $gateway->status])</p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-400">Priority</p>
                        <p class="text-white font-medium">{{ $gateway->priority }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-400">Default</p>
                        <p class="text-white font-medium">{{ $gateway->is_default ? 'Yes' : 'No' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-400">Type</p>
                        <p class="text-white font-medium">{{ \App\Support\PaymentGatewayAdminUi::typeLabel($gateway->type) }}</p>
                    </div>
                </div>
            </div>

            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
                <h2 class="text-xl font-semibold text-white">Capabilities</h2>
                <div class="flex flex-wrap gap-2">
                    @forelse ($capabilityBadges as $badge)
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-400/30 bg-emerald-500/10 px-3 py-1.5 text-sm font-medium text-emerald-200">
                            <span aria-hidden="true">✓</span>
                            {{ $badge['label'] }}
                        </span>
                    @empty
                        <span class="text-slate-400">No capabilities configured.</span>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
                <div class="flex items-start justify-between gap-4">
                    <h2 class="text-xl font-semibold text-white">Linked Financial Account</h2>
                    @if ($financialAccountUrl)
                        <a href="{{ $financialAccountUrl }}" class="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-blue-500/40 to-purple-500/40 border border-blue-400/70 px-3 py-1.5 text-sm font-semibold text-blue-200 hover:text-white transition">
                            Open Account
                        </a>
                    @endif
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <p class="text-sm text-slate-400">Account Type</p>
                        <p class="text-white font-medium">{{ $financialAccountTypeLabel ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-400">Account Name</p>
                        <p class="text-white font-medium">{{ $linkedAccount?->name ?? 'Not linked' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-400">Current Balance</p>
                        <p class="text-white font-semibold text-lg">
                            @if ($linkedBalance !== null)
                                ZMW {{ number_format($linkedBalance, 2) }}
                            @else
                                —
                            @endif
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-400">Opening Balance</p>
                        <p class="text-white font-medium">
                            @if ($linkedAccount)
                                ZMW {{ number_format($linkedAccount->opening_balance ?? 0, 2) }}
                            @else
                                —
                            @endif
                        </p>
                    </div>
                </div>
            </div>

            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
                <h2 class="text-xl font-semibold text-white">Operational Status</h2>
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ($operationalChecks as $check)
                        @php
                            $toneClass = match ($check['tone']) {
                                'success' => 'border-emerald-400/30 bg-emerald-500/10 text-emerald-200',
                                'warning' => 'border-amber-400/30 bg-amber-500/10 text-amber-200',
                                default => 'border-rose-400/30 bg-rose-500/10 text-rose-200',
                            };
                        @endphp
                        <div class="rounded-2xl border px-4 py-3 {{ $toneClass }}">
                            <p class="text-xs uppercase tracking-[0.16em] opacity-80">{{ $check['label'] }}</p>
                            <p class="mt-1 text-sm font-semibold">{{ $check['value'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-6">
            <h2 class="text-xl font-semibold text-white">Recent Activity</h2>

            <div class="grid gap-6 xl:grid-cols-2">
                <div class="space-y-3">
                    <h3 class="text-sm font-semibold uppercase tracking-[0.16em] text-slate-400">Recent Attempts</h3>
                    <div class="overflow-x-auto rounded-2xl border border-white/10">
                        <table class="min-w-full text-sm text-slate-300">
                            <thead>
                                <tr class="border-b border-white/10 text-left text-white/70">
                                    <th class="px-3 py-2">Date</th>
                                    <th class="px-3 py-2">Direction</th>
                                    <th class="px-3 py-2">Amount</th>
                                    <th class="px-3 py-2">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($recentAttempts as $attempt)
                                    <tr class="border-t border-white/10">
                                        <td class="px-3 py-2 whitespace-nowrap">{{ $attempt->created_at?->format('Y-m-d H:i') }}</td>
                                        <td class="px-3 py-2 capitalize">{{ $attempt->direction->value }}</td>
                                        <td class="px-3 py-2">{{ number_format($attempt->amount, 2) }}</td>
                                        <td class="px-3 py-2 capitalize">{{ $attempt->status->value }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="px-3 py-4 text-center text-slate-500">No attempts yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="space-y-3">
                    <h3 class="text-sm font-semibold uppercase tracking-[0.16em] text-slate-400">Recent Successes</h3>
                    <div class="overflow-x-auto rounded-2xl border border-white/10">
                        <table class="min-w-full text-sm text-slate-300">
                            <thead>
                                <tr class="border-b border-white/10 text-left text-white/70">
                                    <th class="px-3 py-2">Date</th>
                                    <th class="px-3 py-2">Reference</th>
                                    <th class="px-3 py-2">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($recentSuccesses as $attempt)
                                    <tr class="border-t border-white/10">
                                        <td class="px-3 py-2 whitespace-nowrap">{{ $attempt->confirmed_at?->format('Y-m-d H:i') ?? $attempt->created_at?->format('Y-m-d H:i') }}</td>
                                        <td class="px-3 py-2 font-mono text-xs">{{ $attempt->internal_reference }}</td>
                                        <td class="px-3 py-2">{{ number_format($attempt->amount, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="px-3 py-4 text-center text-slate-500">No successful attempts yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="space-y-3">
                    <h3 class="text-sm font-semibold uppercase tracking-[0.16em] text-slate-400">Recent Failures</h3>
                    <div class="overflow-x-auto rounded-2xl border border-white/10">
                        <table class="min-w-full text-sm text-slate-300">
                            <thead>
                                <tr class="border-b border-white/10 text-left text-white/70">
                                    <th class="px-3 py-2">Date</th>
                                    <th class="px-3 py-2">Reference</th>
                                    <th class="px-3 py-2">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($recentFailures as $attempt)
                                    <tr class="border-t border-white/10">
                                        <td class="px-3 py-2 whitespace-nowrap">{{ $attempt->created_at?->format('Y-m-d H:i') }}</td>
                                        <td class="px-3 py-2 font-mono text-xs">{{ $attempt->internal_reference }}</td>
                                        <td class="px-3 py-2 capitalize">{{ $attempt->status->value }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="px-3 py-4 text-center text-slate-500">No failed attempts.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="space-y-3">
                    <h3 class="text-sm font-semibold uppercase tracking-[0.16em] text-slate-400">Recent Logs</h3>
                    <div class="overflow-x-auto rounded-2xl border border-white/10">
                        <table class="min-w-full text-sm text-slate-300">
                            <thead>
                                <tr class="border-b border-white/10 text-left text-white/70">
                                    <th class="px-3 py-2">Date</th>
                                    <th class="px-3 py-2">Event</th>
                                    <th class="px-3 py-2">Level</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($recentLogs as $log)
                                    <tr class="border-t border-white/10">
                                        <td class="px-3 py-2 whitespace-nowrap">{{ $log->created_at?->format('Y-m-d H:i') }}</td>
                                        <td class="px-3 py-2">{{ $log->event }}</td>
                                        <td class="px-3 py-2 capitalize">{{ $log->level }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="px-3 py-4 text-center text-slate-500">No logs yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
