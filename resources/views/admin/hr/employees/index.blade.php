@extends('layouts.admin')

@section('title', 'Employees | HR | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Employees',
            'description' => 'Workforce directory, contracts, and employment status.',
            'buttons' => [[
                'action' => 'create',
                'text' => 'Add Employee',
                'href' => route('admin.hr.employees.create'),
                'can' => auth('admin')->user()?->can('hr.employees.create'),
            ]],
        ])

        <form method="GET" class="grid gap-3 rounded-3xl border border-white/10 bg-white/5 p-4 md:grid-cols-2 lg:grid-cols-5 lg:items-end">
            <div class="lg:col-span-2">
                <label for="search" class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-400">Search</label>
                <input
                    id="search"
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="Name, number, or email…"
                    class="w-full rounded-xl border border-white/10 bg-white/10 px-3 py-2 text-sm text-white"
                >
            </div>
            <div>
                <label for="department_id" class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-400">Department</label>
                <select id="department_id" name="department_id" class="w-full rounded-xl border border-white/10 bg-white/10 px-3 py-2 text-sm text-white">
                    <option value="">All departments</option>
                    @foreach ($departments as $dept)
                        <option value="{{ $dept->id }}" @selected((string) request('department_id') === (string) $dept->id)>{{ $dept->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="employment_status" class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-400">Status</label>
                <select id="employment_status" name="employment_status" class="w-full rounded-xl border border-white/10 bg-white/10 px-3 py-2 text-sm text-white">
                    <option value="">All statuses</option>
                    @foreach (['active', 'inactive', 'suspended', 'terminated', 'retired', 'deceased'] as $status)
                        <option value="{{ $status }}" @selected(request('employment_status') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="submit" class="rounded-xl border border-cyan-500/40 bg-cyan-500/20 px-4 py-2 text-sm font-medium text-cyan-200 hover:bg-cyan-500/30">Apply</button>
                @if (request()->hasAny(['search', 'department_id', 'employment_status']))
                    <a href="{{ route('admin.hr.employees.index') }}" class="rounded-xl border border-white/10 px-4 py-2 text-sm text-slate-300 hover:bg-white/10">Clear</a>
                @endif
            </div>
        </form>

        <div class="admin-data-table">
            <div class="admin-data-table__scroll">
                <table class="min-w-full w-full">
                    <thead>
                        <tr>
                            <th scope="col">Employee</th>
                            <th scope="col">No.</th>
                            <th scope="col">Department</th>
                            <th scope="col">Position</th>
                            <th scope="col">Contract</th>
                            <th scope="col">Status</th>
                            <th data-sortable="false" scope="col" class="admin-data-table__actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($employees as $employee)
                            @php
                                $statusClass = $employee->employment_status === 'active' ? 'text-emerald-400' : 'text-slate-400';
                            @endphp
                            <tr>
                                <td>
                                    <p class="font-medium text-white">{{ $employee->full_name }}</p>
                                    @if ($employee->email)
                                        <p class="text-xs text-slate-400">{{ $employee->email }}</p>
                                    @endif
                                </td>
                                <td>{{ $employee->employee_number ?? '—' }}</td>
                                <td>{{ $employee->hrDepartment?->name ?? '—' }}</td>
                                <td>{{ $employee->position?->name ?? '—' }}</td>
                                <td>{{ $employee->activeContract?->contractType?->name ?? '—' }}</td>
                                <td>
                                    <span class="text-sm font-medium capitalize {{ $statusClass }}">{{ $employee->employment_status }}</span>
                                </td>
                                <td>
                                    <div class="inline-flex items-center gap-3">
                                        <a
                                            href="{{ route('admin.hr.employees.show', $employee) }}"
                                            class="inline-flex items-center gap-1.5 rounded-lg border border-blue-400/50 bg-blue-500/10 px-2.5 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-500/20 transition"
                                        >
                                            View
                                        </a>
                                        @can('hr.employees.update')
                                            <a
                                                href="{{ route('admin.hr.employees.edit', $employee) }}"
                                                class="inline-flex items-center gap-1.5 rounded-lg border border-purple-400/50 bg-purple-500/10 px-2.5 py-1 text-xs font-semibold text-purple-700 hover:bg-purple-500/20 transition"
                                            >
                                                Edit
                                            </a>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-8 text-center text-slate-400">No employees match your filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($employees->hasPages())
                <div class="admin-table-footer">
                    {{ $employees->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
