@extends('layouts.admin')

@section('title', 'Payment Operations | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Payment Operations',
            'description' => 'Live gateway queue health, attempt throughput, and operational diagnostics for UAT and production.',
            'buttons' => array_filter([
                [
                    'action' => 'secondary',
                    'text' => 'Failed Financial Jobs',
                    'href' => route('admin.payment-operations.failed-jobs.index'),
                ],
                Route::has('horizon.index') ? [
                    'action' => 'secondary',
                    'text' => 'Open Horizon',
                    'href' => url(config('horizon.path', 'horizon')),
                    'target' => '_blank',
                ] : null,
            ]),
        ])

        @if(session('status'))
            <div class="rounded-2xl border border-emerald-400/60 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">
                {{ session('status') }}
            </div>
        @endif

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                <p class="text-xs uppercase tracking-wide text-slate-400">Redis (default)</p>
                <p class="mt-2 text-lg font-semibold {{ ($metrics['redis']['default'] ?? '') === 'ok' ? 'text-emerald-300' : 'text-rose-300' }}">
                    {{ strtoupper($metrics['redis']['default'] ?? 'unknown') }}
                </p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                <p class="text-xs uppercase tracking-wide text-slate-400">Redis (financial)</p>
                <p class="mt-2 text-lg font-semibold {{ ($metrics['redis']['financial'] ?? '') === 'ok' ? 'text-emerald-300' : 'text-rose-300' }}">
                    {{ strtoupper($metrics['redis']['financial'] ?? 'unknown') }}
                </p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                <p class="text-xs uppercase tracking-wide text-slate-400">Horizon</p>
                <p class="mt-2 text-lg font-semibold {{ ($metrics['horizon']['status'] ?? '') === 'running' ? 'text-emerald-300' : 'text-amber-300' }}">
                    {{ strtoupper($metrics['horizon']['status'] ?? 'unknown') }}
                </p>
                <p class="text-xs text-slate-400 mt-1">{{ $metrics['horizon']['supervisors'] ?? 0 }} supervisor(s)</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                <p class="text-xs uppercase tracking-wide text-slate-400">Failed Financial Jobs</p>
                <p class="mt-2 text-2xl font-semibold {{ ($metrics['failed_financial_jobs'] ?? 0) > 0 ? 'text-rose-300' : 'text-white' }}">
                    {{ number_format($metrics['failed_financial_jobs'] ?? 0) }}
                </p>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            @foreach (['collections' => 'Collections', 'disbursements' => 'Disbursements'] as $key => $label)
                @php($block = $metrics[$key])
                <article class="rounded-3xl border border-cyan-300/20 bg-cyan-500/5 p-6 shadow-lg space-y-5">
                    <h2 class="text-xl font-semibold text-cyan-100">{{ $label }}</h2>
                    <dl class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <dt class="text-slate-400">Waiting</dt>
                            <dd class="text-2xl font-semibold text-white">{{ number_format($block['waiting']) }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-400">Processing</dt>
                            <dd class="text-2xl font-semibold text-amber-200">{{ number_format($block['processing']) }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-400">Failed</dt>
                            <dd class="text-2xl font-semibold text-rose-300">{{ number_format($block['failed']) }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-400">Completed Today</dt>
                            <dd class="text-2xl font-semibold text-emerald-300">{{ number_format($block['completed_today']) }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-400">Avg. Processing Time</dt>
                            <dd class="text-lg font-semibold text-cyan-100">
                                {{ $block['average_seconds'] !== null ? $block['average_seconds'].'s' : '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-slate-400">Oldest Open Attempt</dt>
                            <dd class="text-sm font-medium text-white">
                                @if ($block['oldest'])
                                    <span class="font-mono text-cyan-200">{{ $block['oldest']['correlation_id'] }}</span>
                                    <span class="block text-xs text-slate-400 mt-1">{{ $block['oldest']['age_seconds'] }}s ago · {{ $block['oldest']['status'] }}</span>
                                @else
                                    —
                                @endif
                            </dd>
                        </div>
                    </dl>
                </article>
            @endforeach
        </div>

        <section class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-lg font-semibold text-white">Recent Failed Financial Jobs</h2>
                <a href="{{ route('admin.payment-operations.failed-jobs.index') }}" class="text-sm text-cyan-300 hover:text-cyan-200">View all</a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm text-slate-300">
                    <thead>
                        <tr class="text-xs uppercase tracking-wide text-slate-400 border-b border-white/10">
                            <th class="py-3 pr-4 text-left">Correlation</th>
                            <th class="py-3 pr-4 text-left">Direction</th>
                            <th class="py-3 pr-4 text-left">Queue</th>
                            <th class="py-3 pr-4 text-left">Failed At</th>
                            <th class="py-3 text-left">Summary</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentFailedJobs as $job)
                            <tr class="border-t border-white/5 hover:bg-white/5">
                                <td class="py-3 pr-4">
                                    <a href="{{ route('admin.payment-operations.failed-jobs.show', $job['uuid']) }}" class="font-mono text-cyan-300 hover:underline">
                                        {{ $job['correlation_id'] ?? '—' }}
                                    </a>
                                </td>
                                <td class="py-3 pr-4 capitalize">{{ $job['direction'] ?? '—' }}</td>
                                <td class="py-3 pr-4">{{ $job['queue'] }}</td>
                                <td class="py-3 pr-4">{{ $job['failed_at']->format('Y-m-d H:i') }}</td>
                                <td class="py-3">{{ $job['exception_summary'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-6 text-center text-slate-400">No failed financial jobs.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <p class="text-xs text-slate-500">Snapshot generated {{ $metrics['generated_at'] ?? now()->toIso8601String() }}. Run <code class="text-slate-300">php artisan payments:health</code> from the server for CLI diagnostics.</p>
    </div>
@endsection
