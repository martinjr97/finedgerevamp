<div
    x-show="dependantModalOpen"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
    role="dialog"
    aria-modal="true"
    aria-labelledby="dependantModalTitle"
    @keydown.escape.window="dependantModalOpen = false"
>
    <div
        class="w-full max-w-lg max-h-[90vh] overflow-y-auto rounded-3xl border border-white/10 bg-slate-900 p-6 shadow-2xl"
        @click.outside="dependantModalOpen = false"
    >
        <div class="flex items-start justify-between gap-3 mb-1">
            <div>
                <h3 id="dependantModalTitle" class="text-lg font-semibold text-white">Add dependant</h3>
                <p class="mt-1 text-sm text-slate-400">
                    Record a dependant linked to {{ $employee->full_name }} (e.g. for benefits or records).
                </p>
            </div>
            <button type="button" @click="dependantModalOpen = false" class="shrink-0 rounded-lg border border-white/10 px-2 py-1 text-slate-400 hover:bg-white/10 hover:text-white" aria-label="Close">✕</button>
        </div>

        <form method="POST" action="{{ route('admin.hr.employees.dependants.store', $employee) }}" class="mt-5 space-y-4">
            @csrf
            <input type="hidden" name="_form" value="dependant">

            <div>
                <label for="dependant_full_name" class="text-sm font-medium text-slate-200">
                    Full name <span class="text-rose-400">*</span>
                </label>
                <input
                    id="dependant_full_name"
                    type="text"
                    name="full_name"
                    value="{{ old('_form') === 'dependant' ? old('full_name') : '' }}"
                    required
                    class="mt-2 w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3 text-white focus:border-cyan-400 focus:ring-cyan-400/40 @error('full_name') border-rose-400 @enderror"
                >
                @if (old('_form') === 'dependant')
                    @error('full_name')
                        <p class="mt-1 text-xs text-rose-300">{{ $message }}</p>
                    @enderror
                @endif
            </div>
            <div>
                <label for="dependant_relationship" class="text-sm font-medium text-slate-200">
                    Relationship <span class="text-rose-400">*</span>
                </label>
                <input
                    id="dependant_relationship"
                    type="text"
                    name="relationship"
                    value="{{ old('_form') === 'dependant' ? old('relationship') : '' }}"
                    required
                    placeholder="e.g. Child, Spouse"
                    class="mt-2 w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3 text-white focus:border-cyan-400 focus:ring-cyan-400/40 @error('relationship') border-rose-400 @enderror"
                >
                @if (old('_form') === 'dependant')
                    @error('relationship')
                        <p class="mt-1 text-xs text-rose-300">{{ $message }}</p>
                    @enderror
                @endif
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="dependant_date_of_birth" class="text-sm font-medium text-slate-200">Date of birth</label>
                    <input
                        id="dependant_date_of_birth"
                        type="date"
                        name="date_of_birth"
                        value="{{ old('_form') === 'dependant' ? old('date_of_birth') : '' }}"
                        class="mt-2 w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3 text-white focus:border-cyan-400 focus:ring-cyan-400/40 @error('date_of_birth') border-rose-400 @enderror"
                    >
                    @if (old('_form') === 'dependant')
                        @error('date_of_birth')
                            <p class="mt-1 text-xs text-rose-300">{{ $message }}</p>
                        @enderror
                    @endif
                </div>
                <div>
                    <label for="dependant_gender" class="text-sm font-medium text-slate-200">Gender</label>
                    <input
                        id="dependant_gender"
                        type="text"
                        name="gender"
                        value="{{ old('_form') === 'dependant' ? old('gender') : '' }}"
                        class="mt-2 w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3 text-white focus:border-cyan-400 focus:ring-cyan-400/40"
                    >
                </div>
            </div>
            <div>
                <label for="dependant_national_id" class="text-sm font-medium text-slate-200">National ID</label>
                <input
                    id="dependant_national_id"
                    type="text"
                    name="national_id"
                    value="{{ old('_form') === 'dependant' ? old('national_id') : '' }}"
                    class="mt-2 w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3 text-white focus:border-cyan-400 focus:ring-cyan-400/40"
                >
            </div>
            <div>
                <label for="dependant_notes" class="text-sm font-medium text-slate-200">Notes</label>
                <textarea
                    id="dependant_notes"
                    name="notes"
                    rows="2"
                    class="mt-2 w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3 text-white focus:border-cyan-400 focus:ring-cyan-400/40"
                >{{ old('_form') === 'dependant' ? old('notes') : '' }}</textarea>
            </div>

            <div class="flex flex-wrap justify-end gap-2 border-t border-white/10 pt-4">
                <button type="button" @click="dependantModalOpen = false" class="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-medium text-slate-300 hover:bg-white/10">
                    Cancel
                </button>
                <button type="submit" class="btn-primary rounded-xl px-5 py-2.5 text-sm font-semibold">
                    Save dependant
                </button>
            </div>
        </form>
    </div>
</div>
