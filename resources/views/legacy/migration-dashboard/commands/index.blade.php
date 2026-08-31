@extends('legacy.migration-dashboard.layout')

@section('dashboard-content')
    @include('partials.admin.page-header', [
        'title' => 'Migration Commands',
        'description' => 'Phased CLI playbook — run one dataset at a time. Always dry-run, review, then promote.',
    ])

    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 mb-6">
        <p class="font-semibold">Mandatory execution order</p>
        <p class="mt-1 font-mono text-xs sm:text-sm">{{ $executionOrder }}</p>
        <p class="mt-2 text-xs font-normal">Commands are not exposed in the browser. Copy each command and run it on the server via SSH. Use the Runs and Reconciliation tabs to verify results after each phase.</p>
    </div>

    <div class="space-y-6">
        @foreach ($phases as $phase)
            <section class="rounded-2xl border bg-white p-6 shadow-sm {{ ! empty($phase['optional']) ? 'border-dashed border-slate-300' : '' }}">
                <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">
                            Phase {{ $phase['number'] }}
                            @if (! empty($phase['optional']))
                                <span class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-slate-600">Optional</span>
                            @endif
                        </p>
                        <h2 class="text-lg font-semibold text-primary">{{ $phase['title'] }}</h2>
                        <p class="mt-1 text-sm text-slate-600">{{ $phase['description'] }}</p>
                    </div>
                </div>

                <ol class="space-y-3">
                    @foreach ($phase['steps'] as $index => $step)
                        <li class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <div class="flex flex-wrap items-center gap-2 mb-2">
                                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-primary text-xs font-bold text-white">{{ $index + 1 }}</span>
                                <span class="text-sm font-semibold text-slate-800">{{ $step['label'] }}</span>
                                @if (! empty($step['destructive']))
                                    <span class="rounded bg-rose-100 px-2 py-0.5 text-xs font-semibold text-rose-700">writes data</span>
                                @endif
                            </div>
                            <div class="relative group">
                                <pre class="overflow-x-auto rounded-lg border border-slate-300 bg-white px-4 py-3 text-xs font-mono text-slate-900 shadow-inner"><code class="text-slate-900">{{ $step['command'] }}</code></pre>
                            </div>
                            @if (! empty($step['notes']))
                                <p class="mt-2 text-xs text-slate-500">{{ $step['notes'] }}</p>
                            @endif
                        </li>
                    @endforeach
                </ol>

                @if (! empty($phase['gates']))
                    <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                        <p class="text-xs font-semibold uppercase text-emerald-800 mb-2">Promotion gates — verify before next phase</p>
                        <ul class="list-disc list-inside space-y-1 text-sm text-emerald-900">
                            @foreach ($phase['gates'] as $gate)
                                <li>{{ $gate }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </section>
        @endforeach
    </div>

    <div class="mt-8 space-y-4">
        <h2 class="text-lg font-semibold text-primary">Utilities &amp; advanced options</h2>
        @foreach ($utilities as $group)
            <div class="rounded-2xl border bg-white p-6 shadow-sm">
                <h3 class="font-semibold text-slate-800 mb-3">{{ $group['title'] }}</h3>
                <div class="space-y-3">
                    @foreach ($group['items'] as $item)
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-sm font-medium text-slate-700 mb-2">{{ $item['label'] }}</p>
                            <pre class="overflow-x-auto rounded-lg border border-slate-300 bg-white px-4 py-3 text-xs font-mono text-slate-900 shadow-inner"><code class="text-slate-900">{{ $item['command'] }}</code></pre>
                            @if (! empty($item['notes']))
                                <p class="mt-2 text-xs text-slate-500">{{ $item['notes'] }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
@endsection
