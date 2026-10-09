@extends('layouts.admin')

@section('title', 'New Employee Loan | HR')

@section('content')
<div class="space-y-8" x-data="employeeLoanForm()">
    @include('partials.admin.page-header', ['title' => 'New Employee Loan', 'description' => 'Draft an employee loan using Employee Rate terms.'])

    @if (session('status'))
        <div class="rounded-2xl border border-cyan-500/30 bg-cyan-500/10 px-4 py-3 text-cyan-100 text-sm">{{ session('status') }}</div>
    @endif

    @include('admin.hr.employee-loans.partials.form')
</div>
@endsection
