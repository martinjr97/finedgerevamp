@if(($sharedPaymentDetails['has_matches'] ?? false) && ($sharedPaymentDetails['matches'] ?? []) !== [])
    <div class="rounded-2xl border border-orange-500/40 bg-orange-950/25 p-5 shadow-lg" role="alert">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-orange-500/20 text-orange-300">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-base font-semibold text-orange-100">Shared payment credentials detected</h2>
                    <p class="mt-1 text-sm text-orange-200/90 leading-relaxed">
                        Another {{ $sharedPaymentDetails['total_count'] === 1 ? 'customer has' : $sharedPaymentDetails['total_count'].' other customers have' }}
                        used the same disbursement details as this loan. Review before approving or disbursing.
                    </p>
                </div>
            </div>
            @if(isset($loan) && $loan->customer_id && auth('admin')->user()?->can('fraud-protection.view'))
                <a href="{{ route('admin.fraud-protection.show', $loan->customer_id) }}"
                   class="duplicate-warning-badge shrink-0 self-start">
                    Fraud protection
                </a>
            @endif
        </div>

        <ul class="mt-4 space-y-3">
            @foreach($sharedPaymentDetails['matches'] as $match)
                <li class="rounded-xl border border-orange-500/25 bg-black/20 px-4 py-3 text-sm">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="font-semibold text-white">
                                <a href="{{ route('admin.customers.show', $match['customer_id']) }}"
                                   class="text-cyan-300 hover:text-cyan-200 hover:underline">
                                    {{ $match['customer_name'] }}
                                </a>
                                @if(!empty($match['customer_status']))
                                    <span class="ml-2 text-xs font-normal text-slate-400">({{ ucfirst($match['customer_status']) }})</span>
                                @endif
                            </p>
                            <p class="mt-1 text-orange-100/80">{{ $match['match_reason'] }}</p>
                            <p class="mt-1 text-xs text-slate-400">
                                Matched credential:
                                <span class="font-mono text-slate-300">{{ $match['matched_credential'] }}</span>
                                ·
                                {{ $match['source'] === 'loan' ? 'Found on loan record' : 'Found on customer payment profile' }}
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2 shrink-0">
                            @if(!empty($match['loan_id']))
                                <a href="{{ route('admin.loans.show', $match['loan_id']) }}"
                                   class="inline-flex items-center rounded-lg border border-white/15 bg-white/5 px-3 py-1.5 text-xs font-semibold text-slate-200 hover:bg-white/10 transition">
                                    Loan {{ $match['loan_number'] }}
                                </a>
                            @endif
                            <a href="{{ route('admin.customers.show', $match['customer_id']) }}"
                               class="inline-flex items-center rounded-lg border border-cyan-500/30 bg-cyan-500/10 px-3 py-1.5 text-xs font-semibold text-cyan-200 hover:bg-cyan-500/20 transition">
                                View customer
                            </a>
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
@endif
