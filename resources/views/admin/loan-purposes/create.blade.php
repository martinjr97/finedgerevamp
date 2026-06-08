@extends('layouts.admin')

@section('title', 'Create Loan Purpose | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Create Loan Purpose',
            'buttons' => [
                [
                    'action' => 'secondary',
                    'text' => 'Back',
                    'href' => route('admin.loan-purposes.index'),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>',
                ],
            ],
        ])

        @include('admin.loan-purposes.form')
    </div>
@endsection
