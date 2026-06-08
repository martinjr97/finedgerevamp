@extends('layouts.auth')

@php
    $backgroundImage = '/img/admin.jpg';
    $authOverlayClass = 'bg-transparent';
    $authPageClass = 'auth-page admin-auth-page';
@endphp

@section('title', 'Reset Password | ' . config('app.system_name'))
@section('heading', 'Reset Password')
@section('subheading', 'Set your new password. This link can only be used once.')

@section('content')
    <form method="POST" action="{{ route('admin.password.update') }}" class="space-y-5">
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">
        <input type="hidden" name="email" value="{{ $email }}">

        <div class="space-y-2">
            <label class="text-sm font-medium text-slate-700" for="password">New Password</label>
            <input
                id="password"
                name="password"
                type="password"
                required
                autofocus
                class="w-full rounded-2xl bg-white border border-slate-300 text-slate-900 placeholder:text-slate-400 focus:border-cyan-500 focus:ring-cyan-500/20 focus:outline-none px-4 py-3 transition"
                placeholder="••••••••"
            >
            @error('password')
                <p class="text-sm text-rose-600 font-medium">{{ $message }}</p>
            @enderror
            <p class="text-xs text-slate-500">Minimum 8 characters</p>
        </div>

        <div class="space-y-2">
            <label class="text-sm font-medium text-slate-700" for="password_confirmation">Confirm Password</label>
            <input
                id="password_confirmation"
                name="password_confirmation"
                type="password"
                required
                class="w-full rounded-2xl bg-white border border-slate-300 text-slate-900 placeholder:text-slate-400 focus:border-cyan-500 focus:ring-cyan-500/20 focus:outline-none px-4 py-3 transition"
                placeholder="••••••••"
            >
        </div>

        <button
            type="submit"
            class="w-full inline-flex justify-center items-center gap-2 rounded-2xl bg-gradient-to-r from-cyan-500 to-blue-600 px-4 py-3 font-semibold text-white shadow-lg shadow-cyan-500/40 transition hover:scale-[1.01] hover:shadow-xl hover:shadow-cyan-500/50"
        >
            Reset Password
        </button>

        <div class="text-center">
            <a href="{{ route('admin.login') }}" class="text-sm text-cyan-600 hover:text-cyan-700 font-medium">
                ← Back to Login
            </a>
        </div>
    </form>
@endsection

