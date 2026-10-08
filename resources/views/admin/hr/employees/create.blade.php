@extends('layouts.admin')

@section('title', 'Add Employee | HR | '.config('app.system_name'))

@section('content')
<div class="space-y-6">
    @include('partials.admin.page-header', [
        'title' => 'Add Employee',
        'description' => 'Complete the wizard to register bio data, contract, pay, and payment details.',
        'buttons' => [[
            'action' => 'back',
            'text' => 'Back to employees',
            'href' => route('admin.hr.employees.index'),
        ]],
    ])

    @include('admin.hr.employees.partials.create-wizard')
</div>
@endsection
