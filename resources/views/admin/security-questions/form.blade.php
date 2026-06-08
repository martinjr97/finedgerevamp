@php
    $isEdit = isset($securityQuestion) && $securityQuestion && $securityQuestion->exists;
@endphp

<form action="{{ $isEdit ? route('admin.security-questions.update', $securityQuestion) : route('admin.security-questions.store') }}" method="POST" class="space-y-8">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4 max-w-2xl">
        <div>
            <label class="text-sm font-medium text-slate-300">Question</label>
            <textarea name="question" rows="3" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40" placeholder="e.g. What was the name of your first pet?">{{ old('question', $isEdit ? $securityQuestion->question : '') }}</textarea>
        </div>
        <div>
            <label class="text-sm font-medium text-slate-300">Sort Order</label>
            <input type="number" name="sort_order" value="{{ old('sort_order', $isEdit ? $securityQuestion->sort_order : 0) }}" min="0" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
            <p class="mt-1 text-xs text-slate-400">Lower numbers appear first</p>
        </div>
        <label class="inline-flex items-center gap-2 text-sm text-slate-300">
            <input type="checkbox" name="is_active" value="1" class="rounded border-white/20 bg-white/10 text-cyan-400 focus:ring-cyan-500/30" @checked(old('is_active', $isEdit ? $securityQuestion->is_active : true))>
            Active
        </label>
    </div>

    <div class="flex items-center justify-end gap-3">
        <a href="{{ route('admin.security-questions.index') }}" class="inline-flex items-center gap-2 rounded-2xl border border-white/20 bg-white/5 px-4 py-3 text-base font-medium text-slate-300 hover:bg-white/10 hover:border-white/30 transition">
            Cancel
        </a>
        <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-blue-500 to-blue-600 px-4 py-3 text-base font-semibold text-white shadow-lg shadow-blue-500/30 hover:from-blue-600 hover:to-blue-700 transition">
            {{ $isEdit ? 'Update Question' : 'Create Question' }}
        </button>
    </div>
</form>

