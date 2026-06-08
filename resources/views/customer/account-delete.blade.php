@extends('layouts.auth')

@section('title', 'Delete My Account | ' . config('app.system_name'))

@section('heading')
    {{ $isLoggedIn ? 'Confirm account deletion' : 'Delete my account' }}
@endsection

@section('subheading')
    @if($isLoggedIn)
        Review your account details below, then confirm to permanently delete your account.
    @else
        Log in with your phone number and PIN. After logging in you will see your account details and can confirm deletion.
    @endif
@endsection

@section('content')
    @if($isLoggedIn)
        {{-- Step 2: Show account info and confirm deletion --}}
        <div class="space-y-4 text-left">
            <div class="rounded-xl bg-slate-50 border border-slate-200 p-4 text-sm text-slate-700">
                <p class="font-semibold text-slate-900 mb-2">Account information</p>
                <p><span class="text-slate-500">Name:</span> {{ $customer->first_name }} {{ $customer->last_name }}</p>
                <p><span class="text-slate-500">Phone:</span> {{ $customer->phone ?? '—' }}</p>
                <p><span class="text-slate-500">Email:</span> {{ $customer->email ?? '—' }}</p>
            </div>

            <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 text-sm text-amber-800">
                <p class="font-semibold">This action is permanent and cannot be undone.</p>
                <p class="mt-2">Your account will be closed and your personal data (name, email, phone, address, etc.) will be removed. Historical records will be kept for legal and operational purposes but will no longer be linked to your identity. You will not be able to log in again with this account.</p>
            </div>

            @if(session('error'))
                <div class="rounded-xl bg-red-50 border border-red-200 p-3 text-sm text-red-700">
                    {{ session('error') }}
                </div>
            @endif

            <form method="POST" action="{{ route('customer.account.delete.store') }}" class="space-y-4">
                @csrf

                <div class="space-y-2">
                    <label class="flex items-start gap-3 text-sm text-slate-700 cursor-pointer">
                        <input
                            type="checkbox"
                            name="confirm_checkbox"
                            value="1"
                            required
                            class="mt-1 rounded border-slate-300 text-red-600 focus:ring-red-500"
                        >
                        <span>I understand that my account will be permanently closed and my personal data removed. I want to delete my account.</span>
                    </label>
                    @error('confirm_checkbox')
                        <p class="text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="space-y-1.5">
                    <label class="text-sm font-medium text-slate-800" for="confirm_phrase">
                        Type <strong>DELETE MY ACCOUNT</strong> to confirm:
                    </label>
                    <input
                        id="confirm_phrase"
                        name="confirm_phrase"
                        type="text"
                        value="{{ old('confirm_phrase') }}"
                        placeholder="DELETE MY ACCOUNT"
                        autocomplete="off"
                        class="w-full rounded-xl bg-white border border-slate-300 text-slate-900 placeholder:text-slate-400 focus:border-red-500 focus:ring-red-500/25 focus:outline-none px-3.5 py-2.5 text-sm"
                    >
                    @error('confirm_phrase')
                        <p class="text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex flex-col sm:flex-row gap-3 pt-2">
                    <button
                        type="submit"
                        class="w-full inline-flex justify-center items-center gap-2 rounded-xl bg-red-600 hover:bg-red-700 px-4 py-2.5 text-sm font-semibold text-white shadow transition focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                    >
                        Permanently delete my account
                    </button>
                    <form method="POST" action="{{ route('customer.account.delete.logout') }}" class="w-full sm:w-auto">
                        @csrf
                        <button type="submit" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 transition">
                            Cancel and log out
                        </button>
                    </form>
                </div>
            </form>
        </div>
    @else
        {{-- Step 1: Login form (clear that this is the login step) --}}
        <form method="POST" action="{{ route('customer.account.delete.login') }}" class="space-y-4">
            @csrf

            @if(session('error'))
                <div class="rounded-xl bg-red-50 border border-red-200 p-3 text-sm text-red-700">
                    {{ session('error') }}
                </div>
            @endif

            <p class="text-sm text-slate-600">Enter the same phone number and PIN you use to sign in to the app.</p>

            <div class="space-y-1.5">
                <label class="text-sm font-medium text-slate-800" for="phone">Phone number</label>
                <input type="text" name="phone" value="{{ old('phone') }}" maxlength="12" inputmode="numeric" pattern="260[0-9]{9}" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40 zambian-phone-input" placeholder="260978232334" required>
                @error('phone')
                    <p class="text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="space-y-1.5">
                <label class="text-sm font-medium text-slate-800" for="pin">PIN</label>
                <input
                    id="pin"
                    name="pin"
                    type="password"
                    inputmode="numeric"
                    pattern="[0-9]{4}"
                    maxlength="4"
                    required
                    class="w-full rounded-xl bg-white border border-slate-300 text-slate-900 placeholder:text-slate-400 focus:border-blue-500 focus:ring-blue-500/25 focus:outline-none px-3.5 py-2.5 text-center text-xl tracking-widest font-mono"
                    placeholder="••••"
                >
                @error('pin')
                    <p class="text-xs text-red-600">{{ $message }}</p>
                @enderror
                <p class="text-xs text-slate-500">Your 4-digit PIN</p>
            </div>

            <button
                type="submit"
                class="w-full inline-flex justify-center items-center gap-2 rounded-xl bg-gradient-to-r from-blue-500 to-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow transition hover:from-blue-600 hover:to-blue-700"
            >
                Log in to continue
            </button>
        </form>
    @endif
@endsection
