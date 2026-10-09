@extends('layouts.admin')
@section('content')
<div class="space-y-4 p-6">
<h1 class="text-xl font-semibold">Employee Loan Portfolio</h1>
<table class="min-w-full text-sm"><thead><tr><th>Loan</th><th>Employee</th><th>Outstanding</th><th>Status</th></tr></thead>
<tbody>@foreach($loans as $l)<tr><td>{{ $l->loan_number }}</td><td>{{ $l->employee?->full_name }}</td><td>{{ number_format((float)$l->outstanding_balance,2) }}</td><td>{{ $l->status }}</td></tr>@endforeach</tbody></table>
{{ $loans->links() }}
</div>
@endsection
