@extends('layouts.admin')

@section('title', 'Departments | HR | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Departments',
            'description' => 'Organisational units, department heads, and team sizes.',
            'buttons' => [[
                'action' => 'create',
                'text' => 'Add Department',
                'href' => route('admin.hr.departments.create'),
                'can' => auth('admin')->user()?->can('hr.departments.manage'),
            ]],
        ])

        <div class="admin-data-table">
            <table
                data-datatable="true"
                data-datatable-per-page="10"
                data-datatable-search-placeholder="Search departments…"
                class="min-w-full w-full"
            >
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Code</th>
                        <th scope="col">Head</th>
                        <th scope="col">Parent</th>
                        <th scope="col">Employees</th>
                        <th scope="col">Status</th>
                        <th data-sortable="false" scope="col" class="admin-data-table__actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($departments as $department)
                        <tr>
                            <td class="font-medium text-white">{{ $department->name }}</td>
                            <td><span class="font-mono text-xs text-slate-300">{{ $department->code }}</span></td>
                            <td>{{ $department->head?->full_name ?? '—' }}</td>
                            <td>{{ $department->parent?->name ?? '—' }}</td>
                            <td>{{ number_format($department->employees_count) }}</td>
                            <td>
                                <span class="text-sm font-medium {{ $department->is_active ? 'text-emerald-400' : 'text-rose-400' }}">
                                    {{ $department->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>
                                <div class="inline-flex items-center gap-3">
                                    <a
                                        href="{{ route('admin.hr.departments.show', $department) }}"
                                        class="inline-flex items-center gap-1.5 rounded-lg border border-blue-400/50 bg-blue-500/10 px-2.5 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-500/20 transition"
                                    >
                                        View
                                    </a>
                                    @can('hr.departments.manage')
                                        <a
                                            href="{{ route('admin.hr.departments.edit', $department) }}"
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
                            <td colspan="7" class="py-8 text-center text-slate-400">No departments found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
