@extends('layouts.auth')

@section('title', 'Change PIN | ' . config('app.system_name'))
@section('heading', $mustChangePin ? 'Change Your PIN' : 'Update PIN')
@section('subheading', $mustChangePin ? 'You must change your PIN before continuing' : 'Update your account PIN')

@section('content')
    @if($mustChangePin)
        <div class="rounded-2xl border border-amber-500/30 bg-amber-500/10 p-4 mb-5">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-amber-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <div>
                    <p class="text-sm font-semibold text-amber-300">PIN Change Required</p>
                    <p class="text-xs text-amber-200/80 mt-1">For security reasons, you must change your PIN before accessing your account.</p>
                </div>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('customer.pin.update') }}" class="space-y-5">
        @csrf
        @method('PUT')

        @if(!$mustChangePin)
            <div class="space-y-2">
                <label class="text-sm font-medium text-slate-200" for="current_pin">Current PIN</label>
                <div class="relative">
                    <input
                        id="current_pin"
                        name="current_pin"
                        type="password"
                        inputmode="numeric"
                        pattern="[0-9]{4}"
                        maxlength="4"
                        required
                        autofocus
                        class="w-full rounded-2xl bg-white/10 border border-white/10 text-white placeholder:text-slate-500 focus:border-emerald-400 focus:ring-emerald-400/40 px-4 py-3 pr-12 text-center text-2xl tracking-widest font-mono"
                        placeholder="••••"
                    >
                    <button type="button" onclick="togglePinVisibility('current_pin')" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-200 transition-colors" aria-label="Show current PIN">
                        <svg id="current_pin-eye" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        <svg id="current_pin-eye-off" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.29 3.29m0 0A9.97 9.97 0 015.12 5.12m3.17 1.17L3 3m0 0l18 18m-3.29-3.29a9.97 9.97 0 01-1.563 3.029M12 12l-4.243-4.243"/>
                        </svg>
                    </button>
                </div>
                @error('current_pin')
                    <p class="text-sm text-rose-300">{{ $message }}</p>
                @enderror
            </div>
        @endif

        <div class="space-y-2">
            <label class="text-sm font-medium text-slate-200" for="new_pin">New PIN</label>
            <div class="relative">
                <input
                    id="new_pin"
                    name="new_pin"
                    type="password"
                    inputmode="numeric"
                    pattern="[0-9]{4}"
                    maxlength="4"
                    required
                    {{ $mustChangePin ? 'autofocus' : '' }}
                    class="w-full rounded-2xl bg-white/10 border border-white/10 text-white placeholder:text-slate-500 focus:border-emerald-400 focus:ring-emerald-400/40 px-4 py-3 pr-12 text-center text-2xl tracking-widest font-mono"
                    placeholder="••••"
                >
                <button type="button" onclick="togglePinVisibility('new_pin')" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-200 transition-colors" aria-label="Show new PIN">
                    <svg id="new_pin-eye" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    <svg id="new_pin-eye-off" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.29 3.29m0 0A9.97 9.97 0 015.12 5.12m3.17 1.17L3 3m0 0l18 18m-3.29-3.29a9.97 9.97 0 01-1.563 3.029M12 12l-4.243-4.243"/>
                    </svg>
                </button>
            </div>
            @error('new_pin')
                <p class="text-sm text-rose-300">{{ $message }}</p>
            @enderror
            <p class="text-xs text-slate-400">Enter a 4-digit PIN</p>
        </div>

        <div class="space-y-2">
            <label class="text-sm font-medium text-slate-200" for="new_pin_confirmation">Confirm New PIN</label>
            <div class="relative">
                <input
                    id="new_pin_confirmation"
                    name="new_pin_confirmation"
                    type="password"
                    inputmode="numeric"
                    pattern="[0-9]{4}"
                    maxlength="4"
                    required
                    class="w-full rounded-2xl bg-white/10 border border-white/10 text-white placeholder:text-slate-500 focus:border-emerald-400 focus:ring-emerald-400/40 px-4 py-3 pr-12 text-center text-2xl tracking-widest font-mono"
                    placeholder="••••"
                >
                <button type="button" onclick="togglePinVisibility('new_pin_confirmation')" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-200 transition-colors" aria-label="Show PIN confirmation">
                    <svg id="new_pin_confirmation-eye" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    <svg id="new_pin_confirmation-eye-off" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.29 3.29m0 0A9.97 9.97 0 015.12 5.12m3.17 1.17L3 3m0 0l18 18m-3.29-3.29a9.97 9.97 0 01-1.563 3.029M12 12l-4.243-4.243"/>
                    </svg>
                </button>
            </div>
            @error('new_pin_confirmation')
                <p class="text-sm text-rose-300">{{ $message }}</p>
            @enderror
        </div>

        <button
            type="submit"
            class="w-full inline-flex justify-center items-center gap-2 rounded-2xl bg-gradient-to-r from-emerald-400 to-lime-400 px-4 py-3 font-semibold text-slate-900 shadow-lg shadow-emerald-500/30 transition hover:scale-[1.01]"
        >
            {{ $mustChangePin ? 'Change PIN & Continue' : 'Update PIN' }}
        </button>

        @if(!$mustChangePin)
            <a href="{{ route('customer.dashboard') }}" class="block text-center text-sm text-slate-400 hover:text-slate-300 transition">
                Cancel
            </a>
        @endif
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

        // Restrict PIN inputs to numbers only
        ['current_pin', 'new_pin', 'new_pin_confirmation'].forEach(id => {
            const input = document.getElementById(id);
            if (input) {
                input.addEventListener('input', function(e) {
                    e.target.value = e.target.value.replace(/\D/g, '').slice(0, 4);
                });
            }
        });
    </script>
    @endpush
@endsection

