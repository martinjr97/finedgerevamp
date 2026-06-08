@extends('layouts.admin')

@section('title', 'Review Group Loan | '.config('app.system_name'))

@section('content')
    @php
        $memberIds = collect($wizard['member_ids'] ?? [])->map(fn ($id) => (int) $id);
        $totals = $wizard['totals'] ?? [];
        $openRelationshipManagerModal = $errors->has('relationship_manager_id');
    @endphp

    <div
        class="space-y-8"
        x-data="{ openRelationshipManagerModal: {{ $openRelationshipManagerModal ? 'true' : 'false' }} }"
        x-on:keydown.escape.window="openRelationshipManagerModal = false"
    >
        @include('partials.admin.page-header', [
            'title' => 'Review Group Loan Application',
            'description' => 'Confirm rates, members, and totals before submission',
            'buttons' => [
                [
                    'action' => 'secondary',
                    'text' => 'Back to Documents',
                    'href' => route('admin.loan-applications.group-loans.documents', $loanProduct),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>'
                ],
                [
                    'action' => 'primary',
                    'text' => 'Download Corporate Copy (PDF)',
                    'href' => route('admin.loan-applications.group-loans.review-print', $loanProduct),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9V2h12v7M6 18h12m-12 0v4h12v-4m-12 0H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/></svg>'
                ]
            ]
        ])

        <div class="grid gap-6 md:grid-cols-2">
            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
                <h2 class="text-lg font-semibold text-white">Group Loan Details</h2>
                <div class="grid gap-3 text-sm text-slate-200">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Group Loan Name</p>
                        <p class="text-white font-semibold">{{ $wizard['loan_name'] ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Product</p>
                        <p>{{ $loanProduct->name }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Group Name</p>
                        <p>{{ $group?->name ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Relationship Manager</p>
                            @if ($canAssignRelationshipManager && $relationshipManagers->isNotEmpty())
                                <button
                                    type="button"
                                    class="inline-flex items-center rounded-xl border border-cyan-500/40 bg-cyan-500/10 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-cyan-200 hover:bg-cyan-500/20 transition"
                                    x-on:click="openRelationshipManagerModal = true"
                                >
                                    Change
                                </button>
                            @endif
                        </div>
                        <p>{{ $relationshipManager?->full_name ?? 'Unassigned' }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Repayment Structure</p>
                        <p>{{ ucfirst($wizard['repayment_structure'] ?? 'monthly') }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Start Date</p>
                        <p>{{ !empty($wizard['start_date']) ? \Carbon\Carbon::parse($wizard['start_date'])->format('d M Y') : 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Due Date</p>
                        <p>{{ !empty($wizard['due_date']) ? \Carbon\Carbon::parse($wizard['due_date'])->format('d M Y') : 'N/A' }}</p>
                    </div>
                </div>
            </div>

            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
                <h2 class="text-lg font-semibold text-white">Rates Used</h2>
                <div class="grid gap-3 text-sm text-slate-200">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Processing Fee (%)</p>
                        <p class="font-semibold text-white">{{ number_format((float) ($wizard['processing_fee_percentage'] ?? 0), 4) }}%</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Interest Rate for Full Period (%)</p>
                        <p class="font-semibold text-white">{{ number_format((float) ($wizard['monthly_interest_rate'] ?? 0), 4) }}%</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Arrears Rate (%)</p>
                        <p class="font-semibold text-white">{{ number_format((float) ($wizard['arrears_rate'] ?? 0), 4) }}%</p>
                    </div>
                </div>
                @if (!empty($wizard['terms_and_conditions']))
                    <div>
                        <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Terms and Conditions</p>
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-3 text-sm text-slate-200 whitespace-pre-line">{{ $wizard['terms_and_conditions'] }}</div>
                    </div>
                @endif
            </div>
        </div>

        <div class="rounded-3xl border border-white/10 bg-white/5 shadow-lg overflow-hidden">
            <div class="px-5 py-4 border-b border-white/10">
                <h2 class="text-xl font-semibold text-white">Selected Members</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm text-slate-300">
                    <thead class="bg-white/[0.03] text-xs uppercase tracking-[0.2em] text-slate-400">
                        <tr>
                            <th class="px-4 py-3 text-left">Customer</th>
                            <th class="px-4 py-3 text-left">Group Title</th>
                            <th class="px-4 py-3 text-left">Principal Amount</th>
                            <th class="px-4 py-3 text-left">Repayment Amount</th>
                            <th class="px-4 py-3 text-left">Expected Installment (Per Member)</th>
                            <th class="px-4 py-3 text-left">Disbursement Account</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($memberIds as $memberId)
                            @php
                                $member = $members->get($memberId);
                                $titleId = (int) data_get($wizard, "member_titles.$memberId");
                                $title = $titles->get($titleId);
                                $calc = data_get($wizard, "member_calculations.$memberId", []);
                                $memberSchedule = (array) data_get($memberInstallmentSchedules, $memberId, []);
                                $memberExpectedInstallment = (float) ($memberSchedule[1] ?? 0);
                                $memberFinalInstallment = (float) ($memberSchedule[$installmentCount] ?? $memberExpectedInstallment);
                            @endphp
                            <tr class="border-t border-white/5">
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-white">{{ $member?->full_name ?? 'Unknown Customer' }}</p>
                                    <p class="text-xs text-slate-400">{{ $member?->phone ?: 'No phone' }}</p>
                                </td>
                                <td class="px-4 py-3">{{ $title?->name ?? 'N/A' }}</td>
                                <td class="px-4 py-3">ZMW {{ number_format((float) data_get($calc, 'principal_amount', 0), 2) }}</td>
                                <td class="px-4 py-3">ZMW {{ number_format((float) data_get($calc, 'total_repayment_amount', 0), 2) }}</td>
                                <td class="px-4 py-3">
                                    <p class="font-medium text-white">ZMW {{ number_format($memberExpectedInstallment, 2) }}</p>
                                    @if ($installmentCount > 1 && abs($memberFinalInstallment - $memberExpectedInstallment) > 0.009)
                                        <p class="text-xs text-slate-400">Last installment: ZMW {{ number_format($memberFinalInstallment, 2) }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3">{{ $member?->phone ?: 'N/A' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-3xl border border-white/10 bg-white/5 shadow-lg overflow-hidden">
            <div class="px-5 py-4 border-b border-white/10 flex items-center justify-between">
                <h2 class="text-xl font-semibold text-white">Repayment Schedule</h2>
                <span class="text-xs uppercase tracking-wide text-slate-400">{{ count($repaymentSchedule) }} Installments</span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm text-slate-300">
                    <thead class="bg-white/[0.03] text-xs uppercase tracking-[0.2em] text-slate-400">
                        <tr>
                            <th class="px-4 py-3 text-left">Installment #</th>
                            <th class="px-4 py-3 text-left">Due Date</th>
                            <th class="px-4 py-3 text-left">Expected Amount</th>
                            <th class="px-4 py-3 text-left">Individual Expected Repayment Trail</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($repaymentSchedule as $scheduleItem)
                            <tr class="border-t border-white/5">
                                <td class="px-4 py-3">{{ $scheduleItem['period_number'] }}</td>
                                <td class="px-4 py-3">{{ $scheduleItem['due_date']->format('d M Y') }}</td>
                                <td class="px-4 py-3">ZMW {{ number_format((float) $scheduleItem['expected_amount'], 2) }}</td>
                                <td class="px-4 py-3">
                                    @php $memberBreakdown = (array) ($scheduleItem['member_breakdown'] ?? []); @endphp
                                    @if ($memberBreakdown === [])
                                        <span class="text-slate-400">No per-member breakdown available.</span>
                                    @else
                                        <div class="space-y-1">
                                            @foreach ($memberIds as $memberId)
                                                <div class="flex items-center justify-between gap-3 text-xs">
                                                    <span class="text-slate-400 truncate">{{ $members->get($memberId)?->full_name ?? ('Member #'.$memberId) }}</span>
                                                    <span class="font-medium text-white">ZMW {{ number_format((float) ($memberBreakdown[$memberId] ?? 0), 2) }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr class="border-t border-white/5">
                                <td colspan="4" class="px-4 py-4 text-slate-400">Repayment schedule is not available.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-5">
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                <p class="text-xs uppercase tracking-wide text-slate-400">Total Principal</p>
                <p class="text-lg font-semibold text-white mt-1">ZMW {{ number_format((float) data_get($totals, 'principal_amount', 0), 2) }}</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                <p class="text-xs uppercase tracking-wide text-slate-400">Total Processing Fees</p>
                <p class="text-lg font-semibold text-white mt-1">ZMW {{ number_format((float) data_get($totals, 'processing_fee_amount', 0), 2) }}</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                <p class="text-xs uppercase tracking-wide text-slate-400">Total Interest</p>
                <p class="text-lg font-semibold text-white mt-1">ZMW {{ number_format((float) data_get($totals, 'interest_amount', 0), 2) }}</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                <p class="text-xs uppercase tracking-wide text-slate-400">Projected Repayment Total</p>
                <p class="text-lg font-semibold text-emerald-300 mt-1">ZMW {{ number_format((float) data_get($totals, 'repayment_amount', 0), 2) }}</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                <p class="text-xs uppercase tracking-wide text-slate-400">Total Disbursement</p>
                <p class="text-lg font-semibold text-cyan-300 mt-1">ZMW {{ number_format((float) data_get($totals, 'disbursement_amount', 0), 2) }}</p>
            </div>
        </div>

        @if ($isModificationRevision && $revisionSourceApplication)
            <div class="rounded-3xl border border-orange-500/30 bg-orange-500/10 p-6 shadow-lg space-y-4">
                <div>
                    <h2 class="text-lg font-semibold text-orange-100">Modification Progress Note</h2>
                    <p class="text-sm text-orange-100/90 mt-1">
                        This is a revision for an application rejected for modification. Add a note that will appear in Decision Trail.
                    </p>
                </div>
                @if ($revisionReviewerNotes !== '')
                    <div>
                        <p class="text-xs uppercase tracking-wide text-orange-200/80 mb-1">Reviewer Notes</p>
                        <p class="text-sm text-orange-50 whitespace-pre-line">{{ $revisionReviewerNotes }}</p>
                    </div>
                @endif
                @if ($revisionRejectionActionRequired !== '')
                    <div>
                        <p class="text-xs uppercase tracking-wide text-orange-200/80 mb-1">Required Modifications</p>
                        <p class="text-sm text-orange-50 whitespace-pre-line">{{ $revisionRejectionActionRequired }}</p>
                    </div>
                @endif
                @if ($canAddModificationNote)
                    <form method="POST" action="{{ route('admin.loan-applications.group-loans.store-modification-note', $revisionSourceApplication) }}" class="space-y-3">
                        @csrf
                        <input type="hidden" name="return_to" value="review">
                        <input type="hidden" name="loan_product_id" value="{{ $loanProduct->id }}">
                        <textarea name="modification_note" rows="3" class="w-full rounded-2xl bg-white/10 border border-white/15 text-white px-4 py-3 text-sm focus:border-orange-400 focus:ring-orange-400/40" placeholder="e.g. Rebalanced principal distribution and replaced ineligible member as instructed." required>{{ old('modification_note') }}</textarea>
                        @error('modification_note')<p class="text-xs text-rose-300">{{ $message }}</p>@enderror
                        <div class="flex justify-end">
                            <button type="submit" class="inline-flex items-center rounded-2xl bg-orange-600 text-white px-4 py-2.5 text-sm font-semibold hover:bg-orange-700 transition" style="color: #fff !important;">Add Note to Decision Trail</button>
                        </div>
                    </form>
                @else
                    <p class="text-xs text-orange-100/80">Only the assigned relationship manager or original submitter can add notes for this revision.</p>
                @endif
            </div>
        @endif

        <div class="flex justify-end gap-3">
            <a href="{{ route('admin.loan-applications.group-loans.documents', $loanProduct) }}" class="inline-flex items-center rounded-2xl border border-white/20 px-4 py-3 text-sm font-medium text-slate-300 hover:bg-white/10 transition">Back</a>
            <a href="{{ route('admin.loan-applications.group-loans.review-print', $loanProduct) }}" class="inline-flex items-center rounded-2xl border border-cyan-500/40 px-4 py-3 text-sm font-semibold text-cyan-200 hover:bg-cyan-500/10 transition">Download Corporate Copy (PDF)</a>
            <form method="POST" action="{{ route('admin.loan-applications.group-loans.submit', $loanProduct) }}">
                @csrf
                <button type="submit" class="inline-flex items-center rounded-2xl bg-emerald-500 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-600 transition">Submit for Approval</button>
            </form>
        </div>

        @if ($canAssignRelationshipManager && $relationshipManagers->isNotEmpty())
            <div
                x-cloak
                x-show="openRelationshipManagerModal"
                x-transition.opacity
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
                role="dialog"
                aria-modal="true"
                aria-label="Change relationship manager modal"
            >
                <div class="absolute inset-0 bg-slate-950/75" x-on:click="openRelationshipManagerModal = false"></div>

                <div class="relative w-full max-w-lg rounded-3xl border border-cyan-500/30 bg-slate-900 p-6 shadow-2xl">
                    <div class="mb-4 flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-semibold text-white">Change Relationship Manager</h3>
                            <p class="mt-1 text-sm text-slate-300">
                                Update the relationship manager assigned to this draft before submission.
                            </p>
                        </div>
                        <button
                            type="button"
                            class="rounded-xl border border-white/20 px-3 py-1.5 text-sm text-slate-300 hover:bg-white/10"
                            x-on:click="openRelationshipManagerModal = false"
                        >
                            Close
                        </button>
                    </div>

                    <form method="POST" action="{{ route('admin.loan-applications.group-loans.update-review-relationship-manager', $loanProduct) }}" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-sm font-medium text-slate-300">
                                Relationship Manager <span class="text-rose-400">*</span>
                            </label>
                            <select
                                name="relationship_manager_id"
                                required
                                class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40"
                            >
                                <option value="">Select relationship manager</option>
                                @foreach ($relationshipManagers as $manager)
                                    <option value="{{ $manager->id }}" @selected((int) old('relationship_manager_id', $relationshipManager?->id) === $manager->id)>
                                        {{ $manager->full_name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('relationship_manager_id')
                                <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex justify-end gap-3">
                            <button
                                type="button"
                                class="inline-flex items-center rounded-2xl border border-white/20 px-4 py-2.5 text-sm font-medium text-slate-300 hover:bg-white/10 transition"
                                x-on:click="openRelationshipManagerModal = false"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                class="inline-flex items-center rounded-2xl bg-cyan-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-cyan-600 transition"
                            >
                                Save Relationship Manager
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
@endsection
