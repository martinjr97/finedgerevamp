@if(!empty($arrearsCatchUpPrompt))
    <button type="button"
            onclick="openArrearsCatchUpModal()"
            class="w-full mt-4 flex items-center justify-between gap-3 rounded-2xl border-2 border-red-500/70 bg-red-500/15 px-4 py-3 text-left shadow-lg shadow-red-500/20 animate-pulse hover:bg-red-500/25 transition focus:outline-none focus:ring-2 focus:ring-red-400/60">
        <span class="flex items-center gap-2 text-sm font-semibold text-red-100">
            <svg class="h-5 w-5 shrink-0 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
            </svg>
            Missed arrears accruals — review required
        </span>
        <span class="text-xs font-medium text-red-200/90 shrink-0">View details</span>
    </button>
@endif
