@extends('layouts.admin')
@section('content')
<div class="space-y-4 p-6">
<h1 class="text-xl font-semibold">Active Employee Loans</h1>
<table class="min-w-full text-sm"><thead><tr><th>Loan</th><th>Employee</th><th>Outstanding</th></tr></thead>
<tbody>@foreach($loans as $l)<tr><td>{{ $l->loan_number }}</td><td>{{ $l->employee?->full_name }}</td><td>{{ number_format((float)$l->outstanding_balance,2) }}</td></tr>@endforeach</tbody></table>
{{ $loans->links() }}
</div>
@endsection
