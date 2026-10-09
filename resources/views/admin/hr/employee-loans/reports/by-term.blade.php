@extends('layouts.admin')
@section('content')
<div class="space-y-4 p-6">
<h1 class="text-xl font-semibold">Loans by Term</h1>
<table class="min-w-full text-sm"><thead><tr><th>Term (months)</th><th>Loans</th><th>Principal</th><th>Outstanding</th></tr></thead>
<tbody>@foreach($rows as $row)<tr><td>{{ $row->tenure_months }}</td><td>{{ $row->loan_count }}</td><td>{{ number_format((float)$row->total_principal,2) }}</td><td>{{ number_format((float)$row->outstanding,2) }}</td></tr>@endforeach</tbody></table>
</div>
@endsection
