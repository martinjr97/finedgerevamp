@extends('layouts.admin')

@section('title', 'Edit Company | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        <div class="space-y-2 text-left">
            <p class="text-xs uppercase tracking-[0.4em] text-cyan-300">Partners</p>
            <h1 class="text-3xl font-bold">Edit Company: {{ $company->name }}</h1>
        </div>

        @include('admin.companies.form')
    </div>
@endsection

