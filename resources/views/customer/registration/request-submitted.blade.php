@extends('layouts.auth')

@section('title', 'Registration Request Submitted | ' . config('app.system_name'))
@section('heading', 'Thank you!')
@section('subheading', 'Your registration request has been received.')

@section('content')
    <div class="space-y-5 text-center">
        @if(session('status'))
            <p class="text-base font-medium text-emerald-700">
                {{ session('status') }}
            </p>
        @else
            <p class="text-base text-slate-700">
                Your request has been submitted successfully. Our team will review your details and contact you with next steps.
            </p>
        @endif
        @if(session('reference'))
            <div class="inline-flex flex-col items-center gap-1 rounded-2xl bg-emerald-50 px-4 py-3 border border-emerald-200">
                <p class="text-xs text-emerald-800 font-medium uppercase tracking-[0.18em]">Registration Request ID</p>
                <p class="text-lg font-semibold text-emerald-900">{{ session('reference') }}</p>
                <p class="text-xs text-emerald-700 max-w-md">
                    Please keep this ID safely. You can quote it when following up with support about your registration.
                </p>
            </div>
        @endif
        <p class="text-sm text-slate-500 max-w-xl mx-auto">
            Our team will review your details and may contact you for more information. Once approved, you will receive instructions on how to activate your account and set your PIN.
        </p>

        <div class="pt-4">
            <a href="{{ route('customer.login') }}" class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-blue-500 to-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-blue-500/40 transition hover:scale-[1.01] hover:shadow-xl hover:shadow-blue-500/50">
                Back to login
            </a>
        </div>
    </div>
@endsection


