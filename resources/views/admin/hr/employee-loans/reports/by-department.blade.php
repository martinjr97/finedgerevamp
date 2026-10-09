@extends('layouts.admin')
@section('content')
<div class="p-6"><h1 class="text-xl font-semibold mb-4">Loans by Department</h1>
<table class="min-w-full text-sm"><thead><tr><th>Department</th><th>Loans</th><th>Outstanding</th></tr></thead>
<tbody>@foreach($rows as $row)<tr><td>{{ $row['department'] }}</td><td>{{ $row['loan_count'] }}</td><td>{{ number_format($row['outstanding'],2) }}</td></tr>@endforeach</tbody></table></div>
@endsection
