@if(($arrearsSummary['overdue_installment_amount'] ?? 0) > 0 || ($arrearsSummary['outstanding_arrears_interest'] ?? 0) > 0)
    <div class="rounded-2xl border border-amber-500/30 bg-amber-500/5 p-5 mb-6">
        <h3 class="text-lg font-semibold text-amber-200 mb-4">Arrears Summary</h3>
        <dl class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 text-sm">
            <div>
                <dt class="text-slate-400">Overdue installments</dt>
                <dd class="text-white font-semibold">ZMW {{ number_format($arrearsSummary['overdue_installment_amount'], 2) }}</dd>
            </div>
            <div>
                <dt class="text-slate-400">Arrears interest accrued</dt>
                <dd class="text-white font-semibold">ZMW {{ number_format($arrearsSummary['arrears_interest_accrued'], 2) }}</dd>
            </div>
            <div>
                <dt class="text-slate-400">Outstanding arrears interest</dt>
                <dd class="text-white font-semibold">ZMW {{ number_format($arrearsSummary['outstanding_arrears_interest'], 2) }}</dd>
            </div>
            <div>
                <dt class="text-slate-400">Total arrears exposure</dt>
                <dd class="text-amber-200 font-bold">ZMW {{ number_format($arrearsSummary['total_arrears_exposure'], 2) }}</dd>
            </div>
            <div>
                <dt class="text-slate-400">Oldest overdue due date</dt>
                <dd class="text-white">{{ $arrearsSummary['oldest_overdue_date'] ? \Carbon\Carbon::parse($arrearsSummary['oldest_overdue_date'])->format('d M Y') : '—' }}</dd>
            </div>
            <div>
                <dt class="text-slate-400">Max days overdue</dt>
                <dd class="text-white">{{ $arrearsSummary['max_days_overdue'] }}</dd>
            </div>
            <div>
                <dt class="text-slate-400">Snapshotted arrear rate (daily factor)</dt>
                <dd class="text-white">{{ $arrearsSummary['arrear_rate_display'] ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-slate-400">NPL cutoff</dt>
                <dd class="text-white">{{ \Carbon\Carbon::parse($arrearsSummary['npl_cutoff_date'])->format('d M Y') }}</dd>
            </div>
            <div>
                <dt class="text-slate-400">Classification</dt>
                <dd class="text-white uppercase">{{ $arrearsSummary['performance_status'] ?? 'performing' }}</dd>
            </div>
        </dl>

        @if(!empty($arrearsStatementSegments))
            <details class="mt-5 border-t border-amber-500/20 pt-4 group [&_summary::-webkit-details-marker]:hidden">
                <summary class="cursor-pointer list-none flex items-center justify-between gap-2 text-xs uppercase tracking-wide text-slate-400 hover:text-slate-300 transition select-none">
                    <span>Arrears interest detail ({{ count($arrearsStatementSegments) }} installment{{ count($arrearsStatementSegments) === 1 ? '' : 's' }})</span>
                    <svg class="h-4 w-4 shrink-0 text-amber-400/80 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </summary>
                <div class="mt-3 space-y-3">
                    @foreach($arrearsStatementSegments as $segment)
                        <div class="rounded-xl bg-black/20 p-3 text-sm text-slate-200">
                            <p class="font-semibold text-white">Installment due {{ $segment['due_date'] ? \Carbon\Carbon::parse($segment['due_date'])->format('d M Y') : '—' }}</p>
                            <p>Base: ZMW {{ number_format($segment['opening_overdue_amount'], 2) }} · Rate: {{ $segment['arrear_rate_display'] ?? '—' }} per day</p>
                            <p>Period: {{ \Carbon\Carbon::parse($segment['period_start'])->format('d M Y') }} – {{ \Carbon\Carbon::parse($segment['period_end'])->format('d M Y') }} ({{ $segment['days_accrued'] }} day(s))</p>
                            <p class="text-amber-200 font-semibold">Arrears interest: ZMW {{ number_format($segment['arrears_interest'], 2) }}</p>
                        </div>
                    @endforeach
                </div>
            </details>
        @endif
    </div>
@endif
