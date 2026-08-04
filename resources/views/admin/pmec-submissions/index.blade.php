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

        <div class="admin-data-table">
            <div class="admin-data-table__scroll">
                <table class="min-w-full text-sm text-left">
                    <thead class="bg-white/5 text-slate-400 uppercase text-xs tracking-wider">
                        <tr>
                            <th>Batch</th>
                            <th>Product</th>
                            <th>Month</th>
                            <th>Mode</th>
                            <th>Status</th>
                            <th>Loans</th>
                            <th>Generated</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/10">
                        @forelse ($submissions as $submission)
                            <tr>
                                <td class="text-white font-medium">{{ $submission->batch_number }}</td>
                                <td class="text-slate-300">{{ $submission->loanProduct?->name }}</td>
                                <td class="text-slate-300">{{ $submission->submission_month }}</td>
                                <td class="text-slate-300">{{ \App\Support\PmecSubmissionDefaults::modes()[$submission->mode] ?? $submission->mode }}</td>
                                <td>
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs bg-cyan-500/20 text-cyan-300">{{ $submission->status }}</span>
                                </td>
                                <td class="text-slate-300">{{ $submission->items_count }}</td>
                                <td class="text-slate-400">
                                    {{ $submission->generated_at?->format('d M Y H:i') ?? '—' }}
                                    @if ($submission->generatedBy)
                                        <span class="block text-xs">{{ $submission->generatedBy->first_name }} {{ $submission->generatedBy->last_name }}</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    <a href="{{ route('admin.pmec-submissions.show', $submission) }}" class="text-cyan-400 hover:text-cyan-300">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-8 text-center text-slate-400">No PMEC submissions yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($submissions->hasPages())
                <div class="admin-table-footer">{{ $submissions->withQueryString()->links() }}</div>
            @endif
        </div>
    </div>
@endsection
