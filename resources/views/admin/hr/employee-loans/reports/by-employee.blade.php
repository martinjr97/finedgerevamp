@extends('layouts.admin')
@section('content')
<div class="space-y-4 p-6">
<h1 class="text-xl font-semibold">Loans by Employee</h1>
<table class="min-w-full text-sm"><thead><tr><th>Employee</th><th>Loans</th><th>Borrowed</th><th>Outstanding</th></tr></thead>
<tbody>@foreach($rows as $row)<tr><td>{{ $row['employee']?->full_name ?? '—' }}</td><td>{{ $row['loan_count'] }}</td><td>{{ number_format($row['total_borrowed'],2) }}</td><td>{{ number_format($row['outstanding'],2) }}</td></tr>@endforeach</tbody></table>
</div>
@endsection
