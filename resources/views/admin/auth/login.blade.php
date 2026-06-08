@extends('layouts.auth')

@php
    $backgroundImage = '/img/admin.jpg';
    $brandColor = 'text-purple-600';
    $authOverlayClass = 'bg-transparent';
    $authCardClass = 'admin-auth-card bg-white/90 border-white/70 shadow-slate-900/30';
    $authHeadingClass = 'text-slate-900';
    $authSubheadingClass = 'text-slate-700';
    $authPageClass = 'auth-page admin-auth-page';
@endphp

@section('title', 'Admin Portal | ' . config('app.system_name'))
@section('heading', 'Admin Login')
@section('subheading', 'Authenticate to manage products, partners & teams')

@section('content')
    <form method="POST" action="{{ route('admin.login.store') }}" class="space-y-5">
        @csrf

        <div class="space-y-2">
            <label class="auth-label text-sm font-medium text-slate-700" for="email">Corporate Email</label>
            <input
                id="email"
                name="email"
                type="email"
                value="{{ old('email') }}"
                required
                autofocus
                class="auth-input w-full rounded-2xl bg-white/85 border border-slate-300 text-slate-900 placeholder:text-slate-500 focus:border-sky-500 focus:ring-sky-500/20 focus:outline-none px-4 py-3 transition"
                placeholder="example@mail.com"
            >
            @error('email')
                <p class="auth-error text-sm form-error-text">{{ $message }}</p>
            @enderror
        </div>

        <div class="space-y-2">
            <div class="flex items-center justify-between text-sm text-slate-700">
                <label for="password" class="auth-label font-medium">Password</label>
                <a href="{{ route('admin.password.forgot') }}" class="auth-link text-sky-700 hover:text-sky-800 font-medium">
                    Forgot password?
                </a>
            </div>
            <div class="relative">
                <input
                    id="password"
                    name="password"
                    type="password"
                    required
                    class="auth-input w-full rounded-2xl bg-white/85 border border-slate-300 text-slate-900 placeholder:text-slate-500 focus:border-sky-500 focus:ring-sky-500/20 focus:outline-none px-4 py-3 pr-12 transition"
                    placeholder="••••••••"
                >
                <button
                    type="button"
                    id="togglePassword"
                    class="auth-toggle absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-700 focus:outline-none transition"
                    aria-label="Toggle password visibility"
                >
                    <svg id="eyeIcon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    <svg id="eyeOffIcon" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.906 5.236m0 0L21 21"/>
                    </svg>
                </button>
            </div>
            @error('password')
                <p class="auth-error text-sm form-error-text">{{ $message }}</p>
            @enderror
        </div>

        <label class="auth-label inline-flex items-center space-x-2 text-sm text-slate-700">
            <input type="checkbox" name="remember" class="auth-check rounded border-slate-300 bg-white text-sky-600 focus:ring-sky-500/30 focus:ring-2">
            <span>Keep me logged in on this device</span>
        </label>

        <button
            type="submit"
            class="auth-submit-button w-full inline-flex justify-center items-center gap-2 rounded-2xl bg-gradient-to-r from-amber-500 via-orange-500 to-rose-500 px-4 py-3 font-semibold text-white shadow-lg shadow-orange-500/35 transition hover:scale-[1.01] hover:shadow-xl hover:shadow-orange-500/45"
        >
            Access Admin Dashboard
        </button>
    </form>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            const toggleButton = document.getElementById('togglePassword');
            const eyeIcon = document.getElementById('eyeIcon');
            const eyeOffIcon = document.getElementById('eyeOffIcon');

            if (toggleButton && passwordInput) {
                toggleButton.addEventListener('click', function() {
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);

                    // Toggle icons
                    if (type === 'text') {
                        eyeIcon.classList.add('hidden');
                        eyeOffIcon.classList.remove('hidden');
                    } else {
                        eyeIcon.classList.remove('hidden');
                        eyeOffIcon.classList.add('hidden');
                    }
                });
            }
        });
    </script>
    @endpush
@endsection
