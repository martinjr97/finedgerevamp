@extends('layouts.admin')
@section('content')
@include('partials.admin.page-header', ['title'=>'Contract '.$contract->contract_number])
<p class="text-white">{{ $contract->employee?->full_name }} · {{ $contract->contractType?->name }}</p>
@if($contract->status!=='active' && auth('admin')->user()?->can('hr.contracts.manage'))
<form method="POST" action="{{ route('admin.hr.contracts.activate', $contract) }}">@csrf<button class="mt-4 rounded-xl bg-emerald-600 px-4 py-2 text-white text-sm">Activate</button></form>
@endif
@endsection
