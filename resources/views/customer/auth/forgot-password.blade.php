@extends('layouts.auth')

@section('title', 'Forgot PIN | ' . config('app.system_name'))
@section('heading', 'Forgot PIN')
@section('subheading', 'Enter your phone number and National ID to receive an OTP')

@section('content')
    <form method="POST" action="{{ route('customer.password.email') }}" class="space-y-5">
        @csrf

        <div class="space-y-2">
            <label class="text-sm font-medium text-slate-700" for="phone">Mobile Number</label>
            <input type="text" name="phone" value="{{ old('phone') }}" maxlength="12" inputmode="numeric" pattern="260[0-9]{9}" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40 zambian-phone-input" placeholder="260978232334" required>
            @error('phone')
                <p class="text-sm text-rose-600 font-medium">{{ $message }}</p>
            @enderror
            <p class="text-xs text-slate-500">Enter your mobile number (digits only)</p>
        </div>

        <div class="space-y-2">
            <label class="text-sm font-medium text-slate-700" for="national_id">National ID</label>
            <input
                id="national_id"
                name="national_id"
                type="text"
                value="{{ old('national_id') }}"
                required
                class="w-full rounded-2xl bg-white border border-slate-300 text-slate-900 placeholder:text-slate-400 focus:border-emerald-500 focus:ring-emerald-500/20 focus:outline-none px-4 py-3 transition"
                placeholder="Enter your National ID"
            >
            @error('national_id')
                <p class="text-sm text-rose-600 font-medium">{{ $message }}</p>
            @enderror
        </div>

        <button
            type="submit"
            class="w-full inline-flex justify-center items-center gap-2 rounded-2xl bg-gradient-to-r from-emerald-500 to-lime-500 px-4 py-3 font-semibold text-white shadow-lg shadow-emerald-500/40 transition hover:scale-[1.01] hover:shadow-xl hover:shadow-emerald-500/50"
        >
            Send OTP
        </button>

        <div class="text-center">
            <a href="{{ route('customer.login') }}" class="text-sm text-emerald-600 hover:text-emerald-700 font-medium">
                ← Back to Login
            </a>
        </div>
    </form>

    @push('scripts')
    <script>
        // Restrict phone number to digits only
        const phoneInput = document.getElementById('phone');
        phoneInput.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '');
        });
        phoneInput.addEventListener('keypress', function(e) {
            if (!/[0-9]/.test(e.key)) {
                e.preventDefault();
            }
        });
    </script>
    @endpush
@endsection

