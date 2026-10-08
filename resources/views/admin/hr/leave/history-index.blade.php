@extends('layouts.admin')

@section('title', 'Leave History | HR | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Leave History',
            'description' => 'Past leave decisions — approved or rejected applications only.',
        ])

        <form method="GET" class="grid gap-3 rounded-3xl border border-white/10 bg-white/5 p-4 md:grid-cols-2 lg:grid-cols-5 lg:items-end">
            <div>
                <label for="leave_type_id" class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-400">Leave type</label>
                <select id="leave_type_id" name="leave_type_id" class="w-full rounded-xl border border-white/10 bg-white/10 px-3 py-2 text-sm text-white">
                    <option value="">All types</option>
                    @foreach ($leaveTypes as $type)
                        <option value="{{ $type->id }}" @selected((string) request('leave_type_id') === (string) $type->id)>{{ $type->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="status" class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-400">Decision</label>
                <select id="status" name="status" class="w-full rounded-xl border border-white/10 bg-white/10 px-3 py-2 text-sm text-white">
                    <option value="">Approved & rejected</option>
                    <option value="approved" @selected(request('status') === 'approved')>Approved only</option>
                    <option value="rejected" @selected(request('status') === 'rejected')>Rejected only</option>
                </select>
            </div>
            <div>
                <label for="date_from" class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-400">From</label>
                <input type="date" id="date_from" name="date_from" value="{{ request('date_from') }}" class="w-full rounded-xl border border-white/10 bg-white/10 px-3 py-2 text-sm text-white">
            </div>
            <div>
                <label for="date_to" class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-400">To</label>
                <input type="date" id="date_to" name="date_to" value="{{ request('date_to') }}" class="w-full rounded-xl border border-white/10 bg-white/10 px-3 py-2 text-sm text-white">
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="submit" class="rounded-xl border border-cyan-500/40 bg-cyan-500/20 px-4 py-2 text-sm font-medium text-cyan-200">Filter</button>
                @if (request()->hasAny(['leave_type_id', 'status', 'date_from', 'date_to', 'department_id', 'employee_id']))
                    <a href="{{ route('admin.hr.leave.history.index') }}" class="rounded-xl border border-white/10 px-4 py-2 text-sm text-slate-300 hover:bg-white/10">Clear</a>
                @endif
            </div>
        </form>

        <div class="admin-data-table">
            <table class="min-w-full w-full">
                    <thead>
                        <tr>
                            <th scope="col">Employee</th>
                            <th scope="col">Department</th>
                            <th scope="col">Leave type</th>
                            <th scope="col">Period</th>
                            <th scope="col">Days</th>
                            <th scope="col">Status</th>
                            <th scope="col">Decided by</th>
                            <th scope="col">Comments</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($history as $row)
                            @php
                                $statusClass = $row->status === 'approved' ? 'text-emerald-400' : 'text-rose-400';
                            @endphp
                            <tr>
                                <td class="font-medium text-white">{{ $row->employee?->full_name ?? '—' }}</td>
                                <td>{{ $row->employee?->hrDepartment?->name ?? '—' }}</td>
                                <td>{{ $row->leaveType?->name ?? '—' }}</td>
                                <td>{{ $row->start_date->format('d M Y') }} – {{ $row->end_date->format('d M Y') }}</td>
                                <td>{{ number_format((float) $row->days_requested, 2) }}</td>
                                <td><span class="text-sm font-medium capitalize {{ $statusClass }}">{{ $row->status }}</span></td>
                                <td>{{ $row->approver ? trim($row->approver->first_name.' '.$row->approver->last_name) : '—' }}</td>
                                <td class="max-w-xs truncate" title="{{ $row->approver_comments }}">{{ $row->approver_comments ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-8 text-center text-slate-400">No leave history for these filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
            </table>

            @if ($history->hasPages())
                <div class="admin-table-footer">
                    {{ $history->links() }}
                </div>
            @endif
        </div>

        <p class="text-sm text-slate-400">
            Pending applications are on
            <a href="{{ route('admin.hr.leave.applications.index') }}" class="text-cyan-300 hover:underline">Pending leave</a>.
        </p>
    </div>
@endsection
