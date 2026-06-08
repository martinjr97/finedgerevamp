@extends('layouts.admin')

@section('title', 'Edit Security Question | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        <div class="space-y-2 text-left">
            <p class="text-xs uppercase tracking-[0.4em] text-cyan-300">Configurations</p>
            <h1 class="text-3xl font-bold">Edit Security Question</h1>
        </div>

        @include('admin.security-questions.form')
    </div>
@endsection

