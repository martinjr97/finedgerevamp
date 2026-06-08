@extends('layouts.auth')

@section('title', 'Verify OTP | ' . config('app.system_name'))
@section('heading', 'Verify OTP')
@section('subheading', 'Enter the 6-digit code sent to your phone number')

@section('content')
    <form method="POST" action="{{ route('customer.password.verify-otp.store') }}" class="space-y-5">
        @csrf

        <input type="text" name="phone" value="{{ old('phone', $phone) }}" maxlength="12" inputmode="numeric" pattern="260[0-9]{9}" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40 zambian-phone-input" placeholder="260978232334">
        <input type="hidden" name="national_id" value="{{ old('national_id', $national_id) }}">

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
                class="w-full rounded-2xl bg-white border border-slate-300 text-slate-900 placeholder:text-slate-400 focus:border-emerald-500 focus:ring-emerald-500/20 focus:outline-none px-4 py-3 text-center text-2xl tracking-widest font-mono transition"
                placeholder="000000"
            >
            @error('otp')
                <p class="text-sm text-rose-600 font-medium">{{ $message }}</p>
            @enderror
            <p class="text-xs text-slate-500 text-center">Enter the 6-digit code sent to your registered phone number</p>
        </div>

        <button
            type="submit"
            class="w-full inline-flex justify-center items-center gap-2 rounded-2xl bg-gradient-to-r from-emerald-500 to-lime-500 px-4 py-3 font-semibold text-white shadow-lg shadow-emerald-500/40 transition hover:scale-[1.01] hover:shadow-xl hover:shadow-emerald-500/50"
        >
            Verify OTP
        </button>

        <div class="text-center space-y-2">
            <form method="POST" action="{{ route('customer.password.email') }}" class="inline">
                @csrf
                <input type="text" name="phone" value="{{ $phone }}" maxlength="12" inputmode="numeric" pattern="260[0-9]{9}" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40 zambian-phone-input" placeholder="260978232334">
                <input type="hidden" name="national_id" value="{{ $national_id }}">
                <button type="submit" class="text-sm text-emerald-600 hover:text-emerald-700 font-medium">
                    Resend OTP
                </button>
            </form>
            <div>
                <a href="{{ route('customer.password.forgot') }}" class="text-sm text-slate-600 hover:text-slate-700">
                    ← Use different details
                </a>
            </div>
        </div>
    </form>

    @push('scripts')
    <script>
        // Restrict OTP to digits only
        const otpInput = document.getElementById('otp');
        otpInput.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '').slice(0, 6);
        });
        otpInput.addEventListener('keypress', function(e) {
            if (!/[0-9]/.test(e.key)) {
                e.preventDefault();
            }
        });
    </script>
    @endpush
@endsection

