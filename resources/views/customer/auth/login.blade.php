@extends('layouts.auth')

@section('title', 'Customer Login | ' . config('app.system_name'))
@php
    $hour = (int) date('H');
    $greeting = $hour < 12 ? 'Good morning!' : ($hour < 17 ? 'Good afternoon!' : 'Good evening!');
@endphp
@section('heading', $greeting)
@section('subheading', 'Track your loans, payments & support tickets')

@section('content')
    <form method="POST" action="{{ route('customer.login.store') }}" class="space-y-4" autocomplete="off">
        @csrf

        @include('partials.zambian-phone-field', [
            'name' => 'phone',
            'label' => 'Mobile Number',
            'required' => true,
            'inputClass' => 'w-full rounded-xl bg-white border border-slate-300 text-slate-900 placeholder:text-slate-400 focus:border-blue-500 focus:ring-blue-500/25 focus:outline-none px-3.5 py-2.5 transition',
            'labelClass' => 'text-sm font-medium text-slate-800',
            'errorClass' => 'mt-1 text-xs text-rose-600 font-medium',
            'helpClass' => 'text-xs text-slate-600',
        ])

        <div class="space-y-1.5">
            <label class="text-sm font-medium text-slate-800" for="pin">PIN</label>
            <div class="relative">
                <input
                    id="pin"
                    name="pin"
                    type="password"
                    inputmode="numeric"
                    pattern="[0-9]{4}"
                    maxlength="4"
                    required
                    class="w-full rounded-xl bg-white border border-slate-300 text-slate-900 placeholder:text-slate-400 focus:border-blue-500 focus:ring-blue-500/25 focus:outline-none px-3.5 py-2.5 pr-12 text-center text-xl sm:text-2xl tracking-widest font-mono transition"
                    placeholder="••••"
                >
                <button type="button" onclick="togglePinVisibility('pin')" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-700 focus:outline-none transition" aria-label="Show PIN">
                    <svg id="pin-eye" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    <svg id="pin-eye-off" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.29 3.29m0 0A9.97 9.97 0 015.12 5.12m3.17 1.17L3 3m0 0l18 18m-3.29-3.29a9.97 9.97 0 01-1.563 3.029M12 12l-4.243-4.243"/>
                    </svg>
                </button>
            </div>
            @error('pin')
                <p class="text-xs text-rose-600 font-medium">{{ $message }}</p>
            @enderror
            <p class="text-xs text-slate-600">Enter your 4-digit PIN</p>
        </div>

        <label class="inline-flex items-center space-x-2 text-sm text-slate-800">
            <input type="checkbox" name="remember" class="rounded border-slate-300 bg-white text-blue-600 focus:ring-blue-500/30 focus:ring-2">
            <span>Remember me</span>
        </label>

        <button
            type="submit"
            class="w-full inline-flex justify-center items-center gap-2 rounded-xl bg-gradient-to-r from-blue-500 to-blue-600 px-4 py-2.5 text-sm sm:text-base font-semibold text-white shadow-lg shadow-blue-500/40 transition hover:scale-[1.01] hover:shadow-xl hover:shadow-blue-500/50"
        >
            Sign In Securely
        </button>

        <div class="text-center pt-1 space-y-2">
            <a href="{{ route('customer.password.forgot') }}" class="text-sm text-blue-600 hover:text-blue-700 font-medium">
                Forgot PIN?
            </a>
            @if(!empty($allowCustomerRegistration) && $allowCustomerRegistration)
                <div>
                    <a href="{{ route('customer.register-request.create') }}" class="inline-flex items-center justify-center gap-2 rounded-xl border border-emerald-500/70 bg-emerald-50 px-4 py-2 text-xs sm:text-sm font-semibold text-emerald-800 shadow-sm hover:bg-emerald-100 hover:border-emerald-600 hover:shadow-md transition">
                        <span class="inline-flex h-4 w-4 sm:h-5 sm:w-5 items-center justify-center rounded-full bg-emerald-500 text-white text-xs font-bold">+</span>
                        <span>New here? Request to register</span>
                    </a>
                </div>
            @endif
        </div>
    </form>

    @push('scripts')
    <script>
        function togglePinVisibility(inputId) {
            const input = document.getElementById(inputId);
            const eye = document.getElementById(inputId + '-eye');
            const eyeOff = document.getElementById(inputId + '-eye-off');

            if (!input) {
                return;
            }

            if (input.type === 'password') {
                input.type = 'text';
                eye?.classList.add('hidden');
                eyeOff?.classList.remove('hidden');
            } else {
                input.type = 'password';
                eye?.classList.remove('hidden');
                eyeOff?.classList.add('hidden');
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            const pinInput = document.getElementById('pin');
            if (!pinInput) {
                return;
            }

            pinInput.addEventListener('input', function(e) {
                e.target.value = e.target.value.replace(/\D/g, '').slice(0, 4);
            });
            pinInput.addEventListener('keypress', function(e) {
                if (!/[0-9]/.test(e.key)) {
                    e.preventDefault();
                }
            });
            pinInput.addEventListener('paste', function(e) {
                e.preventDefault();
                const paste = (e.clipboardData || window.clipboardData).getData('text');
                e.target.value = paste.replace(/\D/g, '').slice(0, 4);
            });

            const flyout = document.getElementById('registration-flyout');
            if (flyout) {
                setTimeout(() => {
                    flyout.classList.remove('opacity-0', 'translate-y-2');
                    flyout.classList.add('opacity-100', 'translate-y-0');
                }, 300);

                const closeBtn = flyout.querySelector('[data-close-registration-flyout]');
                if (closeBtn) {
                    closeBtn.addEventListener('click', () => {
                        flyout.classList.add('opacity-0', 'translate-y-2');
                        setTimeout(() => flyout.classList.add('hidden'), 300);
                    });
                }
            }
        });
    </script>
    @endpush
@endsection
