@extends('layouts.admin')

@section('title', 'Employees | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Employees',
            'description' => 'Temporary employee registry for asset ownership. Will be replaced by the HR module.',
            'buttons' => [
                [
                    'action' => 'back',
                    'text' => 'Back to Assets',
                    'href' => route('admin.assets.index'),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>',
                ],
                [
                    'action' => 'create',
                    'text' => 'Add Employee',
                    'href' => route('admin.employees.create'),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>',
                    'can' => auth('admin')->user()?->can('assets.create'),
                ],
            ],
        ])

        <div class="admin-data-table">
            <table
                data-datatable="true"
                data-datatable-per-page="10"
                data-datatable-search-placeholder="Search employees…"
                class="min-w-full w-full"
            >
                <thead>
                    <tr class="font-semibold uppercase text-white/80 text-center">
                        <th scope="col">Name</th>
                        <th scope="col">Employee No.</th>
                        <th scope="col">Department</th>
                        <th scope="col">Phone</th>
                        <th scope="col">Status</th>
                        <th data-sortable="false" scope="col" class="admin-data-table__actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($employees as $employee)
                        <tr class="text-center">
                            <td class="font-medium text-white">{{ $employee->full_name }}</td>
                            <td>{{ $employee->employee_number ?? '—' }}</td>
                            <td>{{ $employee->department ?? '—' }}</td>
                            <td>{{ $employee->phone ?? '—' }}</td>
                            <td>
                                <span class="text-sm font-medium {{ $employee->is_active ? 'text-emerald-400' : 'text-rose-400' }}">
                                    {{ $employee->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>
                                <div class="inline-flex items-center gap-3">
                                    @can('assets.update')
                                    <a href="{{ route('admin.employees.edit', $employee) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-purple-400/50 bg-purple-500/10 px-2.5 py-1 text-xs font-semibold text-purple-700 hover:bg-purple-500/20 transition">
                                        Edit
                                    </a>
                                    @endcan
                                    @can('assets.delete')
                                    <form method="POST" action="{{ route('admin.employees.destroy', $employee) }}" class="inline"
                                          onsubmit="return confirm('Remove this employee?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-rose-400/50 bg-rose-500/10 px-2.5 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-500/20 transition">
                                            Delete
                                        </button>
                                    </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-slate-400">No employees found. Add employees to assign asset owners.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
