<div
    x-show="kinModalOpen"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
    role="dialog"
    aria-modal="true"
    aria-labelledby="kinModalTitle"
    @keydown.escape.window="kinModalOpen = false"
>
    <div
        class="w-full max-w-lg max-h-[90vh] overflow-y-auto rounded-3xl border border-white/10 bg-slate-900 p-6 shadow-2xl"
        @click.outside="kinModalOpen = false"
    >
        <div class="flex items-start justify-between gap-3 mb-1">
            <div>
                <h3 id="kinModalTitle" class="text-lg font-semibold text-white">Add next of kin</h3>
                <p class="mt-1 text-sm text-slate-400">
                    Emergency contact for {{ $employee->full_name }}.
                </p>
            </div>
            <button type="button" @click="kinModalOpen = false" class="shrink-0 rounded-lg border border-white/10 px-2 py-1 text-slate-400 hover:bg-white/10 hover:text-white" aria-label="Close">✕</button>
        </div>

        <form method="POST" action="{{ route('admin.hr.employees.next-of-kin.store', $employee) }}" class="mt-5 space-y-4">
            @csrf
            <input type="hidden" name="_form" value="next_of_kin">

            <div>
                <label for="kin_full_name" class="text-sm font-medium text-slate-200">
                    Full name <span class="text-rose-400">*</span>
                </label>
                <input
                    id="kin_full_name"
                    type="text"
                    name="full_name"
                    value="{{ old('_form') === 'next_of_kin' ? old('full_name') : '' }}"
                    required
                    class="mt-2 w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3 text-white focus:border-cyan-400 focus:ring-cyan-400/40 @error('full_name') border-rose-400 @enderror"
                >
                @if (old('_form') === 'next_of_kin')
                    @error('full_name')
                        <p class="mt-1 text-xs text-rose-300">{{ $message }}</p>
                    @enderror
                @endif
            </div>
            <div>
                <label for="kin_relationship" class="text-sm font-medium text-slate-200">
                    Relationship <span class="text-rose-400">*</span>
                </label>
                <input
                    id="kin_relationship"
                    type="text"
                    name="relationship"
                    value="{{ old('_form') === 'next_of_kin' ? old('relationship') : '' }}"
                    required
                    placeholder="e.g. Spouse, Parent, Sibling"
                    class="mt-2 w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3 text-white focus:border-cyan-400 focus:ring-cyan-400/40 @error('relationship') border-rose-400 @enderror"
                >
                @if (old('_form') === 'next_of_kin')
                    @error('relationship')
                        <p class="mt-1 text-xs text-rose-300">{{ $message }}</p>
                    @enderror
                @endif
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="kin_phone_number" class="text-sm font-medium text-slate-200">Phone</label>
                    <input
                        id="kin_phone_number"
                        type="text"
                        name="phone_number"
                        value="{{ old('_form') === 'next_of_kin' ? old('phone_number') : '' }}"
                        class="mt-2 w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3 text-white focus:border-cyan-400 focus:ring-cyan-400/40 @error('phone_number') border-rose-400 @enderror"
                    >
                    @if (old('_form') === 'next_of_kin')
                        @error('phone_number')
                            <p class="mt-1 text-xs text-rose-300">{{ $message }}</p>
                        @enderror
                    @endif
                </div>
                <div>
                    <label for="kin_alternative_phone" class="text-sm font-medium text-slate-200">Alternative phone</label>
                    <input
                        id="kin_alternative_phone"
                        type="text"
                        name="alternative_phone"
                        value="{{ old('_form') === 'next_of_kin' ? old('alternative_phone') : '' }}"
                        class="mt-2 w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3 text-white focus:border-cyan-400 focus:ring-cyan-400/40"
                    >
                </div>
            </div>
            <div>
                <label for="kin_email" class="text-sm font-medium text-slate-200">Email</label>
                <input
                    id="kin_email"
                    type="email"
                    name="email"
                    value="{{ old('_form') === 'next_of_kin' ? old('email') : '' }}"
                    class="mt-2 w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3 text-white focus:border-cyan-400 focus:ring-cyan-400/40 @error('email') border-rose-400 @enderror"
                >
                @if (old('_form') === 'next_of_kin')
                    @error('email')
                        <p class="mt-1 text-xs text-rose-300">{{ $message }}</p>
                    @enderror
                @endif
            </div>
            <div>
                <label for="kin_address" class="text-sm font-medium text-slate-200">Address</label>
                <textarea
                    id="kin_address"
                    name="address"
                    rows="2"
                    class="mt-2 w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3 text-white focus:border-cyan-400 focus:ring-cyan-400/40"
                >{{ old('_form') === 'next_of_kin' ? old('address') : '' }}</textarea>
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-300">
                <input
                    type="checkbox"
                    name="is_primary"
                    value="1"
                    @checked(old('_form') === 'next_of_kin' && old('is_primary'))
                    class="rounded border-white/20 bg-white/10 text-cyan-500 focus:ring-cyan-400/40"
                >
                Set as primary next of kin
            </label>

            <div class="flex flex-wrap justify-end gap-2 border-t border-white/10 pt-4">
                <button type="button" @click="kinModalOpen = false" class="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-medium text-slate-300 hover:bg-white/10">
                    Cancel
                </button>
                <button type="submit" class="btn-primary rounded-xl px-5 py-2.5 text-sm font-semibold">
                    Save next of kin
                </button>
            </div>
        </form>
    </div>
</div>
