@php
    $isEdit = isset($province) && $province && $province->exists;
@endphp

<form action="{{ $isEdit ? route('admin.provinces.update', $province) : route('admin.provinces.store') }}" method="POST" class="space-y-8">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4 max-w-2xl">
        <div>
            <label class="text-sm font-medium text-slate-300">Name</label>
            <input type="text" name="name" value="{{ old('name', $isEdit ? $province->name : '') }}" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
        </div>
        <div>
            <label class="text-sm font-medium text-slate-300">Code</label>
            <input type="text" name="code" value="{{ old('code', $isEdit ? $province->code : '') }}" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40" placeholder="e.g. NAI">
        </div>
        <div>
            <label class="text-sm font-medium text-slate-300">Country</label>
            <input type="text" name="country" value="{{ old('country', $isEdit ? $province->country : 'Zambia') }}" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
        </div>
        <label class="inline-flex items-center gap-2 text-sm text-slate-300">
            <input type="checkbox" name="is_active" value="1" class="rounded border-white/20 bg-white/10 text-cyan-400 focus:ring-cyan-500/30" @checked(old('is_active', $isEdit ? $province->is_active : true))>
            Active
        </label>
    </div>

    <div class="flex items-center justify-end gap-3">
        <a href="{{ route('admin.provinces.index') }}" class="inline-flex items-center gap-2 rounded-2xl border border-white/20 bg-white/5 px-4 py-3 text-base font-medium text-slate-300 hover:bg-white/10 hover:border-white/30 transition">
            Cancel
        </a>
        <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-blue-500 to-blue-600 px-4 py-3 text-base font-semibold text-white shadow-lg shadow-blue-500/30 hover:from-blue-600 hover:to-blue-700 transition">
            {{ $isEdit ? 'Update Province' : 'Create Province' }}
        </button>
    </div>
</form>

