@extends('layouts.auth')

@php
    $backgroundImage = '/img/admin.jpg';
    $authOverlayClass = 'bg-transparent';
    $authPageClass = 'auth-page admin-auth-page';
@endphp

@section('title', 'Verify OTP | ' . config('app.system_name'))
@section('heading', 'Verify OTP')
@section('subheading', 'Enter the 6-digit code sent to your phone number')

@section('content')
    <form method="POST" action="{{ route('admin.password.verify-otp.store') }}" class="space-y-5">
        @csrf

        <input type="hidden" name="email" value="{{ $email }}">

        <div class="space-y-2">
            <label class="text-sm font-medium text-slate-700" for="otp">OTP Code</label>
            <input
                id="otp"
                name="otp"
                type="text"
                inputmode="numeric"
                pattern="[0-9]{6}"
                maxlength="6"
                required
                autofocus
                class="w-full rounded-2xl bg-white border border-slate-300 text-slate-900 placeholder:text-slate-400 focus:border-cyan-500 focus:ring-cyan-500/20 focus:outline-none px-4 py-3 text-center text-2xl tracking-widest font-mono transition"
                placeholder="000000"
            >
            @error('otp')
                <p class="text-sm text-rose-600 font-medium">{{ $message }}</p>
            @enderror
            <p class="text-xs text-slate-500 text-center">Enter the 6-digit code sent to your registered phone number</p>
        </div>

        <button
            type="submit"
            class="w-full inline-flex justify-center items-center gap-2 rounded-2xl bg-gradient-to-r from-cyan-500 to-blue-600 px-4 py-3 font-semibold text-white shadow-lg shadow-cyan-500/40 transition hover:scale-[1.01] hover:shadow-xl hover:shadow-cyan-500/50"
        >
            Verify OTP
        </button>

        <div class="text-center space-y-2">
            <form method="POST" action="{{ route('admin.password.email') }}" class="inline">
                @csrf
                <input type="hidden" name="email" value="{{ $email }}">
                <button type="submit" class="text-sm text-cyan-600 hover:text-cyan-700 font-medium">
                    Resend OTP
                </button>
            </form>
            <div>
                <a href="{{ route('admin.password.forgot') }}" class="text-sm text-slate-600 hover:text-slate-700">
                    ← Use different email
                </a>
            </div>
        </div>
    </form>
@endsection

