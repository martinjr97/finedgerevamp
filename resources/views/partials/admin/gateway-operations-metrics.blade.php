@if ($gatewayOperationsMetrics ?? null)
    <section>
        <article class="rounded-3xl border border-cyan-300/20 bg-cyan-500/5 p-6 shadow-lg space-y-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold text-cyan-100">Gateway Operations</h2>
                    <p class="text-sm text-cyan-200/70 mt-1">Summary metrics — open the full operations dashboard for queue health and failed job recovery.</p>
                </div>
                <a href="{{ route('admin.payment-operations.index') }}"
                   class="inline-flex items-center gap-2 rounded-xl border border-cyan-300/30 bg-cyan-500/10 px-3 py-2 text-xs font-semibold text-cyan-100 hover:bg-cyan-500/20 transition">
                    Payment Operations
                </a>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                @foreach (['collections' => 'Collections', 'disbursements' => 'Disbursements'] as $key => $label)
                    @php($metrics = $gatewayOperationsMetrics[$key])
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4 space-y-3">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-300">{{ $label }}</h3>
                        <dl class="grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <dt class="text-slate-400">Processing</dt>
                                <dd class="text-2xl font-semibold text-white">{{ number_format($metrics['processing'] ?? 0) }}</dd>
                            </div>
                            <div>
                                <dt class="text-slate-400">Completed</dt>
                                <dd class="text-2xl font-semibold text-emerald-300">{{ number_format($metrics['completed'] ?? 0) }}</dd>
                            </div>
                            <div>
                                <dt class="text-slate-400">Failed</dt>
                                <dd class="text-2xl font-semibold text-rose-300">{{ number_format($metrics['failed'] ?? 0) }}</dd>
                            </div>
                            <div>
                                <dt class="text-slate-400">Avg. time</dt>
                                <dd class="text-lg font-semibold text-cyan-100">
                                    @if (($metrics['average_seconds'] ?? null) !== null)
                                        {{ $metrics['average_seconds'] }}s
                                    @else
                                        —
                                    @endif
                                </dd>
                            </div>
                        </dl>
                    </div>
                @endforeach
            </div>
        </article>
    </section>
@endif
