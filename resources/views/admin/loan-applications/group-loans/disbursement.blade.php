@extends('layouts.admin')

@section('title', 'Group Loan Disbursement | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Group Loan Disbursement - '.$application->reference,
            'description' => 'Disburse member allocations individually using existing loan disbursement logic',
            'buttons' => [
                [
                    'action' => 'secondary',
                    'text' => 'Back to Application',
                    'href' => route('admin.loan-applications.group-loans.show', $application),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>'
                ]
            ]
        ])

        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <div class="grid gap-4 md:grid-cols-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Application Status</p>
                    <p class="font-semibold text-white">{{ ucwords(str_replace('_', ' ', $application->status)) }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Disbursement Mode</p>
                    <p class="font-semibold text-white">{{ ucfirst($disbursementType) }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Total Members</p>
                    <p class="font-semibold text-white">{{ $application->members->count() }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Total Disbursement</p>
                    <p class="font-semibold text-cyan-300">ZMW {{ number_format((float) $application->total_disbursement_amount, 2) }}</p>
                </div>
            </div>
        </div>

        @if ($disbursementType !== 'manual' && in_array($application->status, ['awaiting_disbursement', 'partially_disbursed'], true))
            <div class="rounded-3xl border border-cyan-500/30 bg-cyan-950/30 p-6 shadow-lg flex items-center justify-between gap-4">
                <div>
                    <p class="text-sm text-cyan-100">Automated disbursement is enabled. This will process all members with pending disbursement.</p>
                </div>
                <form method="POST" action="{{ route('admin.loan-applications.group-loans.auto-disburse', $application) }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center rounded-2xl bg-cyan-500 px-4 py-3 text-sm font-semibold text-white hover:bg-cyan-600 transition">Run Automated Disbursement</button>
                </form>
            </div>
        @elseif ($disbursementType === 'manual')
            <div class="rounded-3xl border border-amber-500/30 bg-amber-500/10 p-6 shadow-lg">
                <p class="text-sm text-amber-100">Manual mode is enabled. Open each member loan and complete disbursement using the existing manual disbursement action.</p>
            </div>
        @endif

        <div class="rounded-3xl border border-white/10 bg-white/5 shadow-lg overflow-hidden">
            <div class="px-5 py-4 border-b border-white/10">
                <h2 class="text-xl font-semibold text-white">Member Disbursement Breakdown</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm text-slate-300">
                    <thead class="bg-white/[0.03] text-xs uppercase tracking-[0.2em] text-slate-400">
                        <tr>
                            <th class="px-4 py-3 text-left">Customer</th>
                            <th class="px-4 py-3 text-left">Title</th>
                            <th class="px-4 py-3 text-left">Disbursement Amount</th>
                            <th class="px-4 py-3 text-left">Account Reference</th>
                            <th class="px-4 py-3 text-left">Disbursement Status</th>
                            <th class="px-4 py-3 text-left">Reference</th>
                            <th class="px-4 py-3 text-right">Loan Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($application->members as $member)
                            <tr class="border-t border-white/5">
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-white">{{ $member->customer?->full_name ?? 'N/A' }}</p>
                                    <p class="text-xs text-slate-400">{{ $member->loan?->loan_number ?? 'Loan pending' }}</p>
                                </td>
                                <td class="px-4 py-3">{{ $member->groupMemberTitle?->name ?? 'N/A' }}</td>
                                <td class="px-4 py-3">ZMW {{ number_format((float) $member->disbursement_amount, 2) }}</td>
                                <td class="px-4 py-3">{{ $member->disbursement_account_reference ?: ($member->customer?->phone ?: 'N/A') }}</td>
                                <td class="px-4 py-3">{{ ucfirst($member->disbursement_status ?? 'pending') }}</td>
                                <td class="px-4 py-3">{{ $member->disbursement_reference ?: '—' }}</td>
                                <td class="px-4 py-3 text-right">
                                    @if ($member->loan)
                                        <a href="{{ route('admin.loans.show', $member->loan) }}" class="rounded-xl border border-white/20 px-3 py-1.5 text-xs font-semibold text-white hover:bg-white/10 transition">
                                            Open Loan
                                        </a>
                                    @else
                                        <span class="text-xs text-slate-500">Loan not created</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
