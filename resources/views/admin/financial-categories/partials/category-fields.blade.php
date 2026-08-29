<div>
    <label class="text-sm font-medium text-slate-300">Name <span class="text-rose-400">*</span></label>
    <input type="text" name="name" value="{{ old('name', $model->name ?? '') }}" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3">
    @error('name')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
</div>

<div>
    <label class="text-sm font-medium text-slate-300">Code</label>
    <input type="text" name="code" value="{{ old('code', $model->code ?? '') }}" placeholder="Auto-generated from name if left blank" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3">
    @error('code')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
</div>

<div>
    <label class="text-sm font-medium text-slate-300">Description</label>
    <textarea name="description" rows="3" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3">{{ old('description', $model->description ?? '') }}</textarea>
    @error('description')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label class="text-sm font-medium text-slate-300">Sort order</label>
        <input type="number" name="sort_order" min="0" value="{{ old('sort_order', $model->sort_order ?? 0) }}" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3">
    </div>
    <div class="flex items-end">
        <label class="inline-flex items-center gap-2 text-sm text-slate-300">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $model->is_active ?? true)) class="rounded border-white/20 bg-white/10 text-cyan-500">
            Active
        </label>
    </div>
</div>
