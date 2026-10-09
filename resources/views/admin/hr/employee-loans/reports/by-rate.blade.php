@extends('layouts.admin')
@section('content')
<div class="space-y-4 p-6">
<h1 class="text-xl font-semibold">Loans by Rate</h1>
<table class="min-w-full text-sm"><thead><tr><th>Rate</th><th>Loans</th><th>Outstanding</th></tr></thead>
<tbody>@foreach($rows as $row)<tr><td>{{ $row['rate']?->term_interest_percentage ?? '—' }}% / {{ $row['rate']?->tenure_months ?? '—' }}m</td><td>{{ $row['loan_count'] }}</td><td>{{ number_format($row['outstanding'],2) }}</td></tr>@endforeach</tbody></table>
</div>
@endsection
