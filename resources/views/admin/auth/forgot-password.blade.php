@extends('layouts.auth')

@php
    $backgroundImage = '/img/admin.jpg';
    $authOverlayClass = 'bg-transparent';
    $authPageClass = 'auth-page admin-auth-page';
@endphp

@section('title', 'Forgot Password | ' . config('app.system_name'))
@section('heading', 'Forgot Password')
@section('subheading', 'Enter your email to receive an OTP on your phone')

@section('content')
    <form method="POST" action="{{ route('admin.password.email') }}" class="space-y-5">
        @csrf

        <div class="space-y-2">
            <label class="text-sm font-medium text-slate-700" for="email">Corporate Email</label>
            <input
                id="email"
                name="email"
                type="email"
                value="{{ old('email') }}"
                required
                autofocus
                class="w-full rounded-2xl bg-white border border-slate-300 text-slate-900 placeholder:text-slate-400 focus:border-cyan-500 focus:ring-cyan-500/20 focus:outline-none px-4 py-3 transition"
                placeholder="superadmin@example.com"
            >
            @error('email')
                <p class="text-sm text-rose-600 font-medium">{{ $message }}</p>
            @enderror
        </div>

        <button
            type="submit"
            class="w-full inline-flex justify-center items-center gap-2 rounded-2xl bg-gradient-to-r from-cyan-500 to-blue-600 px-4 py-3 font-semibold text-white shadow-lg shadow-cyan-500/40 transition hover:scale-[1.01] hover:shadow-xl hover:shadow-cyan-500/50"
        >
            Send Reset Code
        </button>

        <div class="text-center">
            <a href="{{ route('admin.login') }}" class="text-sm text-cyan-600 hover:text-cyan-700 font-medium">
                ← Back to Login
            </a>
        </div>
    </form>
@endsection

