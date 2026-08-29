@extends('layouts.admin')

@section('title', 'Add Employee | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        <div class="space-y-2 text-left">
            <p class="text-xs uppercase tracking-[0.4em] text-cyan-300">Financial Management</p>
            <h1 class="text-3xl font-bold">Add Employee</h1>
            <p class="text-sm text-slate-400">Temporary registry for asset owners until the HR module is available.</p>
        </div>

        <div class="mx-auto w-full max-w-2xl">
            @include('admin.employees.form')
        </div>
    </div>
@endsection
