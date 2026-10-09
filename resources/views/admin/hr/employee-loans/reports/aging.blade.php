@extends('layouts.admin')
@section('content')
<div class="p-6"><h1 class="text-xl font-semibold mb-4">Employee Loan Aging</h1>
<ul class="space-y-2">@foreach($buckets as $k => $v)<li>{{ $k }}: {{ $v }}</li>@endforeach</ul></div>
@endsection
