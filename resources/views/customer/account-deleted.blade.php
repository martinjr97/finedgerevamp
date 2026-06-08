@extends('layouts.auth')

@section('title', 'Account deleted | ' . config('app.system_name'))
@section('heading', 'Account deleted')
@section('subheading', 'Your account has been permanently closed')

@section('content')
    <div class="space-y-4 text-center text-slate-700">
        @if(session('status'))
            <p class="font-medium text-slate-800">{{ session('status') }}</p>
        @else
            <p class="font-medium text-slate-800">Your account has been permanently deleted.</p>
        @endif
        <p class="text-sm">Your personal data has been removed. You can no longer log in with this account.</p>
        <a
            href="{{ route('customer.login') }}"
            class="block w-full text-center rounded-xl bg-gradient-to-r from-blue-500 to-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow transition hover:from-blue-600 hover:to-blue-700"
        >
            Back to login
        </a>
    </div>
@endsection
