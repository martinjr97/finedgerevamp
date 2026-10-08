@extends('layouts.admin')

@section('title', 'Pending Leave | HR | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Pending Leave Applications',
            'description' => 'Review and approve or reject leave awaiting a decision.',
            'buttons' => [[
                'action' => 'create',
                'text' => 'Apply',
                'href' => route('admin.hr.leave.applications.create'),
                'can' => auth('admin')->user()?->can('hr.leave.apply'),
            ]],
        ])

        <div class="admin-data-table">
            <table class="min-w-full w-full">
                    <thead>
                        <tr>
                            <th scope="col">Employee</th>
                            <th scope="col">Department</th>
                            <th scope="col">Leave type</th>
                            <th scope="col">Period</th>
                            <th scope="col">Days</th>
                            <th scope="col">Reason</th>
                            @if ($canApprove)
                                <th data-sortable="false" scope="col" class="admin-data-table__actions">Actions</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($applications as $application)
                            <tr>
                                <td class="font-medium text-white">{{ $application->employee?->full_name ?? '—' }}</td>
                                <td>{{ $application->employee?->hrDepartment?->name ?? '—' }}</td>
                                <td>{{ $application->leaveType?->name ?? '—' }}</td>
                                <td>
                                    {{ $application->start_date->format('d M Y') }}
                                    –
                                    {{ $application->end_date->format('d M Y') }}
                                </td>
                                <td>{{ number_format((float) $application->days_requested, 2) }}</td>
                                <td class="max-w-xs truncate" title="{{ $application->reason }}">{{ $application->reason ?: '—' }}</td>
                                @if ($canApprove)
                                    <td>
                                        <div class="flex flex-col gap-2 min-w-[12rem]">
                                            <form method="POST" action="{{ route('admin.hr.leave.applications.approve', $application) }}" class="space-y-1">
                                                @csrf
                                                <input
                                                    type="text"
                                                    name="comments"
                                                    placeholder="Approval note (optional)"
                                                    class="w-full rounded-lg border border-white/10 bg-white/10 px-2 py-1 text-xs text-white"
                                                >
                                                <button type="submit" class="w-full rounded-lg border border-emerald-400/50 bg-emerald-500/10 px-2 py-1 text-xs font-semibold text-emerald-300 hover:bg-emerald-500/20">
                                                    Approve
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.hr.leave.applications.reject', $application) }}" class="space-y-1">
                                                @csrf
                                                <input
                                                    type="text"
                                                    name="comments"
                                                    placeholder="Rejection reason (optional)"
                                                    class="w-full rounded-lg border border-white/10 bg-white/10 px-2 py-1 text-xs text-white"
                                                >
                                                <button type="submit" class="w-full rounded-lg border border-rose-400/50 bg-rose-500/10 px-2 py-1 text-xs font-semibold text-rose-300 hover:bg-rose-500/20">
                                                    Reject
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $canApprove ? 7 : 6 }}" class="py-8 text-center text-slate-400">
                                    No pending leave applications.
                                    @can('hr.leave.apply')
                                        <a href="{{ route('admin.hr.leave.applications.create') }}" class="ml-1 text-cyan-300 hover:underline">Apply for leave</a>
                                    @endcan
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
            </table>

            @if ($applications->hasPages())
                <div class="admin-table-footer">
                    {{ $applications->links() }}
                </div>
            @endif
        </div>

        <p class="text-sm text-slate-400">
            Approved and rejected applications are listed under
            <a href="{{ route('admin.hr.leave.history.index') }}" class="text-cyan-300 hover:underline">Leave history</a>.
        </p>
    </div>
@endsection
