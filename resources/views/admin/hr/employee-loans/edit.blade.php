@extends('layouts.admin')

@section('title', 'Edit '.$employeeLoan->loan_number.' | HR')

@section('content')
<div class="space-y-8" x-data="employeeLoanForm()">
    @include('partials.admin.page-header', [
        'title' => 'Edit draft loan',
        'description' => $employeeLoan->loan_number.' · '.$employeeLoan->employee?->full_name,
        'buttons' => [[
            'action' => 'secondary',
            'text' => 'Back to loan',
            'href' => route('admin.hr.employee-loans.show', $employeeLoan),
        ]],
    ])

    @include('admin.hr.employee-loans.partials.form', [
        'submitLabel' => 'Review changes',
        'submitHint' => 'Confirm the updated breakdown before saving this draft.',
    ])
</div>
@endsection
