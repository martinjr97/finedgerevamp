@extends('legacy.migration-dashboard.layout')

@section('dashboard-content')
    @include('partials.admin.page-header', ['title' => 'Identity Resolution', 'description' => 'Approved duplicate NRC / alias merges.'])

    <div class="rounded-2xl border bg-white p-6 shadow-sm overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-b bg-slate-100 text-left text-xs uppercase tracking-wide text-slate-700">
                    <th class="px-3 py-2">NRC (masked)</th>
                    <th class="px-3 py-2">Legacy Users</th>
                    <th class="px-3 py-2">Primary</th>
                    <th class="px-3 py-2">Target Customer</th>
                    <th class="px-3 py-2">Classification</th>
                    <th class="px-3 py-2">Reason</th>
                </tr>
            </thead>
            <tbody>
                @forelse($resolutions as $row)
                    <tr class="border-b hover:bg-slate-50">
                        <td class="px-3 py-2 font-mono">{{ $row['nrc'] }}</td>
                        <td class="px-3 py-2">{{ implode(' + ', $row['legacy_users']) }}</td>
                        <td class="px-3 py-2">{{ $row['primary_legacy_user_id'] }}</td>
                        <td class="px-3 py-2">
                            @if($row['target_customer_id'])
                                <a href="{{ route('admin.customers.show', $row['target_customer_id']) }}" class="text-primary hover:underline">#{{ $row['target_customer_id'] }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-3 py-2">{{ $row['classification'] }}</td>
                        <td class="px-3 py-2 text-xs">{{ $row['reason'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-3 py-6 text-center text-slate-500">No approved identity resolutions in registry.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
