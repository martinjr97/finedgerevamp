@extends('layouts.admin')

@section('title', 'Employee Loan Reports')

@section('content')
<div class="space-y-8">
    @include('partials.admin.page-header', ['title' => 'Employee Loan Reports', 'description' => 'HR-only portfolio analytics.'])

    <div class="grid md:grid-cols-3 gap-4">
        @foreach ([
            'Total loans' => $kpis['total_loans'],
            'Active loans' => $kpis['active_loans'],
            'Employees with loans' => $kpis['employees_with_loans'],
            'Total disbursed' => number_format($kpis['total_disbursed'], 2),
            'Outstanding' => number_format($kpis['outstanding_balance'], 2),
            'NPL count' => $kpis['npl_count'],
        ] as $label => $value)
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                <div class="text-xs text-slate-400">{{ $label }}</div>
                <div class="text-2xl font-semibold">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    <ul class="grid md:grid-cols-2 gap-2 text-cyan-300">
        <li><a href="{{ route('admin.hr.employee-loans.reports.portfolio') }}">Employee Loan Portfolio</a></li>
        <li><a href="{{ route('admin.hr.employee-loans.reports.active') }}">Active Employee Loans</a></li>
        <li><a href="{{ route('admin.hr.employee-loans.reports.settled') }}">Settled Employee Loans</a></li>
        <li><a href="{{ route('admin.hr.employee-loans.reports.repayments') }}">Repayment History</a></li>
        <li><a href="{{ route('admin.hr.employee-loans.reports.arrears') }}">Employee Loan Arrears</a></li>
        <li><a href="{{ route('admin.hr.employee-loans.reports.aging') }}">Employee Loan Aging</a></li>
        <li><a href="{{ route('admin.hr.employee-loans.reports.by-department') }}">Loans by Department</a></li>
        <li><a href="{{ route('admin.hr.employee-loans.reports.by-employee') }}">Loans by Employee</a></li>
        <li><a href="{{ route('admin.hr.employee-loans.reports.by-rate') }}">Loans by Rate</a></li>
        <li><a href="{{ route('admin.hr.employee-loans.reports.by-term') }}">Loans by Term</a></li>
    </ul>
</div>
@endsection
