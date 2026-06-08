@extends('layouts.auth')

@section('title', 'Reset PIN | ' . config('app.system_name'))
@section('heading', 'Reset PIN')
@section('subheading', 'Set your new 4-digit PIN')

@section('content')
    <form method="POST" action="{{ route('customer.password.update') }}" class="space-y-5">
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">
        <input type="text" name="phone" value="{{ $phone }}" maxlength="12" inputmode="numeric" pattern="260[0-9]{9}" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40 zambian-phone-input" placeholder="260978232334">
        <input type="hidden" name="national_id" value="{{ $national_id }}">

        <div class="space-y-2">
            <label class="text-sm font-medium text-slate-700" for="pin">New PIN</label>
            <input
                id="pin"
                name="pin"
                type="password"
                inputmode="numeric"
                pattern="[0-9]{4}"
                maxlength="4"
                required
                autofocus
                class="w-full rounded-2xl bg-white border border-slate-300 text-slate-900 placeholder:text-slate-400 focus:border-emerald-500 focus:ring-emerald-500/20 focus:outline-none px-4 py-3 text-center text-2xl tracking-widest font-mono transition"
                placeholder="••••"
            >
            @error('pin')
                <p class="text-sm text-rose-600 font-medium">{{ $message }}</p>
            @enderror
            <p class="text-xs text-slate-500 text-center">Enter a 4-digit PIN</p>
        </div>

        <div class="space-y-2">
            <label class="text-sm font-medium text-slate-700" for="pin_confirmation">Confirm PIN</label>
            <input
                id="pin_confirmation"
                name="pin_confirmation"
                type="password"
                inputmode="numeric"
                pattern="[0-9]{4}"
                maxlength="4"
                required
                class="w-full rounded-2xl bg-white border border-slate-300 text-slate-900 placeholder:text-slate-400 focus:border-emerald-500 focus:ring-emerald-500/20 focus:outline-none px-4 py-3 text-center text-2xl tracking-widest font-mono transition"
                placeholder="••••"
            >
        </div>

        <button
            type="submit"
            class="w-full inline-flex justify-center items-center gap-2 rounded-2xl bg-gradient-to-r from-emerald-500 to-lime-500 px-4 py-3 font-semibold text-white shadow-lg shadow-emerald-500/40 transition hover:scale-[1.01] hover:shadow-xl hover:shadow-emerald-500/50"
        >
            Reset PIN
        </button>

        <div class="text-center">
            <a href="{{ route('customer.login') }}" class="text-sm text-emerald-600 hover:text-emerald-700 font-medium">
                ← Back to Login
            </a>
        </div>
    </form>

    @push('scripts')
    <script>
        // Restrict PIN to numbers only (4 digits)
        const pinInput = document.getElementById('pin');
        const pinConfirmInput = document.getElementById('pin_confirmation');
        
        [pinInput, pinConfirmInput].forEach(input => {
            input.addEventListener('input', function(e) {
                e.target.value = e.target.value.replace(/\D/g, '').slice(0, 4);
            });
            input.addEventListener('keypress', function(e) {
                if (!/[0-9]/.test(e.key)) {
                    e.preventDefault();
                }
            });
            input.addEventListener('paste', function(e) {
                e.preventDefault();
                const paste = (e.clipboardData || window.clipboardData).getData('text');
                e.target.value = paste.replace(/\D/g, '').slice(0, 4);
            });
        });
    </script>
    @endpush
@endsection

