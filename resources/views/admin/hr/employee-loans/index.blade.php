@extends('layouts.admin')

@section('title', 'Employee Loans | HR | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Employee Loans',
            'description' => 'HR staff loan portfolio (separate from customer loans).',
            'buttons' => [[
                'action' => 'create',
                'text' => 'New Employee Loan',
                'href' => route('admin.hr.employee-loans.create'),
                'can' => auth('admin')->user()?->can('hr.employee-loans.create'),
            ], [
                'action' => 'view',
                'text' => 'Reports',
                'href' => route('admin.hr.employee-loans.reports.index'),
                'can' => auth('admin')->user()?->can('hr.employee-loans.reports'),
            ]],
        ])

        @if (session('status'))
            <div class="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-emerald-200">{{ session('status') }}</div>
        @endif

        <form method="GET" class="grid md:grid-cols-4 gap-4 rounded-3xl border border-white/10 bg-white/5 p-4">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Loan #, employee…" class="rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2">
            <select name="status" class="rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2">
                <option value="">All statuses</option>
                @foreach ($statusOptions as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="employee_id" class="rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2">
                <option value="">All employees</option>
                @foreach ($employees as $employee)
                    <option value="{{ $employee->id }}" @selected((string) request('employee_id') === (string) $employee->id)>{{ $employee->full_name }}</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-2xl bg-cyan-600 px-4 py-2 font-semibold text-white">Filter</button>
        </form>

        <div class="admin-data-table rounded-3xl border border-white/10 bg-white/5 overflow-hidden">
            <table class="min-w-full w-full">
                <thead>
                    <tr>
                        <th>Loan #</th>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Principal</th>
                        <th>Rate</th>
                        <th>Total</th>
                        <th>Outstanding</th>
                        <th>Status</th>
                        <th class="admin-data-table__actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($loans as $loan)
                        <tr>
                            <td>{{ $loan->loan_number }}</td>
                            <td>{{ $loan->employee?->full_name }}<br><span class="text-xs text-slate-400">{{ $loan->employee?->employee_number }}</span></td>
                            <td>{{ $loan->employee?->hrDepartment?->name ?? $loan->employee?->department ?? '—' }}</td>
                            <td>{{ number_format((float) $loan->principal_amount, 2) }}</td>
                            <td>{{ $loan->quoted_term_rate ? number_format((float) $loan->quoted_term_rate, 2).'%' : '—' }}</td>
                            <td>{{ number_format((float) $loan->total_amount, 2) }}</td>
                            <td>{{ number_format((float) $loan->outstanding_balance, 2) }}</td>
                            <td><span class="rounded-full px-2 py-0.5 text-xs bg-white/10">{{ ucfirst(str_replace('_', ' ', $loan->status)) }}</span></td>
                            <td class="admin-data-table__actions"><a href="{{ route('admin.hr.employee-loans.show', $loan) }}" class="text-cyan-300 hover:underline">View</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-slate-400 py-8">No employee loans yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $loans->links() }}
    </div>
@endsection
