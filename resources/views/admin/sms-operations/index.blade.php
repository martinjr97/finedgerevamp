@extends('layouts.admin')

@section('title', 'SMS Operations | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'SMS Operations',
            'description' => 'SMS gateway configuration, queue health, and recent outbound message audit.',
        ])

        <div class="rounded-2xl border px-4 py-3 text-sm
            @if($overall['status'] === 'pass') border-emerald-400/60 bg-emerald-500/10 text-emerald-100
            @elseif($overall['status'] === 'warning') border-amber-400/60 bg-amber-500/10 text-amber-100
            @else border-rose-400/60 bg-rose-500/10 text-rose-100 @endif">
            Overall status: <strong>{{ strtoupper($overall['status']) }}</strong>
            @if($overall['detail'])
                — {{ $overall['detail'] }}
            @endif
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                <p class="text-xs uppercase tracking-wide text-slate-400">SMS Enabled</p>
                <p class="mt-2 text-lg font-semibold {{ $snapshot['enabled'] ? 'text-emerald-300' : 'text-amber-300' }}">
                    {{ $snapshot['enabled'] ? 'YES' : 'NO' }}
                </p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                <p class="text-xs uppercase tracking-wide text-slate-400">Provider</p>
                <p class="mt-2 text-lg font-semibold text-white">{{ strtoupper($snapshot['provider']) }}</p>
                <p class="text-xs text-slate-400 mt-1">Queue: {{ $snapshot['queue'] }}</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                <p class="text-xs uppercase tracking-wide text-slate-400">Pending</p>
                <p class="mt-2 text-2xl font-semibold {{ $snapshot['pending'] > 0 ? 'text-amber-200' : 'text-white' }}">
                    {{ number_format($snapshot['pending']) }}
                </p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                <p class="text-xs uppercase tracking-wide text-slate-400">Failed Today</p>
                <p class="mt-2 text-2xl font-semibold {{ $snapshot['failed_today'] > 0 ? 'text-rose-300' : 'text-emerald-300' }}">
                    {{ number_format($snapshot['failed_today']) }}
                </p>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <article class="rounded-3xl border border-cyan-300/20 bg-cyan-500/5 p-6 shadow-lg space-y-4">
                <h2 class="text-xl font-semibold text-cyan-100">Configuration</h2>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-400">Queue connection</dt>
                        <dd class="text-white font-mono">{{ $snapshot['queue_connection'] }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-400">Redis</dt>
                        <dd class="{{ $snapshot['redis_ok'] ? 'text-emerald-300' : 'text-rose-300' }}">
                            {{ $snapshot['redis_ok'] ? 'OK' : 'FAIL' }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-400">Zamtel configured</dt>
                        <dd class="{{ $snapshot['zamtel_configured'] ? 'text-emerald-300' : 'text-amber-300' }}">
                            {{ $snapshot['zamtel_configured'] ? 'Yes' : 'No' }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-400">Provider health</dt>
                        <dd class="text-white text-right">{{ $snapshot['provider_health']->responseMessage }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-400">Sent today</dt>
                        <dd class="text-emerald-300">{{ number_format($snapshot['sent_today']) }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-400">Skipped today</dt>
                        <dd class="text-amber-200">{{ number_format($snapshot['skipped_today']) }}</dd>
                    </div>
                </dl>
            </article>

            <article class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
                <h2 class="text-xl font-semibold text-white">UAT / Testing</h2>
                <p class="text-sm text-slate-300">
                    Use the CLI to verify SMS without exposing credentials in the admin UI.
                </p>
                <div class="rounded-xl bg-slate-900/80 p-4 font-mono text-xs text-cyan-200 space-y-2">
                    <p>php artisan sms:health</p>
                    <p>php artisan sms:test --to=26097xxxxxxx --message="Test message"</p>
                    <p>php artisan sms:test --to=26097xxxxxxx --message="Test" --provider=zamtel --force</p>
                </div>
                <p class="text-xs text-slate-400">
                    Defaults: <code class="text-slate-300">SMS_PROVIDER=log</code>, <code class="text-slate-300">SMS_ENABLED=false</code>.
                    Never commit real API keys.
                </p>
            </article>
        </div>

        @if($snapshot['recent_failures']->isNotEmpty())
            <article class="rounded-3xl border border-rose-300/20 bg-rose-500/5 p-6 shadow-lg space-y-4">
                <h2 class="text-xl font-semibold text-rose-100">Latest Failures</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-slate-400 text-left">
                            <tr>
                                <th class="pb-2 pr-4">ID</th>
                                <th class="pb-2 pr-4">Type</th>
                                <th class="pb-2 pr-4">Category</th>
                                <th class="pb-2 pr-4">Failed</th>
                                <th class="pb-2">Reason</th>
                            </tr>
                        </thead>
                        <tbody class="text-slate-200">
                            @foreach($snapshot['recent_failures'] as $failure)
                                <tr class="border-t border-white/5">
                                    <td class="py-2 pr-4 font-mono">#{{ $failure->id }}</td>
                                    <td class="py-2 pr-4">{{ $failure->message_type }}</td>
                                    <td class="py-2 pr-4">{{ $failure->message_category?->value ?? $failure->message_category }}</td>
                                    <td class="py-2 pr-4">{{ $failure->failed_at?->diffForHumans() }}</td>
                                    <td class="py-2">{{ data_get($failure->provider_response, 'message', $failure->skip_reason ?? '—') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </article>
        @endif

        <article class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
            <h2 class="text-xl font-semibold text-white">Recent SMS Messages</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-slate-400 text-left">
                        <tr>
                            <th class="pb-2 pr-4">ID</th>
                            <th class="pb-2 pr-4">Type</th>
                            <th class="pb-2 pr-4">Category</th>
                            <th class="pb-2 pr-4">Status</th>
                            <th class="pb-2 pr-4">Provider</th>
                            <th class="pb-2 pr-4">Preview</th>
                            <th class="pb-2">Created</th>
                        </tr>
                    </thead>
                    <tbody class="text-slate-200">
                        @forelse($recentMessages as $msg)
                            <tr class="border-t border-white/5">
                                <td class="py-2 pr-4 font-mono">#{{ $msg->id }}</td>
                                <td class="py-2 pr-4">{{ $msg->message_type }}</td>
                                <td class="py-2 pr-4">{{ $msg->message_category?->value ?? $msg->message_category }}</td>
                                <td class="py-2 pr-4">
                                    <span class="rounded-full px-2 py-0.5 text-xs
                                        @if($msg->status === 'sent') bg-emerald-500/20 text-emerald-300
                                        @elseif($msg->status === 'failed') bg-rose-500/20 text-rose-300
                                        @elseif($msg->status === 'skipped') bg-amber-500/20 text-amber-300
                                        @else bg-slate-500/20 text-slate-300 @endif">
                                        {{ $msg->status }}
                                    </span>
                                </td>
                                <td class="py-2 pr-4">{{ $msg->provider }}</td>
                                <td class="py-2 pr-4 max-w-xs truncate">{{ $msg->message_preview ?? $msg->message_body }}</td>
                                <td class="py-2">{{ $msg->created_at?->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-6 text-center text-slate-400">No SMS messages recorded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>
    </div>
@endsection
