@extends('layouts.admin')

@section('title', 'Failed Financial Jobs | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Failed Financial Jobs',
            'description' => 'Review and recover failed payment and disbursement queue jobs without opening Horizon.',
            'buttons' => [
                [
                    'action' => 'secondary',
                    'text' => '← Payment Operations',
                    'href' => route('admin.payment-operations.index'),
                ],
            ],
        ])

        @if(session('status'))
            <div class="rounded-2xl border border-emerald-400/60 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">
                {{ session('status') }}
            </div>
        @endif

        <div class="rounded-3xl border border-white/10 bg-white/5 p-4 shadow-lg overflow-x-auto">
            <table data-datatable="true" data-datatable-per-page="25" class="min-w-full w-full text-sm text-slate-300">
                <thead>
                    <tr class="text-xs font-semibold uppercase tracking-[0.2em] text-white/80 text-left border-b-2 border-white/20">
                        <th class="px-3 py-4">Correlation ID</th>
                        <th class="px-3 py-4">Gateway</th>
                        <th class="px-3 py-4">Direction</th>
                        <th class="px-3 py-4">Loan</th>
                        <th class="px-3 py-4">Customer</th>
                        <th class="px-3 py-4">Queue</th>
                        <th class="px-3 py-4">Job</th>
                        <th class="px-3 py-4">Failed At</th>
                        <th class="px-3 py-4">Exception</th>
                        <th class="px-3 py-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($failedJobs as $job)
                        <tr class="border-t border-white/10 hover:bg-white/5">
                            <td class="px-3 py-4 font-mono text-cyan-300">{{ $job['correlation_id'] ?? '—' }}</td>
                            <td class="px-3 py-4">{{ $job['gateway_code'] ?? '—' }}</td>
                            <td class="px-3 py-4 capitalize">{{ $job['direction'] ?? '—' }}</td>
                            <td class="px-3 py-4">
                                @if ($job['loan_id'])
                                    <a href="{{ route('admin.loans.show', $job['loan_id']) }}" class="text-cyan-300 hover:underline">{{ $job['loan_number'] ?? '#'.$job['loan_id'] }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-3 py-4">{{ $job['customer_name'] ?? ($job['customer_id'] ? '#'.$job['customer_id'] : '—') }}</td>
                            <td class="px-3 py-4">{{ $job['queue'] }}</td>
                            <td class="px-3 py-4">{{ $job['job_class'] ?? '—' }}</td>
                            <td class="px-3 py-4 whitespace-nowrap">{{ $job['failed_at']->format('Y-m-d H:i:s') }}</td>
                            <td class="px-3 py-4 max-w-xs truncate" title="{{ $job['exception_summary'] }}">{{ $job['exception_summary'] }}</td>
                            <td class="px-3 py-4 whitespace-nowrap space-x-2">
                                <a href="{{ route('admin.payment-operations.failed-jobs.show', $job['uuid']) }}" class="text-cyan-300 hover:underline">View</a>
                                @can('payment-gateways.manage')
                                    <form action="{{ route('admin.payment-operations.failed-jobs.retry', $job['uuid']) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="text-emerald-300 hover:underline">Retry</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-3 py-8 text-center text-slate-400">No failed financial jobs found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
