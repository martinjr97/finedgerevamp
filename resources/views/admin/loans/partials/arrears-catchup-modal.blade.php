@if(!empty($arrearsCatchUpPrompt))
    <div id="arrearsCatchUpModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
        <div class="rounded-3xl border border-orange-500/30 bg-slate-900 w-full max-w-lg shadow-2xl max-h-[90vh] overflow-y-auto">
            <div class="p-6 space-y-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs uppercase tracking-[0.35em] text-orange-300">Arrears catch-up</p>
                        <h3 class="text-xl font-semibold text-white mt-1">Missed daily arrears accruals</h3>
                    </div>
                    <button type="button"
                            onclick="closeArrearsCatchUpModal()"
                            class="rounded-full p-2 text-slate-400 hover:text-white hover:bg-white/10 transition"
                            aria-label="Close">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <p class="text-sm text-slate-300">
                    The system found
                    <strong class="text-white">{{ number_format($arrearsCatchUpPrompt['missed_accrual_count']) }}</strong>
                    daily accrual row(s) not yet posted for this loan through
                    <strong class="text-white">{{ \Carbon\Carbon::parse($arrearsCatchUpPrompt['through_date'])->format('d M Y') }}</strong>.
                </p>

                <div class="rounded-2xl border border-white/10 bg-white/5 p-4 space-y-3 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Estimated arrears to post</span>
                        <span class="text-lg font-bold text-orange-200">ZMW {{ number_format((float) $arrearsCatchUpPrompt['total_arrears_charge'], 2) }}</span>
                    </div>
                    <div class="flex items-center justify-between border-t border-white/10 pt-3">
                        <span class="text-slate-400">Catch-up from</span>
                        <span class="text-white">{{ \Carbon\Carbon::parse($arrearsCatchUpPrompt['start_date'])->format('d M Y') }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Through</span>
                        <span class="text-white">{{ \Carbon\Carbon::parse($arrearsCatchUpPrompt['through_date'])->format('d M Y') }}</span>
                    </div>
                    @if(!empty($arrearsCatchUpPrompt['engine_effective_date']))
                        <div class="flex items-center justify-between border-t border-white/10 pt-3">
                            <span class="text-slate-400">Engine effective date</span>
                            <span class="text-white">{{ \Carbon\Carbon::parse($arrearsCatchUpPrompt['engine_effective_date'])->format('d M Y') }}</span>
                        </div>
                    @endif
                </div>

                <p class="text-xs text-slate-500">
                    Approving posts the missing daily arrears ledger rows using the same rules as the scheduled accrual job.
                    Rejecting hides this reminder permanently for this loan.
                </p>

                <div class="flex flex-col-reverse sm:flex-row gap-3 pt-2">
                    @can('loans.view')
                        <form method="POST" action="{{ route('admin.loans.arrears-catchup.dismiss', $loan) }}" class="flex-1"
                              onsubmit="return confirm('Dismiss this reminder permanently for this loan? Missed accruals will not be posted automatically.');">
                            @csrf
                            <button type="submit"
                                    class="w-full rounded-2xl border border-white/20 bg-white/5 px-4 py-2.5 text-sm font-semibold text-slate-200 hover:bg-white/10 transition">
                                Reject (don&apos;t ask again)
                            </button>
                        </form>
                    @endcan
                    @can('loans.disburse')
                        <form method="POST" action="{{ route('admin.loans.arrears-catchup.apply', $loan) }}" class="flex-1"
                              onsubmit="return confirm('Post ZMW {{ number_format((float) $arrearsCatchUpPrompt['total_arrears_charge'], 2) }} in missed arrears accruals for this loan?');">
                            @csrf
                            <button type="submit"
                                    class="w-full rounded-2xl border border-emerald-400/40 bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-500 transition">
                                Approve &amp; post accruals
                            </button>
                        </form>
                    @endcan
                </div>
            </div>
        </div>
    </div>
@endif
