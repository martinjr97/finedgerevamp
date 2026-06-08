@php
    $isEdit = isset($user);
    $userRoles = $isEdit ? $user->roles->pluck('name')->toArray() : [];
@endphp

<form action="{{ $isEdit ? route('admin.users.update', $user) : route('admin.users.store') }}" method="POST" class="space-y-8">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="grid gap-6 md:grid-cols-2">
        <div class="rounded-3xl border border-muted bg-soft-white p-6 shadow space-y-4">
            <div>
                <label class="text-sm font-medium text-primary">First Name</label>
                <input type="text" name="first_name" value="{{ old('first_name', $user->first_name ?? '') }}" required class="mt-2 w-full rounded-2xl bg-white border border-muted text-primary px-4 py-3 focus:border-primary focus:ring-primary/20">
            </div>
            <div>
                <label class="text-sm font-medium text-primary">Last Name</label>
                <input type="text" name="last_name" value="{{ old('last_name', $user->last_name ?? '') }}" required class="mt-2 w-full rounded-2xl bg-white border border-muted text-primary px-4 py-3 focus:border-primary focus:ring-primary/20">
            </div>
            <div>
                <label class="text-sm font-medium text-primary">Email</label>
                <input type="email" name="email" value="{{ old('email', $user->email ?? '') }}" required class="mt-2 w-full rounded-2xl bg-white border border-muted text-primary px-4 py-3 focus:border-primary focus:ring-primary/20">
            </div>
            <div>
                <label class="text-sm font-medium text-primary">Phone</label>
                <input type="text" name="phone" value="{{ old('phone', $user->phone ?? '') }}" class="mt-2 w-full rounded-2xl bg-white border border-muted text-primary px-4 py-3 focus:border-primary focus:ring-primary/20" placeholder="Optional contact number">
            </div>
        </div>

        <div class="rounded-3xl border border-muted bg-soft-white p-6 shadow space-y-4">
            <div>
                <label class="text-sm font-medium text-primary">Company</label>
                <select name="company_id" required class="mt-2 w-full rounded-2xl bg-white border border-muted text-primary px-4 py-3 focus:border-primary focus:ring-primary/20">
                    <option value="">Select company</option>
                    @foreach ($companies as $company)
                        <option value="{{ $company->id }}" @selected(old('company_id', $user->company_id ?? '') == $company->id)>
                            {{ $company->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-sm font-medium text-primary">Branch</label>
                <select name="branch_id" required class="mt-2 w-full rounded-2xl bg-white border border-muted text-primary px-4 py-3 focus:border-primary focus:ring-primary/20">
                    <option value="">Select branch</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected(old('branch_id', $user->branch_id ?? '') == $branch->id)>
                            {{ $branch->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-sm font-medium text-primary">Employee Number</label>
                <input type="text" name="employee_number" value="{{ old('employee_number', $user->employee_number ?? '') }}" class="mt-2 w-full rounded-2xl bg-white border border-muted text-primary px-4 py-3 focus:border-primary focus:ring-primary/20" placeholder="EMP-001">
                @error('employee_number')
                    <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm font-medium text-primary">NRC Number</label>
                <input type="text" name="nrc" value="{{ old('nrc', $user->nrc ?? '') }}" class="mt-2 w-full rounded-2xl bg-white border border-muted text-primary px-4 py-3 focus:border-primary focus:ring-primary/20" placeholder="123456/78/9012">
                @error('nrc')
                    <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm font-medium text-primary">Roles</label>
                <select name="role_names[]" multiple class="mt-2 w-full min-h-[110px] rounded-2xl bg-white border border-muted text-primary px-4 py-3 focus:border-primary focus:ring-primary/20">
                    @foreach ($roles as $role)
                        <option value="{{ $role->name }}" @selected(in_array($role->name, old('role_names', $userRoles)))>
                            {{ $role->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            @if (!$isEdit)
                <p class="text-sm text-muted">
                    A secure temporary password will be generated automatically and emailed to the admin. They'll be required to change it on first login.
                </p>
            @endif
            <div class="space-y-3">
                <label class="inline-flex items-center gap-2 text-sm text-primary">
                    <input type="checkbox" name="is_active" value="1" class="rounded border-muted bg-white text-primary focus:ring-primary/30" @checked(old('is_active', $isEdit ? $user->is_active : true))>
                    Active
                </label>
                <label class="inline-flex items-center gap-2 text-sm text-primary">
                    <input type="checkbox" name="is_relationship_manager" value="1" class="rounded border-muted bg-white text-primary focus:ring-primary/30" @checked(old('is_relationship_manager', $isEdit ? $user->is_relationship_manager : false))>
                    Relationship Manager
                </label>
            </div>
        </div>
    </div>

    <div class="flex items-center justify-end gap-3">
        <a href="{{ route('admin.users.index') }}" class="btn-secondary inline-flex items-center gap-2 rounded-2xl px-4 py-3 text-base font-medium">
            Cancel
        </a>
        <button type="submit" class="btn-primary inline-flex items-center gap-2 rounded-2xl px-4 py-3 text-base font-semibold">
            {{ $isEdit ? 'Update Admin' : 'Create Admin' }}
        </button>
    </div>
</form>
