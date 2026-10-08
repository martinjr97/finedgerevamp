@extends('layouts.admin')

@section('title', 'Add Contract Type | HR | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('admin.hr.contract-types.partials.form', [
            'contractType' => null,
            'leaveTypes' => $leaveTypes,
        ])
    </div>
@endsection
