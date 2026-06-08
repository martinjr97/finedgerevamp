@extends('layouts.admin')

@section('title', 'PMEC Submission '.$submission->batch_number.' | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'PMEC Submission',
            'description' => $submission->batch_number,
            'buttons' => [
                [
                    'action' => 'export',
                    'text' => 'Download Excel',
                    'href' => route('admin.pmec-submissions.download', $submission),
                    'can' => auth('admin')->user()?->can('pmec_submissions.export') && filled($submission->file_path),
                ],
                [
                    'action' => 'create',
                    'text' => 'New submission',
                    'href' => route('admin.pmec-submissions.create'),
                    'can' => auth('admin')->user()?->can('pmec_submissions.create'),
                ],
            ],
        ])

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                <p class="text-xs text-slate-400 uppercase">Product</p>
                <p class="text-white font-medium">{{ $submission->loanProduct?->name }}</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                <p class="text-xs text-slate-400 uppercase">Month</p>
                <p class="text-white font-medium">{{ $submission->submission_month }}</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                <p class="text-xs text-slate-400 uppercase">Mode</p>
                <p class="text-white font-medium">{{ $modes[$submission->mode] ?? $submission->mode }}</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                <p class="text-xs text-slate-400 uppercase">Status</p>
                <p class="text-white font-medium">{{ $submissionStatuses[$submission->status] ?? $submission->status }}</p>
            </div>
        </div>

        @if ($submission->notes)
            <p class="text-sm text-slate-400"><span class="text-slate-300">Notes:</span> {{ $submission->notes }}</p>
        @endif

        <div class="rounded-3xl border border-white/10 bg-white/5 overflow-hidden shadow-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm text-left">
                    <thead class="bg-white/5 text-slate-400 uppercase text-xs">
                        <tr>
                            <th class="px-3 py-3">PERNR</th>
                            <th class="px-3 py-3">Name</th>
                            <th class="px-3 py-3">NRC</th>
                            <th class="px-3 py-3">Loan</th>
                            <th class="px-3 py-3">BEGDA</th>
                            <th class="px-3 py-3">ENDDA</th>
                            <th class="px-3 py-3">BETRG</th>
                            <th class="px-3 py-3">Item status</th>
                            <th class="px-3 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/10">
                        @foreach ($submission->items as $item)
                            <tr class="hover:bg-white/5">
                                <td class="px-3 py-3 text-slate-300">{{ $item->pernr }}</td>
                                <td class="px-3 py-3 text-white">{{ $item->first_name }} {{ $item->surname }}</td>
                                <td class="px-3 py-3 text-slate-300">{{ $item->nrc }}</td>
                                <td class="px-3 py-3 text-slate-300">{{ $item->loan?->loan_number }}</td>
                                <td class="px-3 py-3 text-slate-300">{{ $item->begda->format('d.m.Y') }}</td>
                                <td class="px-3 py-3 text-slate-300">{{ $item->endda->format('d.m.Y') }}</td>
                                <td class="px-3 py-3 text-slate-300">{{ number_format($item->betrg, 2) }}</td>
                                <td class="px-3 py-3">
                                    <span class="text-xs text-cyan-300">{{ $itemStatuses[$item->status] ?? $item->status }}</span>
                                    @if ($item->failure_reason)
                                        <p class="text-xs text-rose-300 mt-1">{{ $item->failure_reason }}</p>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-right space-x-2">
                                    @can('pmec_submissions.export')
                                        @if ($item->status !== 'submitted')
                                            <form action="{{ route('admin.pmec-submissions.items.mark-submitted', $item) }}" method="POST" class="inline">
                                                @csrf
                                                <button type="submit" class="text-xs text-emerald-400 hover:text-emerald-300">Mark submitted</button>
                                            </form>
                                        @endif
                                    @endcan
                                    @can('pmec_submissions.mark_failed')
                                        @if ($item->status !== 'failed')
                                            <form action="{{ route('admin.pmec-submissions.items.mark-failed', $item) }}" method="POST" class="inline" onsubmit="return confirm('Mark this item as failed/missed for PMEC?');">
                                                @csrf
                                                <input type="hidden" name="failure_reason" value="Marked failed by admin">
                                                <button type="submit" class="text-xs text-rose-400 hover:text-rose-300">Mark failed</button>
                                            </form>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
