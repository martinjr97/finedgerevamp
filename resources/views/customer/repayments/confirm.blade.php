@extends('layouts.customer')

@section('title', 'Confirm Repayment')

@section('content')
    <div class="space-y-6 max-w-2xl mx-auto">
        {{-- Header --}}
        <div class="bg-primary rounded-2xl p-6 shadow-xl border border-muted">
            <h1 class="text-3xl font-bold mb-2 text-white">Confirm Repayment</h1>
            <p class="text-slate-200">Review your repayment details before proceeding</p>
        </div>

        {{-- Repayment Summary --}}
        <div class="card rounded-2xl p-6 shadow-lg">
            <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-white">Repayment Details</h2>
            
            <div class="space-y-4">
                <div class="flex justify-between items-center py-2 border-b border-gray-200 dark:border-gray-700">
                    <span class="text-gray-600 dark:text-gray-400">Repayment Type</span>
                    <span class="font-semibold text-gray-900 dark:text-white capitalize">
                        @if($repaymentType == 'partial')
                            Partial Payment
                        @elseif($repaymentType == 'overdue')
                            Overdue Amount
                        @else
                            Full Payment
                        @endif
                    </span>
                </div>
                
                <div class="flex justify-between items-center py-2 border-b border-gray-200 dark:border-gray-700">
                    <span class="text-gray-600 dark:text-gray-400">Amount to Pay</span>
                    <span class="text-2xl font-bold text-primary">ZMW {{ number_format($repaymentAmount, 2) }}</span>
                </div>
                
                <div class="flex justify-between items-center py-2 border-b border-gray-200 dark:border-gray-700">
                    <span class="text-gray-600 dark:text-gray-400">Payment Channel</span>
                    <span class="font-semibold text-gray-900 dark:text-white">{{ $channel->name }}</span>
                </div>
                <div class="flex justify-between items-center py-2 border-b border-gray-200 dark:border-gray-700">
                    <span class="text-gray-600 dark:text-gray-400">Method</span>
                    <span class="font-semibold text-gray-900 dark:text-white">
                        @php
                            $repaymentTypeLabel = match ($channel->type) {
                                \App\Models\Channel::TYPE_BANK => 'Bank Transfer',
                                \App\Models\Channel::TYPE_CASH => 'Cash',
                                default => 'Mobile Money',
                            };
                        @endphp
                        {{ $repaymentTypeLabel }}
                    </span>
                </div>

                @if($phoneNumber && ($channel->type ?? 'mobile_wallet') !== \App\Models\Channel::TYPE_CASH)
                    <div class="flex justify-between items-center py-2 border-b border-gray-200 dark:border-gray-700">
                        <span class="text-gray-600 dark:text-gray-400">
                            {{ ($channel->type ?? '') === \App\Models\Channel::TYPE_BANK ? 'Contact phone' : 'Mobile money number' }}
                        </span>
                        <span class="font-semibold text-gray-900 dark:text-white">{{ $phoneNumber }}</span>
                    </div>
                @endif
                
                @if($selectedLoan)
                    <div class="flex justify-between items-center py-2 border-b border-gray-200 dark:border-gray-700">
                        <span class="text-gray-600 dark:text-gray-400">Loan Number</span>
                        <span class="font-semibold text-gray-900 dark:text-white">{{ $selectedLoan->loan_number }}</span>
                    </div>
                @endif
            </div>
        </div>

        {{-- Confirmation Form --}}
        <form action="{{ route('customer.repayments.process') }}" method="POST" class="space-y-6">
            @csrf
            
            <div class="bg-amber-50 dark:bg-amber-900/30 border-2 border-amber-300 dark:border-amber-600 rounded-2xl p-6 shadow-lg">
                <div class="flex items-start gap-3">
                    <svg class="w-6 h-6 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <div>
                        <p class="text-amber-800 dark:text-amber-200 font-semibold mb-1">Please confirm your repayment</p>
                        <p class="text-sm text-amber-700 dark:text-amber-300">By clicking "Confirm & Pay", you authorize the payment of ZMW {{ number_format($repaymentAmount, 2) }} via {{ $channel->name }}.</p>
                    </div>
                </div>
            </div>

            {{-- Action Buttons --}}
            <div class="flex items-center justify-between gap-4 pt-4">
                <a href="{{ route('customer.repayments.select-channel') }}" 
                   class="inline-flex items-center gap-2 rounded-xl border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 px-6 py-3 font-semibold hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Back
                </a>
                <button type="submit" 
                        class="inline-flex items-center gap-2 rounded-xl bg-primary hover:opacity-90 text-white px-6 py-3 font-bold shadow-xl border border-primary transition transform hover:scale-[1.02] hover:shadow-2xl">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>Confirm & Pay</span>
                </button>
            </div>
        </form>
    </div>
@endsection
