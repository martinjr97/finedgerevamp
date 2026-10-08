@extends('layouts.admin')

@section('title', $department->name.' | Departments | HR | '.config('app.system_name'))

@section('content')
    @php
        $admin = auth('admin')->user();
        $canManage = $admin?->can('hr.departments.manage') ?? false;
        $canViewEmployees = $admin?->can('hr.employees.view') ?? false;
        $canCreateEmployee = $admin?->can('hr.employees.create') ?? false;
        $canViewPositions = $admin?->can('hr.positions.view') ?? false;
    @endphp

    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => $department->name,
            'description' => 'Department profile, team roster, and linked positions.',
            'buttons' => array_filter([
                [
                    'action' => 'back',
                    'text' => 'All departments',
                    'href' => route('admin.hr.departments.index'),
                ],
                $canManage ? [
                    'action' => 'edit',
                    'text' => 'Edit department',
                    'href' => route('admin.hr.departments.edit', $department),
                ] : null,
            ]),
        ])

        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                <div class="flex items-start gap-4">
                    <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-cyan-500/15 text-lg font-bold text-cyan-200 ring-1 ring-cyan-400/30">
                        {{ strtoupper(substr($department->code, 0, 2)) }}
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-2xl font-semibold text-white">{{ $department->name }}</h2>
                            <span class="rounded-full border px-2.5 py-0.5 text-xs font-semibold {{ $department->is_active ? 'border-emerald-400/40 bg-emerald-500/10 text-emerald-300' : 'border-rose-400/40 bg-rose-500/10 text-rose-300' }}">
                                {{ $department->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        <p class="mt-1 font-mono text-sm text-slate-400">Code: {{ $department->code }}</p>
                        @if ($department->description)
                            <p class="mt-3 max-w-2xl text-sm text-slate-300">{{ $department->description }}</p>
                        @endif
                    </div>
                </div>
                <dl class="grid min-w-[14rem] gap-3 text-sm sm:grid-cols-2 lg:grid-cols-1">
                    <div class="rounded-2xl border border-white/10 bg-black/20 px-4 py-3">
                        <dt class="text-xs uppercase tracking-wide text-slate-500">Department head</dt>
                        <dd class="mt-1 font-medium text-white">
                            @if ($department->head && $canViewEmployees)
                                <a href="{{ route('admin.hr.employees.show', $department->head) }}" class="text-cyan-200 hover:underline">
                                    {{ $department->head->full_name }}
                                </a>
                            @else
                                {{ $department->head?->full_name ?? 'Not assigned' }}
                            @endif
                        </dd>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-black/20 px-4 py-3">
                        <dt class="text-xs uppercase tracking-wide text-slate-500">Parent department</dt>
                        <dd class="mt-1 font-medium text-white">
                            @if ($department->parent)
                                <a href="{{ route('admin.hr.departments.show', $department->parent) }}" class="text-cyan-200 hover:underline">
                                    {{ $department->parent->name }}
                                </a>
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4 shadow-lg">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Total employees</p>
                <p class="mt-2 text-3xl font-bold text-white">{{ number_format($stats['employees_total']) }}</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4 shadow-lg">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Active</p>
                <p class="mt-2 text-3xl font-bold text-emerald-300">{{ number_format($stats['employees_active']) }}</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4 shadow-lg">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Not active</p>
                <p class="mt-2 text-3xl font-bold text-amber-300">{{ number_format($stats['employees_inactive']) }}</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4 shadow-lg">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Positions</p>
                <p class="mt-2 text-3xl font-bold text-cyan-300">{{ number_format($stats['positions']) }}</p>
            </div>
        </div>

        @if ($department->children->isNotEmpty())
            <section class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
                <h3 class="text-lg font-semibold text-white">Sub-departments</h3>
                <div class="admin-data-table">
                    <table class="min-w-full w-full">
                        <thead>
                            <tr>
                                <th scope="col">Name</th>
                                <th scope="col">Code</th>
                                <th scope="col">Head</th>
                                <th scope="col">Employees</th>
                                <th scope="col" class="admin-data-table__actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($department->children as $child)
                                <tr>
                                    <td class="font-medium text-white">{{ $child->name }}</td>
                                    <td><span class="font-mono text-xs text-slate-300">{{ $child->code }}</span></td>
                                    <td>{{ $child->head?->full_name ?? '—' }}</td>
                                    <td>{{ $child->employees_count }}</td>
                                    <td>
                                        <a href="{{ route('admin.hr.departments.show', $child) }}" class="inline-flex rounded-lg border border-blue-400/50 bg-blue-500/10 px-2.5 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-500/20">View</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        <section class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-lg font-semibold text-white">Team members</h3>
                    <p class="text-sm text-slate-400">Employees assigned to this department.</p>
                </div>
                @if ($canCreateEmployee)
                    <a href="{{ route('admin.hr.employees.create') }}" class="inline-flex items-center rounded-xl border border-cyan-500/40 bg-cyan-500/10 px-3 py-2 text-xs font-semibold text-cyan-200 hover:bg-cyan-500/20">
                        Add employee
                    </a>
                @endif
            </div>

            <div class="admin-data-table">
                <div class="admin-data-table__scroll">
                    <table class="min-w-full w-full">
                        <thead>
                            <tr>
                                <th scope="col">Employee</th>
                                <th scope="col">No.</th>
                                <th scope="col">Position</th>
                                <th scope="col">Contract</th>
                                <th scope="col">Status</th>
                                @if ($canViewEmployees)
                                    <th scope="col" class="admin-data-table__actions">Actions</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($department->employees as $employee)
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
                                    <td>{{ $employee->position?->name ?? '—' }}</td>
                                    <td>{{ $employee->activeContract?->contractType?->name ?? '—' }}</td>
                                    <td><span class="text-sm font-medium capitalize {{ $statusClass }}">{{ $employee->employment_status }}</span></td>
                                    @if ($canViewEmployees)
                                        <td>
                                            <a href="{{ route('admin.hr.employees.show', $employee) }}" class="inline-flex rounded-lg border border-blue-400/50 bg-blue-500/10 px-2.5 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-500/20">View</a>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $canViewEmployees ? 6 : 5 }}" class="py-10 text-center text-slate-400">
                                        No employees in this department yet.
                                        @if ($canCreateEmployee)
                                            <a href="{{ route('admin.hr.employees.create') }}" class="ml-1 text-cyan-300 hover:underline">Add the first employee</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        @if ($canViewPositions && $department->positions->isNotEmpty())
            <section class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-white">Positions in this department</h3>
                        <p class="text-sm text-slate-400">Roles linked to {{ $department->name }}.</p>
                    </div>
                    <a href="{{ route('admin.hr.positions.index') }}" class="text-xs font-semibold text-cyan-300 hover:underline">Manage all positions</a>
                </div>
                <div class="admin-data-table">
                    <table class="min-w-full w-full">
                        <thead>
                            <tr>
                                <th scope="col">Position</th>
                                <th scope="col">Code</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($department->positions as $position)
                                <tr>
                                    <td class="font-medium text-white">{{ $position->name }}</td>
                                    <td><span class="font-mono text-xs text-slate-300">{{ $position->code }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
@endsection
