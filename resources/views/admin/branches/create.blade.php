@extends('layouts.admin')

@section('title', 'Create Branch | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        <div class="space-y-2 text-left">
            <p class="text-xs uppercase tracking-[0.4em] text-cyan-300">Configurations</p>
            <h1 class="text-3xl font-bold">Create Branch</h1>
        </div>

        @include('admin.branches.form')
    </div>
@endsection


