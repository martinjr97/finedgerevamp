@if ($errors->any())
    <div
        id="form-validation-errors"
        class="rounded-2xl border-2 border-rose-500/50 bg-rose-950/40 p-4 shadow-lg"
        role="alert"
        aria-live="polite"
    >
        <div class="flex items-start gap-3">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <div class="min-w-0 flex-1">
                <p class="font-semibold text-rose-100">Please correct the highlighted fields below</p>
                <p class="mt-1 text-sm text-rose-200/90">Your other entries have been kept — you only need to fix what is listed here.</p>
                <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-rose-100">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
@endif
