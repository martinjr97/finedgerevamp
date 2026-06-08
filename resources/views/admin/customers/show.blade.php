@extends('layouts.admin')

@section('title', 'View Customer | '.config('app.system_name'))

@section('content')
	@php
	    $isApprovedCustomer = $customer->approval_status === 'approved';
	    $hasKycForApproval = (bool) $customer->latestKycDocument || $customer->kyc_status === 'verified';
	    $isPendingApproval = $customer->approval_status === 'pending';
	    $isPendingWithoutKyc = $isPendingApproval && ! $hasKycForApproval;
	    $paymentDetail = $customer->paymentDetail;
	@endphp
	<div class="space-y-8">
        @if($isPendingApproval)
            <div class="attention-banner {{ $isPendingWithoutKyc ? 'border border-amber-400/40 bg-amber-500/10' : '' }}">
                <div class="flex items-start gap-3">
                    <div class="attention-icon">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <p class="font-semibold">Action required</p>
                        @if($isPendingWithoutKyc)
                            <p class="text-sm opacity-90">
                                This customer is pending approval. Please upload KYC details before they can be approved.
                            </p>
                        @else
                            <p class="text-sm opacity-90">This customer is pending review. Please approve or reject to continue onboarding.</p>
                        @endif
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        @if($isPendingWithoutKyc)
                            @can('kyc.create')
                            <a href="{{ route('admin.customers.kyc.create', $customer) }}" class="inline-flex items-center gap-2 rounded-xl border border-amber-400/50 bg-amber-500/20 px-3 py-2 text-sm font-semibold text-amber-100 hover:bg-amber-500/30 transition">
                                Upload KYC Documents
                            </a>
                            @endcan
                        @else
                            @can('approvals.approve')
                            <button type="button" onclick="showApproveModal({{ $customer->id }})" class="btn-approve-critical px-3 py-2">Approve</button>
                            @endcan
                            @can('approvals.reject')
                            <button type="button" onclick="showRejectModal({{ $customer->id }})" class="btn-reject-critical px-3 py-2">Reject</button>
                            @endcan
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <div class="flex items-center justify-between">
            <div class="space-y-1">
                <p class="text-xs uppercase tracking-[0.4em] text-cyan-300">Customer Management</p>
                <div class="flex items-center gap-3">
                    <h1 class="text-3xl font-bold">{{ $customer->full_name }}</h1>
                    <span class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-semibold
                        @if($customer->customer_type === 'company') bg-blue-500/20 text-blue-200
                        @elseif($customer->customer_type === 'representative') bg-emerald-500/20 text-emerald-200
                        @else bg-purple-500/20 text-purple-200 @endif">
                        @if($customer->customer_type === 'company')
                            Company Borrower
                        @elseif($customer->customer_type === 'representative')
                            Company Representative
                        @else
                            Individual
                        @endif
                    </span>
                    @if($customer->customer_type === 'representative' && $customer->parentCustomer)
                        <span class="inline-flex items-center gap-2 rounded-full bg-white/5 border border-white/10 px-3 py-1 text-xs text-slate-200">
                            Parent: {{ $customer->parentCustomer->registered_name ?? $customer->parentCustomer->full_name }}
                        </span>
                    @endif
                    @if(isset($duplicateInfo) && $duplicateInfo['has_duplicates'])
                        <a href="{{ route('admin.fraud-protection.show', $customer) }}" class="duplicate-warning-badge">
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                            Possible Duplicate ({{ $duplicateInfo['total_count'] }})
                        </a>
                    @endif
                </div>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                @if ($isPendingApproval && $hasKycForApproval)
                    @can('approvals.approve')
                    <button type="button" onclick="showApproveModal({{ $customer->id }})" class="btn-approve-critical">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Approve Customer
                    </button>
                    @endcan
                    @can('approvals.reject')
                    <button type="button" onclick="showRejectModal({{ $customer->id }})" class="btn-reject-critical">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        Reject Customer
                    </button>
                    @endcan
                @endif
                @if ($customer->latestKycDocument)
                    @can('kyc.view')
                    <a href="{{ route('admin.customers.kyc.show', $customer) }}" class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-blue-500 to-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-md shadow-blue-500/30 hover:from-blue-600 hover:to-blue-700 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        View KYC Documents
                    </a>
                    @endcan
                @else
                    @can('kyc.create')
                    <a href="{{ route('admin.customers.kyc.create', $customer) }}" class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-blue-500 to-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-md shadow-blue-500/30 hover:from-blue-600 hover:to-blue-700 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                        </svg>
                        Upload KYC Documents
                    </a>
                    @endcan
                @endif
                @if ($isApprovedCustomer)
                    @if ($customer->loanProduct && in_array($customer->loanProduct->category, ['character', 'collateral', 'government', 'group_loans'], true))
                        @can('customers.change-group')
                        <a href="{{ route('admin.customers.change-group', $customer) }}" class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-blue-500 to-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-md shadow-blue-500/30 hover:from-blue-600 hover:to-blue-700 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                            {{ $customer->customerGroup ? 'Change Group' : 'Link to Group' }}
                        </a>
                        @endcan
                    @endif
                    @can('customers.view')
                    <a href="{{ route('admin.customers.login-audit', $customer) }}" class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-purple-500 to-purple-600 px-3 py-2 text-sm font-semibold text-white shadow-md shadow-purple-500/30 hover:from-purple-600 hover:to-purple-700 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Login Audit
                    </a>
                    @endcan
                @endif
                @can('customers.update')
                <a href="{{ route('admin.customers.edit', $customer) }}" class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-blue-500 to-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-md shadow-blue-500/30 hover:from-blue-600 hover:to-blue-700 transition">
                    Edit Customer
                </a>
                @endcan
                @if ($isApprovedCustomer)
                    @can('customers.reset-pin')
                    <button type="button" onclick="showResetPinModal()" class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-blue-500 to-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-md shadow-blue-500/30 hover:from-blue-600 hover:to-blue-700 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                        Reset PIN
                    </button>
                    @endcan
                    @can('customers.send-message')
                    <button type="button" onclick="showSendMessageModal()" class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-blue-500 to-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-md shadow-blue-500/30 hover:from-blue-600 hover:to-blue-700 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                        </svg>
                        Send Message
                    </button>
                    @endcan
                    @can('customers.loans')
                    <a href="{{ route('admin.customers.loans', $customer) }}" class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-blue-500 to-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-md shadow-blue-500/30 hover:from-blue-600 hover:to-blue-700 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Customer Loans
                    </a>
                    @endcan
                    @can('loans.create')
                        @if ($customer->loanProduct)
                        <a href="{{ $customer->loanProduct->category === 'group_loans'
                            ? route('admin.loan-applications.group-loans.members', $customer->loanProduct)
                            : route('admin.loan-applications.loan-details', [$customer->loanProduct, $customer]) }}" class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-indigo-500 to-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-md shadow-indigo-500/30 hover:from-indigo-600 hover:to-blue-700 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            New Loan Application
                        </a>
                        @endif
                    @endcan
                    @can('repayments.create')
                    <a href="{{ route('admin.customers.repayments.create', $customer) }}" class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-600 px-3 py-2 text-sm font-semibold text-white shadow-md shadow-emerald-500/30 hover:from-emerald-600 hover:to-teal-700 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8v8m0 0v1m0-1a9 9 0 100-18 9 9 0 000 18z"/>
                        </svg>
                        Initiate Repayment
                    </a>
                    @endcan
                    @can('customers.repayments')
                    <a href="{{ route('admin.customers.repayments', $customer) }}" class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-blue-500 to-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-md shadow-blue-500/30 hover:from-blue-600 hover:to-blue-700 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Customer Repayments
                    </a>
                    @endcan
                @endif
                @can('customers.view')
                <a href="{{ route('admin.customers.statement', $customer) }}" class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-indigo-500 to-violet-600 px-3 py-2 text-sm font-semibold text-white shadow-md shadow-indigo-500/30 hover:from-indigo-600 hover:to-violet-700 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    View Statement
                </a>
                @endcan
                <a href="{{ route('admin.customers.index') }}" class="inline-flex items-center gap-2 rounded-xl border border-white/20 bg-white/5 px-3 py-2 text-sm font-medium text-slate-300 hover:bg-white/10 hover:border-white/30 transition">
                    Back to List
                </a>
            </div>
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            {{-- Bio Data --}}
            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
                <h2 class="text-xl font-semibold text-white">Bio Data</h2>
                <div class="space-y-3 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Name:</span>
                        <span class="font-medium text-white">{{ $customer->full_name }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Email:</span>
                        <span class="font-medium text-white">{{ $customer->email }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Phone:</span>
                        <span class="font-medium text-white">{{ $customer->phone ?? '—' }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Date of Birth:</span>
                        <span class="font-medium text-white">{{ $customer->date_of_birth?->format('d M Y') ?? '—' }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">National ID:</span>
                        <span class="font-medium text-white">{{ $customer->national_id ?? '—' }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">TPIN:</span>
                        <span class="font-medium text-white">{{ $customer->tpin ?? '—' }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Company:</span>
                        <span class="font-medium text-white">{{ $customer->company->name ?? '—' }}</span>
                    </div>
                    @if($customer->customer_type === 'representative' && $customer->parentCustomer)
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Parent Company Customer:</span>
                            <span class="font-medium text-white">{{ $customer->parentCustomer->registered_name ?? $customer->parentCustomer->full_name }}</span>
                        </div>
                    @endif
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Referred By:</span>
                        @if($customer->referredBy)
                            <a href="{{ route('admin.customers.show', $customer->referredBy) }}" class="font-medium text-cyan-400 hover:text-cyan-300 hover:underline transition">
                                {{ $customer->referredBy->full_name }}
                            </a>
                        @else
                            <span class="font-medium text-white">—</span>
                        @endif
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Status:</span>
                        <span class="inline-block rounded-full px-2 py-1 text-xs {{ $customer->status === 'active' ? 'bg-emerald-500/20 text-emerald-300' : ($customer->status === 'pending' ? 'bg-amber-500/20 text-amber-300' : 'bg-rose-500/20 text-rose-300') }}">
                            {{ ucfirst($customer->status) }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">KYC Status:</span>
                        <span class="inline-block rounded-full px-2 py-1 text-xs {{ $customer->kyc_status === 'verified' ? 'bg-emerald-500/20 text-emerald-300' : ($customer->kyc_status === 'in_review' ? 'bg-amber-500/20 text-amber-300' : 'bg-slate-500/20 text-slate-300') }}">
                            {{ ucfirst(str_replace('_', ' ', $customer->kyc_status)) }}
                        </span>
                    </div>
                    @if ($customer->approval_status)
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Approval Status:</span>
                            <span class="inline-block rounded-full px-2 py-1 text-xs {{ $customer->approval_status === 'approved' ? 'bg-emerald-500/20 text-emerald-300' : ($customer->approval_status === 'pending' ? 'bg-amber-500/20 text-amber-300' : 'bg-rose-500/20 text-rose-300') }}">
                                {{ ucfirst($customer->approval_status) }}
                            </span>
                        </div>
                        @if ($customer->approved_by)
                            <div class="flex items-center justify-between">
                                <span class="text-slate-400">Approved By:</span>
                                <span class="font-medium text-white">{{ $customer->approver?->full_name ?? 'Admin #'.$customer->approved_by }}</span>
                            </div>
                        @endif
                        @if ($customer->approved_at)
                            <div class="flex items-center justify-between">
                                <span class="text-slate-400">Approved At:</span>
                                <span class="font-medium text-white">{{ optional($customer->approved_at)->format('d M Y, H:i') ?? $customer->approved_at }}</span>
                            </div>
                        @endif
                        @if ($customer->approval_notes)
                            <div>
                                <span class="text-slate-400">Approval Notes:</span>
                                <p class="mt-1 font-medium text-white">{{ $customer->approval_notes }}</p>
                            </div>
                        @endif
                    @endif
                </div>
            </div>

	            {{-- Product Information --}}
	            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
	                <div class="space-y-4">
	                    <h2 class="text-xl font-semibold text-white">Product Information</h2>
	                    <div class="space-y-3 text-sm">
	                        <div class="flex items-center justify-between">
	                            <span class="text-slate-400">Product:</span>
	                            @if($customer->loanProduct)
	                                <a href="{{ route('admin.loan-products.show', $customer->loanProduct) }}" class="font-medium text-cyan-400 hover:text-cyan-300 hover:underline transition">
	                                    {{ $customer->loanProduct->name }}
	                                </a>
	                            @else
	                                <span class="font-medium text-white">—</span>
	                            @endif
	                        </div>
	                        <div class="flex items-center justify-between">
	                            <span class="text-slate-400">Code:</span>
	                            <span class="text-xs text-slate-400">{{ $customer->loanProduct->code ?? '—' }}</span>
	                        </div>
	                        <div class="flex items-center justify-between">
	                            <span class="text-slate-400">Category:</span>
	                            <span class="inline-block rounded-full bg-cyan-500/20 px-2 py-1 text-xs text-cyan-300 capitalize">
	                                {{ isset($customer->loanProduct->category) ? str_replace('_', ' ', $customer->loanProduct->category) : '—' }}
	                            </span>
	                        </div>
	                        @if ($customer->loanProduct && in_array($customer->loanProduct->category, ['character', 'collateral', 'government', 'group_loans'], true))
	                            <div class="flex items-center justify-between">
	                                <span class="text-slate-400">Customer Group:</span>
	                                @if($customer->customerGroup)
	                                    <a href="{{ route('admin.customer-groups.show', $customer->customerGroup) }}" class="font-medium text-cyan-400 hover:text-cyan-300 hover:underline transition">
	                                        {{ $customer->customerGroup->name }}
	                                    </a>
	                                @else
	                                    <span class="font-medium text-white">—</span>
	                                @endif
	                            </div>
	                            @if ($customer->customerGroup)
	                                <div class="flex items-center justify-between">
	                                    <span class="text-slate-400">Group Code:</span>
	                                    <span class="text-xs text-slate-400">{{ $customer->customerGroup->code ?? '—' }}</span>
	                                </div>
	                            @endif
	                        @endif
	                    </div>
	                </div>

	                <div class="mt-6 pt-6 border-t border-white/10 space-y-4">
	                    <div class="flex items-start justify-between gap-3">
	                        <div>
	                            <h2 class="text-xl font-semibold text-white">Default Payment Information</h2>
	                            <p class="text-xs text-slate-400">Customer payment details for repayments and disbursements.</p>
	                        </div>
	                        @can('customers.update')
	                            <a href="{{ route('admin.customers.payment-details.edit', $customer) }}" class="shrink-0 inline-flex items-center gap-2 rounded-xl border border-white/20 bg-white/5 px-3 py-2 text-sm font-semibold text-slate-200 hover:bg-white/10 hover:border-white/30 transition">
	                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
	                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
	                                </svg>
	                                Edit Payment
	                            </a>
	                        @endcan
	                    </div>

	                    <div class="space-y-3 text-sm">
	                        @if(!$paymentDetail)
	                            <div class="flex items-center justify-between">
	                                <span class="text-slate-400">Method:</span>
	                                <span class="font-medium text-white">—</span>
	                            </div>
	                        @else
	                            <div class="flex items-center justify-between">
	                                <span class="text-slate-400">Method:</span>
	                                <span class="inline-block rounded-full px-2 py-1 text-xs font-semibold {{ $paymentDetail->method_type === 'wallet' ? 'bg-purple-500/20 text-purple-200' : 'bg-emerald-500/20 text-emerald-200' }}">
	                                    {{ $paymentDetail->method_type === 'wallet' ? 'Wallet' : 'Bank' }}
	                                </span>
	                            </div>

	                            @if($paymentDetail->method_type === 'wallet')
	                                <div class="flex items-center justify-between">
	                                    <span class="text-slate-400">Wallet provider:</span>
	                                    <span class="font-medium text-white">{{ $paymentDetail->wallet_provider ?: '—' }}</span>
	                                </div>
	                                <div class="flex items-center justify-between">
	                                    <span class="text-slate-400">Wallet number:</span>
	                                    <span class="font-medium text-white">{{ $paymentDetail->wallet_number ?: '—' }}</span>
	                                </div>
	                            @else
	                                <div class="flex items-center justify-between">
	                                    <span class="text-slate-400">Bank:</span>
	                                    <span class="font-medium text-white">{{ $paymentDetail->bank_name ?: '—' }}</span>
	                                </div>
	                                <div class="flex items-center justify-between">
	                                    <span class="text-slate-400">Branch:</span>
	                                    <span class="font-medium text-white">{{ $paymentDetail->bank_branch ?: '—' }}</span>
	                                </div>
	                                <div class="flex items-center justify-between">
	                                    <span class="text-slate-400">Account name:</span>
	                                    <span class="font-medium text-white">{{ $paymentDetail->account_name ?: '—' }}</span>
	                                </div>
	                                <div class="flex items-center justify-between">
	                                    <span class="text-slate-400">Account number:</span>
	                                    <span class="font-medium text-white">{{ $paymentDetail->account_number ?: '—' }}</span>
	                                </div>
	                            @endif
	                        @endif
	                    </div>
	                </div>
	            </div>

            @if ($customer->loanProduct && $customer->loanProduct->category === 'collateral')
                {{-- Financial Information for Collateral-based Loans --}}
                <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
                    <h2 class="text-xl font-semibold text-white">Financial Information</h2>
                    <div class="space-y-3 text-sm">
                        @if ($customer->gross_salary)
                            <div class="flex items-center justify-between">
                                <span class="text-slate-400">Gross Salary:</span>
                                <span class="font-medium text-white">ZMW {{ number_format($customer->gross_salary, 2) }}</span>
                            </div>
                        @endif
                        @if ($customer->net_salary)
                            <div class="flex items-center justify-between">
                                <span class="text-slate-400">Net Salary:</span>
                                <span class="font-medium text-white">ZMW {{ number_format($customer->net_salary, 2) }}</span>
                            </div>
                        @endif
                        @if ($customer->annual_income)
                            <div class="flex items-center justify-between">
                                <span class="text-slate-400">Annual Income:</span>
                                <span class="font-medium text-white">ZMW {{ number_format($customer->annual_income, 2) }}</span>
                            </div>
                        @endif
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Maximum loan exposure:</span>
                            <span class="font-medium text-white">ZMW {{ number_format($customer->maximum_loan_take ?? 0, 2) }}</span>
                        </div>
                        @if($customer->customerGroup)
                            <div class="flex items-center justify-between">
                                <span class="text-slate-400">Multiple active loans:</span>
                                <span class="font-medium {{ $customer->customerGroup->allow_multiple_loans ? 'text-emerald-400' : 'text-amber-300' }}">
                                    {{ $customer->customerGroup->allow_multiple_loans ? 'Allowed by group' : 'Not allowed by group' }}
                                </span>
                            </div>
                        @endif
                        @php
                            $creditScore = $customer->credit_score;
                            $scoreCategory = $creditScore !== null ? \App\Support\CreditScoreService::getScoreCategory($creditScore) : null;
                        @endphp
                        @if($creditScore !== null)
                            <div class="flex items-center justify-between border-t border-white/10 pt-3">
                                <span class="text-slate-400">Credit Score:</span>
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-lg {{ $scoreCategory['text_color'] }}">{{ number_format($creditScore, 1) }}</span>
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $scoreCategory['bg_color'] }} {{ $scoreCategory['text_color'] }} border {{ $scoreCategory['border_color'] }}">
                                        {{ $scoreCategory['category'] }}
                                    </span>
                                </div>
                            </div>
                            @if($customer->credit_score_updated_at)
                                <div class="text-xs text-slate-500 mt-1">
                                    Last updated: {{ $customer->credit_score_updated_at->format('M d, Y H:i') }}
                                </div>
                            @endif
                            @can('customers.update')
                            <form method="POST" action="{{ route('admin.customers.recalculate-credit-score', $customer) }}" class="mt-2">
                                @csrf
                                <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-cyan-500/40 to-blue-600/40 border-2 border-cyan-400/70 px-3 py-1.5 text-xs font-semibold text-cyan-200 hover:from-cyan-500/60 hover:to-blue-600/60 hover:border-cyan-400 hover:text-white transition shadow-md shadow-cyan-500/20">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                    </svg>
                                    Recalculate
                                </button>
                            </form>
                            @endcan
                        @else
                            <div class="flex items-center justify-between border-t border-white/10 pt-3">
                                <span class="text-slate-400">Credit Score:</span>
                                <span class="text-xs text-slate-500">Not calculated yet</span>
                            </div>
                            @can('customers.update')
                            <form method="POST" action="{{ route('admin.customers.recalculate-credit-score', $customer) }}" class="mt-2">
                                @csrf
                                <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-cyan-500/40 to-blue-600/40 border-2 border-cyan-400/70 px-3 py-1.5 text-xs font-semibold text-cyan-200 hover:from-cyan-500/60 hover:to-blue-600/60 hover:border-cyan-400 hover:text-white transition shadow-md shadow-cyan-500/20">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    Calculate Credit Score
                                </button>
                            </form>
                            @endcan
                        @endif
                        <div class="flex items-center justify-between border-t border-white/10 pt-3">
                            <span class="text-slate-400">Outstanding exposure:</span>
                            <span class="font-medium text-rose-400">ZMW {{ number_format($customer->getTotalOutstandingBalance(), 2) }}</span>
                        </div>
                        <div class="flex items-center justify-between border-t border-white/10 pt-3">
                            <span class="text-slate-400">Available exposure:</span>
                            <span class="font-medium text-emerald-400">ZMW {{ number_format($customer->getAvailableLoanAmount(), 2) }}</span>
                        </div>
                    </div>
                </div>
            @endif

            @if ($customer->loanProduct && $customer->loanProduct->category === 'government')
                {{-- Work Information --}}
                <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
                    <h2 class="text-xl font-semibold text-white">Work Information</h2>
                    <div class="space-y-3 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Employee Number:</span>
                            <span class="font-medium text-white">{{ $customer->employee_number ?? '—' }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Ministry:</span>
                            <span class="font-medium text-white">{{ $customer->ministry->name ?? '—' }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Date of Employment:</span>
                            <span class="font-medium text-white">{{ $customer->date_of_employment?->format('d M Y') ?? '—' }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Contract End Date:</span>
                            <span class="font-medium text-white">{{ $customer->contract_end_date?->format('d M Y') ?? '—' }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Gross Salary:</span>
                            <span class="font-medium text-white">{{ $customer->gross_salary ? number_format($customer->gross_salary, 2) : '—' }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Net Salary:</span>
                            <span class="font-medium text-white">{{ $customer->net_salary ? number_format($customer->net_salary, 2) : '—' }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Deductions:</span>
                            <span class="font-medium text-white">{{ $customer->deductions ? number_format($customer->deductions, 2) : '—' }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Verified By:</span>
                            <div class="text-right">
                                <span class="font-medium text-white">{{ $customer->verifier->full_name ?? '—' }}</span>
                                @if ($customer->verifier)
                                    <p class="text-xs text-slate-400">{{ $customer->verifier->email }}</p>
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Maximum Loan Take:</span>
                            <span class="font-medium text-white">{{ $customer->maximum_loan_take ? number_format($customer->maximum_loan_take, 2) : '—' }}</span>
                        </div>
                        @php
                            $creditScore = $customer->credit_score;
                            $scoreCategory = $creditScore !== null ? \App\Support\CreditScoreService::getScoreCategory($creditScore) : null;
                        @endphp
                        @if($creditScore !== null)
                            <div class="flex items-center justify-between border-t border-white/10 pt-3">
                                <span class="text-slate-400">Credit Score:</span>
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-lg {{ $scoreCategory['text_color'] }}">{{ number_format($creditScore, 1) }}</span>
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $scoreCategory['bg_color'] }} {{ $scoreCategory['text_color'] }} border {{ $scoreCategory['border_color'] }}">
                                        {{ $scoreCategory['category'] }}
                                    </span>
                                </div>
                            </div>
                            @if($customer->credit_score_updated_at)
                                <div class="text-xs text-slate-500 mt-1">
                                    Last updated: {{ $customer->credit_score_updated_at->format('M d, Y H:i') }}
                                </div>
                            @endif
                            @can('customers.update')
                            <form method="POST" action="{{ route('admin.customers.recalculate-credit-score', $customer) }}" class="mt-2">
                                @csrf
                                <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-cyan-500/40 to-blue-600/40 border-2 border-cyan-400/70 px-3 py-1.5 text-xs font-semibold text-cyan-200 hover:from-cyan-500/60 hover:to-blue-600/60 hover:border-cyan-400 hover:text-white transition shadow-md shadow-cyan-500/20">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                    </svg>
                                    Recalculate
                                </button>
                            </form>
                            @endcan
                        @else
                            <div class="flex items-center justify-between border-t border-white/10 pt-3">
                                <span class="text-slate-400">Credit Score:</span>
                                <span class="text-xs text-slate-500">Not calculated yet</span>
                            </div>
                            @can('customers.update')
                            <form method="POST" action="{{ route('admin.customers.recalculate-credit-score', $customer) }}" class="mt-2">
                                @csrf
                                <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-cyan-500/40 to-blue-600/40 border-2 border-cyan-400/70 px-3 py-1.5 text-xs font-semibold text-cyan-200 hover:from-cyan-500/60 hover:to-blue-600/60 hover:border-cyan-400 hover:text-white transition shadow-md shadow-cyan-500/20">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    Calculate Credit Score
                                </button>
                            </form>
                            @endcan
                        @endif
                    </div>
                </div>

                {{-- Work Address --}}
                <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
                    <h2 class="text-xl font-semibold text-white">Work Address</h2>
                    <div class="space-y-3 text-sm">
                        <div>
                            <span class="text-slate-400">Address:</span>
                            <p class="mt-1 font-medium text-white">
                                @if ($customer->work_address_line1)
                                    {{ $customer->work_address_line1 }}<br>
                                    @if ($customer->work_address_line2){{ $customer->work_address_line2 }}<br>@endif
                                    {{ $customer->work_city ?? '' }}{{ $customer->work_city && ($customer->workProvince || $customer->workDistrict) ? ', ' : '' }}{{ $customer->workProvince->name ?? '' }}{{ $customer->workProvince && $customer->workDistrict ? ', ' : '' }}{{ $customer->workDistrict->name ?? '' }}<br>
                                    {{ $customer->work_postal_code ?? '' }}<br>
                                    {{ $customer->work_country ?? '' }}
                                @else
                                    —
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            @if ($customer->loanProduct && ($customer->loanProduct->category === 'character' || $customer->loanProduct->category === 'marketeer'))
                {{-- Work Information for Character-based Loans --}}
                <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
                    <h2 class="text-xl font-semibold text-white">Work Information</h2>
                    <div class="space-y-3 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Customer Group:</span>
                            @if($customer->customerGroup)
                                <a href="{{ route('admin.customer-groups.show', $customer->customerGroup) }}" class="font-medium text-cyan-400 hover:text-cyan-300 hover:underline transition">
                                    {{ $customer->customerGroup->name }}
                                </a>
                            @else
                                <span class="font-medium text-white">—</span>
                            @endif
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Employment Status:</span>
                            <span class="inline-block rounded-full px-2 py-1 text-xs {{ $customer->is_employed ? 'bg-emerald-500/20 text-emerald-300' : 'bg-slate-500/20 text-slate-300' }}">
                                {{ $customer->is_employed ? 'Employed' : 'Not Employed' }}
                            </span>
                        </div>
                        @if ($customer->is_employed && $customer->employee_number)
                            <div class="flex items-center justify-between">
                                <span class="text-slate-400">Employee Number:</span>
                                <span class="font-medium text-white">{{ $customer->employee_number }}</span>
                            </div>
                        @endif
                        @if ($customer->payday)
                            <div class="flex items-center justify-between">
                                <span class="text-slate-400">Payday:</span>
                                <span class="font-medium text-white">{{ $customer->payday }}{{ $customer->payday == 1 || $customer->payday == 21 || $customer->payday == 31 ? 'st' : ($customer->payday == 2 || $customer->payday == 22 ? 'nd' : ($customer->payday == 3 || $customer->payday == 23 ? 'rd' : 'th')) }} of each month</span>
                            </div>
                        @endif
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Gross Salary:</span>
                            <span class="font-medium text-white">{{ $customer->gross_salary ? number_format($customer->gross_salary, 2) : '—' }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Net Salary:</span>
                            <span class="font-medium text-white">{{ $customer->net_salary ? number_format($customer->net_salary, 2) : '—' }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Maximum Loan Take:</span>
                            <span class="font-medium text-white">{{ $customer->maximum_loan_take ? number_format($customer->maximum_loan_take, 2) : '—' }}</span>
                        </div>
                        @php
                            $creditScore = $customer->credit_score;
                            $scoreCategory = $creditScore !== null ? \App\Support\CreditScoreService::getScoreCategory($creditScore) : null;
                        @endphp
                        @if($creditScore !== null)
                            <div class="flex items-center justify-between border-t border-white/10 pt-3">
                                <span class="text-slate-400">Credit Score:</span>
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-lg {{ $scoreCategory['text_color'] }}">{{ number_format($creditScore, 1) }}</span>
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $scoreCategory['bg_color'] }} {{ $scoreCategory['text_color'] }} border {{ $scoreCategory['border_color'] }}">
                                        {{ $scoreCategory['category'] }}
                                    </span>
                                </div>
                            </div>
                            @if($customer->credit_score_updated_at)
                                <div class="text-xs text-slate-500 mt-1">
                                    Last updated: {{ $customer->credit_score_updated_at->format('M d, Y H:i') }}
                                </div>
                            @endif
                            @can('customers.update')
                            <form method="POST" action="{{ route('admin.customers.recalculate-credit-score', $customer) }}" class="mt-2">
                                @csrf
                                <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-cyan-500/40 to-blue-600/40 border-2 border-cyan-400/70 px-3 py-1.5 text-xs font-semibold text-cyan-200 hover:from-cyan-500/60 hover:to-blue-600/60 hover:border-cyan-400 hover:text-white transition shadow-md shadow-cyan-500/20">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                    </svg>
                                    Recalculate
                                </button>
                            </form>
                            @endcan
                        @else
                            <div class="flex items-center justify-between border-t border-white/10 pt-3">
                                <span class="text-slate-400">Credit Score:</span>
                                <span class="text-xs text-slate-500">Not calculated yet</span>
                            </div>
                            @can('customers.update')
                            <form method="POST" action="{{ route('admin.customers.recalculate-credit-score', $customer) }}" class="mt-2">
                                @csrf
                                <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-cyan-500/40 to-blue-600/40 border-2 border-cyan-400/70 px-3 py-1.5 text-xs font-semibold text-cyan-200 hover:from-cyan-500/60 hover:to-blue-600/60 hover:border-cyan-400 hover:text-white transition shadow-md shadow-cyan-500/20">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    Calculate Credit Score
                                </button>
                            </form>
                            @endcan
                        @endif
                    </div>
                </div>

                {{-- Next of Kin Information --}}
                @if ($customer->next_of_kin_name)
                <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
                    <h2 class="text-xl font-semibold text-white">Next of Kin</h2>
                    <div class="space-y-3 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Name:</span>
                            <span class="font-medium text-white">{{ $customer->next_of_kin_name }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Phone:</span>
                            <span class="font-medium text-white">{{ $customer->next_of_kin_phone ?? '—' }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Relationship:</span>
                            <span class="font-medium text-white capitalize">{{ $customer->next_of_kin_relationship ?? '—' }}</span>
                        </div>
                        @if ($customer->next_of_kin_address_line1)
                        <div>
                            <span class="text-slate-400">Address:</span>
                            <p class="mt-1 font-medium text-white">
                                {{ $customer->next_of_kin_address_line1 }}<br>
                                @if ($customer->next_of_kin_address_line2){{ $customer->next_of_kin_address_line2 }}<br>@endif
                                {{ $customer->next_of_kin_city ?? '' }}{{ $customer->next_of_kin_city && $customer->next_of_kin_country ? ', ' : '' }}{{ $customer->next_of_kin_country ?? '' }}
                            </p>
                        </div>
                        @endif
                    </div>
                </div>
                @endif
            @endif

            {{-- Address --}}
            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
                <h2 class="text-xl font-semibold text-white">Customer Address</h2>
                <div class="space-y-3 text-sm">
                    <div>
                        <span class="text-slate-400">Address:</span>
                        <p class="mt-1 font-medium text-white">
                            @if ($customer->address_line1)
                                {{ $customer->address_line1 }}<br>
                                @if ($customer->address_line2){{ $customer->address_line2 }}<br>@endif
                                {{ $customer->city ?? '' }}{{ $customer->city && $customer->state ? ', ' : '' }}{{ $customer->state ?? '' }}<br>
                                {{ $customer->postal_code ?? '' }}<br>
                                {{ $customer->country ?? '' }}
                            @else
                                —
                            @endif
                        </p>
                    </div>
                </div>
            </div>

            {{-- Cashflow & PAR --}}
            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-semibold text-white">Cashflow & PAR</h2>
                        <p class="text-sm text-slate-400">Disbursement vs repayment momentum and risk for this customer.</p>
                    </div>
                    <span class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs text-slate-200">
                        PAR = (Arrears ÷ Portfolio) × 100
                    </span>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="rounded-2xl border border-cyan-500/30 bg-cyan-500/10 p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <p class="text-sm uppercase tracking-[0.25em] text-cyan-100">Disbursed</p>
                            <span class="text-xs text-cyan-100/80">All time</span>
                        </div>
                        <p class="text-2xl font-semibold text-white">ZMW {{ number_format($customerCashflowStats['disbursements']['total'], 2) }}</p>
                        <div class="grid grid-cols-3 gap-2 text-xs text-slate-200">
                            <div class="rounded-xl bg-white/10 px-2 py-2">
                                <p class="text-[11px] uppercase tracking-wide text-slate-300">Today</p>
                                <p class="font-semibold text-white">ZMW {{ number_format($customerCashflowStats['disbursements']['daily'], 2) }}</p>
                            </div>
                            <div class="rounded-xl bg-white/10 px-2 py-2">
                                <p class="text-[11px] uppercase tracking-wide text-slate-300">7d</p>
                                <p class="font-semibold text-white">ZMW {{ number_format($customerCashflowStats['disbursements']['weekly'], 2) }}</p>
                            </div>
                            <div class="rounded-xl bg-white/10 px-2 py-2">
                                <p class="text-[11px] uppercase tracking-wide text-slate-300">Last 3 mo</p>
                                <p class="font-semibold text-white">ZMW {{ number_format(array_sum($customerCashflowStats['disbursements']['monthly']), 2) }}</p>
                            </div>
                        </div>
                        <div class="space-y-1">
                            <p class="text-[11px] uppercase tracking-[0.2em] text-slate-300">By Month</p>
                            <div class="flex flex-wrap gap-2">
                                @foreach($customerCashflowStats['months'] as $window)
                                    @php
                                        $value = $customerCashflowStats['disbursements']['monthly'][$window['key']] ?? 0;
                                    @endphp
                                    <span class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs text-white">
                                        {{ $window['label'] }}
                                        <span class="text-cyan-200 font-semibold">ZMW {{ number_format($value, 0) }}</span>
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <p class="text-sm uppercase tracking-[0.25em] text-emerald-100">Repaid</p>
                            <span class="text-xs text-emerald-100/80">All time</span>
                        </div>
                        <p class="text-2xl font-semibold text-emerald-100">ZMW {{ number_format($customerCashflowStats['repayments']['total'], 2) }}</p>
                        <div class="grid grid-cols-3 gap-2 text-xs text-slate-200">
                            <div class="rounded-xl bg-white/10 px-2 py-2">
                                <p class="text-[11px] uppercase tracking-wide text-slate-300">Today</p>
                                <p class="font-semibold text-white">ZMW {{ number_format($customerCashflowStats['repayments']['daily'], 2) }}</p>
                            </div>
                            <div class="rounded-xl bg-white/10 px-2 py-2">
                                <p class="text-[11px] uppercase tracking-wide text-slate-300">7d</p>
                                <p class="font-semibold text-white">ZMW {{ number_format($customerCashflowStats['repayments']['weekly'], 2) }}</p>
                            </div>
                            <div class="rounded-xl bg-white/10 px-2 py-2">
                                <p class="text-[11px] uppercase tracking-wide text-slate-300">Last 3 mo</p>
                                <p class="font-semibold text-white">ZMW {{ number_format(array_sum($customerCashflowStats['repayments']['monthly']), 2) }}</p>
                            </div>
                        </div>
                        <div class="space-y-1">
                            <p class="text-[11px] uppercase tracking-[0.2em] text-slate-300">By Month</p>
                            <div class="flex flex-wrap gap-2">
                                @foreach($customerCashflowStats['months'] as $window)
                                    @php
                                        $value = $customerCashflowStats['repayments']['monthly'][$window['key']] ?? 0;
                                    @endphp
                                    <span class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs text-white">
                                        {{ $window['label'] }}
                                        <span class="text-emerald-200 font-semibold">ZMW {{ number_format($value, 0) }}</span>
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-2xl bg-white/5 border border-white/10 p-3 text-sm">
                        <p class="text-slate-300">Portfolio</p>
                        <p class="text-xl font-semibold text-white mt-1">ZMW {{ number_format($customerCashflowStats['portfolio']['total'], 2) }}</p>
                    </div>
                    <div class="rounded-2xl bg-white/5 border border-white/10 p-3 text-sm">
                        <p class="text-slate-300">Arrears</p>
                        <p class="text-xl font-semibold text-amber-200 mt-1">ZMW {{ number_format($customerCashflowStats['portfolio']['arrears'], 2) }}</p>
                    </div>
                    <div class="rounded-2xl bg-white/5 border border-white/10 p-3 text-sm">
                        <p class="text-slate-300">Portfolio at Risk</p>
                        <p class="text-xl font-semibold {{ $customerCashflowStats['portfolio']['par'] >= 20 ? 'text-rose-300' : ($customerCashflowStats['portfolio']['par'] >= 10 ? 'text-amber-200' : 'text-emerald-200') }} mt-1">
                            {{ number_format($customerCashflowStats['portfolio']['par'], 2) }}%
                        </p>
                        <p class="text-[11px] text-slate-400 mt-1">Total Arrears ÷ Total Portfolio × 100</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Customer Loans Table --}}
        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <div class="mb-6 flex items-center justify-between">
                <h2 class="text-xl font-semibold text-white flex items-center gap-2">
                    <span class="w-1 h-6 rounded-full bg-cyan-500"></span>Customer Loans
                </h2>
                <span class="rounded-full bg-cyan-500/20 px-3 py-1 text-sm font-medium text-cyan-300">
                    {{ $customer->loans->count() }} {{ $customer->loans->count() == 1 ? 'Loan' : 'Loans' }}
                </span>
            </div>
            @if($customer->loans->count() > 0)
                <div class="overflow-x-auto">
                    <table data-datatable="true" data-datatable-per-page="10" class="min-w-full w-full text-sm text-slate-300">
                        <thead>
                            <tr class="text-sm font-semibold uppercase tracking-[0.25em] text-white/80 text-center border-b border-white/10">
                                <th class="px-4 py-4 text-base">Loan Number</th>
                                <th class="px-4 py-4 text-base">Product</th>
                                <th class="px-4 py-4 text-base">Principal Amount</th>
                                <th class="px-4 py-4 text-base">Booked Total</th>
                                <th class="px-4 py-4 text-base">Booked Outstanding</th>
                                <th class="px-4 py-4 text-base">Tenure</th>
                                <th class="px-4 py-4 text-base">Start Date</th>
                                <th class="px-4 py-4 text-base">Status</th>
                                <th class="px-4 py-4 text-base">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($customer->loans as $loan)
                                <tr class="border-t border-white/5 text-center hover:bg-white/5 transition">
                                    <td class="px-4 py-3 font-medium text-white">
                                        {{ $loan->loan_number }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full bg-cyan-500/20 px-2 py-1 text-xs text-cyan-300">
                                            {{ $loan->loanProduct->name ?? '—' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 font-medium text-white">
                                        ZMW {{ number_format($loan->principal_amount, 2) }}
                                    </td>
                                    <td class="px-4 py-3 font-medium text-white">
                                        ZMW {{ number_format($loan->total_amount, 2) }}
                                    </td>
                                    <td class="px-4 py-3 font-medium text-white">
                                        ZMW {{ number_format($loan->outstanding_balance, 2) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ $loan->tenure_months }} {{ $loan->tenure_months == 1 ? 'Month' : 'Months' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ $loan->loan_start_date->format('d M Y') }}
                                    </td>
                                    <td class="px-4 py-3">
                                        @php
                                            $statusColors = [
                                                'pending_approval' => 'bg-amber-500/20 text-amber-300',
                                                'approved' => 'bg-blue-500/20 text-blue-300',
                                                'active' => 'bg-emerald-500/20 text-emerald-300',
                                                'completed' => 'bg-green-500/20 text-green-300',
                                                'defaulted' => 'bg-rose-500/20 text-rose-300',
                                                'cancelled' => 'bg-slate-500/20 text-slate-300',
                                            ];
                                            $statusColor = $statusColors[$loan->status] ?? 'bg-slate-500/20 text-slate-300';
                                        @endphp
                                        <span class="inline-block rounded-full px-2 py-1 text-xs {{ $statusColor }}">
                                            {{ ucfirst(str_replace('_', ' ', $loan->status)) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <a href="{{ route('admin.loans.show', $loan) }}" class="rounded-full bg-blue-500/20 border border-blue-500/50 px-3 py-1.5 text-xs font-medium text-blue-300 hover:bg-blue-500/30 hover:border-blue-500 transition">View</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-center text-slate-400 py-12">This customer has no loans yet.</p>
            @endif
        </div>
    </div>

    @if ($isPendingApproval && $hasKycForApproval)
        <!-- Approve Modal -->
        <div id="approveModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
            <div class="rounded-3xl border border-white/10 bg-slate-900 p-6 w-full max-w-md shadow-2xl">
                <h3 class="text-xl font-semibold text-white mb-4">Approve Customer</h3>
                <p class="text-sm text-slate-300 mb-4">
                    Are you sure you want to approve <span class="font-semibold text-white">{{ $customer->full_name }}</span>?
                </p>
                <form id="approveForm" method="POST" action="{{ route('admin.approvals.customers.approve', $customer) }}">
                    @csrf
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Approval Notes (optional)</label>
                        <textarea name="notes" rows="3" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40" placeholder="Add notes for this approval..."></textarea>
                    </div>
                    <div class="flex gap-3">
                        <button type="button" onclick="closeApproveModal()" class="flex-1 rounded-2xl border border-white/10 px-4 py-2 text-sm text-white hover:bg-white/10 transition">
                            Cancel
                        </button>
                        <button type="submit" class="flex-1 rounded-2xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-emerald-500/30 hover:bg-emerald-700 transition">
                            Confirm Approve
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Reject Modal -->
        <div id="rejectModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
            <div class="rounded-3xl border border-white/10 bg-slate-900 p-6 w-full max-w-md shadow-2xl">
                <h3 class="text-xl font-semibold text-white mb-4">Reject Customer</h3>
                <form id="rejectForm" method="POST" action="{{ route('admin.approvals.customers.reject', $customer) }}">
                    @csrf
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Rejection Notes (optional)</label>
                        <textarea name="notes" rows="3" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40" placeholder="Provide a reason for rejection..."></textarea>
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

        @push('scripts')
        <script>
            function showApproveModal(customerId) {
                document.getElementById('approveModal').classList.remove('hidden');
            }

            function closeApproveModal() {
                document.getElementById('approveModal').classList.add('hidden');
                document.getElementById('approveForm').reset();
            }

            // Close modal on outside click
            document.getElementById('approveModal')?.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeApproveModal();
                }
            });

            function showRejectModal(customerId) {
                document.getElementById('rejectModal').classList.remove('hidden');
            }

            function closeRejectModal() {
                document.getElementById('rejectModal').classList.add('hidden');
                document.getElementById('rejectForm').reset();
            }

            // Close modal on outside click
            document.getElementById('rejectModal')?.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeRejectModal();
                }
            });
        </script>
        @endpush
    @endif

    @push('styles')
    <style>
        /* Reset PIN Modal Theme Styles */
        body[data-theme="light"] #resetPinModal .modal-content {
            background-color: #ffffff;
            border-color: #e2e8f0;
        }
        body[data-theme="light"] #resetPinModal .modal-title {
            color: #0f172a;
        }
        body[data-theme="light"] #resetPinModal .modal-text {
            color: #1e293b;
        }
        body[data-theme="light"] #resetPinModal .modal-text strong {
            color: #0f172a;
        }
        body[data-theme="light"] #resetPinModal .modal-cancel {
            border-color: #cbd5e1;
            color: #1e293b;
        }
        body[data-theme="light"] #resetPinModal .modal-cancel:hover {
            background-color: #f1f5f9;
        }
        
        body[data-theme="dark"] #resetPinModal .modal-content {
            background-color: #0f172a;
            border-color: rgba(255, 255, 255, 0.1);
        }
        body[data-theme="dark"] #resetPinModal .modal-title {
            color: #f8fafc;
        }
        body[data-theme="dark"] #resetPinModal .modal-text {
            color: #94a3b8;
        }
        body[data-theme="dark"] #resetPinModal .modal-text strong {
            color: #f8fafc;
        }
        body[data-theme="dark"] #resetPinModal .modal-cancel {
            border-color: rgba(255, 255, 255, 0.1);
            color: #f8fafc;
        }
        body[data-theme="dark"] #resetPinModal .modal-cancel:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
    </style>
    @endpush

    <!-- Reset PIN Modal -->
    <div id="resetPinModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div id="resetPinModalContent" class="modal-content rounded-3xl border p-6 w-full max-w-md shadow-2xl">
            <h3 class="modal-title text-xl font-semibold mb-4">Reset Customer PIN</h3>
            <p class="modal-text mb-6">
                Are you sure you want to reset the PIN for <strong>{{ $customer->full_name }}</strong>? 
                A new PIN will be generated and sent to the customer's email address. They will be required to change it on their next login.
            </p>
            <form id="resetPinForm" method="POST" action="{{ route('admin.customers.reset-pin', $customer) }}">
                @csrf
                <div class="flex gap-3">
                    <button type="button" onclick="closeResetPinModal()" class="modal-cancel flex-1 rounded-2xl border px-4 py-2 text-sm transition">
                        Cancel
                    </button>
                    <button type="submit" class="flex-1 rounded-2xl bg-amber-600 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-amber-500/30 hover:bg-amber-700 transition">
                        Confirm Reset
                    </button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        function showResetPinModal() {
            document.getElementById('resetPinModal').classList.remove('hidden');
        }

        function closeResetPinModal() {
            document.getElementById('resetPinModal').classList.add('hidden');
        }

        // Close modal on outside click
        document.getElementById('resetPinModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeResetPinModal();
            }
        });

        function showSendMessageModal() {
            document.getElementById('sendMessageModal').classList.remove('hidden');
        }

        function closeSendMessageModal() {
            document.getElementById('sendMessageModal').classList.add('hidden');
            document.getElementById('sendMessageForm').reset();
            // Reset radio buttons
            document.querySelectorAll('input[name="type"]').forEach(radio => {
                radio.checked = false;
            });
        }

        // Close modal on outside click
        document.getElementById('sendMessageModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeSendMessageModal();
            }
        });

        // Toggle subject field based on message type
        document.querySelectorAll('input[name="type"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const subjectField = document.getElementById('subjectField');
                const subjectInput = document.getElementById('subject');
                if (this.value === 'sms') {
                    subjectField.classList.add('hidden');
                    subjectInput.removeAttribute('required');
                    subjectInput.value = '';
                } else {
                    subjectField.classList.remove('hidden');
                    subjectInput.setAttribute('required', 'required');
                }
            });
        });
    </script>
    @endpush

    <!-- Send Message Modal -->
    <div id="sendMessageModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="rounded-3xl border border-white/10 bg-slate-900 p-6 w-full max-w-2xl shadow-2xl">
            <h3 class="text-xl font-semibold text-white mb-4">Send Message to {{ $customer->full_name }}</h3>
            <form id="sendMessageForm" method="POST" action="{{ route('admin.customers.send-message', $customer) }}">
                @csrf
                <div class="space-y-4">
                    {{-- Message Type --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Message Type <span class="text-red-400">*</span></label>
                        <div class="flex gap-4">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="type" value="email" class="w-4 h-4 text-indigo-600 bg-slate-700 border-slate-600 focus:ring-indigo-500">
                                <span class="text-slate-300">Email</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="type" value="sms" class="w-4 h-4 text-indigo-600 bg-slate-700 border-slate-600 focus:ring-indigo-500">
                                <span class="text-slate-300">SMS</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="type" value="both" class="w-4 h-4 text-indigo-600 bg-slate-700 border-slate-600 focus:ring-indigo-500">
                                <span class="text-slate-300">Both</span>
                            </label>
                        </div>
                        @error('type')
                            <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Subject (for email) --}}
                    <div id="subjectField" class="hidden">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Subject</label>
                        <input type="text" id="subject" name="subject" value="{{ old('subject') }}" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-indigo-400 focus:ring-indigo-400/40" placeholder="Enter email subject">
                        @error('subject')
                            <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Message --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Message <span class="text-red-400">*</span></label>
                        <textarea name="message" rows="6" required class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-indigo-400 focus:ring-indigo-400/40" placeholder="Enter your message...">{{ old('message') }}</textarea>
                        @error('message')
                            <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs text-slate-400">
                            @if($customer->email && $customer->phone)
                                Will be sent to: {{ $customer->email }} and {{ $customer->phone }}
                            @elseif($customer->email)
                                Will be sent to: {{ $customer->email }}
                            @elseif($customer->phone)
                                Will be sent to: {{ $customer->phone }}
                            @else
                                <span class="text-amber-400">Warning: Customer has no email or phone number</span>
                            @endif
                        </p>
                    </div>
                </div>
                <div class="flex gap-3 mt-6">
                    <button type="button" onclick="closeSendMessageModal()" class="flex-1 rounded-2xl border border-white/10 px-4 py-2 text-sm text-white hover:bg-white/10 transition">
                        Cancel
                    </button>
                    @can('customers.send-message')
                    <button type="submit" class="flex-1 rounded-2xl bg-gradient-to-r from-indigo-500 to-purple-500 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-indigo-500/30 hover:shadow-indigo-500/50 transition">
                        Send Message
                    </button>
                    @else
                    <button type="button" disabled class="flex-1 rounded-2xl bg-slate-600 px-4 py-2 text-sm font-semibold text-white/50 cursor-not-allowed">
                        Send Message (No Permission)
                    </button>
                    @endcan
                </div>
            </form>
        </div>
    </div>

    {{-- Delete Customer Section --}}
    @php
        $hasLoans = $customer->loans()->exists();
        $hasRepayments = \App\Models\Repayment::where('customer_id', $customer->id)->exists();
        $canDelete = !$hasLoans && !$hasRepayments;
    @endphp
    <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-white mb-2">Danger Zone</h3>
                <p class="text-sm text-slate-400">
                    @if($canDelete)
                        This customer can be deleted. They have no loans or repayments associated with their account.
                    @else
                        <span class="text-amber-400">⚠️ This customer cannot be deleted because they have loans or repayments associated with their account. Deleting would corrupt financial data.</span>
                    @endif
                </p>
            </div>
            @if($canDelete && auth('admin')->user()?->can('customers.delete'))
                <button type="button" onclick="showDeleteModal()" class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-rose-600 to-red-600 px-4 py-3 font-semibold text-white shadow-lg shadow-rose-500/30 hover:shadow-rose-500/50 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    Delete Customer
                </button>
            @elseif(!$canDelete)
                <button type="button" disabled class="inline-flex items-center gap-2 rounded-2xl bg-slate-600/50 px-4 py-3 font-semibold text-slate-400 cursor-not-allowed">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    Cannot Delete
                </button>
            @endif
        </div>
    </div>

    {{-- Delete Modal --}}
    @if($canDelete)
    <div id="deleteModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="rounded-3xl border border-rose-500/30 bg-slate-900 p-6 w-full max-w-md shadow-2xl">
            <h3 class="text-xl font-semibold text-white mb-4">Delete Customer</h3>
            <p class="text-slate-300 mb-6">
                Are you sure you want to delete <strong class="text-white">{{ $customer->full_name }}</strong>? 
                This action cannot be undone and will permanently remove all customer data.
            </p>
            <form method="POST" action="{{ route('admin.customers.destroy', $customer) }}">
                @csrf
                @method('DELETE')
                <div class="flex gap-3">
                    <button type="button" onclick="closeDeleteModal()" class="flex-1 rounded-2xl border border-white/10 px-4 py-2 text-sm text-white hover:bg-white/10 transition">
                        Cancel
                    </button>
                    <button type="submit" class="flex-1 rounded-2xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-rose-500/30 hover:bg-rose-700 transition">
                        Delete Customer
                    </button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        function showDeleteModal() {
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }

        // Close modal on outside click
        document.getElementById('deleteModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });
    </script>
    @endpush
    @endif
@endsection
