@php
    $isEdit = isset($loanPurpose) && $loanPurpose && $loanPurpose->exists;
@endphp

<form action="{{ $isEdit ? route('admin.loan-purposes.update', $loanPurpose) : route('admin.loan-purposes.store') }}" method="POST" class="space-y-8">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4 max-w-2xl">
        <div>
            <label class="text-sm font-medium text-slate-300">Name</label>
            <input type="text" name="name" value="{{ old('name', $isEdit ? $loanPurpose->name : '') }}" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
            @error('name')
                <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label class="text-sm font-medium text-slate-300">Description</label>
            <textarea name="description" rows="3" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">{{ old('description', $isEdit ? $loanPurpose->description : '') }}</textarea>
        </div>
        <div>
            <label class="text-sm font-medium text-slate-300">Sort Order</label>
            <input type="number" name="sort_order" min="0" value="{{ old('sort_order', $isEdit ? $loanPurpose->sort_order : 0) }}" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
            <p class="mt-1 text-xs text-slate-400">Lower numbers appear first in loan application dropdowns.</p>
        </div>
        <label class="inline-flex items-center gap-2 text-sm text-slate-300">
            <input type="checkbox" name="is_active" value="1" class="rounded border-white/20 bg-white/10 text-cyan-400 focus:ring-cyan-500/30" @checked(old('is_active', $isEdit ? $loanPurpose->is_active : true))>
            Active
        </label>
    </div>

    <div class="flex items-center justify-end gap-3">
        <a href="{{ route('admin.loan-purposes.index') }}" class="inline-flex items-center gap-2 rounded-2xl border border-white/20 bg-white/5 px-4 py-3 text-base font-medium text-slate-300 hover:bg-white/10 hover:border-white/30 transition">
            Cancel
        </a>
        <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-blue-500 to-blue-600 px-4 py-3 text-base font-semibold text-white shadow-lg shadow-blue-500/30 hover:from-blue-600 hover:to-blue-700 transition">
            {{ $isEdit ? 'Update Loan Purpose' : 'Create Loan Purpose' }}
        </button>
    </div>
</form>
