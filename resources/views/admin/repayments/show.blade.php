@extends('layouts.admin')

@section('title', 'View Repayment | '.config('app.system_name'))

@section('content')
    @php
        $canApproveRepayment = auth('admin')->user()?->can('repayments.approve') || auth('admin')->user()?->can('repayments.process');
        $canRejectRepayment = auth('admin')->user()?->can('repayments.reject') || auth('admin')->user()?->can('repayments.process');
        $canProcessRepayment = auth('admin')->user()?->can('repayments.process');
        $canRecheckGateway = auth('admin')->user()?->can('repayments.view') || auth('admin')->user()?->can('repayments.process');
        $canApplyGatewaySync = auth('admin')->user()?->can('repayments.process');
        $canReconcile = $canProcessRepayment || $canApproveRepayment;
        $isPendingRepayment = $repayment->status === 'pending';
        $gatewayAttempt = $gatewayShowState->gatewayAttempt;
        $openApproveModal = $isPendingRepayment && $canApproveRepayment && $errors->hasAny([
            'channel_id',
            'manual_source',
            'bank_id',
            'wallet_id',
            'external_reference',
            'external_transaction_id',
            'notes',
        ]);
        $openRejectModal = $isPendingRepayment && $canRejectRepayment && $errors->has('reason');
        $openManualReconciliationModal = $repayment->status === 'processing' && $canProcessRepayment && $errors->hasAny([
            'provider_status',
            'provider_message',
            'external_reference',
            'external_transaction_id',
        ]);
        $openGatewayRecheckResultModal = filled($gatewayRecheckResult);
        $approveSourceRaw = old('manual_source', $repayment->received_via_type ?? ($repayment->metadata['manual_source'] ?? 'bank'));
        $defaultApproveSource = in_array($approveSourceRaw, ['bank', 'wallet'], true) ? $approveSourceRaw : 'bank';

        $panelStyles = [
            'gateway_processing' => ['border' => 'border-cyan-400/40', 'bg' => 'bg-cyan-500/10', 'title' => 'text-cyan-200', 'text' => 'text-cyan-100/90'],
            'gateway_reconciliation_required' => ['border' => 'border-amber-400/40', 'bg' => 'bg-amber-500/10', 'title' => 'text-amber-200', 'text' => 'text-amber-100/90'],
            'gateway_failed_expired' => ['border' => 'border-rose-400/40', 'bg' => 'bg-rose-500/10', 'title' => 'text-rose-200', 'text' => 'text-rose-100/90'],
            'manual_pending' => ['border' => 'border-amber-400/40', 'bg' => 'bg-amber-500/10', 'title' => 'text-amber-200', 'text' => 'text-amber-100/90'],
            'manual_processing' => ['border' => 'border-blue-400/40', 'bg' => 'bg-blue-500/10', 'title' => 'text-blue-200', 'text' => 'text-blue-100/90'],
            'completed' => ['border' => 'border-emerald-400/40', 'bg' => 'bg-emerald-500/10', 'title' => 'text-emerald-200', 'text' => 'text-emerald-100/90'],
            'failed' => ['border' => 'border-rose-400/40', 'bg' => 'bg-rose-500/10', 'title' => 'text-rose-200', 'text' => 'text-rose-100/90'],
            'cancelled' => ['border' => 'border-slate-400/40', 'bg' => 'bg-slate-500/10', 'title' => 'text-slate-200', 'text' => 'text-slate-100/90'],
        ];
        $panelStyle = $panelStyles[$gatewayShowState->panelType] ?? $panelStyles['manual_processing'];
    @endphp

    <div
        class="space-y-8"
        x-data="{
            approveModalOpen: @js($openApproveModal),
            rejectModalOpen: @js($openRejectModal),
            manualReconciliationModalOpen: @js($openManualReconciliationModal),
            gatewayRecheckResultModalOpen: @js($openGatewayRecheckResultModal),
            approveSource: @js($defaultApproveSource)
        }"
        @keydown.escape.window="approveModalOpen = false; rejectModalOpen = false; manualReconciliationModalOpen = false; gatewayRecheckResultModalOpen = false"
    >
        <div class="flex items-center justify-between">
            <div class="space-y-1">
                <p class="text-xs uppercase tracking-[0.4em] text-cyan-300">Repayment Management</p>
                <h1 class="text-3xl font-bold">{{ $repayment->repayment_number }}</h1>
            </div>
            <div class="flex items-center gap-3">
                @if($repayment->customer && auth('admin')->user()?->can('repayments.create'))
                    <a href="{{ route('admin.customers.repayments.create', $repayment->customer) }}" class="inline-flex items-center gap-2 rounded-2xl bg-emerald-500/20 border border-emerald-500/40 px-4 py-3 text-sm text-emerald-200 hover:bg-emerald-500/30 transition">
                        Initiate Repayment
                    </a>
                @endif
                <a href="{{ route('admin.repayments.index') }}" class="inline-flex items-center gap-2 rounded-2xl border border-white/10 px-4 py-3 text-sm text-white hover:bg-white/10 transition">
                    Back to List
                </a>
            </div>
        </div>

        @if($gatewayAttempt)
            <div class="rounded-3xl border {{ $panelStyle['border'] }} {{ $panelStyle['bg'] }} p-6 shadow-lg space-y-5">
                <div>
                    <h2 class="text-lg font-semibold {{ $panelStyle['title'] }}">Gateway Processing</h2>
                    <p class="text-sm {{ $panelStyle['text'] }} mt-1">{{ $gatewayShowState->title }} — {{ $gatewayShowState->message }}</p>
                    @if($queuePollingActive)
                        <p class="text-xs text-slate-300 mt-2">Background polling is active every {{ $pollIntervalSeconds }} seconds. Payment window expires after {{ $paymentExpiryMinutes }} minutes.</p>
                    @elseif((string) config('queue.default') === 'sync')
                        <p class="text-xs text-amber-200/90 mt-2">Queue is running in sync mode. Automatic background polling is disabled — use Recheck Gateway Status or configure Redis/Horizon workers.</p>
                    @endif
                    @if($pollingStale)
                        <p class="text-xs text-amber-200/90 mt-2">Polling appears stale. The next scheduled check is overdue — verify queue workers and scheduler are running.</p>
                    @endif
                </div>

                @if(! empty($gatewayTimeline))
                    <ol class="space-y-2">
                        @foreach($gatewayTimeline as $step)
                            <li class="flex items-start gap-3 text-sm">
                                <span @class([
                                    'mt-1 h-2.5 w-2.5 rounded-full shrink-0',
                                    'bg-emerald-400' => $step['state'] === 'complete',
                                    'bg-cyan-400 animate-pulse' => $step['state'] === 'current',
                                    'bg-slate-500' => $step['state'] === 'upcoming',
                                ])></span>
                                <div class="flex-1">
                                    <p @class([
                                        'font-medium',
                                        'text-emerald-200' => $step['state'] === 'complete',
                                        'text-cyan-200' => $step['state'] === 'current',
                                        'text-slate-400' => $step['state'] === 'upcoming',
                                    ])>{{ $step['label'] }}</p>
                                    @if($step['at'])
                                        <p class="text-xs text-slate-500">{{ \Carbon\Carbon::parse($step['at'])->format('d M Y, g:i A') }}</p>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif

                <div class="grid gap-3 md:grid-cols-2 text-sm">
                    <div class="flex items-center justify-between gap-4">
                        <span class="text-slate-400">Gateway:</span>
                        <span class="font-medium text-white">{{ $gatewayShowState->gatewayName ?? '—' }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <span class="text-slate-400">Repayment Status:</span>
                        <span class="font-medium text-white">{{ ucfirst($repayment->status) }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <span class="text-slate-400">Attempt Status:</span>
                        <span class="font-medium text-white">{{ ucfirst($gatewayAttempt->status->value) }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <span class="text-slate-400">Internal Reference:</span>
                        <span class="font-mono text-xs text-white">{{ $gatewayAttempt->internal_reference ?? '—' }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <span class="text-slate-400">Provider Reference:</span>
                        <span class="font-mono text-xs text-white">{{ $gatewayAttempt->provider_reference ?? '—' }}</span>
                    </div>
                    @if($gatewayAttempt->provider_transaction_id)
                        <div class="flex items-center justify-between gap-4">
                            <span class="text-slate-400">Provider Transaction ID:</span>
                            <span class="font-mono text-xs text-white">{{ $gatewayAttempt->provider_transaction_id }}</span>
                        </div>
                    @endif
                    <div class="flex items-center justify-between gap-4">
                        <span class="text-slate-400">Collection Amount:</span>
                        <span class="font-medium text-white">ZMW {{ number_format((float) $gatewayAttempt->amount, 2) }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <span class="text-slate-400">Customer Phone:</span>
                        <span class="font-medium text-white">{{ $gatewayAttempt->customer_phone ?? $repayment->phone_number ?? '—' }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <span class="text-slate-400">Query Attempts:</span>
                        <span class="font-medium text-white">{{ (int) $gatewayAttempt->query_attempts }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <span class="text-slate-400">Linked Account:</span>
                        <span class="font-medium text-white">{{ $linkedFinancialAccountLabel ?? 'Not linked' }}</span>
                    </div>
                    @if($gatewayAttempt->last_queried_at)
                        <div class="flex items-center justify-between gap-4">
                            <span class="text-slate-400">Last Checked:</span>
                            <span class="font-medium text-white">{{ $gatewayAttempt->last_queried_at->format('d M Y, g:i A') }}</span>
                        </div>
                    @endif
                    @if($gatewayAttempt->next_query_at && ! $gatewayAttempt->isTerminal())
                        <div class="flex items-center justify-between gap-4">
                            <span class="text-slate-400">Next Check:</span>
                            <span class="font-medium text-white">{{ $gatewayAttempt->next_query_at->format('d M Y, g:i A') }}</span>
                        </div>
                    @endif
                    @if($gatewayShowState->expiresAt && ! $gatewayAttempt->isTerminal())
                        <div class="flex items-center justify-between gap-4">
                            <span class="text-slate-400">Expires At:</span>
                            <span class="font-medium text-white">{{ $gatewayShowState->expiresAt->format('d M Y, g:i A') }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <span class="text-slate-400">Time Remaining:</span>
                            <span class="font-medium text-white">
                                @if(($gatewayShowState->secondsUntilExpiry ?? 0) > 0)
                                    {{ (int) floor($gatewayShowState->secondsUntilExpiry / 60) }}m {{ $gatewayShowState->secondsUntilExpiry % 60 }}s
                                @else
                                    Expired
                                @endif
                            </span>
                        </div>
                    @endif
                    @if($gatewayAttempt->response_message)
                        <div class="md:col-span-2 pt-2 border-t border-white/10">
                            <span class="text-slate-400">Status Message:</span>
                            <p class="font-medium text-white mt-1">{{ $gatewayAttempt->response_message }}</p>
                        </div>
                    @endif
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    @if($gatewayShowState->showRecheckAction && $canRecheckGateway)
                        <form method="POST" action="{{ route('admin.repayments.gateway-recheck', $repayment) }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-cyan-500/20 border border-cyan-500/50 px-4 py-2.5 text-sm font-semibold text-cyan-200 hover:bg-cyan-500/30 transition">
                                Recheck Gateway Status
                            </button>
                        </form>
                    @endif

                    @if($gatewayAttempt->paymentGateway && auth('admin')->user()?->can('payment-gateways.view'))
                        <a href="{{ route('admin.payment-gateways.show', $gatewayAttempt->paymentGateway) }}" class="inline-flex items-center gap-2 rounded-2xl border border-white/20 px-4 py-2.5 text-sm font-semibold text-slate-200 hover:bg-white/10 transition">
                            View Gateway Logs
                        </a>
                    @endif

                    @if($gatewayShowState->showRetryCollectionAction && $canProcessRepayment)
                        <form method="POST" action="{{ route('admin.repayments.retry-gateway-collection', $repayment) }}" onsubmit="return confirm('Start a new gateway collection attempt for this repayment?');">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-violet-500/20 border border-violet-500/50 px-4 py-2.5 text-sm font-semibold text-violet-200 hover:bg-violet-500/30 transition">
                                Retry Collection
                            </button>
                        </form>
                    @endif

                    @if($gatewayShowState->showManualReconciliationAction && $canReconcile)
                        <button
                            type="button"
                            @click="manualReconciliationModalOpen = true"
                            class="inline-flex items-center gap-2 rounded-2xl border border-white/20 px-4 py-2.5 text-sm font-semibold text-slate-200 hover:bg-white/10 transition"
                        >
                            Manual Reconciliation
                        </button>
                    @endif
                </div>
            </div>
        @endif

        @if($gatewayShowState->panelType === 'manual_pending')
            <div class="rounded-3xl border border-amber-400/40 bg-amber-500/10 p-6 shadow-lg space-y-5">
                <div>
                    <h2 class="text-lg font-semibold text-amber-200">Manual Repayment Approval</h2>
                    <p class="text-sm text-amber-100/90 mt-1">This repayment was recorded manually and requires approval before loan balances are updated.</p>
                </div>
                <div class="grid gap-3 md:grid-cols-2 text-sm">
                    <div class="flex items-center justify-between gap-4"><span class="text-slate-400">Amount:</span><span class="font-medium text-white">ZMW {{ number_format($repayment->total_amount, 2) }}</span></div>
                    <div class="flex items-center justify-between gap-4"><span class="text-slate-400">Channel:</span><span class="font-medium text-white">{{ $repayment->channel->name ?? '—' }}</span></div>
                    <div class="flex items-center justify-between gap-4"><span class="text-slate-400">Customer:</span><span class="font-medium text-white">{{ $repayment->customer->full_name ?? '—' }}</span></div>
                    <div class="flex items-center justify-between gap-4"><span class="text-slate-400">Phone:</span><span class="font-medium text-white">{{ $repayment->phone_number ?? $repayment->customer->phone ?? '—' }}</span></div>
                    @if($repayment->external_reference)
                        <div class="flex items-center justify-between gap-4"><span class="text-slate-400">Reference:</span><span class="font-mono text-xs text-white">{{ $repayment->external_reference }}</span></div>
                    @endif
                    @if($repayment->external_transaction_id)
                        <div class="flex items-center justify-between gap-4"><span class="text-slate-400">Transaction ID:</span><span class="font-mono text-xs text-white">{{ $repayment->external_transaction_id }}</span></div>
                    @endif
                    @if($repayment->metadata['manual_source'] ?? null)
                        <div class="flex items-center justify-between gap-4"><span class="text-slate-400">Manual Source:</span><span class="font-medium text-white">{{ ucfirst($repayment->metadata['manual_source']) }}</span></div>
                    @endif
                    @if($repayment->metadata['notes'] ?? null)
                        <div class="md:col-span-2"><span class="text-slate-400">Notes:</span><p class="font-medium text-white mt-1">{{ $repayment->metadata['notes'] }}</p></div>
                    @endif
                </div>
            </div>
        @endif

        @if($isPendingRepayment && ($canApproveRepayment || $canRejectRepayment))
            <div class="flex flex-wrap items-center gap-3">
                @if($canApproveRepayment)
                    <button
                        type="button"
                        @click="approveModalOpen = true"
                        class="inline-flex items-center gap-2 rounded-2xl bg-emerald-500/20 border border-emerald-500/50 px-4 py-2.5 text-sm font-semibold text-emerald-200 hover:bg-emerald-500/30 transition"
                    >
                        Approve Manual Repayment
                    </button>
                @endif
                @if($canRejectRepayment)
                    <button
                        type="button"
                        @click="rejectModalOpen = true"
                        class="inline-flex items-center gap-2 rounded-2xl bg-rose-500/20 border border-rose-500/50 px-4 py-2.5 text-sm font-semibold text-rose-200 hover:bg-rose-500/30 transition"
                    >
                        Reject Repayment
                    </button>
                @endif
            </div>
        @endif

        <div class="grid gap-6 md:grid-cols-2">
            {{-- Repayment Details --}}
            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
                <h2 class="text-xl font-semibold text-white">Repayment Details</h2>
                <div class="space-y-3 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Repayment Number:</span>
                        <span class="font-medium text-white">{{ $repayment->repayment_number }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Date:</span>
                        <span class="font-medium text-white">{{ $repayment->created_at->format('d M Y, g:i A') }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Status:</span>
                        @php
                            $statusColors = [
                                'pending' => 'bg-amber-500/20 text-amber-300',
                                'processing' => 'bg-blue-500/20 text-blue-300',
                                'completed' => 'bg-emerald-500/20 text-emerald-300',
                                'failed' => 'bg-rose-500/20 text-rose-300',
                                'cancelled' => 'bg-slate-500/20 text-slate-300',
                            ];
                            $statusColor = $statusColors[$repayment->status] ?? 'bg-slate-500/20 text-slate-300';
                        @endphp
                        <span class="inline-block rounded-full px-3 py-1 text-xs font-medium {{ $statusColor }}">
                            {{ ucfirst($repayment->status) }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Total Amount:</span>
                        <span class="font-bold text-lg text-emerald-400">ZMW {{ number_format($repayment->total_amount, 2) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Recovery Method:</span>
                        <span class="font-medium text-white">{{ $repayment->recoveryMethodLabel() }}</span>
                    </div>
                    @if($repayment->processed_at)
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Processed At:</span>
                            <span class="font-medium text-white">{{ $repayment->processed_at->format('d M Y, g:i A') }}</span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Customer Information --}}
            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
                <h2 class="text-xl font-semibold text-white">Customer Information</h2>
                <div class="space-y-3 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Name:</span>
                        @if($repayment->customer)
                            <a href="{{ route('admin.customers.show', $repayment->customer) }}" class="font-medium text-cyan-400 hover:text-cyan-300 hover:underline transition">
                                {{ $repayment->customer->full_name }}
                            </a>
                        @else
                            <span class="font-medium text-white">—</span>
                        @endif
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Email:</span>
                        <span class="font-medium text-white">{{ $repayment->customer->email ?? '—' }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Phone:</span>
                        <span class="font-medium text-white">{{ $repayment->customer->phone ?? '—' }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Repayment Phone:</span>
                        <span class="font-medium text-white">{{ $repayment->phone_number ?? '—' }}</span>
                    </div>
                </div>
            </div>

            {{-- Channel Information --}}
            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
                <h2 class="text-xl font-semibold text-white">Payment Channel</h2>
                <div class="space-y-3 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Channel:</span>
                        <span class="font-medium text-white">{{ $repayment->channel->name ?? '—' }}</span>
                    </div>
                    @if($repayment->external_reference)
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">External Reference:</span>
                            <span class="font-mono text-xs text-white">{{ $repayment->external_reference }}</span>
                        </div>
                    @endif
                    @if($repayment->external_transaction_id)
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Transaction ID:</span>
                            <span class="font-mono text-xs text-white">{{ $repayment->external_transaction_id }}</span>
                        </div>
                    @endif
                    @if($repayment->status_message)
                        <div class="pt-2 border-t border-white/10">
                            <span class="text-slate-400">Status Message:</span>
                            <p class="font-medium text-white mt-1">{{ $repayment->status_message }}</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Payment Summary --}}
            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
                <h2 class="text-xl font-semibold text-white">Payment Breakdown</h2>
                <div class="space-y-3 text-sm">
                    @php
                        $totalPrincipal = $repayment->loanRepayments->sum('principal_amount');
                        $totalInterest = $repayment->loanRepayments->sum('interest_amount');
                        $totalFee = $repayment->loanRepayments->sum('processing_fee_amount');
                    @endphp
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Principal Amount:</span>
                        <span class="font-medium text-green-400">ZMW {{ number_format($totalPrincipal, 2) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Interest Amount:</span>
                        <span class="font-medium text-amber-400">ZMW {{ number_format($totalInterest, 2) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Processing Fee:</span>
                        <span class="font-medium text-blue-400">ZMW {{ number_format($totalFee, 2) }}</span>
                    </div>
                    <div class="pt-2 border-t border-white/10">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Total Amount:</span>
                            <span class="font-bold text-lg text-emerald-400">ZMW {{ number_format($repayment->total_amount, 2) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Loans Affected --}}
        @if($repayment->loanRepayments->isNotEmpty())
            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                <h2 class="text-xl font-semibold text-white mb-4">Loans Affected by This Repayment</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full w-full text-sm text-slate-300">
                        <thead>
                            <tr class="bg-slate-100 text-center text-sm font-semibold uppercase tracking-[0.25em] border-b border-slate-300">
                                <th class="px-4 py-4 text-base text-slate-800 font-bold">Loan Number</th>
                                <th class="px-4 py-4 text-base text-slate-800 font-bold">Customer</th>
                                <th class="px-4 py-4 text-base text-slate-800 font-bold">Product</th>
                                <th class="px-4 py-4 text-base text-slate-800 font-bold">Amount Applied</th>
                                <th class="px-4 py-4 text-base text-slate-800 font-bold">Principal</th>
                                <th class="px-4 py-4 text-base text-slate-800 font-bold">Interest</th>
                                <th class="px-4 py-4 text-base text-slate-800 font-bold">Processing Fee</th>
                                <th class="px-4 py-4 text-base text-slate-800 font-bold">Balance Before</th>
                                <th class="px-4 py-4 text-base text-slate-800 font-bold">Balance After</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($repayment->loanRepayments as $loanRepayment)
                                @php
                                    $loan = $loanRepayment->loan;
                                @endphp
                                <tr class="border-t border-white/5 text-center hover:bg-white/5 transition">
                                    <td class="px-4 py-3">
                                        <a href="{{ route('admin.loans.show', $loan) }}" class="font-medium text-cyan-400 hover:text-cyan-300 hover:underline transition">
                                            {{ $loan->loan_number }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="text-left">
                                            <div class="font-medium text-white">{{ $loan->customer->full_name ?? 'N/A' }}</div>
                                            <div class="text-xs text-slate-400">{{ $loan->customer->email ?? 'N/A' }}</div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="text-white">{{ $loan->loanProduct->name ?? '—' }}</span>
                                        <div class="text-xs text-slate-400">{{ ucfirst($loan->loanProduct->category ?? '—') }}</div>
                                    </td>
                                    <td class="px-4 py-3 font-semibold text-white">
                                        ZMW {{ number_format($loanRepayment->amount, 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-green-400 font-medium">
                                        ZMW {{ number_format($loanRepayment->principal_amount, 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-amber-400 font-medium">
                                        ZMW {{ number_format($loanRepayment->interest_amount, 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-blue-400 font-medium">
                                        ZMW {{ number_format($loanRepayment->processing_fee_amount, 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-slate-400">
                                        ZMW {{ number_format($loanRepayment->outstanding_balance_before, 2) }}
                                    </td>
                                    <td class="px-4 py-3 font-medium text-white">
                                        ZMW {{ number_format($loanRepayment->outstanding_balance_after, 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-cyan-500/30 bg-gradient-to-r from-cyan-500/10 to-blue-500/10">
                                <td colspan="3" class="px-4 py-4 text-right font-semibold text-white">
                                    Totals:
                                </td>
                                <td class="px-4 py-4 text-center font-bold text-white">
                                    ZMW {{ number_format($repayment->total_amount, 2) }}
                                </td>
                                <td class="px-4 py-4 text-center font-medium text-green-400">
                                    ZMW {{ number_format($totalPrincipal, 2) }}
                                </td>
                                <td class="px-4 py-4 text-center font-medium text-amber-400">
                                    ZMW {{ number_format($totalInterest, 2) }}
                                </td>
                                <td class="px-4 py-4 text-center font-medium text-blue-400">
                                    ZMW {{ number_format($totalFee, 2) }}
                                </td>
                                <td colspan="2" class="px-4 py-4"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        @endif

        @if($isPendingRepayment && $canApproveRepayment)
            <div
                x-show="approveModalOpen"
                x-cloak
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4 py-6"
            >
                <div class="w-full max-w-3xl rounded-3xl border border-white/10 bg-slate-900 p-6 shadow-2xl max-h-[90vh] overflow-y-auto" @click.away="approveModalOpen = false">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-semibold text-white">Approve Repayment</h2>
                        <button type="button" @click="approveModalOpen = false" class="rounded-lg border border-white/15 px-3 py-1.5 text-sm text-slate-300 hover:bg-white/10 transition">Close</button>
                    </div>
                    <p class="text-sm text-slate-400 mb-5">Confirm payment details before approving and applying this repayment.</p>

                    <form method="POST" action="{{ route('admin.repayments.approve', $repayment) }}" class="grid gap-4 md:grid-cols-2">
                        @csrf
                        <div class="md:col-span-2">
                            <label class="text-sm font-medium text-slate-200">Repayment Channel</label>
                            @php
                                $selectedChannelId = (string) old('channel_id', $repayment->channel_id);
                            @endphp
                            <select name="channel_id" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40" required>
                                @foreach($channels as $channel)
                                    <option value="{{ $channel->id }}" @selected($selectedChannelId === (string) $channel->id)>{{ $channel->name }}</option>
                                @endforeach
                            </select>
                            @error('channel_id')
                                <p class="text-xs text-rose-300 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-200">Money Received In</label>
                            <select x-model="approveSource" name="manual_source" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40" required>
                                @php
                                    $manualSource = old('manual_source', $repayment->metadata['manual_source'] ?? '');
                                @endphp
                                <option value="cash" @selected($manualSource === 'cash')>Cash</option>
                                <option value="bank" @selected($manualSource === 'bank')>Bank</option>
                                <option value="wallet" @selected($manualSource === 'wallet')>Wallet</option>
                            </select>
                            @error('manual_source')
                                <p class="text-xs text-rose-300 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div x-show="approveSource === 'cash'" x-cloak>
                            <label class="text-sm font-medium text-slate-200">Cash Register</label>
                            <select name="cash_register_id" :disabled="approveSource !== 'cash'" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                                @foreach($cashRegisters as $register)
                                    <option value="{{ $register->id }}" @selected((string) old('cash_register_id', $repayment->received_via_type === 'cash' ? $repayment->received_via_id : $cashRegisters->first()?->id) === (string) $register->id)>{{ $register->display_name }}</option>
                                @endforeach
                            </select>
                            @error('cash_register_id')
                                <p class="text-xs text-rose-300 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div x-show="approveSource === 'bank'" x-cloak>
                            <label class="text-sm font-medium text-slate-200">Bank Account</label>
                            <select name="bank_id" :disabled="approveSource !== 'bank'" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                                <option value="">Select bank</option>
                                @foreach($banks as $bank)
                                    <option value="{{ $bank->id }}" @selected((string) old('bank_id') === (string) $bank->id)>{{ $bank->display_name }}</option>
                                @endforeach
                            </select>
                            @error('bank_id')
                                <p class="text-xs text-rose-300 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div x-show="approveSource === 'wallet'" x-cloak>
                            <label class="text-sm font-medium text-slate-200">Wallet Account</label>
                            <select name="wallet_id" :disabled="approveSource !== 'wallet'" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                                <option value="">Select wallet</option>
                                @foreach($wallets as $wallet)
                                    <option value="{{ $wallet->id }}" @selected((string) old('wallet_id') === (string) $wallet->id)>{{ $wallet->display_name }}</option>
                                @endforeach
                            </select>
                            @error('wallet_id')
                                <p class="text-xs text-rose-300 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="md:col-span-2">
                            <label class="text-sm font-medium text-slate-200">Reference Number</label>
                            <input type="text" name="external_reference" value="{{ old('external_reference', $repayment->external_reference) }}" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                            @error('external_reference')
                                <p class="text-xs text-rose-300 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="md:col-span-2">
                            <label class="text-sm font-medium text-slate-200">Approval Notes</label>
                            <textarea name="notes" rows="2" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">{{ old('notes') }}</textarea>
                            @error('notes')
                                <p class="text-xs text-rose-300 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="md:col-span-2 flex flex-wrap items-center gap-3">
                            <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-emerald-500/20 border border-emerald-500/50 px-4 py-2.5 text-sm font-semibold text-emerald-200 hover:bg-emerald-500/30 transition">
                                Approve And Process
                            </button>
                            <button type="button" @click="approveModalOpen = false" class="inline-flex items-center gap-2 rounded-2xl border border-white/15 px-4 py-2.5 text-sm font-semibold text-slate-300 hover:bg-white/10 transition">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        @if($isPendingRepayment && $canRejectRepayment)
            <div
                x-show="rejectModalOpen"
                x-cloak
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4 py-6"
            >
                <div class="w-full max-w-lg rounded-3xl border border-white/10 bg-slate-900 p-6 shadow-2xl" @click.away="rejectModalOpen = false">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-semibold text-white">Reject Repayment</h2>
                        <button type="button" @click="rejectModalOpen = false" class="rounded-lg border border-white/15 px-3 py-1.5 text-sm text-slate-300 hover:bg-white/10 transition">Close</button>
                    </div>
                    <p class="text-sm text-slate-400 mb-5">Provide a clear reason before rejecting this repayment.</p>

                    <form method="POST" action="{{ route('admin.repayments.reject', $repayment) }}" class="space-y-3">
                        @csrf
                        <div>
                            <label class="text-sm font-medium text-slate-200">Rejection Reason</label>
                            <textarea name="reason" rows="3" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-rose-400 focus:ring-rose-400/40" required>{{ old('reason') }}</textarea>
                            @error('reason')
                                <p class="text-xs text-rose-300 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="flex flex-wrap items-center gap-3">
                            <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-rose-500/20 border border-rose-500/50 px-4 py-2.5 text-sm font-semibold text-rose-200 hover:bg-rose-500/30 transition">
                                Confirm Rejection
                            </button>
                            <button type="button" @click="rejectModalOpen = false" class="inline-flex items-center gap-2 rounded-2xl border border-white/15 px-4 py-2.5 text-sm font-semibold text-slate-300 hover:bg-white/10 transition">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        @if($repayment->status === 'processing' && $canReconcile)
            <div
                x-show="manualReconciliationModalOpen"
                x-cloak
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4 py-6"
            >
                <div class="w-full max-w-3xl rounded-3xl border border-white/10 bg-slate-900 p-6 shadow-2xl max-h-[90vh] overflow-y-auto" @click.away="manualReconciliationModalOpen = false">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-semibold text-white">Manual Provider Confirmation</h2>
                        <button type="button" @click="manualReconciliationModalOpen = false" class="rounded-lg border border-white/15 px-3 py-1.5 text-sm text-slate-300 hover:bg-white/10 transition">Close</button>
                    </div>

                    <div class="rounded-2xl border border-amber-500/40 bg-amber-500/10 p-4 mb-5">
                        <p class="text-sm text-amber-100">
                            Use this only after independently verifying the manual payment.
                            @if($gatewayShowState->isGatewayOriented)
                                <strong class="text-amber-200">This repayment has a gateway attempt.</strong> Prefer Recheck Gateway Status and Apply Gateway Synchronization when the provider has already confirmed payment.
                            @endif
                            This action may update loan balances but may not fully credit the linked finance account.
                        </p>
                    </div>

                    <form method="POST" action="{{ route('admin.repayments.processing-status', $repayment) }}" class="grid gap-4 md:grid-cols-2">
                        @csrf
                        <div>
                            <label class="text-sm font-medium text-slate-200">Provider Status</label>
                            <select name="provider_status" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40" required>
                                <option value="success" @selected(old('provider_status') === 'success')>Success</option>
                                <option value="failed" @selected(old('provider_status') === 'failed')>Failed</option>
                            </select>
                            @error('provider_status')
                                <p class="text-xs text-rose-300 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-200">External Reference</label>
                            <input type="text" name="external_reference" value="{{ old('external_reference', $repayment->external_reference) }}" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                            @error('external_reference')
                                <p class="text-xs text-rose-300 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-200">External Transaction ID</label>
                            <input type="text" name="external_transaction_id" value="{{ old('external_transaction_id', $repayment->external_transaction_id) }}" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                            @error('external_transaction_id')
                                <p class="text-xs text-rose-300 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="md:col-span-2">
                            <label class="text-sm font-medium text-slate-200">Provider Message</label>
                            <textarea name="provider_message" rows="2" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">{{ old('provider_message') }}</textarea>
                            @error('provider_message')
                                <p class="text-xs text-rose-300 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="md:col-span-2 flex flex-wrap items-center gap-3">
                            <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-cyan-500/20 border border-cyan-500/50 px-4 py-2.5 text-sm font-semibold text-cyan-200 hover:bg-cyan-500/30 transition">
                                Save Provider Response
                            </button>
                            <button type="button" @click="manualReconciliationModalOpen = false" class="inline-flex items-center gap-2 rounded-2xl border border-white/15 px-4 py-2.5 text-sm font-semibold text-slate-300 hover:bg-white/10 transition">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        @if($gatewayRecheckResult)
            <div
                x-show="gatewayRecheckResultModalOpen"
                x-cloak
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4 py-6"
            >
                <div class="w-full max-w-3xl rounded-3xl border border-white/10 bg-slate-900 p-6 shadow-2xl max-h-[90vh] overflow-y-auto" @click.away="gatewayRecheckResultModalOpen = false">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-semibold text-white">Gateway Status Recheck Result</h2>
                        <button type="button" @click="gatewayRecheckResultModalOpen = false" class="rounded-lg border border-white/15 px-3 py-1.5 text-sm text-slate-300 hover:bg-white/10 transition">Close</button>
                    </div>

                    <div class="rounded-2xl border border-cyan-500/30 bg-cyan-500/10 p-4 mb-5">
                        <p class="text-sm text-cyan-100">{{ $gatewayRecheckResult['summary'] ?? 'Gateway status checked.' }}</p>
                        @if(! empty($gatewayRecheckResult['recommended_action']))
                            <p class="text-xs text-slate-300 mt-2">{{ $gatewayRecheckResult['recommended_action'] }}</p>
                        @endif
                    </div>

                    <div class="grid gap-3 md:grid-cols-2 text-sm">
                        <div class="rounded-2xl border border-white/10 p-4 space-y-2">
                            <p class="text-xs uppercase tracking-wider text-slate-400">FineEdge (Local)</p>
                            <div class="flex justify-between gap-4"><span class="text-slate-400">Repayment</span><span class="text-white font-medium">{{ ucfirst($gatewayRecheckResult['local_repayment_status'] ?? '—') }}</span></div>
                            <div class="flex justify-between gap-4"><span class="text-slate-400">Attempt</span><span class="text-white font-medium">{{ ucfirst($gatewayRecheckResult['local_attempt_status'] ?? '—') }}</span></div>
                            @if(! empty($gatewayRecheckResult['local_last_queried_at']))
                                <div class="flex justify-between gap-4"><span class="text-slate-400">Last polled</span><span class="text-white text-xs">{{ \Carbon\Carbon::parse($gatewayRecheckResult['local_last_queried_at'])->format('d M Y, g:i A') }}</span></div>
                            @endif
                        </div>
                        <div class="rounded-2xl border border-white/10 p-4 space-y-2">
                            <p class="text-xs uppercase tracking-wider text-slate-400">Gateway (Live Query)</p>
                            <div class="flex justify-between gap-4"><span class="text-slate-400">Status</span><span class="text-white font-medium">{{ ucfirst($gatewayRecheckResult['gateway_normalized_status'] ?? '—') }}</span></div>
                            @if(isset($gatewayRecheckResult['response_code']))
                                <div class="flex justify-between gap-4"><span class="text-slate-400">Response code</span><span class="text-white font-medium">{{ $gatewayRecheckResult['response_code'] }}</span></div>
                            @endif
                            @if(! empty($gatewayRecheckResult['provider_transaction_id']))
                                <div class="flex justify-between gap-4"><span class="text-slate-400">Transaction ID</span><span class="font-mono text-xs text-white">{{ $gatewayRecheckResult['provider_transaction_id'] }}</span></div>
                            @endif
                        </div>
                    </div>

                    @if(! empty($gatewayRecheckResult['response_message']))
                        <div class="mt-4 pt-4 border-t border-white/10 text-sm">
                            <span class="text-slate-400">Gateway response message:</span>
                            <p class="font-medium text-white mt-1">{{ $gatewayRecheckResult['response_message'] }}</p>
                        </div>
                    @endif

                    @if(! empty($gatewayRecheckResult['status_changed']))
                        <p class="mt-4 text-sm text-amber-200">Gateway status differs from the recorded FineEdge state.</p>
                    @endif

                    <div class="mt-6 flex flex-col gap-4">
                        @if(! empty($gatewayRecheckResult['show_apply_synchronization']) && $canApplyGatewaySync)
                            <form method="POST" action="{{ route('admin.repayments.gateway-recheck.apply', $repayment) }}" class="space-y-3 rounded-2xl border border-emerald-500/30 bg-emerald-500/5 p-4">
                                @csrf
                                <p class="text-sm text-emerald-100">Apply gateway synchronization to update loan balances and credit the linked finance account.</p>
                                <div>
                                    <label class="text-sm font-medium text-slate-200">Admin note (required)</label>
                                    <textarea name="note" rows="2" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-emerald-400 focus:ring-emerald-400/40" required>{{ old('note') }}</textarea>
                                    @error('note')
                                        <p class="text-xs text-rose-300 mt-1">{{ $message }}</p>
                                    @enderror
                                </div>
                                <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-emerald-500/20 border border-emerald-500/50 px-4 py-2.5 text-sm font-semibold text-emerald-200 hover:bg-emerald-500/30 transition">
                                    Apply Gateway Synchronization
                                </button>
                            </form>
                        @endif

                        @if(! empty($gatewayRecheckResult['show_mark_failed']) && $canApplyGatewaySync)
                            <form method="POST" action="{{ route('admin.repayments.gateway-recheck.mark-failed', $repayment) }}" class="space-y-3 rounded-2xl border border-rose-500/30 bg-rose-500/5 p-4">
                                @csrf
                                <p class="text-sm text-rose-100">Mark this repayment as failed to align FineEdge with the gateway result. Loan balances will not be updated.</p>
                                <div>
                                    <label class="text-sm font-medium text-slate-200">Admin note (required)</label>
                                    <textarea name="note" rows="2" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-rose-400 focus:ring-rose-400/40" required>{{ old('note') }}</textarea>
                                </div>
                                <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-rose-500/20 border border-rose-500/50 px-4 py-2.5 text-sm font-semibold text-rose-200 hover:bg-rose-500/30 transition">
                                    Mark as Failed
                                </button>
                            </form>
                        @endif

                        <button type="button" @click="gatewayRecheckResultModalOpen = false" class="inline-flex items-center gap-2 rounded-2xl border border-white/15 px-4 py-2.5 text-sm font-semibold text-slate-300 hover:bg-white/10 transition w-fit">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        @endif

        {{-- Metadata --}}
        @if($repayment->metadata)
            <details class="group rounded-3xl border border-white/10 bg-white/5 shadow-lg">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-4 p-6 [&::-webkit-details-marker]:hidden">
                    <div>
                        <h2 class="text-xl font-semibold text-white">Additional Information</h2>
                        <p class="mt-1 text-sm text-slate-400">Repayment metadata — expand to view</p>
                    </div>
                    <span class="shrink-0 text-slate-400 transition group-open:rotate-180" aria-hidden="true">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </span>
                </summary>
                <div class="border-t border-white/10 px-6 pb-6 pt-4">
                    <div class="rounded-2xl bg-white/5 p-4">
                        <pre class="text-xs text-slate-300 font-mono whitespace-pre-wrap">{{ json_encode($repayment->metadata, JSON_PRETTY_PRINT) }}</pre>
                    </div>
                </div>
            </details>
        @endif
    </div>
@endsection
