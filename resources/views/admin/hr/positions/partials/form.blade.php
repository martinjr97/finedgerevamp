<div>
    <label class="text-sm text-slate-300">Name <span class="text-rose-400">*</span></label>
    <input name="name" required value="{{ old('name', $position->name ?? '') }}" class="mt-1 w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-2 text-white">
</div>
<div>
    <label class="text-sm text-slate-300">Code</label>
    <input name="code" value="{{ old('code', $position->code ?? '') }}" class="mt-1 w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-2 text-white" placeholder="Auto-generated if empty">
</div>
<div>
    <label class="text-sm text-slate-300">Department</label>
    <select name="department_id" class="mt-1 w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-2 text-white">
        <option value="">Organisation-wide</option>
        @foreach ($departments as $dept)
            <option value="{{ $dept->id }}" @selected((int) old('department_id', $position->department_id ?? 0) === $dept->id)>{{ $dept->name }}</option>
        @endforeach
    </select>
</div>
<div>
    <label class="text-sm text-slate-300">Description</label>
    <textarea name="description" rows="2" class="mt-1 w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-2 text-white">{{ old('description', $position->description ?? '') }}</textarea>
</div>
<label class="flex items-center gap-2 text-sm text-slate-300">
    <input type="checkbox" name="is_active" value="1" class="rounded border-white/20 bg-white/10 text-cyan-500" @checked(old('is_active', $position->is_active ?? true))>
    Active
</label>
