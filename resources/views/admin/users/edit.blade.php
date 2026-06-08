@extends('layouts.admin')

@section('title', 'Edit Admin | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        <div class="space-y-2 text-left">
            <p class="text-xs uppercase tracking-[0.4em] text-cyan-300">Team Management</p>
            <h1 class="text-3xl font-bold">Edit Admin: {{ $user->full_name }}</h1>
        </div>

        @include('admin.users.form')
    </div>
@endsection

