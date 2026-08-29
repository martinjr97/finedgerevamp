<div>
    <label class="text-sm font-medium text-slate-300">Name <span class="text-rose-400">*</span></label>
    <input type="text" name="name" value="{{ old('name') }}" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3">
    @error('name')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
</div>

<div>
    <label class="text-sm font-medium text-slate-300">Code</label>
    <input type="text" name="code" value="{{ old('code') }}" placeholder="Auto-generated from name if left blank" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3">
    @error('code')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
</div>

<div>
    <label class="text-sm font-medium text-slate-300">Description</label>
    <textarea name="description" rows="3" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3">{{ old('description') }}</textarea>
    @error('description')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
</div>

<div>
    <label class="inline-flex items-center gap-2 text-sm text-slate-300">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true)) class="rounded border-white/20 bg-white/10 text-cyan-500">
        Active
    </label>
</div>
