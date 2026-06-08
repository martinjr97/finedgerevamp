@extends('layouts.admin')

@section('title', 'PMEC Submissions | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'PMEC Submissions',
            'buttons' => [
                [
                    'action' => 'create',
                    'text' => 'New submission',
                    'href' => route('admin.pmec-submissions.create'),
                    'can' => auth('admin')->user()?->can('pmec_submissions.create'),
                ],
            ],
        ])

        <div class="rounded-3xl border border-white/10 bg-white/5 overflow-hidden shadow-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm text-left">
                    <thead class="bg-white/5 text-slate-400 uppercase text-xs tracking-wider">
                        <tr>
                            <th class="px-4 py-3">Batch</th>
                            <th class="px-4 py-3">Product</th>
                            <th class="px-4 py-3">Month</th>
                            <th class="px-4 py-3">Mode</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Loans</th>
                            <th class="px-4 py-3">Generated</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/10">
                        @forelse ($submissions as $submission)
                            <tr class="hover:bg-white/5">
                                <td class="px-4 py-3 text-white font-medium">{{ $submission->batch_number }}</td>
                                <td class="px-4 py-3 text-slate-300">{{ $submission->loanProduct?->name }}</td>
                                <td class="px-4 py-3 text-slate-300">{{ $submission->submission_month }}</td>
                                <td class="px-4 py-3 text-slate-300">{{ \App\Support\PmecSubmissionDefaults::modes()[$submission->mode] ?? $submission->mode }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs bg-cyan-500/20 text-cyan-300">{{ $submission->status }}</span>
                                </td>
                                <td class="px-4 py-3 text-slate-300">{{ $submission->items_count }}</td>
                                <td class="px-4 py-3 text-slate-400">
                                    {{ $submission->generated_at?->format('d M Y H:i') ?? '—' }}
                                    @if ($submission->generatedBy)
                                        <span class="block text-xs">{{ $submission->generatedBy->first_name }} {{ $submission->generatedBy->last_name }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.pmec-submissions.show', $submission) }}" class="text-cyan-400 hover:text-cyan-300">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-slate-400">No PMEC submissions yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($submissions->hasPages())
                <div class="px-4 py-3 border-t border-white/10">{{ $submissions->links() }}</div>
            @endif
        </div>
    </div>
@endsection
