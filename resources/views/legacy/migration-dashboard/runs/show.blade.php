@extends('legacy.migration-dashboard.layout')

@section('dashboard-content')
    @php $run = $detail->run; $summary = $detail->summary; @endphp
    @include('partials.admin.page-header', [
        'title' => 'Run #'.$run->id,
        'description' => ($run->phase ?? 'migration').' · '.($run->status ?? 'unknown'),
        'buttons' => [['text' => 'Back to runs', 'href' => route('legacy.migration-dashboard.runs.index'), 'action' => 'secondary']],
    ])

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-2xl border bg-white p-6 shadow-sm text-sm space-y-2">
            <h2 class="font-semibold text-primary">Run metadata</h2>
            <p><span class="text-slate-500">UUID:</span> <span class="font-mono">{{ $run->run_uuid ?? '—' }}</span></p>
            <p><span class="text-slate-500">Phase:</span> {{ $run->phase }}</p>
            <p><span class="text-slate-500">Scope:</span> {{ $run->scope ?? '—' }}</p>
            <p><span class="text-slate-500">Mode:</span> {{ ($summary['promote'] ?? false) ? 'promote' : 'dry-run' }}</p>
            <p><span class="text-slate-500">Started:</span> {{ $run->started_at }}</p>
            <p><span class="text-slate-500">Completed:</span> {{ $run->completed_at ?? '—' }}</p>
        </div>
        <div class="rounded-2xl border bg-white p-6 shadow-sm text-sm">
            <h2 class="font-semibold text-primary mb-2">Result counts</h2>
            <dl class="grid grid-cols-2 gap-2">
                @foreach($detail->counts as $key => $value)
                    <div><dt class="text-slate-500 uppercase text-xs">{{ $key }}</dt><dd class="font-bold">{{ number_format($value) }}</dd></div>
                @endforeach
            </dl>
        </div>
    </div>

    @if(!empty($summary))
        <div class="mt-6 rounded-2xl border bg-white p-6 shadow-sm">
            <h2 class="font-semibold text-primary mb-2">Summary (safe fields)</h2>
            <pre class="overflow-x-auto rounded-xl bg-slate-50 p-4 text-xs text-slate-800">{{ json_encode(collect($summary)->except(['password', 'secret', 'token', 'api_key', 'credentials']), JSON_PRETTY_PRINT) }}</pre>
        </div>
    @endif

    <div class="mt-6 grid gap-4 lg:grid-cols-2">
        <div class="rounded-2xl border bg-white p-6 shadow-sm overflow-x-auto">
            <h2 class="font-semibold text-primary mb-3">Created records ({{ $detail->created_records->count() }})</h2>
            <table class="min-w-full text-xs">
                <thead><tr class="border-b bg-slate-100"><th class="px-2 py-1 text-left">Type</th><th class="px-2 py-1 text-left">Target ID</th><th class="px-2 py-1 text-left">Legacy</th></tr></thead>
                <tbody>
                    @forelse($detail->created_records as $record)
                        <tr class="border-b"><td class="px-2 py-1">{{ $record->record_type }}</td><td class="px-2 py-1">{{ $record->target_id }}</td><td class="px-2 py-1">{{ $record->legacy_identifier ?? '—' }}</td></tr>
                    @empty
                        <tr><td colspan="3" class="py-4 text-center text-slate-500">None</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="rounded-2xl border bg-white p-6 shadow-sm overflow-x-auto">
            <h2 class="font-semibold text-primary mb-3">Entity maps ({{ $detail->entity_maps->count() }})</h2>
            <table class="min-w-full text-xs">
                <thead><tr class="border-b bg-slate-100"><th class="px-2 py-1 text-left">Type</th><th class="px-2 py-1 text-left">Legacy</th><th class="px-2 py-1 text-left">Target</th><th class="px-2 py-1 text-left">Method</th></tr></thead>
                <tbody>
                    @forelse($detail->entity_maps as $map)
                        <tr class="border-b"><td class="px-2 py-1">{{ $map->entity_type }}</td><td class="px-2 py-1">{{ $map->legacy_identifier }}</td><td class="px-2 py-1">{{ $map->target_id }}</td><td class="px-2 py-1">{{ $map->mapping_method }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="py-4 text-center text-slate-500">None</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
