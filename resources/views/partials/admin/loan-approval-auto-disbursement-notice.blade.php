@php
    /** @var \App\Services\Loans\DTOs\LoanApprovalAutoDisbursementPreview $preview */
    $preview = $approvalAutoDisbursementPreview ?? null;
@endphp

@if($preview)
    @if($preview->autoDisbursementApplicable && $preview->autoDisbursementReady)
        <div class="mb-0 rounded-2xl border border-cyan-400/30 bg-cyan-500/10 p-4 text-sm space-y-4 h-full">
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0 mt-0.5">
                    <svg class="w-5 h-5 text-cyan-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div class="space-y-2 min-w-0">
                    <p class="font-semibold text-cyan-100">Automatic gateway disbursement is ready</p>
                    <p class="text-slate-200 leading-relaxed">{{ $preview->warningMessage }}</p>
                   
                </div>
            </div>

            <dl class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 text-slate-200">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-400">Route</dt>
                    <dd class="font-medium text-white">{{ $preview->routeLabel }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-400">Gateway</dt>
                    <dd class="font-medium text-white">{{ $preview->gatewayName }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-400">Linked account</dt>
                    <dd class="font-medium text-white">{{ $preview->linkedAccountLabel }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-400">Linked account balance</dt>
                    <dd class="font-medium text-white">{{ $preview->formattedBalance() ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-400">Disbursement amount</dt>
                    <dd class="font-medium text-white">{{ $preview->formattedDisbursementAmount() }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-400">Destination</dt>
                    <dd class="font-medium text-white">{{ $preview->destinationLabel }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-400">Status</dt>
                    <dd class="font-medium text-emerald-300">{{ $preview->statusLabel }}</dd>
                </div>
            </dl>

            @if($preview->balanceWarning)
                <div class="rounded-xl border border-amber-400/30 bg-amber-500/10 px-4 py-3 text-amber-100">
                    <p class="text-sm leading-relaxed">{{ $preview->balanceWarning }}</p>
                </div>
            @endif
        </div>
    @elseif($preview->autoDisbursementApplicable && ! $preview->autoDisbursementReady)
        <div class="mb-0 rounded-2xl border border-amber-400/30 bg-amber-500/10 p-4 text-sm space-y-3 h-full">
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0 mt-0.5">
                    <svg class="w-5 h-5 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <div class="space-y-2 min-w-0">
                    <p class="font-semibold text-amber-100">Automatic disbursement is configured but not ready</p>
                    <p class="text-slate-200 leading-relaxed">
                        Automatic gateway disbursement is enabled for this route, but it is not ready:
                        <span class="font-medium text-white">{{ $preview->warningMessage }}</span>.
                        The loan will still be approved, but manual disbursement will be required.
                    </p>
                </div>
            </div>

            <dl class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 text-slate-200">
                @if($preview->routeLabel)
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-400">Route</dt>
                        <dd class="font-medium text-white">{{ $preview->routeLabel }}</dd>
                    </div>
                @endif
                @if($preview->gatewayName)
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-400">Gateway</dt>
                        <dd class="font-medium text-white">{{ $preview->gatewayName }}</dd>
                    </div>
                @endif
                @if($preview->statusLabel)
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-400">Status</dt>
                        <dd class="font-medium text-amber-200">{{ $preview->statusLabel }}</dd>
                    </div>
                @endif
                @if($preview->linkedAccountLabel)
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-400">Linked account</dt>
                        <dd class="font-medium text-white">{{ $preview->linkedAccountLabel }}</dd>
                    </div>
                @endif
            </dl>
        </div>
    @else
        <div class="mb-0 rounded-2xl border border-white/10 bg-white/5 p-4 text-sm h-full">
            <p class="text-slate-300 leading-relaxed">
                Manual disbursement will be required after approval.
            </p>
        </div>
    @endif
@endif
