@extends('layouts.admin')
@section('content')
<div class="p-6 space-y-4"><h1 class="text-xl font-semibold">Employee Loan Arrears</h1>
<table class="min-w-full text-sm"><thead><tr><th>Loan</th><th>Due</th><th>Remaining</th><th>Days overdue</th></tr></thead>
<tbody>@foreach($schedules as $s)<tr><td>{{ $s->employeeLoan?->loan_number }}</td><td>{{ $s->due_date->format('Y-m-d') }}</td><td>{{ number_format((float)$s->remaining_amount,2) }}</td><td>{{ $s->days_overdue }}</td></tr>@endforeach</tbody></table>
{{ $schedules->links() }}</div>
@endsection
