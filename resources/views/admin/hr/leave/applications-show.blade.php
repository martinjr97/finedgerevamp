@extends('layouts.admin')

@section('title', 'Leave Application | HR | '.config('app.system_name'))

@section('content')
    @php
        $employee = $leaveApplication->employee;
        $payload = [
            'employee' => $employee?->full_name ?? '—',
            'leaveType' => $leaveApplication->leaveType?->name ?? '—',
            'period' => $leaveApplication->start_date->format('d M Y').' – '.$leaveApplication->end_date->format('d M Y'),
            'days' => number_format((float) $leaveApplication->days_requested, 2),
            'available' => $availableBalance !== null ? number_format((float) $availableBalance, 2).' day(s)' : null,
            'approveUrl' => route('admin.hr.leave.applications.approve', $leaveApplication),
            'rejectUrl' => route('admin.hr.leave.applications.reject', $leaveApplication),
        ];
    @endphp

    <div
        class="space-y-8"
        x-data="{
            selectedApplication: @js($payload),
            approveModalOpen: false,
            rejectModalOpen: false,
            approveComments: '',
            rejectComments: '',
            openApprove() { this.approveComments = ''; this.approveModalOpen = true; },
            openReject() { this.rejectComments = ''; this.rejectModalOpen = true; },
        }"
    >
        @include('partials.admin.page-header', [
            'title' => 'Leave application',
            'description' => 'Pending request details and decision actions.',
            'buttons' => array_filter([
                [
                    'action' => 'back',
                    'text' => 'Pending applications',
                    'href' => route('admin.hr.leave.applications.index'),
                ],
                $employee ? [
                    'action' => 'secondary',
                    'text' => 'View employee',
                    'href' => route('admin.hr.employees.show', $employee),
                ] : null,
            ]),
        ])

        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <div class="flex flex-wrap items-center gap-2 mb-6">
                <span class="rounded-full border border-amber-400/40 bg-amber-500/10 px-2.5 py-0.5 text-xs font-semibold text-amber-200 capitalize">
                    {{ $leaveApplication->status }}
                </span>
                <span class="text-sm text-slate-400">Submitted {{ $leaveApplication->created_at?->format('d M Y H:i') ?? '—' }}</span>
            </div>

            <dl class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @include('admin.hr.employees.partials.detail-field', ['label' => 'Employee', 'value' => $employee?->full_name])
                @include('admin.hr.employees.partials.detail-field', ['label' => 'Department', 'value' => $employee?->hrDepartment?->name])
                @include('admin.hr.employees.partials.detail-field', ['label' => 'Leave type', 'value' => $leaveApplication->leaveType?->name])
                @include('admin.hr.employees.partials.detail-field', [
                    'label' => 'Period',
                    'value' => $leaveApplication->start_date->format('d M Y').' – '.$leaveApplication->end_date->format('d M Y'),
                ])
                @include('admin.hr.employees.partials.detail-field', [
                    'label' => 'Days requested',
                    'value' => number_format((float) $leaveApplication->days_requested, 2),
                ])
                @if ($availableBalance !== null && $leaveApplication->leaveType?->requiresBalanceCheck())
                    @include('admin.hr.employees.partials.detail-field', [
                        'label' => 'Available balance (now)',
                        'value' => number_format((float) $availableBalance, 2).' day(s)',
                    ])
                @endif
                @include('admin.hr.employees.partials.detail-field', ['label' => 'Reason', 'value' => $leaveApplication->reason])
            </dl>

            @if ($isPending && $canApprove)
                <div class="mt-8 flex flex-wrap justify-end gap-3 border-t border-white/10 pt-6">
                    <button type="button" @click="openReject()" class="rounded-xl border border-rose-400/50 bg-rose-500/10 px-5 py-2.5 text-sm font-semibold text-rose-200 hover:bg-rose-500/20">
                        Reject
                    </button>
                    <button type="button" @click="openApprove()" class="btn-primary rounded-xl px-5 py-2.5 text-sm font-semibold">
                        Approve
                    </button>
                </div>
            @endif
        </div>

        @if ($isPending && $canApprove)
            @include('admin.hr.leave.partials.application-decision-modals')
        @endif
    </div>
@endsection
