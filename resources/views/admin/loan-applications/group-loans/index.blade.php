@extends('layouts.admin')

@section('title', 'Group Loan Applications | '.config('app.system_name'))

@section('content')
    @php
        $canCreateLoans = auth('admin')->user()?->can('loans.create');
        $canAssignRelationshipManager = auth('admin')->user()?->can('can assign relationship manager to group');
    @endphp
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Group Loan Applications',
            'description' => 'Track group loan applications and their disbursement status',
            'buttons' => [
                [
                    'action' => 'secondary',
                    'text' => 'New Application',
                    'href' => route('admin.loan-applications.index'),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>'
                ]
            ]
        ])

        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <form method="GET" action="{{ route('admin.loan-applications.group-loans.index') }}" class="grid gap-4 md:grid-cols-4">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Reference, group, loan name"
                           class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2.5 focus:border-cyan-400 focus:ring-cyan-400/40">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Status</label>
                    <select name="status" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2.5 focus:border-cyan-400 focus:ring-cyan-400/40">
                        <option value="">All Statuses</option>
                        @foreach (['pending_approval', 'awaiting_disbursement', 'partially_disbursed', 'disbursed', 'rejected'] as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucwords(str_replace('_', ' ', $status)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Group</label>
                    <select name="customer_group_id" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2.5 focus:border-cyan-400 focus:ring-cyan-400/40">
                        <option value="">All Groups</option>
                        @foreach ($groups as $group)
                            <option value="{{ $group->id }}" @selected((int) request('customer_group_id') === $group->id)>{{ $group->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-cyan-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-cyan-600 transition">
                        Apply Filters
                    </button>
                    <a href="{{ route('admin.loan-applications.group-loans.index') }}" class="inline-flex items-center justify-center rounded-2xl border border-white/20 px-4 py-2.5 text-sm font-medium text-slate-300 hover:bg-white/10 transition">
                        Reset
                    </a>
                </div>
            </form>
        </div>

        <div class="rounded-3xl border border-white/10 bg-white/5 shadow-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm text-slate-300">
                    <thead class="bg-white/[0.03] text-xs uppercase tracking-[0.2em] text-slate-400">
                        <tr>
                            <th class="px-4 py-3 text-left">Reference</th>
                            <th class="px-4 py-3 text-left">Loan / Group</th>
                            <th class="px-4 py-3 text-left">Members</th>
                            <th class="px-4 py-3 text-left">Totals</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-left">Submitted</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($applications as $application)
                            <tr class="border-t border-white/5">
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-white">{{ $application->reference }}</p>
                                    <p class="text-xs text-slate-400">{{ $application->loanProduct?->name ?? 'N/A' }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <p class="text-white">{{ $application->loan_name }}</p>
                                    <p class="text-xs text-slate-400">{{ $application->group_name }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <p>{{ number_format($application->members_count) }} selected</p>
                                    <p class="text-xs text-slate-400">{{ ucfirst($application->repayment_structure) }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <p>Disb.: ZMW {{ number_format((float) $application->total_disbursement_amount, 2) }}</p>
                                    <p class="text-xs text-slate-400">Repay.: ZMW {{ number_format((float) $application->total_repayment_amount, 2) }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    @php
                                        $itemMetadata = is_array($application->metadata) ? $application->metadata : [];
                                        $itemRejectionResolution = (string) data_get($itemMetadata, 'rejection.resolution', '');
                                        $itemActionRequired = trim((string) data_get($itemMetadata, 'rejection.action_required', ''));
                                        $itemIsAssignedRelationshipManager = (int) ($application->relationship_manager_id ?? 0) === (int) auth('admin')->id();
                                        $itemCanPrepareRevision = $canCreateLoans
                                            && ($canAssignRelationshipManager || $itemIsAssignedRelationshipManager)
                                            && $application->status === 'rejected'
                                            && $itemRejectionResolution === 'changes_requested';
                                        $itemStatusLabel = $application->status === 'rejected' && $itemRejectionResolution === 'changes_requested'
                                            ? 'Changes Requested'
                                            : ucwords(str_replace('_', ' ', $application->status));
                                    @endphp
                                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold
                                        {{ $application->status === 'pending_approval' ? 'bg-amber-500/20 text-amber-200' : '' }}
                                        {{ $application->status === 'awaiting_disbursement' ? 'bg-blue-500/20 text-blue-200' : '' }}
                                        {{ $application->status === 'partially_disbursed' ? 'bg-indigo-500/20 text-indigo-200' : '' }}
                                        {{ $application->status === 'disbursed' ? 'bg-emerald-500/20 text-emerald-200' : '' }}
                                        {{ $application->status === 'rejected' && $itemRejectionResolution === 'changes_requested' ? 'bg-orange-500/20 text-orange-200' : '' }}
                                        {{ $application->status === 'rejected' && $itemRejectionResolution !== 'changes_requested' ? 'bg-rose-500/20 text-rose-200' : '' }}
                                    ">
                                        {{ $itemStatusLabel }}
                                    </span>
                                    @if ($application->status === 'rejected' && $itemRejectionResolution === 'changes_requested' && $itemActionRequired !== '')
                                        <p class="mt-1 max-w-xs whitespace-normal text-[11px] text-orange-200/90">
                                            Action required: {{ \Illuminate\Support\Str::limit($itemActionRequired, 120) }}
                                        </p>
                                    @endif
                                </td>
                                <td class="px-4 py-3">{{ optional($application->submitted_at ?? $application->created_at)->format('d M Y, H:i') }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <a href="{{ route('admin.loan-applications.group-loans.show', $application) }}" class="rounded-xl border border-white/20 px-3 py-1.5 text-xs font-semibold text-white hover:bg-white/10 transition">View</a>
                                        @if ($itemCanPrepareRevision)
                                            <form method="POST" action="{{ route('admin.loan-applications.group-loans.revision-draft', $application) }}">
                                                @csrf
                                                <button type="submit" class="rounded-xl border border-orange-400/50 bg-orange-500/20 px-3 py-1.5 text-xs font-semibold text-orange-100 hover:bg-orange-500/30 transition">
                                                    Modify Application
                                                </button>
                                            </form>
                                        @endif
                                        @if (in_array($application->status, ['awaiting_disbursement', 'partially_disbursed'], true))
                                            <a href="{{ route('admin.loan-applications.group-loans.disbursement', $application) }}" class="rounded-xl border border-cyan-500/40 px-3 py-1.5 text-xs font-semibold text-cyan-200 hover:bg-cyan-500/10 transition">Disbursement</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-10 text-center text-slate-400">No group loan applications found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            {{ $applications->links() }}
        </div>
    </div>
@endsection
