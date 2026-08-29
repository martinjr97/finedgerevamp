@extends('legacy.migration-dashboard.layout')

@section('dashboard-content')
    @include('partials.admin.page-header', ['title' => 'Migration Runs', 'description' => 'History of migration command executions.'])

    <div class="rounded-2xl border bg-white p-6 shadow-sm overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-b bg-slate-100 text-left text-xs uppercase tracking-wide text-slate-700">
                    <th class="px-3 py-2">Run UUID</th>
                    <th class="px-3 py-2">Phase</th>
                    <th class="px-3 py-2">Mode</th>
                    <th class="px-3 py-2">Started</th>
                    <th class="px-3 py-2">Completed</th>
                    <th class="px-3 py-2">Status</th>
                    <th class="px-3 py-2">Read</th>
                    <th class="px-3 py-2">Created</th>
                    <th class="px-3 py-2">Matched</th>
                    <th class="px-3 py-2">Skipped</th>
                    <th class="px-3 py-2">Manual</th>
                    <th class="px-3 py-2">Failed</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($runs as $run)
                    <tr class="border-b hover:bg-slate-50">
                        <td class="px-3 py-2 font-mono text-xs">{{ $run->run_uuid ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $run->phase }}</td>
                        <td class="px-3 py-2">{{ $run->promote ? 'promote' : 'dry-run' }}</td>
                        <td class="px-3 py-2">{{ $run->started_at }}</td>
                        <td class="px-3 py-2">{{ $run->completed_at ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $run->status }}</td>
                        <td class="px-3 py-2">{{ $run->counts['read'] }}</td>
                        <td class="px-3 py-2">{{ $run->counts['created'] }}</td>
                        <td class="px-3 py-2">{{ $run->counts['matched'] }}</td>
                        <td class="px-3 py-2">{{ $run->counts['skipped'] }}</td>
                        <td class="px-3 py-2">{{ $run->counts['manual'] }}</td>
                        <td class="px-3 py-2">{{ $run->counts['failed'] }}</td>
                        <td class="px-3 py-2">
                            <a href="{{ route('legacy.migration-dashboard.runs.show', $run->id) }}" class="text-primary font-semibold hover:underline">Open</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="13" class="px-3 py-6 text-center text-slate-500">No migration runs recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $runs->links() }}</div>
    </div>
@endsection
