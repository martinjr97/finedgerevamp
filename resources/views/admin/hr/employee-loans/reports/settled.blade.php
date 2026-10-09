@extends('layouts.admin')
@section('content')
<div class="space-y-4 p-6">
<h1 class="text-xl font-semibold">Settled Employee Loans</h1>
<table class="min-w-full text-sm"><thead><tr><th>Loan</th><th>Employee</th><th>Settled</th></tr></thead>
<tbody>@foreach($loans as $l)<tr><td>{{ $l->loan_number }}</td><td>{{ $l->employee?->full_name }}</td><td>{{ $l->settled_at?->format('Y-m-d') ?? '—' }}</td></tr>@endforeach</tbody></table>
{{ $loans->links() }}
</div>
@endsection
