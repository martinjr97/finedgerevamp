@extends('layouts.admin')
@section('content')
<form method="POST" action="{{ route('admin.hr.departments.update', $department) }}" class="rounded-3xl border border-white/10 bg-white/5 p-6 space-y-3 max-w-xl">@csrf @method('PUT')
@include('partials.admin.page-header', ['title'=>'Edit Department'])
<input name="code" required value="{{ $department->code }}" class="w-full rounded-xl bg-white/10 border border-white/10 px-3 py-2 text-white">
<input name="name" required value="{{ $department->name }}" class="w-full rounded-xl bg-white/10 border border-white/10 px-3 py-2 text-white">
<select name="head_employee_id" class="w-full rounded-xl bg-white/10 border border-white/10 px-3 py-2 text-white"><option value="">Head employee</option>@foreach($employees as $e)<option value="{{ $e->id }}" @selected($department->head_employee_id==$e->id)>{{ $e->full_name }}</option>@endforeach</select>
<button class="rounded-xl bg-cyan-600 px-4 py-2 text-white">Update</button>
</form>
@endsection
