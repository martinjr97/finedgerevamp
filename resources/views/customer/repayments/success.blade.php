@extends('layouts.customer')

@section('title', 'Repayment Status')

@section('content')
    @php
        $state = $context['state'] ?? 'submitted';
        $isPrompt = $state === 'provider_prompt';
        $isPending = $state === 'manual_pending';
    @endphp

    <div class="space-y-6 max-w-2xl mx-auto">
        <div class="rounded-2xl p-6 sm:p-8 shadow-xl border-2 text-center {{ $isPrompt ? 'bg-gradient-to-r from-blue-600 via-indigo-600 to-slate-700 border-blue-400' : 'bg-gradient-to-r from-amber-500 via-orange-500 to-yellow-600 border-amber-400' }}">
            <div class="mb-4 flex justify-center">
                <div class="rounded-full bg-white p-4 shadow-lg">
                    @if($isPrompt)
                        <svg class="w-14 h-14 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                        </svg>
                    @else
                        <svg class="w-14 h-14 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    @endif
                </div>
            </div>
            <h1 class="text-3xl font-bold mb-2 text-white">{{ $context['title'] ?? 'Repayment Submitted' }}</h1>
            <p class="text-white/90">{{ $context['subtitle'] ?? '' }}</p>
            @if(!empty($context['repayment_number']))
                <p class="mt-3 text-sm font-semibold text-white/90">Reference: {{ $context['repayment_number'] }}</p>
            @endif
        </div>

        <div class="rounded-2xl border border-slate-300 bg-white p-6 shadow">
            <p class="text-slate-700 text-base">{{ $context['detail'] ?? '' }}</p>

            @if($isPrompt)
                <div class="mt-4 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
                    If you do not receive a prompt, confirm your network and try again from the repayments screen.
                </div>
            @endif

            @if($isPending)
                <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    Your balance will update only after approval by an authorized repayments officer.
                </div>
            @endif
        </div>

        <div class="flex flex-col sm:flex-row gap-4">
            <a href="{{ route('customer.dashboard') }}"
               class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl bg-slate-900 text-white px-6 py-3 font-semibold hover:bg-slate-800 transition">
                <span>Back to Dashboard</span>
            </a>
            <a href="{{ route('customer.notifications') }}"
               class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white text-slate-700 px-6 py-3 font-semibold hover:bg-slate-50 transition">
                <span>View Notifications</span>
            </a>
        </div>
    </div>
@endsection
