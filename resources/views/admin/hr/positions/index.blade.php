@extends('layouts.admin')

@section('title', 'Positions | HR | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Positions',
            'description' => 'Job titles and roles, optionally linked to departments.',
            'buttons' => [[
                'action' => 'create',
                'text' => 'Add Position',
                'href' => route('admin.hr.positions.create'),
                'can' => auth('admin')->user()?->can('hr.positions.manage'),
            ]],
        ])

        <div class="admin-data-table">
            <table
                data-datatable="true"
                data-datatable-per-page="15"
                data-datatable-search-placeholder="Search positions…"
                class="min-w-full w-full"
            >
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Code</th>
                        <th scope="col">Department</th>
                        <th scope="col">Status</th>
                        <th data-sortable="false" scope="col" class="admin-data-table__actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($positions as $position)
                        <tr>
                            <td class="font-medium text-white">{{ $position->name }}</td>
                            <td><span class="font-mono text-xs text-slate-300">{{ $position->code }}</span></td>
                            <td>{{ $position->department?->name ?? '—' }}</td>
                            <td>
                                <span class="text-sm font-medium {{ $position->is_active ? 'text-emerald-400' : 'text-rose-400' }}">
                                    {{ $position->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>
                                @can('hr.positions.manage')
                                    <a href="{{ route('admin.hr.positions.edit', $position) }}" class="inline-flex rounded-lg border border-purple-400/50 bg-purple-500/10 px-2.5 py-1 text-xs font-semibold text-purple-700 hover:bg-purple-500/20">Edit</a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-8 text-center text-slate-400">No positions yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
