@extends('layouts.admin')

@section('title', 'Audit Logs | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Audit Logs',
            'description' => 'Track what changed, by who, and when across records in the system.',
        ])

        @if(! $auditReady)
            <div class="rounded-3xl border border-amber-300 bg-amber-50 p-6">
                <p class="text-sm text-amber-800">
                    Audit logging table is not available yet. Run migrations to enable full audit retrieval.
                </p>
            </div>
        @else
            <div class="rounded-3xl border border-white/10 bg-white p-6 shadow-lg">
                <form method="GET" action="{{ route('admin.audit-logs.index') }}" class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                    <div>
                        <label for="event" class="block text-xs uppercase tracking-wide text-muted mb-2">Event</label>
                        <select id="event" name="event" class="w-full rounded-xl border border-muted bg-white px-3 py-2 text-sm text-primary">
                            <option value="">All Events</option>
                            @foreach($availableEvents as $event)
                                <option value="{{ $event }}" @selected(request('event') === $event)>{{ ucfirst($event) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="model" class="block text-xs uppercase tracking-wide text-muted mb-2">Model</label>
                        <select id="model" name="model" class="w-full rounded-xl border border-muted bg-white px-3 py-2 text-sm text-primary">
                            <option value="">All Models</option>
                            @foreach($availableModels as $model)
                                <option value="{{ $model }}" @selected(request('model') === $model)>{{ class_basename($model) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="actor" class="block text-xs uppercase tracking-wide text-muted mb-2">Actor</label>
                        <input id="actor" name="actor" type="text" value="{{ request('actor') }}" placeholder="Name or ID" class="w-full rounded-xl border border-muted bg-white px-3 py-2 text-sm text-primary">
                    </div>
                    <div>
                        <label for="date_from" class="block text-xs uppercase tracking-wide text-muted mb-2">Date From</label>
                        <input id="date_from" name="date_from" type="date" value="{{ request('date_from') }}" class="w-full rounded-xl border border-muted bg-white px-3 py-2 text-sm text-primary">
                    </div>
                    <div>
                        <label for="date_to" class="block text-xs uppercase tracking-wide text-muted mb-2">Date To</label>
                        <input id="date_to" name="date_to" type="date" value="{{ request('date_to') }}" class="w-full rounded-xl border border-muted bg-white px-3 py-2 text-sm text-primary">
                    </div>
                    <div>
                        <label for="search" class="block text-xs uppercase tracking-wide text-muted mb-2">Search</label>
                        <input id="search" name="search" type="text" value="{{ request('search') }}" placeholder="Type, id, URL..." class="w-full rounded-xl border border-muted bg-white px-3 py-2 text-sm text-primary">
                    </div>
                    <div class="md:col-span-2 xl:col-span-6 flex items-center gap-2">
                        <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white">
                            Apply Filters
                        </button>
                        <a href="{{ route('admin.audit-logs.index') }}" class="inline-flex items-center gap-2 rounded-xl border border-muted bg-white px-4 py-2 text-sm font-medium text-primary hover:bg-slate-50 transition">
                            Reset
                        </a>
                    </div>
                </form>
            </div>

            <div class="rounded-3xl border border-white/10 bg-white p-6 shadow-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full w-full text-sm">
                        <thead>
                            <tr class="bg-slate-100 border-b border-slate-300 text-left">
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.2em] text-slate-800">When</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.2em] text-slate-800">Event</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.2em] text-slate-800">Record</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.2em] text-slate-800">Actor</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.2em] text-slate-800">Changed Fields</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.2em] text-slate-800">Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($auditLogs as $log)
                                <tr class="border-t border-slate-200 align-top">
                                    <td class="px-4 py-3">
                                        <p class="font-medium text-primary">{{ $log->created_at?->format('d M Y H:i:s') ?? '—' }}</p>
                                        @if($log->created_at)
                                            <p class="text-xs text-muted">{{ $log->created_at->diffForHumans() }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex rounded-full border border-primary/25 bg-primary/5 px-2.5 py-1 text-xs font-semibold text-primary">
                                            {{ ucfirst($log->event) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <p class="font-medium text-primary">{{ class_basename($log->auditable_type) }}</p>
                                        <p class="text-xs text-muted">ID: {{ $log->auditable_id }}</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <p class="font-medium text-primary">{{ $log->actor_name ?: 'System' }}</p>
                                        <p class="text-xs text-muted">{{ class_basename($log->actor_type ?? '') ?: '—' }} {{ $log->actor_id ? '#'.$log->actor_id : '' }}</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        @if(!empty($log->changed_fields))
                                            <div class="flex flex-wrap gap-1">
                                                @foreach($log->changed_fields as $field)
                                                    <span class="rounded-full border border-slate-300 bg-slate-100 px-2 py-0.5 text-[11px] text-slate-700">{{ $field }}</span>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <details class="rounded-xl border border-slate-300 bg-slate-50">
                                            <summary class="cursor-pointer px-3 py-2 text-xs font-semibold text-primary">View</summary>
                                            <div class="border-t border-slate-200 px-3 py-3 space-y-3">
                                                <div>
                                                    <p class="text-[11px] uppercase tracking-[0.2em] text-muted mb-1">Old Values</p>
                                                    <pre class="text-xs text-primary whitespace-pre-wrap">{{ json_encode($log->old_values ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                                </div>
                                                <div>
                                                    <p class="text-[11px] uppercase tracking-[0.2em] text-muted mb-1">New Values</p>
                                                    <pre class="text-xs text-primary whitespace-pre-wrap">{{ json_encode($log->new_values ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                                </div>
                                                <div>
                                                    <p class="text-[11px] uppercase tracking-[0.2em] text-muted mb-1">Request</p>
                                                    <pre class="text-xs text-primary whitespace-pre-wrap">{{ json_encode([
                                                        'method' => $log->http_method,
                                                        'ip' => $log->ip_address,
                                                        'url' => $log->url,
                                                        'guard' => $log->actor_guard,
                                                    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                                </div>
                                            </div>
                                        </details>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-10 text-center text-sm text-muted">No audit logs found for the selected filters.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if(method_exists($auditLogs, 'links'))
                    <div class="mt-6">
                        {{ $auditLogs->links() }}
                    </div>
                @endif
            </div>
        @endif
    </div>
@endsection
