@extends('layouts.admin')

@section('title', 'Group Loan Application | '.config('app.system_name'))

@section('content')
    @php
        $canApprove = auth('admin')->user()?->can('loans.approve') || auth('admin')->user()?->can('approvals.approve');
        $canReject = auth('admin')->user()?->can('loans.reject') || auth('admin')->user()?->can('approvals.reject');
        $canDisburse = auth('admin')->user()?->can('loans.disburse');
        $canViewCustomers = auth('admin')->user()?->can('customers.view');
        $canCreateLoans = auth('admin')->user()?->can('loans.create');
        $canAssignRelationshipManager = auth('admin')->user()?->can('can assign relationship manager to group');
        $isAssignedRelationshipManager = (int) ($application->relationship_manager_id ?? 0) === (int) auth('admin')->id();
        $isApplicationSubmitter = (int) ($application->created_by ?? 0) === (int) auth('admin')->id();
        $canPrepareRevision = $canCreateLoans && ($canAssignRelationshipManager || $isAssignedRelationshipManager);
        $canAddModificationNote = $canCreateLoans && ($isAssignedRelationshipManager || $isApplicationSubmitter);
        $canTakeDecision = $application->status === 'pending_approval' && ($canApprove || $canReject);
        $metadata = is_array($application->metadata) ? $application->metadata : [];
        $rejectionResolution = (string) data_get($metadata, 'rejection.resolution', '');
        $rejectionActionRequired = (string) data_get($metadata, 'rejection.action_required', '');
        $rejectionStatus = (string) data_get($metadata, 'rejection.status', '');
        $statusLabel = $application->status === 'rejected' && $rejectionResolution === 'changes_requested'
            ? 'Changes Requested'
            : ucwords(str_replace('_', ' ', $application->status));
        $statusBadgeClass = match (true) {
            $application->status === 'pending_approval' => 'bg-amber-500/20 text-amber-200',
            $application->status === 'awaiting_disbursement' => 'bg-blue-500/20 text-blue-200',
            $application->status === 'partially_disbursed' => 'bg-indigo-500/20 text-indigo-200',
            $application->status === 'disbursed' => 'bg-emerald-500/20 text-emerald-200',
            $application->status === 'rejected' && $rejectionResolution === 'changes_requested' => 'bg-orange-500/20 text-orange-200',
            $application->status === 'rejected' => 'bg-rose-500/20 text-rose-200',
            default => 'bg-slate-500/20 text-slate-200',
        };
        $decisionTrail = collect(data_get($metadata, 'decision_trail', []))
            ->filter(fn ($entry) => is_array($entry))
            ->reverse()
            ->values();
        $openRejectModal = $errors->has('rejection_resolution') || $errors->has('notes') || $errors->has('action_required');
    @endphp

    <div
        class="space-y-8"
        x-data="{ openApproveModal: false, openRejectModal: {{ $openRejectModal ? 'true' : 'false' }}, rejectionResolution: '{{ old('rejection_resolution', 'changes_requested') }}' }"
        x-on:keydown.escape.window="openApproveModal = false; openRejectModal = false"
    >
        @include('partials.admin.page-header', [
            'title' => 'Group Loan '.$application->reference,
            'description' => 'Review, approval, and disbursement details',
            'buttons' => [
                [
                    'action' => 'secondary',
                    'text' => 'Back to Group Loans',
                    'href' => route('admin.loan-applications.group-loans.index'),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>'
                ]
            ]
        ])

        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Status</p>
                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $statusBadgeClass }}">
                        {{ $statusLabel }}
                    </span>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Submitted By</p>
                    <p class="text-white font-semibold">{{ $application->creator?->full_name ?? 'System' }}</p>
                    <p class="text-xs text-slate-400">{{ optional($application->submitted_at ?? $application->created_at)->format('d M Y, H:i') }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Approved By</p>
                    <p class="text-white font-semibold">{{ $application->approver?->full_name ?? 'Not approved yet' }}</p>
                    <p class="text-xs text-slate-400">{{ optional($application->approved_at)->format('d M Y, H:i') ?: '—' }}</p>
                </div>
            </div>
        </div>

        @if ($canTakeDecision)
            <div class="rounded-3xl border border-amber-500/30 bg-amber-500/10 p-6 shadow-lg">
                <h2 class="text-lg font-semibold text-amber-100 mb-3">Approval Decision</h2>
                <p class="text-sm text-amber-100/90 mb-4">
                    Use the confirmation modals below to approve or reject this group loan application.
                </p>
                <div class="flex flex-wrap gap-3">
                    @if ($canApprove)
                        <button type="button" x-on:click="openApproveModal = true" class="inline-flex items-center rounded-2xl bg-emerald-500 text-white px-4 py-3 text-sm font-semibold hover:bg-emerald-600 transition" style="color: #fff !important;">Approve Application</button>
                    @endif
                    @if ($canReject)
                        <button type="button" x-on:click="openRejectModal = true" class="inline-flex items-center rounded-2xl bg-rose-500 text-white px-4 py-3 text-sm font-semibold hover:bg-rose-600 transition" style="color: #fff !important;">Reject Application</button>
                    @endif
                </div>
            </div>
        @endif

        <div class="grid gap-6 md:grid-cols-2">
            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-3">
                <h2 class="text-lg font-semibold text-white">Application Details</h2>
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Group Loan Name</p>
                    <p class="text-white">{{ $application->loan_name }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Product</p>
                    <p class="text-white">{{ $application->loanProduct?->name ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Group Name</p>
                    <p class="text-white">{{ $application->group_name }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Relationship Manager</p>
                    <p class="text-white">{{ $application->relationshipManager?->full_name ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Repayment Structure</p>
                    <p class="text-white">{{ ucfirst($application->repayment_structure) }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Start / Due Date</p>
                    <p class="text-white">{{ optional($application->start_date)->format('d M Y') }} - {{ optional($application->due_date)->format('d M Y') }}</p>
                </div>
                @if ($application->terms_and_conditions)
                    <div>
                        <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Terms and Conditions</p>
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-3 text-sm text-slate-200 whitespace-pre-line">{{ $application->terms_and_conditions }}</div>
                    </div>
                @endif
            </div>

            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-3">
                <h2 class="text-lg font-semibold text-white">Rates and Totals</h2>
                <div class="grid gap-3 md:grid-cols-2 text-sm">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Processing Fee (%)</p>
                        <p class="text-white">{{ number_format((float) $application->processing_fee_percentage, 4) }}%</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Interest Rate for Full Period (%)</p>
                        <p class="text-white">{{ number_format((float) $application->monthly_interest_rate, 4) }}%</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Arrears Rate (%)</p>
                        <p class="text-white">{{ number_format((float) $application->arrears_rate, 4) }}%</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Members</p>
                        <p class="text-white">{{ $application->members->count() }}</p>
                    </div>
                </div>
                <div class="grid gap-3 md:grid-cols-2 text-sm">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Total Principal</p>
                        <p class="text-white">ZMW {{ number_format((float) $application->total_principal_amount, 2) }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Total Processing Fees</p>
                        <p class="text-white">ZMW {{ number_format((float) $application->total_processing_fee_amount, 2) }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Total Interest</p>
                        <p class="text-white">ZMW {{ number_format((float) $application->total_interest_amount, 2) }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Projected Repayment Total</p>
                        <p class="text-emerald-300">ZMW {{ number_format((float) $application->total_repayment_amount, 2) }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Total Disbursement</p>
                        <p class="text-cyan-300">ZMW {{ number_format((float) $application->total_disbursement_amount, 2) }}</p>
                    </div>
                </div>
            </div>
        </div>

        @if ($application->status === 'rejected' && $rejectionResolution === 'changes_requested')
            <div class="rounded-3xl border border-orange-500/30 bg-orange-500/10 p-6 shadow-lg space-y-4">
                <div>
                    <h2 class="text-lg font-semibold text-orange-100">Modification Request</h2>
                    <p class="text-sm text-orange-100/90 mt-1">
                        Reviewer sent this application back for updates. Relationship manager should revise members/amounts as instructed, then resubmit.
                    </p>
                </div>
                @if ($application->approval_notes)
                    <div>
                        <p class="text-xs uppercase tracking-wide text-orange-200/80 mb-1">Reviewer Notes</p>
                        <p class="text-sm text-orange-50 whitespace-pre-line">{{ $application->approval_notes }}</p>
                    </div>
                @endif
                @if ($rejectionActionRequired !== '')
                    <div>
                        <p class="text-xs uppercase tracking-wide text-orange-200/80 mb-1">Required Modifications</p>
                        <p class="text-sm text-orange-50 whitespace-pre-line">{{ $rejectionActionRequired }}</p>
                    </div>
                @endif
                @if ($canPrepareRevision)
                    <div class="pt-1">
                        <form method="POST" action="{{ route('admin.loan-applications.group-loans.revision-draft', $application) }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center rounded-2xl bg-orange-500 text-white px-4 py-2.5 text-sm font-semibold hover:bg-orange-600 transition" style="color: #fff !important;">Modify Application</button>
                        </form>
                    </div>
                @endif
                @if ($canAddModificationNote)
                    <div class="rounded-2xl border border-orange-400/20 bg-black/10 p-4">
                        <p class="text-xs uppercase tracking-wide text-orange-200/80 mb-2">Modification Note</p>
                        <p class="text-xs text-orange-100/80 mb-3">Relationship manager or original submitter can add a progress/update note before resubmitting. This will be recorded in the decision trail.</p>
                        <form method="POST" action="{{ route('admin.loan-applications.group-loans.store-modification-note', $application) }}" class="space-y-3">
                            @csrf
                            <textarea name="modification_note" rows="3" class="w-full rounded-2xl bg-white/10 border border-white/15 text-white px-4 py-3 text-sm focus:border-orange-400 focus:ring-orange-400/40" placeholder="e.g. Removed member X and reduced total principal to ZMW 42,000 as requested." required>{{ old('modification_note') }}</textarea>
                            @error('modification_note')<p class="text-xs text-rose-300">{{ $message }}</p>@enderror
                            <div class="flex justify-end">
                                <button type="submit" class="inline-flex items-center rounded-2xl bg-orange-600 text-white px-4 py-2.5 text-sm font-semibold hover:bg-orange-700 transition" style="color: #fff !important;">Add Note to Trail</button>
                            </div>
                        </form>
                    </div>
                @endif
                @if (! $canPrepareRevision)
                    <p class="text-xs text-orange-100/80">Only the assigned relationship manager (or an authorized assigner) can modify this application and resubmit it.</p>
                @endif
            </div>
        @endif

        <div class="rounded-3xl border border-white/10 bg-white/5 shadow-lg overflow-hidden">
            <div class="px-5 py-4 border-b border-white/10">
                <h2 class="text-xl font-semibold text-white">Member Allocations</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm text-slate-300">
                    <thead class="bg-white/[0.03] text-xs uppercase tracking-[0.2em] text-slate-400">
                        <tr>
                            <th class="px-4 py-3 text-left">Customer</th>
                            <th class="px-4 py-3 text-left">Group Title</th>
                            <th class="px-4 py-3 text-left">Principal</th>
                            <th class="px-4 py-3 text-left">Repayment</th>
                            <th class="px-4 py-3 text-left">Disbursement Account</th>
                            <th class="px-4 py-3 text-left">Member Disbursement</th>
                            <th class="px-4 py-3 text-right">Loan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($application->members as $member)
                            <tr class="border-t border-white/5">
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-white">{{ $member->customer?->full_name ?? 'N/A' }}</p>
                                    <p class="text-xs text-slate-400">{{ $member->customer?->phone ?: 'No phone' }}</p>
                                    @if ($canViewCustomers && $member->customer)
                                        <a href="{{ route('admin.customers.show', $member->customer) }}" class="inline-flex mt-2 rounded-xl border border-cyan-500/40 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-cyan-200 hover:bg-cyan-500/10 transition">View Customer Profile</a>
                                    @endif
                                </td>
                                <td class="px-4 py-3">{{ $member->groupMemberTitle?->name ?? 'N/A' }}</td>
                                <td class="px-4 py-3">ZMW {{ number_format((float) $member->principal_amount, 2) }}</td>
                                <td class="px-4 py-3">ZMW {{ number_format((float) $member->calculated_total_repayment_amount, 2) }}</td>
                                <td class="px-4 py-3">{{ $member->disbursement_account_reference ?: ($member->customer?->phone ?: 'N/A') }}</td>
                                <td class="px-4 py-3">{{ ucfirst($member->disbursement_status ?? 'pending') }}</td>
                                <td class="px-4 py-3 text-right">
                                    @if ($member->loan)
                                        <a href="{{ route('admin.loans.show', $member->loan) }}" class="rounded-xl border border-white/20 px-3 py-1.5 text-xs font-semibold text-white hover:bg-white/10 transition">{{ $member->loan->loan_number }}</a>
                                    @else
                                        <span class="text-xs text-slate-500">Not created</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if ($application->documents->isNotEmpty())
            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                <h2 class="text-lg font-semibold text-white mb-4">Supporting Documents</h2>
                <div class="grid gap-3 md:grid-cols-2">
                    @foreach ($application->documents as $document)
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                            <p class="font-semibold text-white">{{ $document->document_name }}</p>
                            @if ($document->description)
                                <p class="text-sm text-slate-300 mt-2">{{ $document->description }}</p>
                            @endif
                            <p class="mt-2 text-xs text-slate-400">
                                Uploaded {{ optional($document->created_at)->format('d M Y, H:i') ?: 'N/A' }} by {{ $document->uploader?->full_name ?? 'System' }}
                            </p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <a href="{{ route('admin.loan-applications.group-loans.documents.view', [$application, $document]) }}" target="_blank" rel="noopener" class="inline-flex rounded-xl border border-cyan-500/40 px-3 py-1.5 text-xs font-semibold text-cyan-200 hover:bg-cyan-500/10 transition">View Attachment</a>
                                <a href="{{ route('admin.loan-applications.group-loans.documents.download', [$application, $document]) }}" class="inline-flex rounded-xl border border-emerald-500/40 px-3 py-1.5 text-xs font-semibold text-emerald-200 hover:bg-emerald-500/10 transition">Download Attachment</a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($decisionTrail->isNotEmpty())
            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                <h2 class="text-lg font-semibold text-white mb-4">Decision Trail</h2>
                <div class="space-y-3">
                    @foreach ($decisionTrail as $trailItem)
                        @php
                            $trailAction = ucwords(str_replace('_', ' ', (string) ($trailItem['action'] ?? 'update')));
                            $trailTitle = (string) ($trailItem['event_title'] ?? $trailAction);
                            $trailAt = !empty($trailItem['at']) ? \Carbon\Carbon::parse($trailItem['at']) : null;
                        @endphp
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="font-semibold text-white">{{ $trailTitle }}</p>
                                <p class="text-xs text-slate-400">{{ $trailAt?->format('d M Y, H:i') ?? 'N/A' }}</p>
                            </div>
                            <p class="text-xs text-slate-400 mt-1">By {{ $trailItem['actor_name'] ?? 'System' }}</p>
                            @if (!empty($trailItem['notes']))
                                <p class="text-sm text-slate-200 mt-2 whitespace-pre-line">{{ $trailItem['notes'] }}</p>
                            @endif
                            @if (!empty($trailItem['action_required']))
                                <p class="text-xs text-orange-200 mt-2">Action required: {{ $trailItem['action_required'] }}</p>
                            @endif
                            @if (!empty($trailItem['revision_application_id']) || !empty($trailItem['revision_application_reference']))
                                <p class="text-xs text-cyan-200 mt-2">
                                    Revision submitted:
                                    @if (!empty($trailItem['revision_application_id']))
                                        <a href="{{ route('admin.loan-applications.group-loans.show', (int) $trailItem['revision_application_id']) }}" class="underline hover:text-cyan-100">
                                            {{ $trailItem['revision_application_reference'] ?? ('Application #'.$trailItem['revision_application_id']) }}
                                        </a>
                                    @else
                                        {{ $trailItem['revision_application_reference'] }}
                                    @endif
                                </p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($canDisburse && in_array($application->status, ['awaiting_disbursement', 'partially_disbursed'], true))
            <div class="flex justify-end">
                <a href="{{ route('admin.loan-applications.group-loans.disbursement', $application) }}" class="inline-flex items-center rounded-2xl bg-cyan-500 px-4 py-3 text-sm font-semibold text-white hover:bg-cyan-600 transition" style="color: #fff !important;">Go to Disbursement</a>
            </div>
        @endif

        @if ($canTakeDecision && $canApprove)
            <div
                x-cloak
                x-show="openApproveModal"
                x-transition.opacity
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
                role="dialog"
                aria-modal="true"
                aria-label="Approve group loan application"
            >
                <div class="absolute inset-0 bg-slate-950/75" x-on:click="openApproveModal = false"></div>

                <div class="relative w-full max-w-lg rounded-3xl border border-emerald-500/30 bg-slate-900 p-6 shadow-2xl">
                    <div class="mb-4 flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-semibold text-white">Confirm Approval</h3>
                            <p class="mt-1 text-sm text-slate-300">This will move the application to <strong>Awaiting Disbursement</strong> and create member loan records.</p>
                        </div>
                        <button type="button" class="rounded-xl border border-white/20 px-3 py-1.5 text-sm text-slate-300 hover:bg-white/10" x-on:click="openApproveModal = false">Close</button>
                    </div>

                    <form method="POST" action="{{ route('admin.loan-applications.group-loans.approve', $application) }}" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-sm font-medium text-slate-300">Approval Notes (optional)</label>
                            <textarea name="notes" rows="3" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-emerald-400 focus:ring-emerald-400/40"></textarea>
                        </div>

                        <div class="flex justify-end gap-3">
                            <button type="button" class="inline-flex items-center rounded-2xl border border-white/20 px-4 py-2.5 text-sm font-medium text-slate-300 hover:bg-white/10 transition" x-on:click="openApproveModal = false">Cancel</button>
                            <button type="submit" class="inline-flex items-center rounded-2xl bg-emerald-500 text-white px-4 py-2.5 text-sm font-semibold hover:bg-emerald-600 transition" style="color: #fff !important;">Confirm Approve</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        @if ($canTakeDecision && $canReject)
            <div
                x-cloak
                x-show="openRejectModal"
                x-transition.opacity
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
                role="dialog"
                aria-modal="true"
                aria-label="Reject group loan application"
            >
                <div class="absolute inset-0 bg-slate-950/75" x-on:click="openRejectModal = false"></div>

                <div class="relative w-full max-w-lg rounded-3xl border border-rose-500/30 bg-slate-900 p-6 shadow-2xl">
                    <div class="mb-4 flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-semibold text-white">Confirm Rejection</h3>
                            <p class="mt-1 text-sm text-slate-300">This will reject the application and stop it from progressing to disbursement.</p>
                        </div>
                        <button type="button" class="rounded-xl border border-white/20 px-3 py-1.5 text-sm text-slate-300 hover:bg-white/10" x-on:click="openRejectModal = false">Close</button>
                    </div>

                    <form method="POST" action="{{ route('admin.loan-applications.group-loans.reject', $application) }}" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-sm font-medium text-slate-300">Decision Type</label>
                            <select name="rejection_resolution" x-model="rejectionResolution" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-rose-400 focus:ring-rose-400/40" required>
                                <option value="changes_requested" @selected(old('rejection_resolution', 'changes_requested') === 'changes_requested')>Send Back for Modifications</option>
                                <option value="rejected_permanent" @selected(old('rejection_resolution') === 'rejected_permanent')>Permanent Rejection</option>
                            </select>
                            @error('rejection_resolution')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-300">Rejection Notes</label>
                            <textarea name="notes" rows="3" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-rose-400 focus:ring-rose-400/40" required>{{ old('notes') }}</textarea>
                            @error('notes')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
                        </div>
                        <div x-show="rejectionResolution === 'changes_requested'" x-cloak>
                            <label class="block text-sm font-medium text-slate-300">Required Modifications</label>
                            <textarea name="action_required" rows="3" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-orange-400 focus:ring-orange-400/40" :required="rejectionResolution === 'changes_requested'" placeholder="e.g. Remove 2 ineligible members and reduce total principal to ZMW 45,000.">{{ old('action_required') }}</textarea>
                            @error('action_required')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
                        </div>

                        <div class="flex justify-end gap-3">
                            <button type="button" class="inline-flex items-center rounded-2xl border border-white/20 px-4 py-2.5 text-sm font-medium text-slate-300 hover:bg-white/10 transition" x-on:click="openRejectModal = false">Cancel</button>
                            <button type="submit" class="inline-flex items-center rounded-2xl bg-rose-500 text-white px-4 py-2.5 text-sm font-semibold hover:bg-rose-600 transition" style="color: #fff !important;">Confirm Reject</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
@endsection
