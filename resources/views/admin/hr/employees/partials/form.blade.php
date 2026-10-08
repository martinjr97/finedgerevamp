<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div><label class="text-sm text-slate-300">Employee Number</label><input name="employee_number" value="{{ old('employee_number', $employee->employee_number ?? '') }}" class="mt-1 w-full rounded-2xl bg-white/10 border border-white/10 px-4 py-2 text-white"></div>
    <div><label class="text-sm text-slate-300">Title</label>
        <select name="title" class="mt-1 w-full rounded-2xl bg-white/10 border border-white/10 px-4 py-2 text-white">
            <option value="">—</option>
            @foreach ($employeeTitles as $employeeTitle)
                <option value="{{ $employeeTitle }}" @selected(old('title', $employee->title ?? '') === $employeeTitle)>{{ $employeeTitle }}</option>
            @endforeach
        </select>
    </div>
    <div><label class="text-sm text-slate-300">First Name *</label><input name="first_name" required value="{{ old('first_name', $employee->first_name ?? '') }}" class="mt-1 w-full rounded-2xl bg-white/10 border border-white/10 px-4 py-2 text-white"></div>
    <div><label class="text-sm text-slate-300">Middle Name</label><input name="middle_name" value="{{ old('middle_name', $employee->middle_name ?? '') }}" class="mt-1 w-full rounded-2xl bg-white/10 border border-white/10 px-4 py-2 text-white"></div>
    <div><label class="text-sm text-slate-300">Last Name *</label><input name="last_name" required value="{{ old('last_name', $employee->last_name ?? '') }}" class="mt-1 w-full rounded-2xl bg-white/10 border border-white/10 px-4 py-2 text-white"></div>
    <div><label class="text-sm text-slate-300">Employment Status *</label>
        <select name="employment_status" class="mt-1 w-full rounded-2xl bg-white/10 border border-white/10 px-4 py-2 text-white">
            @foreach ($employmentStatuses as $status)
                <option value="{{ $status }}" @selected(old('employment_status', $employee->employment_status ?? 'active') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
    </div>
    <div><label class="text-sm text-slate-300">Department</label>
        <select name="department_id" class="mt-1 w-full rounded-2xl bg-white/10 border border-white/10 px-4 py-2 text-white">
            <option value="">—</option>
            @foreach ($departments as $dept)
                <option value="{{ $dept->id }}" @selected((int) old('department_id', $employee->department_id ?? 0) === $dept->id)>{{ $dept->name }}</option>
            @endforeach
        </select>
    </div>
    <div><label class="text-sm text-slate-300">Position</label>
        <select name="position_id" class="mt-1 w-full rounded-2xl bg-white/10 border border-white/10 px-4 py-2 text-white">
            <option value="">—</option>
            @foreach ($positions as $pos)
                <option value="{{ $pos->id }}" @selected((int) old('position_id', $employee->position_id ?? 0) === $pos->id)>{{ $pos->name }}</option>
            @endforeach
        </select>
    </div>
    <div><label class="text-sm text-slate-300">Email</label><input type="email" name="email" value="{{ old('email', $employee->email ?? '') }}" class="mt-1 w-full rounded-2xl bg-white/10 border border-white/10 px-4 py-2 text-white"></div>
    <div><label class="text-sm text-slate-300">Phone</label><input name="phone" value="{{ old('phone', $employee->phone ?? '') }}" class="mt-1 w-full rounded-2xl bg-white/10 border border-white/10 px-4 py-2 text-white"></div>
    <div><label class="text-sm text-slate-300">Branch (work location)</label>
        <select name="branch_id" class="mt-1 w-full rounded-2xl bg-white/10 border border-white/10 px-4 py-2 text-white">
            <option value="">—</option>
            @foreach ($branches as $branch)
                <option value="{{ $branch->id }}" @selected((int) old('branch_id', $employee->branch_id ?? 0) === $branch->id)>{{ $branch->name }}</option>
            @endforeach
        </select>
    </div>
    <div><label class="text-sm text-slate-300">Date Joined</label><input type="date" name="date_joined" value="{{ old('date_joined', optional($employee->date_joined ?? null)->format('Y-m-d')) }}" class="mt-1 w-full rounded-2xl bg-white/10 border border-white/10 px-4 py-2 text-white"></div>
    <div><label class="text-sm text-slate-300">National ID</label><input name="national_id" value="{{ old('national_id', $employee->national_id ?? '') }}" class="mt-1 w-full rounded-2xl bg-white/10 border border-white/10 px-4 py-2 text-white"></div>
</div>
