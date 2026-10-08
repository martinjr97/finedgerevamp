@extends('layouts.admin')

@section('title', 'Pending Leave | HR | '.config('app.system_name'))

@section('content')
    <div
        class="space-y-8"
        x-data="{
            selectedApplication: null,
            approveModalOpen: false,
            rejectModalOpen: false,
            approveComments: '',
            rejectComments: '',
            openApprove(app) {
                this.selectedApplication = app;
                this.approveComments = '';
                this.approveModalOpen = true;
            },
            openReject(app) {
                this.selectedApplication = app;
                this.rejectComments = '';
                this.rejectModalOpen = true;
            },
        }"
    >
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
            <table
                data-datatable="true"
                data-datatable-per-page="25"
                data-datatable-search-placeholder="Search pending leave…"
                class="min-w-full w-full"
            >
                <thead>
                    <tr>
                        <th scope="col">Employee</th>
                        <th scope="col">Department</th>
                        <th scope="col">Leave type</th>
                        <th scope="col">Period</th>
                        <th scope="col" class="text-right">Days requested</th>
                        <th scope="col" class="text-right">Days available</th>
                        <th scope="col">Reason</th>
                        <th data-sortable="false" scope="col" class="admin-data-table__actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($applications as $application)
                        @php
                            $modalPayload = [
                                'employee' => $application->employee?->full_name ?? '—',
                                'leaveType' => $application->leaveType?->name ?? '—',
                                'period' => $application->start_date->format('d M Y').' – '.$application->end_date->format('d M Y'),
                                'days' => number_format((float) $application->days_requested, 2),
                                'available' => $application->available_balance !== null
                                    ? number_format((float) $application->available_balance, 2).' day(s)'
                                    : null,
                                'approveUrl' => route('admin.hr.leave.applications.approve', $application),
                                'rejectUrl' => route('admin.hr.leave.applications.reject', $application),
                            ];
                        @endphp
                        <tr>
                            <td class="font-medium text-white">{{ $application->employee?->full_name ?? '—' }}</td>
                            <td>{{ $application->employee?->hrDepartment?->name ?? '—' }}</td>
                            <td>{{ $application->leaveType?->name ?? '—' }}</td>
                            <td class="whitespace-nowrap text-sm">
                                {{ $application->start_date->format('d M Y') }}
                                –
                                {{ $application->end_date->format('d M Y') }}
                            </td>
                            <td class="text-right font-medium text-white tabular-nums">
                                {{ number_format((float) $application->days_requested, 2) }}
                            </td>
                            <td class="text-right tabular-nums">
                                @if ($application->available_balance !== null)
                                    @php $avail = (float) $application->available_balance; @endphp
                                    <span class="text-sm font-semibold {{ $avail + 0.0001 >= (float) $application->days_requested ? 'text-emerald-400' : 'text-rose-400' }}">
                                        {{ number_format($avail, 2) }}
                                    </span>
                                @else
                                    <span class="text-slate-500" title="Not balance-tracked for this leave type">—</span>
                                @endif
                            </td>
                            <td class="max-w-[10rem] truncate" title="{{ $application->reason }}">{{ $application->reason ?: '—' }}</td>
                            <td>
                                <div class="inline-flex flex-wrap items-center gap-2">
                                    <a
                                        href="{{ route('admin.hr.leave.applications.show', $application) }}"
                                        class="inline-flex items-center rounded-lg border border-blue-400/50 bg-blue-500/10 px-2.5 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-500/20 transition"
                                    >
                                        View
                                    </a>
                                    @if ($canApprove)
                                        <button
                                            type="button"
                                            @click="openApprove(@js($modalPayload))"
                                            class="inline-flex items-center rounded-lg border border-emerald-400/50 bg-emerald-500/10 px-2.5 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-500/20 transition"
                                        >
                                            Approve
                                        </button>
                                        <button
                                            type="button"
                                            @click="openReject(@js($modalPayload))"
                                            class="inline-flex items-center rounded-lg border border-rose-400/50 bg-rose-500/10 px-2.5 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-500/20 transition"
                                        >
                                            Reject
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 text-center text-slate-400">
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

        @if ($canApprove)
            @include('admin.hr.leave.partials.application-decision-modals')
        @endif

        <p class="text-sm text-slate-400">
            Approved and rejected applications are listed under
            <a href="{{ route('admin.hr.leave.history.index') }}" class="text-cyan-300 hover:underline">Leave history</a>.
        </p>
    </div>
@endsection
