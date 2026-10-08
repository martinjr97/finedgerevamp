<div
    x-show="compensationModalOpen"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
    role="dialog"
    aria-modal="true"
    aria-labelledby="compensationModalTitle"
    @keydown.escape.window="compensationModalOpen = false"
>
    <div
        class="w-full max-w-lg rounded-3xl border border-white/10 bg-slate-900 p-6 shadow-2xl"
        @click.outside="compensationModalOpen = false"
    >
        <div class="flex items-start justify-between gap-3 mb-1">
            <div>
                <h3 id="compensationModalTitle" class="text-lg font-semibold text-white">Update basic pay</h3>
                <p class="mt-1 text-sm text-slate-400">
                    Record a new basic salary for {{ $employee->full_name }}. The previous amount stays in history with an end date.
                </p>
            </div>
            <button type="button" @click="compensationModalOpen = false" class="shrink-0 rounded-lg border border-white/10 px-2 py-1 text-slate-400 hover:bg-white/10 hover:text-white" aria-label="Close">✕</button>
        </div>

        @if ($employee->currentCompensation)
            <div class="mt-4 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm">
                <span class="text-slate-400">Current:</span>
                <span class="ml-1 font-semibold text-white">
                    {{ $employee->currentCompensation->currency ?? 'ZMW' }}
                    {{ number_format((float) $employee->currentCompensation->basic_pay, 2) }}
                </span>
                <span class="text-slate-500">· effective {{ $employee->currentCompensation->effective_from?->format('d M Y') }}</span>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.hr.employees.compensation.store', $employee) }}" class="mt-5 space-y-4">
            @csrf
            <div>
                <label for="compensation_basic_pay" class="text-sm font-medium text-slate-200">
                    New basic pay (ZMW) <span class="text-rose-400">*</span>
                </label>
                <input
                    id="compensation_basic_pay"
                    type="number"
                    step="0.01"
                    min="0"
                    name="basic_pay"
                    value="{{ old('basic_pay') }}"
                    required
                    class="mt-2 w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3 text-white focus:border-cyan-400 focus:ring-cyan-400/40 @error('basic_pay') border-rose-400 @enderror"
                    placeholder="e.g. 12500.00"
                >
                @error('basic_pay')
                    <p class="mt-1 text-xs text-rose-300">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="compensation_effective_from" class="text-sm font-medium text-slate-200">
                    Effective from <span class="text-rose-400">*</span>
                </label>
                <input
                    id="compensation_effective_from"
                    type="date"
                    name="effective_from"
                    value="{{ old('effective_from', now()->toDateString()) }}"
                    required
                    class="mt-2 w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3 text-white focus:border-cyan-400 focus:ring-cyan-400/40 @error('effective_from') border-rose-400 @enderror"
                >
                @error('effective_from')
                    <p class="mt-1 text-xs text-rose-300">{{ $message }}</p>
                @enderror
                <p class="mt-1.5 text-xs text-slate-500">The date this pay rate starts. Previous rates are closed on this date.</p>
            </div>
            <input type="hidden" name="currency" value="ZMW">

            <div class="flex flex-wrap justify-end gap-2 border-t border-white/10 pt-4">
                <button type="button" @click="compensationModalOpen = false" class="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-medium text-slate-300 hover:bg-white/10">
                    Cancel
                </button>
                <button type="submit" class="btn-primary rounded-xl px-5 py-2.5 text-sm font-semibold">
                    Save compensation
                </button>
            </div>
        </form>
    </div>
</div>
