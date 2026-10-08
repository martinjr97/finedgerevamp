@extends('layouts.admin')
@section('content')
<form method="POST" action="{{ route('admin.hr.departments.store') }}" class="rounded-3xl border border-white/10 bg-white/5 p-6 space-y-3 max-w-xl">@csrf
@include('partials.admin.page-header', ['title'=>'Add Department'])
<input name="code" required placeholder="Code" class="w-full rounded-xl bg-white/10 border border-white/10 px-3 py-2 text-white">
<input name="name" required placeholder="Name" class="w-full rounded-xl bg-white/10 border border-white/10 px-3 py-2 text-white">
<select name="head_employee_id" class="w-full rounded-xl bg-white/10 border border-white/10 px-3 py-2 text-white"><option value="">Head employee</option>@foreach($employees as $e)<option value="{{ $e->id }}">{{ $e->full_name }}</option>@endforeach</select>
<button type="submit" class="btn-primary rounded-xl px-4 py-2 text-sm font-semibold">Save</button>
</form>
@endsection
