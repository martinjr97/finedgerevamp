@php
    $isEdit = isset($role);
    $rolePermissions = $isEdit ? $role->permissions->pluck('name')->toArray() : [];
@endphp

<form action="{{ $isEdit ? route('admin.roles.update', $role) : route('admin.roles.store') }}" method="POST" class="space-y-8">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
        <div>
            <label class="text-sm font-medium text-slate-300">Role Name</label>
            <input
                type="text"
                name="name"
                value="{{ old('name', $role->name ?? '') }}"
                required
                class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 placeholder:text-slate-500 focus:border-cyan-400 focus:ring-cyan-400/40"
                placeholder="e.g. Loan Approver"
            />
            @error('name')
                <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        @if ($isEdit)
            <div>
                <label class="text-sm font-medium text-slate-300">Assign Admins</label>
                <select
                    name="admin_ids[]"
                    multiple
                    class="mt-2 w-full min-h-[110px] rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40"
                >
                </select>
            </div>
        @endif
    </div>

    <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-semibold text-white">Permissions Map</p>
                <p class="text-xs text-slate-400">Toggle granular actions per module for this role.</p>
            </div>
            <button type="button" onclick="document.querySelectorAll('.permission-checkbox').forEach(cb => cb.checked = true);" class="text-xs text-cyan-300 hover:text-white">
                Select All
            </button>
        </div>

        <div class="space-y-4">
            @foreach ($permissionGroups as $group => $permissions)
                <details class="group rounded-2xl border border-white/10 bg-white/0">
                    <summary class="flex items-center justify-between px-4 py-3 cursor-pointer list-none">
                        <div>
                            <p class="text-sm font-semibold text-white">{{ $group }}</p>
                            <p class="text-xs text-slate-500">{{ count($permissions) }} actions</p>
                        </div>
                        <span class="text-slate-400 text-xs">Expand</span>
                    </summary>
                    <div class="px-4 pb-4 grid gap-3 sm:grid-cols-2">
                        @foreach ($permissions as $permission)
                            <label class="flex items-center gap-3 rounded-2xl bg-white/5 px-3 py-2 text-sm text-slate-200">
                                <input
                                    type="checkbox"
                                    name="permissions[]"
                                    value="{{ $permission['name'] }}"
                                    class="permission-checkbox rounded border-white/20 bg-white/10 text-cyan-400 focus:ring-cyan-500/30"
                                    @checked(in_array($permission['name'], old('permissions', $rolePermissions)))
                                >
                                <span>{{ $permission['label'] }}</span>
                            </label>
                        @endforeach
                    </div>
                </details>
            @endforeach
        </div>
    </div>

    <div class="flex items-center justify-end gap-3">
        <a href="{{ route('admin.roles.index') }}" class="inline-flex items-center gap-2 rounded-2xl border border-white/20 bg-white/5 px-4 py-3 text-base font-medium text-slate-300 hover:bg-white/10 hover:border-white/30 transition">
            Cancel
        </a>
        <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-blue-500 to-blue-600 px-4 py-3 text-base font-semibold text-white shadow-lg shadow-blue-500/30 hover:from-blue-600 hover:to-blue-700 transition">
            {{ $isEdit ? 'Update Role' : 'Create Role' }}
        </button>
    </div>
</form>

