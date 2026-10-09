@extends('layouts.admin')
@section('content')
<div class="space-y-4 p-6"><h1 class="text-xl font-semibold">Employee Repayments</h1>
<table class="min-w-full text-sm"><thead><tr><th>Date</th><th>Loan</th><th>Amount</th></tr></thead>
<tbody>@foreach($repayments as $r)<tr><td>{{ $r->effective_date->format('Y-m-d') }}</td><td>{{ $r->employeeLoan?->loan_number }}</td><td>{{ number_format((float)$r->amount,2) }}</td></tr>@endforeach</tbody></table>
{{ $repayments->links() }}</div>
@endsection
