@extends('layouts.admin')

@section('title', 'Edit '.$contractType->name.' | Contract Types | HR | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('admin.hr.contract-types.partials.form', [
            'contractType' => $contractType,
            'leaveTypes' => $leaveTypes,
        ])
    </div>
@endsection
