@extends('layouts.admin')

@section('title', 'Failed Job | Payment Operations')

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Failed Financial Job',
            'description' => 'Operational recovery view — sensitive payloads are not displayed.',
            'buttons' => [
                [
                    'action' => 'secondary',
                    'text' => '← Back to list',
                    'href' => route('admin.payment-operations.failed-jobs.index'),
                ],
            ],
        ])

        @if(session('status'))
            <div class="rounded-2xl border border-emerald-400/60 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">
                {{ session('status') }}
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-2">
            <article class="rounded-3xl border border-white/10 bg-white/5 p-6 space-y-4">
                <h2 class="text-lg font-semibold text-white">Job Details</h2>
                <dl class="grid gap-3 text-sm">
                    <div><dt class="text-slate-400">Correlation ID</dt><dd class="font-mono text-cyan-300">{{ $job['correlation_id'] ?? '—' }}</dd></div>
                    <div><dt class="text-slate-400">Gateway</dt><dd>{{ $job['gateway_code'] ?? '—' }}</dd></div>
                    <div><dt class="text-slate-400">Direction</dt><dd class="capitalize">{{ $job['direction'] ?? '—' }}</dd></div>
                    <div><dt class="text-slate-400">Queue</dt><dd>{{ $job['queue'] }}</dd></div>
                    <div><dt class="text-slate-400">Connection</dt><dd>{{ $job['connection'] }}</dd></div>
                    <div><dt class="text-slate-400">Job Class</dt><dd>{{ $job['job_class'] ?? '—' }}</dd></div>
                    <div><dt class="text-slate-400">Failed At</dt><dd>{{ $job['failed_at']->format('Y-m-d H:i:s') }}</dd></div>
                    <div><dt class="text-slate-400">Loan</dt>
                        <dd>
                            @if ($job['loan_id'])
                                <a href="{{ route('admin.loans.show', $job['loan_id']) }}" class="text-cyan-300 hover:underline">{{ $job['loan_number'] ?? '#'.$job['loan_id'] }}</a>
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div><dt class="text-slate-400">Customer</dt><dd>{{ $job['customer_name'] ?? ($job['customer_id'] ? '#'.$job['customer_id'] : '—') }}</dd></div>
                </dl>
            </article>

            <article class="rounded-3xl border border-rose-300/20 bg-rose-500/5 p-6 space-y-4">
                <h2 class="text-lg font-semibold text-rose-100">Exception Summary</h2>
                <p class="text-sm text-slate-200 whitespace-pre-wrap">{{ $job['exception_summary'] }}</p>
                @if (!empty($job['exception_detail']))
                    <pre class="text-xs text-slate-400 overflow-x-auto rounded-xl bg-black/20 p-4">{{ $job['exception_detail'] }}</pre>
                @endif
            </article>
        </div>

        @can('payment-gateways.manage')
            <div class="flex flex-wrap gap-3">
                <form action="{{ route('admin.payment-operations.failed-jobs.retry', $job['uuid']) }}" method="POST">
                    @csrf
                    <button type="submit" class="rounded-xl bg-emerald-500/20 border border-emerald-400/40 px-4 py-2 text-sm font-semibold text-emerald-100 hover:bg-emerald-500/30">
                        Retry Job
                    </button>
                </form>

                <form action="{{ route('admin.payment-operations.failed-jobs.discard', $job['uuid']) }}" method="POST" onsubmit="return confirm('Discard this failed job permanently?');">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="confirm" value="1">
                    <button type="submit" class="rounded-xl bg-rose-500/20 border border-rose-400/40 px-4 py-2 text-sm font-semibold text-rose-100 hover:bg-rose-500/30">
                        Discard Job
                    </button>
                </form>
            </div>
        @endcan
    </div>
@endsection
