@extends('layouts.admin')

@section('title', 'Pending Approvals | '.config('app.system_name'))

@section('content')
    @php
        $adminUser = auth('admin')->user();
        $counts = [
            'admins' => $pendingAdmins->count(),
            'companies' => $pendingCompanies->count(),
            'customers' => $pendingCustomers->count(),
            'loans' => $pendingLoans->count(),
            'group_loans' => $pendingGroupLoanApplications->count(),
            'repayments' => $pendingRepayments->count(),
            'transfers' => $pendingTransfers->count(),
        ];
        $totalPending = array_sum($counts);

        $canViewAdmin = $adminUser?->can('admins.view');
        $canViewCompany = $adminUser?->can('companies.view');
        $canViewCustomer = $adminUser?->can('customers.view');
        $canViewLoan = $adminUser?->can('loans.view');
        $canViewRepayment = $adminUser?->can('repayments.view');
        $canViewTransfer = $adminUser?->can('transfers.view');

        $canApproveGeneral = $adminUser?->can('approvals.approve');
        $canRejectGeneral = $adminUser?->can('approvals.reject');
        $canApproveLoan = $adminUser?->can('loans.approve');
        $canRejectLoan = $adminUser?->can('loans.reject');
        $canApproveRepayment = $adminUser?->can('repayments.approve') || $adminUser?->can('repayments.process');
        $canRejectRepayment = $adminUser?->can('repayments.reject') || $adminUser?->can('repayments.process');
        $canApproveTransfer = $adminUser?->can('approvals.approve') && $adminUser?->can('transfers.approve');
        $canRejectTransfer = $adminUser?->can('approvals.reject') && $adminUser?->can('transfers.reject');
    @endphp

    <style>
        .approval-grid {
            display: grid;
            gap: 1.5rem;
            grid-template-columns: minmax(0, 1fr);
        }

        .approval-grid > section {
            min-width: 0;
        }

        @media (min-width: 1024px) {
            .approval-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
    </style>

    <div class="space-y-8">
        <div class="space-y-2 text-left">
            <p class="text-xs uppercase tracking-[0.4em] text-cyan-300">Approval Management</p>
            <h1 class="text-3xl font-bold">Pending Approvals</h1>
            <p class="text-sm text-slate-400">Review pending items by module and take action quickly.</p>
        </div>

        <section class="rounded-3xl border border-white/10 bg-white/5 p-5 shadow-lg">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-8">
                <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Admins</p>
                    <p class="text-2xl font-semibold text-white mt-1">{{ number_format($counts['admins']) }}</p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Companies</p>
                    <p class="text-2xl font-semibold text-white mt-1">{{ number_format($counts['companies']) }}</p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Customers</p>
                    <p class="text-2xl font-semibold text-white mt-1">{{ number_format($counts['customers']) }}</p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Loans</p>
                    <p class="text-2xl font-semibold text-white mt-1">{{ number_format($counts['loans']) }}</p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Group Loans</p>
                    <p class="text-2xl font-semibold text-white mt-1">{{ number_format($counts['group_loans']) }}</p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Repayments</p>
                    <p class="text-2xl font-semibold text-white mt-1">{{ number_format($counts['repayments']) }}</p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Transfers</p>
                    <p class="text-2xl font-semibold text-white mt-1">{{ number_format($counts['transfers']) }}</p>
                </div>
                <div class="rounded-2xl border border-amber-400/30 bg-amber-500/10 p-4">
                    <p class="text-xs uppercase tracking-wide text-amber-200">Total Pending</p>
                    <p class="text-2xl font-semibold text-amber-100 mt-1">{{ number_format($totalPending) }}</p>
                </div>
            </div>
        </section>

        @if ($totalPending > 0)
            <nav class="rounded-3xl border border-white/10 bg-white/[0.03] px-4 py-3">
                <p class="text-xs uppercase tracking-[0.25em] text-slate-500 mb-2">Jump To Queue</p>
                <div class="flex flex-wrap gap-2">
                    @if ($counts['admins'] > 0)
                        <a href="#pending-admins" class="rounded-xl border border-white/15 px-3 py-1.5 text-xs font-medium text-slate-200 hover:bg-white/10 transition">Admins ({{ $counts['admins'] }})</a>
                    @endif
                    @if ($counts['companies'] > 0)
                        <a href="#pending-companies" class="rounded-xl border border-white/15 px-3 py-1.5 text-xs font-medium text-slate-200 hover:bg-white/10 transition">Companies ({{ $counts['companies'] }})</a>
                    @endif
                    @if ($counts['customers'] > 0)
                        <a href="#pending-customers" class="rounded-xl border border-white/15 px-3 py-1.5 text-xs font-medium text-slate-200 hover:bg-white/10 transition">Customers ({{ $counts['customers'] }})</a>
                    @endif
                    @if ($counts['loans'] > 0)
                        <a href="#pending-loans" class="rounded-xl border border-white/15 px-3 py-1.5 text-xs font-medium text-slate-200 hover:bg-white/10 transition">Loans ({{ $counts['loans'] }})</a>
                    @endif
                    @if ($counts['group_loans'] > 0)
                        <a href="#pending-group-loans" class="rounded-xl border border-white/15 px-3 py-1.5 text-xs font-medium text-slate-200 hover:bg-white/10 transition">Group Loans ({{ $counts['group_loans'] }})</a>
                    @endif
                    @if ($counts['repayments'] > 0)
                        <a href="#pending-repayments" class="rounded-xl border border-white/15 px-3 py-1.5 text-xs font-medium text-slate-200 hover:bg-white/10 transition">Repayments ({{ $counts['repayments'] }})</a>
                    @endif
                    @if ($counts['transfers'] > 0)
                        <a href="#pending-transfers" class="rounded-xl border border-white/15 px-3 py-1.5 text-xs font-medium text-slate-200 hover:bg-white/10 transition">Transfers ({{ $counts['transfers'] }})</a>
                    @endif
                </div>
            </nav>
        @endif

        @if ($totalPending === 0)
            <div class="rounded-3xl border border-white/10 bg-white/5 p-8 text-center max-w-md mx-auto">
                <svg class="mx-auto h-10 w-10 text-slate-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-base font-medium text-slate-300">No pending approvals</p>
                <p class="text-sm text-slate-400 mt-1">All items have been reviewed.</p>
            </div>
        @else
            <div class="approval-grid">
            @if ($pendingAdmins->isNotEmpty())
                <section id="pending-admins" class="h-full rounded-3xl border border-white/10 bg-white/5 shadow-lg overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-4 border-b border-white/10">
                        <h2 class="text-xl font-semibold text-white">Pending Admin Users</h2>
                        <span class="rounded-full bg-amber-500/20 border border-amber-500/30 px-3 py-1 text-xs font-semibold text-amber-300">{{ $counts['admins'] }}</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm text-slate-300">
                            <thead class="bg-white/[0.03] text-xs uppercase tracking-[0.2em] text-slate-400">
                                <tr>
                                    <th class="px-4 py-3 text-left">Admin</th>
                                    <th class="px-4 py-3 text-left">Company</th>
                                    <th class="px-4 py-3 text-left">Roles</th>
                                    <th class="px-4 py-3 text-left">Submitted</th>
                                    <th class="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($pendingAdmins as $admin)
                                    <tr class="border-t border-white/5">
                                        <td class="px-4 py-3">
                                            <p class="font-semibold text-white">{{ $admin->full_name }}</p>
                                            <p class="text-xs text-slate-400">{{ $admin->email }}</p>
                                        </td>
                                        <td class="px-4 py-3">{{ $admin->company->name ?? '—' }}</td>
                                        <td class="px-4 py-3">{{ $admin->roles->pluck('name')->join(', ') ?: 'None' }}</td>
                                        <td class="px-4 py-3">{{ $admin->created_at->format('d M Y, H:i') }}</td>
                                        <td class="px-4 py-3">
                                            <div class="flex flex-wrap justify-end gap-2">
                                                @if($canViewAdmin)
                                                    <a href="{{ route('admin.users.show', $admin) }}" class="rounded-xl border border-white/20 px-3 py-1.5 text-xs font-semibold text-white hover:bg-white/10 transition">View</a>
                                                @endif
                                                @if($canApproveGeneral)
                                                    <form method="POST" action="{{ route('admin.approvals.admins.approve', $admin) }}">
                                                        @csrf
                                                        <button type="submit" class="btn-approve-critical !px-3 !py-1.5 !text-xs">Approve</button>
                                                    </form>
                                                @endif
                                                @if($canRejectGeneral)
                                                    <button type="button" onclick="showRejectModal('admin', {{ $admin->id }}, @js($admin->full_name))" class="btn-reject-critical !px-3 !py-1.5 !text-xs">Reject</button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            @if ($pendingCompanies->isNotEmpty())
                <section id="pending-companies" class="h-full rounded-3xl border border-white/10 bg-white/5 shadow-lg overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-4 border-b border-white/10">
                        <h2 class="text-xl font-semibold text-white">Pending Companies</h2>
                        <span class="rounded-full bg-amber-500/20 border border-amber-500/30 px-3 py-1 text-xs font-semibold text-amber-300">{{ $counts['companies'] }}</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm text-slate-300">
                            <thead class="bg-white/[0.03] text-xs uppercase tracking-[0.2em] text-slate-400">
                                <tr>
                                    <th class="px-4 py-3 text-left">Company</th>
                                    <th class="px-4 py-3 text-left">Contact</th>
                                    <th class="px-4 py-3 text-left">Type / Location</th>
                                    <th class="px-4 py-3 text-left">Submitted</th>
                                    <th class="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($pendingCompanies as $company)
                                    <tr class="border-t border-white/5">
                                        <td class="px-4 py-3">
                                            <p class="font-semibold text-white">{{ $company->name }}</p>
                                            <p class="text-xs text-slate-400">{{ $company->code }}</p>
                                        </td>
                                        <td class="px-4 py-3">
                                            <p>{{ $company->contact_email ?? '—' }}</p>
                                            <p class="text-xs text-slate-400">{{ $company->contact_phone ?? '—' }}</p>
                                        </td>
                                        <td class="px-4 py-3">
                                            <p>{{ ucfirst($company->type) }}</p>
                                            <p class="text-xs text-slate-400">{{ trim(($company->city ?? '').' '.($company->country ?? '')) ?: '—' }}</p>
                                        </td>
                                        <td class="px-4 py-3">{{ $company->created_at->format('d M Y, H:i') }}</td>
                                        <td class="px-4 py-3">
                                            <div class="flex flex-wrap justify-end gap-2">
                                                @if($canViewCompany)
                                                    <a href="{{ route('admin.companies.show', $company) }}" class="rounded-xl border border-white/20 px-3 py-1.5 text-xs font-semibold text-white hover:bg-white/10 transition">View</a>
                                                @endif
                                                @if($canApproveGeneral)
                                                    <form method="POST" action="{{ route('admin.approvals.companies.approve', $company) }}">
                                                        @csrf
                                                        <button type="submit" class="btn-approve-critical !px-3 !py-1.5 !text-xs">Approve</button>
                                                    </form>
                                                @endif
                                                @if($canRejectGeneral)
                                                    <button type="button" onclick="showRejectModal('company', {{ $company->id }}, @js($company->name))" class="btn-reject-critical !px-3 !py-1.5 !text-xs">Reject</button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            @if ($pendingCustomers->isNotEmpty())
                <section id="pending-customers" class="h-full rounded-3xl border border-white/10 bg-white/5 shadow-lg overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-4 border-b border-white/10">
                        <h2 class="text-xl font-semibold text-white">Pending Customers</h2>
                        <span class="rounded-full bg-amber-500/20 border border-amber-500/30 px-3 py-1 text-xs font-semibold text-amber-300">{{ $counts['customers'] }}</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm text-slate-300">
                            <thead class="bg-white/[0.03] text-xs uppercase tracking-[0.2em] text-slate-400">
                                <tr>
                                    <th class="px-4 py-3 text-left">Customer</th>
                                    <th class="px-4 py-3 text-left">Company / Product</th>
                                    <th class="px-4 py-3 text-left">Contact / KYC</th>
                                    <th class="px-4 py-3 text-left">Submitted</th>
                                    <th class="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($pendingCustomers as $customer)
                                    <tr class="border-t border-white/5">
                                        <td class="px-4 py-3">
                                            <p class="font-semibold text-white">{{ $customer->full_name }}</p>
                                            <p class="text-xs text-slate-400">{{ $customer->email }}</p>
                                        </td>
                                        <td class="px-4 py-3">
                                            <p>{{ $customer->company->name ?? '—' }}</p>
                                            <p class="text-xs text-slate-400">{{ $customer->loanProduct->name ?? '—' }}</p>
                                        </td>
                                        <td class="px-4 py-3">
                                            <p>{{ $customer->phone ?? '—' }}</p>
                                            <p class="text-xs text-slate-400">{{ ucfirst(str_replace('_', ' ', $customer->kyc_status ?? 'unknown')) }}</p>
                                        </td>
                                        <td class="px-4 py-3">{{ $customer->created_at->format('d M Y, H:i') }}</td>
                                        <td class="px-4 py-3">
                                            <div class="flex flex-wrap justify-end gap-2">
                                                @if($canViewCustomer)
                                                    <a href="{{ route('admin.customers.show', $customer) }}" class="rounded-xl border border-white/20 px-3 py-1.5 text-xs font-semibold text-white hover:bg-white/10 transition">View</a>
                                                @endif
                                                @if($canApproveGeneral)
                                                    @php
                                                        $hasKycForApproval = $customer->latestKycDocument || $customer->kyc_status === 'verified';
                                                    @endphp
                                                    @if($hasKycForApproval)
                                                        <button
                                                            type="button"
                                                            onclick="showApproveCustomerModal({{ $customer->id }}, @js($customer->full_name))"
                                                            class="btn-approve-critical !px-3 !py-1.5 !text-xs"
                                                        >
                                                            Approve
                                                        </button>
                                                    @else
                                                        <button
                                                            type="button"
                                                            disabled
                                                            title="Upload KYC before approval"
                                                            class="rounded-xl border border-amber-400/40 bg-amber-500/10 px-3 py-1.5 text-xs font-semibold text-amber-300 opacity-70 cursor-not-allowed"
                                                        >
                                                            Upload KYC First
                                                        </button>
                                                    @endif
                                                @endif
                                                @if($canRejectGeneral)
                                                    <button type="button" onclick="showRejectModal('customer', {{ $customer->id }}, @js($customer->full_name))" class="btn-reject-critical !px-3 !py-1.5 !text-xs">Reject</button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            @if ($pendingLoans->isNotEmpty())
                <section id="pending-loans" class="h-full rounded-3xl border border-white/10 bg-white/5 shadow-lg overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-4 border-b border-white/10">
                        <h2 class="text-xl font-semibold text-white">Pending Loans</h2>
                        <span class="rounded-full bg-amber-500/20 border border-amber-500/30 px-3 py-1 text-xs font-semibold text-amber-300">{{ $counts['loans'] }}</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm text-slate-300">
                            <thead class="bg-white/[0.03] text-xs uppercase tracking-[0.2em] text-slate-400">
                                <tr>
                                    <th class="px-4 py-3 text-left">Loan</th>
                                    <th class="px-4 py-3 text-left">Customer</th>
                                    <th class="px-4 py-3 text-left">Amounts</th>
                                    <th class="px-4 py-3 text-left">Terms</th>
                                    <th class="px-4 py-3 text-left">Submitted</th>
                                    <th class="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($pendingLoans as $loan)
                                    <tr class="border-t border-white/5">
                                        <td class="px-4 py-3">
                                            <p class="font-semibold text-white">{{ $loan->loan_number }}</p>
                                            <p class="text-xs text-slate-400">{{ $loan->loanProduct->name ?? '—' }}</p>
                                        </td>
                                        <td class="px-4 py-3">
                                            <p>{{ $loan->customer->full_name ?? 'N/A' }}</p>
                                            <p class="text-xs text-slate-400">{{ $loan->customerGroup->name ?? '—' }}</p>
                                        </td>
                                        <td class="px-4 py-3">
                                            <p>Principal: ZMW {{ number_format($loan->principal_amount, 2) }}</p>
                                            <p class="text-xs text-slate-400">Total: ZMW {{ number_format($loan->total_amount, 2) }}</p>
                                        </td>
                                        <td class="px-4 py-3">
                                            <p>{{ $loan->tenure_months }} {{ $loan->tenure_months === 1 ? 'Month' : 'Months' }}</p>
                                            <p class="text-xs text-slate-400">{{ ucfirst(str_replace('_', ' ', $loan->accrual_type)) }}</p>
                                        </td>
                                        <td class="px-4 py-3">{{ $loan->created_at->format('d M Y, H:i') }}</td>
                                        <td class="px-4 py-3">
                                            <div class="flex flex-wrap justify-end gap-2">
                                                @if($canViewLoan)
                                                    <a href="{{ route('admin.loans.show', $loan) }}" class="rounded-xl border border-white/20 px-3 py-1.5 text-xs font-semibold text-white hover:bg-white/10 transition">View</a>
                                                @endif
                                                @if($canApproveLoan)
                                                    <form method="POST" action="{{ route('admin.approvals.loans.approve', $loan) }}">
                                                        @csrf
                                                        <button type="submit" class="btn-approve-critical !px-3 !py-1.5 !text-xs">Approve</button>
                                                    </form>
                                                @endif
                                                @if($canRejectLoan)
                                                    <button type="button" onclick="showRejectModal('loan', {{ $loan->id }}, @js($loan->loan_number))" class="btn-reject-critical !px-3 !py-1.5 !text-xs">Reject</button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            @if ($pendingGroupLoanApplications->isNotEmpty())
                <section id="pending-group-loans" class="h-full rounded-3xl border border-white/10 bg-white/5 shadow-lg overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-4 border-b border-white/10">
                        <h2 class="text-xl font-semibold text-white">Pending Group Loan Applications</h2>
                        <span class="rounded-full bg-amber-500/20 border border-amber-500/30 px-3 py-1 text-xs font-semibold text-amber-300">{{ $counts['group_loans'] }}</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm text-slate-300">
                            <thead class="bg-white/[0.03] text-xs uppercase tracking-[0.2em] text-slate-400">
                                <tr>
                                    <th class="px-4 py-3 text-left">Reference</th>
                                    <th class="px-4 py-3 text-left">Product / Group</th>
                                    <th class="px-4 py-3 text-left">Members</th>
                                    <th class="px-4 py-3 text-left">Totals</th>
                                    <th class="px-4 py-3 text-left">Submitted</th>
                                    <th class="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($pendingGroupLoanApplications as $application)
                                    <tr class="border-t border-white/5">
                                        <td class="px-4 py-3">
                                            <p class="font-semibold text-white">{{ $application->reference }}</p>
                                            <p class="text-xs text-slate-400">{{ $application->loan_name }}</p>
                                        </td>
                                        <td class="px-4 py-3">
                                            <p>{{ $application->loanProduct?->name ?? 'N/A' }}</p>
                                            <p class="text-xs text-slate-400">{{ $application->customerGroup?->name ?? $application->group_name }}</p>
                                        </td>
                                        <td class="px-4 py-3">
                                            <p>{{ number_format($application->members_count) }} selected</p>
                                            <p class="text-xs text-slate-400">{{ ucfirst($application->repayment_structure) }}</p>
                                        </td>
                                        <td class="px-4 py-3">
                                            <p>Disbursement: ZMW {{ number_format((float) $application->total_disbursement_amount, 2) }}</p>
                                            <p class="text-xs text-slate-400">Repayment: ZMW {{ number_format((float) $application->total_repayment_amount, 2) }}</p>
                                        </td>
                                        <td class="px-4 py-3">{{ optional($application->submitted_at ?? $application->created_at)->format('d M Y, H:i') }}</td>
                                        <td class="px-4 py-3">
                                            <div class="flex flex-wrap justify-end gap-2">
                                                @if($canViewLoan)
                                                    <a href="{{ route('admin.loan-applications.group-loans.show', $application) }}" class="rounded-xl border border-white/20 px-3 py-1.5 text-xs font-semibold text-white hover:bg-white/10 transition">View</a>
                                                @endif
                                                @if($canApproveLoan || $canApproveGeneral)
                                                    <form method="POST" action="{{ route('admin.loan-applications.group-loans.approve', $application) }}">
                                                        @csrf
                                                        <button type="submit" class="btn-approve-critical !px-3 !py-1.5 !text-xs">Approve</button>
                                                    </form>
                                                @endif
                                                @if($canRejectLoan || $canRejectGeneral)
                                                    <button type="button" onclick="showRejectModal('group-loan', {{ $application->id }}, @js($application->reference))" class="btn-reject-critical !px-3 !py-1.5 !text-xs">Reject</button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            @if ($pendingRepayments->isNotEmpty())
                <section id="pending-repayments" class="h-full rounded-3xl border border-white/10 bg-white/5 shadow-lg overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-4 border-b border-white/10">
                        <h2 class="text-xl font-semibold text-white">Pending Repayments</h2>
                        <span class="rounded-full bg-amber-500/20 border border-amber-500/30 px-3 py-1 text-xs font-semibold text-amber-300">{{ $counts['repayments'] }}</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm text-slate-300">
                            <thead class="bg-white/[0.03] text-xs uppercase tracking-[0.2em] text-slate-400">
                                <tr>
                                    <th class="px-4 py-3 text-left">Repayment</th>
                                    <th class="px-4 py-3 text-left">Customer</th>
                                    <th class="px-4 py-3 text-left">Channel / Type</th>
                                    <th class="px-4 py-3 text-left">Amount</th>
                                    <th class="px-4 py-3 text-left">Submitted</th>
                                    <th class="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($pendingRepayments as $repayment)
                                    <tr class="border-t border-white/5">
                                        <td class="px-4 py-3">
                                            <p class="font-semibold text-white">{{ $repayment->repayment_number }}</p>
                                            <p class="text-xs text-slate-400">{{ $repayment->status_message ?: 'Awaiting approval' }}</p>
                                        </td>
                                        <td class="px-4 py-3">
                                            <p>{{ $repayment->customer->full_name ?? 'N/A' }}</p>
                                            <p class="text-xs text-slate-400">{{ $repayment->customer->phone ?? '—' }}</p>
                                        </td>
                                        <td class="px-4 py-3">
                                            <p>{{ $repayment->channel->name ?? '—' }}</p>
                                            <p class="text-xs text-slate-400">{{ ucfirst($repayment->metadata['repayment_type'] ?? 'n/a') }}</p>
                                        </td>
                                        <td class="px-4 py-3 font-semibold text-emerald-300">ZMW {{ number_format($repayment->total_amount, 2) }}</td>
                                        <td class="px-4 py-3">{{ $repayment->created_at->format('d M Y, H:i') }}</td>
                                        <td class="px-4 py-3">
                                            <div class="flex flex-wrap justify-end gap-2">
                                                @if($canViewRepayment)
                                                    <a href="{{ route('admin.repayments.show', $repayment) }}" class="rounded-xl border border-white/20 px-3 py-1.5 text-xs font-semibold text-white hover:bg-white/10 transition">View</a>
                                                @endif
                                                @if($canApproveRepayment)
                                                    <form method="POST" action="{{ route('admin.repayments.approve', $repayment) }}">
                                                        @csrf
                                                        <button type="submit" class="btn-approve-critical !px-3 !py-1.5 !text-xs">Approve</button>
                                                    </form>
                                                @endif
                                                @if($canRejectRepayment)
                                                    <button type="button" onclick="showRejectModal('repayment', {{ $repayment->id }}, @js($repayment->repayment_number))" class="btn-reject-critical !px-3 !py-1.5 !text-xs">Reject</button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            @if ($pendingTransfers->isNotEmpty())
                <section id="pending-transfers" class="h-full rounded-3xl border border-white/10 bg-white/5 shadow-lg overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-4 border-b border-white/10">
                        <h2 class="text-xl font-semibold text-white">Pending Transfers</h2>
                        <span class="rounded-full bg-amber-500/20 border border-amber-500/30 px-3 py-1 text-xs font-semibold text-amber-300">{{ $counts['transfers'] }}</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm text-slate-300">
                            <thead class="bg-white/[0.03] text-xs uppercase tracking-[0.2em] text-slate-400">
                                <tr>
                                    <th class="px-4 py-3 text-left">Transfer</th>
                                    <th class="px-4 py-3 text-left">Direction</th>
                                    <th class="px-4 py-3 text-left">Amount</th>
                                    <th class="px-4 py-3 text-left">Created By</th>
                                    <th class="px-4 py-3 text-left">Submitted</th>
                                    <th class="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($pendingTransfers as $transfer)
                                    <tr class="border-t border-white/5">
                                        <td class="px-4 py-3">
                                            <p class="font-semibold text-white">{{ $transfer->transaction_number }}</p>
                                            <p class="text-xs text-slate-400">{{ $transfer->description ?: 'Transfer approval pending' }}</p>
                                        </td>
                                        <td class="px-4 py-3">
                                            <p>{{ $transfer->source->name ?? '—' }}</p>
                                            <p class="text-xs text-slate-400">{{ $transfer->destination->name ?? '—' }}</p>
                                        </td>
                                        <td class="px-4 py-3 font-semibold text-emerald-300">ZMW {{ number_format($transfer->amount, 2) }}</td>
                                        <td class="px-4 py-3">{{ $transfer->creator->full_name ?? $transfer->creator->email ?? '—' }}</td>
                                        <td class="px-4 py-3">{{ $transfer->created_at->format('d M Y, H:i') }}</td>
                                        <td class="px-4 py-3">
                                            <div class="flex flex-wrap justify-end gap-2">
                                                @if($canViewTransfer)
                                                    <a href="{{ route('admin.transfers.show', $transfer) }}" class="rounded-xl border border-white/20 px-3 py-1.5 text-xs font-semibold text-white hover:bg-white/10 transition">View</a>
                                                @endif
                                                @if($canApproveTransfer)
                                                    <form method="POST" action="{{ route('admin.transfers.approve', $transfer) }}">
                                                        @csrf
                                                        <button type="submit" class="btn-approve-critical !px-3 !py-1.5 !text-xs">Approve</button>
                                                    </form>
                                                @endif
                                                @if($canRejectTransfer)
                                                    <button type="button" onclick="showRejectModal('transfer', {{ $transfer->id }}, @js($transfer->transaction_number))" class="btn-reject-critical !px-3 !py-1.5 !text-xs">Reject</button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif
            </div>
        @endif
    </div>

    <div id="rejectModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm px-4">
        <div class="rounded-3xl border border-white/10 bg-slate-900 p-6 w-full max-w-lg shadow-2xl">
            <h3 class="text-xl font-semibold text-white mb-2">Reject Pending Item</h3>
            <p id="rejectContext" class="text-sm text-slate-400 mb-4"></p>

            <form id="rejectForm" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label id="rejectLabel" class="block text-sm font-medium text-slate-300 mb-2">Rejection Notes (optional)</label>
                    <textarea id="rejectMessage" name="notes" rows="3" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40" placeholder="Provide a reason for rejection..."></textarea>
                    <p id="rejectHelp" class="text-xs text-slate-500 mt-2">This note is stored on the approval record.</p>
                </div>

                <div class="flex gap-3">
                    <button type="button" onclick="closeRejectModal()" class="flex-1 rounded-2xl border border-white/10 px-4 py-2 text-sm text-white hover:bg-white/10 transition">
                        Cancel
                    </button>
                    <button type="submit" class="flex-1 rounded-2xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-rose-500/30 hover:bg-rose-700 transition">
                        Confirm Reject
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="approveCustomerModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm px-4">
        <div class="rounded-3xl border border-white/10 bg-slate-900 p-6 w-full max-w-lg shadow-2xl">
            <h3 class="text-xl font-semibold text-white mb-3">Approve Customer</h3>
            <p class="text-sm text-slate-400 mb-5">
                Are you sure you want to approve
                <span id="approveCustomerName" class="font-semibold text-white"></span>?
            </p>

            <form id="approveCustomerForm" method="POST" class="space-y-4">
                @csrf
                <div class="flex gap-3">
                    <button
                        type="button"
                        onclick="closeApproveCustomerModal()"
                        class="flex-1 rounded-2xl border border-white/10 px-4 py-2 text-sm text-white hover:bg-white/10 transition"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="flex-1 rounded-2xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-emerald-500/30 hover:bg-emerald-700 transition"
                    >
                        Confirm Approve
                    </button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
        <script>
            function showApproveCustomerModal(id, customerName) {
                const modal = document.getElementById('approveCustomerModal');
                const form = document.getElementById('approveCustomerForm');
                const nameEl = document.getElementById('approveCustomerName');

                form.action = `{{ url('admin/approvals/customers') }}/${id}/approve`;
                nameEl.textContent = customerName || `Customer #${id}`;
                modal.classList.remove('hidden');
            }

            function closeApproveCustomerModal() {
                const modal = document.getElementById('approveCustomerModal');
                const form = document.getElementById('approveCustomerForm');
                const nameEl = document.getElementById('approveCustomerName');

                modal.classList.add('hidden');
                form.action = '';
                nameEl.textContent = '';
            }

            function showRejectModal(type, id, reference) {
                const form = document.getElementById('rejectForm');
                const field = document.getElementById('rejectMessage');
                const label = document.getElementById('rejectLabel');
                const help = document.getElementById('rejectHelp');
                const context = document.getElementById('rejectContext');

                const routes = {
                    admin: `{{ url('admin/approvals/admins') }}/${id}/reject`,
                    company: `{{ url('admin/approvals/companies') }}/${id}/reject`,
                    customer: `{{ url('admin/approvals/customers') }}/${id}/reject`,
                    loan: `{{ url('admin/approvals/loans') }}/${id}/reject`,
                    'group-loan': `{{ url('admin/loan-applications/group-loans') }}/${id}/reject`,
                    repayment: `{{ url('admin/repayments') }}/${id}/reject`,
                    transfer: `{{ url('admin/transfers') }}/${id}/reject`,
                };

                if (!routes[type]) {
                    return;
                }

                form.action = routes[type];
                field.value = '';

                if (type === 'repayment') {
                    field.name = 'reason';
                    field.required = true;
                    label.textContent = 'Rejection Reason';
                    field.placeholder = 'Provide a clear reason for rejecting this repayment...';
                    help.textContent = 'A reason is required for repayment rejection.';
                } else {
                    field.name = 'notes';
                    field.required = false;
                    label.textContent = 'Rejection Notes (optional)';
                    field.placeholder = 'Provide a reason for rejection...';
                    help.textContent = 'This note is stored on the approval record.';
                }

                const readableType = type === 'group-loan'
                    ? 'Group Loan Application'
                    : (type.charAt(0).toUpperCase() + type.slice(1));
                context.textContent = `You are rejecting ${readableType}: ${reference || '#' + id}`;

                document.getElementById('rejectModal').classList.remove('hidden');
                field.focus();
            }

            function closeRejectModal() {
                const modal = document.getElementById('rejectModal');
                const form = document.getElementById('rejectForm');
                const field = document.getElementById('rejectMessage');
                const label = document.getElementById('rejectLabel');
                const help = document.getElementById('rejectHelp');
                const context = document.getElementById('rejectContext');

                modal.classList.add('hidden');
                form.action = '';
                form.reset();

                field.name = 'notes';
                field.required = false;
                label.textContent = 'Rejection Notes (optional)';
                field.placeholder = 'Provide a reason for rejection...';
                help.textContent = 'This note is stored on the approval record.';
                context.textContent = '';
            }

            document.getElementById('rejectModal')?.addEventListener('click', function (e) {
                if (e.target === this) {
                    closeRejectModal();
                }
            });

            document.getElementById('approveCustomerModal')?.addEventListener('click', function (e) {
                if (e.target === this) {
                    closeApproveCustomerModal();
                }
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    closeApproveCustomerModal();
                    closeRejectModal();
                }
            });
        </script>
    @endpush
@endsection
