@php
    $isEdit = isset($employee) && $employee && $employee->exists;
@endphp

<form action="{{ $isEdit ? route('admin.employees.update', $employee) : route('admin.employees.store') }}" method="POST" class="space-y-8">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4 max-w-2xl">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="text-sm font-medium text-slate-300">First Name <span class="text-rose-400">*</span></label>
                <input type="text" name="first_name" value="{{ old('first_name', $isEdit ? $employee->first_name : '') }}" required
                       class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                @error('first_name')
                    <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm font-medium text-slate-300">Last Name <span class="text-rose-400">*</span></label>
                <input type="text" name="last_name" value="{{ old('last_name', $isEdit ? $employee->last_name : '') }}" required
                       class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                @error('last_name')
                    <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div>
            <label class="text-sm font-medium text-slate-300">Employee Number</label>
            <input type="text" name="employee_number" value="{{ old('employee_number', $isEdit ? $employee->employee_number : '') }}"
                   class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
            @error('employee_number')
                <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="text-sm font-medium text-slate-300">Phone</label>
                <input type="text" name="phone" value="{{ old('phone', $isEdit ? $employee->phone : '') }}"
                       class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                @error('phone')
                    <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm font-medium text-slate-300">Email</label>
                <input type="email" name="email" value="{{ old('email', $isEdit ? $employee->email : '') }}"
                       class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                @error('email')
                    <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div>
            <label class="text-sm font-medium text-slate-300">Department</label>
            <input type="text" name="department" value="{{ old('department', $isEdit ? $employee->department : '') }}"
                   class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
            @error('department')
                <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        <label class="inline-flex items-center gap-2 text-sm text-slate-300">
            <input type="checkbox" name="is_active" value="1" class="rounded border-white/20 bg-white/10 text-cyan-400 focus:ring-cyan-500/30" @checked(old('is_active', $isEdit ? $employee->is_active : true))>
            Active
        </label>
    </div>

    <div class="flex items-center justify-end gap-3">
        <a href="{{ route('admin.employees.index') }}" class="inline-flex items-center gap-2 rounded-2xl border border-white/20 bg-white/5 px-4 py-3 text-base font-medium text-slate-300 hover:bg-white/10 hover:border-white/30 transition">
            Cancel
        </a>
        <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-blue-500 to-blue-600 px-4 py-3 text-base font-semibold text-white shadow-lg shadow-blue-500/30 hover:from-blue-600 hover:to-blue-700 transition">
            {{ $isEdit ? 'Update Employee' : 'Add Employee' }}
        </button>
    </div>
</form>
