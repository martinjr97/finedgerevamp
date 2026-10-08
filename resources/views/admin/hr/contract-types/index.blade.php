@extends('layouts.admin')

@section('title', 'Contract Types | HR | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Contract Types',
            'description' => 'Employment contract categories, leave accrual, and renewal rules.',
            'buttons' => [[
                'action' => 'create',
                'text' => 'Add Type',
                'href' => route('admin.hr.contract-types.create'),
                'can' => auth('admin')->user()?->can('hr.contract-types.manage'),
            ]],
        ])

        <div class="admin-data-table">
            <table
                data-datatable="true"
                data-datatable-per-page="10"
                data-datatable-search-placeholder="Search contract types…"
                class="min-w-full w-full"
            >
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Code</th>
                        <th scope="col">Permanent</th>
                        <th scope="col">End date</th>
                        <th scope="col">Leave / month</th>
                        <th scope="col">Status</th>
                        <th data-sortable="false" scope="col" class="admin-data-table__actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($contractTypes as $type)
                        <tr>
                            <td class="font-medium text-white">{{ $type->name }}</td>
                            <td>{{ $type->code }}</td>
                            <td>{{ $type->is_permanent ? 'Yes' : 'No' }}</td>
                            <td>{{ $type->has_end_date ? 'Required' : '—' }}</td>
                            <td>{{ $type->leave_days_per_month !== null ? number_format((float) $type->leave_days_per_month, 2) : '—' }}</td>
                            <td>
                                <span class="text-sm font-medium {{ $type->is_active ? 'text-emerald-400' : 'text-rose-400' }}">
                                    {{ $type->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>
                                <div class="inline-flex items-center gap-3">
                                    <a
                                        href="{{ route('admin.hr.contract-types.show', $type) }}"
                                        class="inline-flex items-center gap-1.5 rounded-lg border border-blue-400/50 bg-blue-500/10 px-2.5 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-500/20 transition"
                                    >
                                        View
                                    </a>
                                    @can('hr.contract-types.manage')
                                        <a
                                            href="{{ route('admin.hr.contract-types.edit', $type) }}"
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
                            <td colspan="7" class="py-8 text-center text-slate-400">No contract types found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
