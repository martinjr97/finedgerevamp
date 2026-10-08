<div
    x-show="adjustmentModalOpen"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
    role="dialog"
    aria-modal="true"
    aria-labelledby="leaveAdjustmentModalTitle"
    @keydown.escape.window="adjustmentModalOpen = false"
>
    <div
        class="w-full max-w-lg rounded-3xl border border-white/10 bg-slate-900 p-6 shadow-2xl"
        @click.outside="adjustmentModalOpen = false"
    >
        <div class="flex items-start justify-between gap-3 mb-1">
            <div>
                <h3 id="leaveAdjustmentModalTitle" class="text-lg font-semibold text-white">Manual leave adjustment</h3>
                <p class="mt-1 text-sm text-slate-400">Add or subtract days. Use negative values to deduct.</p>
            </div>
            <button type="button" @click="adjustmentModalOpen = false" class="shrink-0 rounded-lg border border-white/10 px-2 py-1 text-slate-400 hover:bg-white/10 hover:text-white" aria-label="Close">✕</button>
        </div>

        <form method="POST" action="{{ route('admin.hr.leave.balances.adjust') }}" class="mt-5 space-y-4">
            @csrf
            <input type="hidden" name="employee_id" :value="adjustmentEmployeeId">
            <input type="hidden" name="return_employee_id" :value="returnEmployeeId ?? ''">

            <div>
                <label for="adjust_employee_id" class="text-sm font-medium text-slate-200">
                    Employee <span class="text-rose-400">*</span>
                </label>
                <select
                    id="adjust_employee_id"
                    required
                    x-model.number="adjustmentEmployeeId"
                    class="mt-2 w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3 text-sm text-white focus:border-cyan-400 focus:ring-cyan-400/40 @error('employee_id') border-rose-400 @enderror"
                >
                    <option value="" disabled>Select employee</option>
                    @foreach ($employees as $emp)
                        <option value="{{ $emp->id }}">{{ $emp->full_name }} ({{ $emp->employee_number ?? '—' }})</option>
                    @endforeach
                </select>
                @error('employee_id')
                    <p class="mt-1 text-xs text-rose-300">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="adjust_leave_type_id" class="text-sm font-medium text-slate-200">
                    Leave type <span class="text-rose-400">*</span>
                </label>
                <select
                    id="adjust_leave_type_id"
                    name="leave_type_id"
                    required
                    class="mt-2 w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3 text-sm text-white focus:border-cyan-400 focus:ring-cyan-400/40 @error('leave_type_id') border-rose-400 @enderror"
                >
                    @foreach ($leaveTypes as $type)
                        <option value="{{ $type->id }}" @selected((int) old('leave_type_id') === $type->id)>{{ $type->name }}</option>
                    @endforeach
                </select>
                @error('leave_type_id')
                    <p class="mt-1 text-xs text-rose-300">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="adjust_days" class="text-sm font-medium text-slate-200">
                    Days (+ / −) <span class="text-rose-400">*</span>
                </label>
                <input
                    id="adjust_days"
                    type="number"
                    step="0.01"
                    name="days"
                    value="{{ old('days') }}"
                    required
                    placeholder="e.g. 2 or -1"
                    class="mt-2 w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3 text-white focus:border-cyan-400 focus:ring-cyan-400/40 @error('days') border-rose-400 @enderror"
                >
                @error('days')
                    <p class="mt-1 text-xs text-rose-300">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="adjust_description" class="text-sm font-medium text-slate-200">Note</label>
                <input
                    id="adjust_description"
                    type="text"
                    name="description"
                    value="{{ old('description') }}"
                    placeholder="Reason for adjustment"
                    class="mt-2 w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3 text-white focus:border-cyan-400 focus:ring-cyan-400/40 @error('description') border-rose-400 @enderror"
                >
                @error('description')
                    <p class="mt-1 text-xs text-rose-300">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-wrap justify-end gap-2 border-t border-white/10 pt-4">
                <button type="button" @click="adjustmentModalOpen = false" class="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-medium text-slate-300 hover:bg-white/10">
                    Cancel
                </button>
                <button type="submit" class="btn-primary rounded-xl px-5 py-2.5 text-sm font-semibold" :disabled="!adjustmentEmployeeId">
                    Apply adjustment
                </button>
            </div>
        </form>
    </div>
</div>
