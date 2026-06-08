	@php
	    $customer = auth('customer')->user();
	    $customer->loadMissing(['loanProduct', 'customerGroup.relationshipManager', 'paymentDetail']);
	    $isGroupLoanCustomer = (string) ($customer->loanProduct?->category ?? '') === 'group_loans';
	    $relationshipManager = $customer->customerGroup?->relationshipManager;
	    $paymentDetail = $customer->paymentDetail;
	    $paymentEditsLocked = $customer->loans()->whereIn('status', ['approved', 'active'])->exists();
	@endphp

@extends('layouts.customer')

@section('title', 'My Profile')

@section('content')
    <div class="max-w-2xl mx-auto">
        <div class="bg-gray-50 dark:bg-gray-800 rounded-2xl border-2 border-gray-200 dark:border-gray-700 p-6 shadow-lg">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">My Profile</h2>
            
	            <div class="bg-white dark:bg-gray-700 rounded-xl border border-gray-200 dark:border-gray-600 p-6 space-y-4">
                <div class="flex items-center gap-4 pb-4 border-b-2 border-gray-200 dark:border-gray-600">
                    <div class="h-16 w-16 rounded-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white font-bold text-2xl shadow-md">
                        {{ strtoupper(substr($customer->first_name, 0, 1)) }}
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $customer->first_name }} {{ $customer->last_name }}</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $customer->email }}</p>
                    </div>
                </div>

	                <div class="space-y-3">
                    <div class="flex justify-between items-center py-3 border-b border-gray-200 dark:border-gray-600">
                        <span class="text-gray-600 dark:text-gray-400 text-sm font-medium">Phone</span>
                        <span class="text-gray-900 dark:text-white font-semibold">{{ $customer->phone ?? 'Not provided' }}</span>
                    </div>
                    @if($customer->company)
                        <div class="flex justify-between items-center py-3 border-b border-gray-200 dark:border-gray-600">
                            <span class="text-gray-600 dark:text-gray-400 text-sm font-medium">Company</span>
                            <span class="text-gray-900 dark:text-white font-semibold">{{ $customer->company->name }}</span>
                        </div>
                    @endif
                    <div class="flex justify-between items-center py-3 border-b border-gray-200 dark:border-gray-600">
                        <span class="text-gray-600 dark:text-gray-400 text-sm font-medium">Status</span>
                        <span class="text-gray-900 dark:text-white font-semibold capitalize">{{ $customer->status ?? 'Pending' }}</span>
                    </div>
                    @if($isGroupLoanCustomer)
                        <div class="flex justify-between items-center py-3 border-b border-gray-200 dark:border-gray-600">
                            <span class="text-gray-600 dark:text-gray-400 text-sm font-medium">Relationship Manager</span>
                            <span class="text-gray-900 dark:text-white font-semibold text-right">
                                {{ $relationshipManager?->full_name ?? 'Not assigned' }}
                                @if($relationshipManager?->phone)
                                    <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $relationshipManager->phone }}</span>
                                @endif
                            </span>
                        </div>
                    @endif
	                    <div class="flex justify-between items-center py-3">
	                        <span class="text-gray-600 dark:text-gray-400 text-sm font-medium">KYC Status</span>
	                        <span class="text-gray-900 dark:text-white font-semibold capitalize">{{ $customer->kyc_status ?? 'Unverified' }}</span>
	                    </div>
	                </div>

	                <div class="pt-4 border-t-2 border-gray-200 dark:border-gray-600 space-y-3">
	                    <div class="flex items-center justify-between">
	                        <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Default Payment</h4>
	                        <a href="{{ route('customer.payment-details.edit') }}" class="inline-flex items-center gap-2 rounded-xl bg-green-600 hover:bg-green-700 text-white font-semibold px-4 py-2 text-sm shadow-md transition">
	                            Manage Payment Details
	                        </a>
	                    </div>
	                    <div class="text-sm text-gray-700 dark:text-gray-200">
	                        @if(!$paymentDetail)
	                            <p>No payment details on file.</p>
	                        @elseif($paymentDetail->method_type === 'wallet')
	                            <p><span class="font-semibold">Wallet:</span> {{ $paymentDetail->wallet_provider ?? '—' }} — {{ $paymentDetail->wallet_number ?? '—' }}</p>
	                        @else
	                            <p><span class="font-semibold">Bank:</span> {{ $paymentDetail->bank_name ?? '—' }} — {{ $paymentDetail->bank_branch ?? '—' }}</p>
	                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Account: {{ $paymentDetail->account_name ?? '—' }} ({{ $paymentDetail->account_number ?? '—' }})</p>
	                        @endif
	                    </div>
	                    @if($paymentEditsLocked)
	                        <p class="text-xs text-amber-700 dark:text-amber-300">
	                            Editing is disabled because you have an approved or active loan. Contact support to update payment details.
	                        </p>
	                    @else
	                        <p class="text-xs text-gray-500 dark:text-gray-400">
	                            Tip: Keep your bank or wallet details up to date for disbursements and repayments.
	                        </p>
	                    @endif
	                </div>
	            </div>
	        </div>
	    </div>
	@endsection
