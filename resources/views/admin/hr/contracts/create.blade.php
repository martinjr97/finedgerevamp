@extends('layouts.admin')
@section('content')
<form method="POST" action="{{ route('admin.hr.contracts.store') }}" class="rounded-3xl border border-white/10 bg-white/5 p-6 space-y-3 max-w-xl">@csrf
@include('partials.admin.page-header', ['title'=>'New Contract'])
<select name="employee_id" required class="w-full rounded-xl bg-white/10 border border-white/10 px-3 py-2 text-white">@foreach($employees as $e)<option value="{{ $e->id }}" @selected($preselectedEmployeeId===$e->id)>{{ $e->full_name }}</option>@endforeach</select>
<select name="contract_type_id" required class="w-full rounded-xl bg-white/10 border border-white/10 px-3 py-2 text-white">@foreach($contractTypes as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach</select>
<input type="date" name="start_date" required class="w-full rounded-xl bg-white/10 border border-white/10 px-3 py-2 text-white">
<input type="date" name="end_date" class="w-full rounded-xl bg-white/10 border border-white/10 px-3 py-2 text-white">
<select name="status" class="w-full rounded-xl bg-white/10 border border-white/10 px-3 py-2 text-white"><option value="draft">Draft</option><option value="active">Active</option></select>
<button type="submit" class="btn-primary rounded-xl px-4 py-2 text-sm font-semibold">Save</button>
</form>
@endsection
