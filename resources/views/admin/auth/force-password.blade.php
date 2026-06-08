@extends('layouts.admin')

@section('title', 'Update Password | '.config('app.system_name'))

@section('content')
    <div class="max-w-2xl mx-auto space-y-6">
        
        <div class="space-y-2 text-left">
            <p class="text-xs uppercase tracking-[0.4em] text-cyan-300">Security</p>
            <h1 class="text-3xl font-bold">Set a New Password</h1>
            <p class="text-sm text-slate-400">For your security, please update your password before continuing.</p>
        </div>

        <form method="POST" action="{{ route('admin.password.change') }}" class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4 max-w-xl" id="password-form">
            @csrf
            <div>
                <label class="text-sm font-medium text-slate-300">New Password</label>
                <div class="relative mt-2">
                    <input type="password" id="password" name="password" value="{{ old('password') }}" required class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 pr-12 focus:border-cyan-400 focus:ring-cyan-400/40 @error('password') border-red-500/50 @enderror">
                    <button type="button" onclick="togglePassword('password', 'password-toggle')" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-200 transition-colors" id="password-toggle" aria-label="Toggle password visibility">
                        <svg id="password-eye" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        <svg id="password-eye-off" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.29 3.29m0 0A9.97 9.97 0 015.12 5.12m3.17 1.17L3 3m0 0l18 18m-3.29-3.29a9.97 9.97 0 01-1.563 3.029M12 12l-4.243-4.243"/>
                        </svg>
                    </button>
                </div>
                @error('password')
                    <p class="text-sm text-rose-300 mt-2">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm font-medium text-slate-300">Confirm Password</label>
                <div class="relative mt-2">
                    <input type="password" id="password_confirmation" name="password_confirmation" value="{{ old('password_confirmation') }}" required class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 pr-12 focus:border-cyan-400 focus:ring-cyan-400/40 @error('password_confirmation') border-red-500/50 @enderror">
                    <button type="button" onclick="togglePassword('password_confirmation', 'password-confirmation-toggle')" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-200 transition-colors" id="password-confirmation-toggle" aria-label="Toggle password visibility">
                        <svg id="password-confirmation-eye" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        <svg id="password-confirmation-eye-off" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.29 3.29m0 0A9.97 9.97 0 015.12 5.12m3.17 1.17L3 3m0 0l18 18m-3.29-3.29a9.97 9.97 0 01-1.563 3.029M12 12l-4.243-4.243"/>
                        </svg>
                    </button>
                </div>
                @error('password_confirmation')
                    <p class="text-sm text-rose-300 mt-2">{{ $message }}</p>
                @enderror
            </div>
            <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-cyan-400 to-blue-500 px-4 py-3 font-semibold text-slate-900 shadow-lg shadow-cyan-500/30">
                Update Password
            </button>
        </form>

        <script>
            function togglePassword(inputId, toggleId) {
                const input = document.getElementById(inputId);
                const eye = document.getElementById(inputId + '-eye');
                const eyeOff = document.getElementById(inputId + '-eye-off');
                
                if (input.type === 'password') {
                    input.type = 'text';
                    if (eye) eye.classList.add('hidden');
                    if (eyeOff) eyeOff.classList.remove('hidden');
                } else {
                    input.type = 'password';
                    if (eye) eye.classList.remove('hidden');
                    if (eyeOff) eyeOff.classList.add('hidden');
                }
            }
        </script>
    </div>
@endsection

