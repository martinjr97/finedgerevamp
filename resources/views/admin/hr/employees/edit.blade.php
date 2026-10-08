@extends('layouts.admin')
@section('content')
<div class="space-y-6">
    @include('partials.admin.page-header', ['title' => 'Edit Employee'])
    <form method="POST" action="{{ route('admin.hr.employees.update', $employee) }}" class="rounded-3xl border border-white/10 bg-white/5 p-6 space-y-4">@csrf @method('PUT')
        @include('admin.hr.employees.partials.form')
        <button class="rounded-2xl bg-cyan-600 px-6 py-2 text-white font-semibold">Update</button>
    </form>
</div>
@endsection
