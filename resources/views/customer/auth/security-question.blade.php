@extends('layouts.auth')

@section('title', 'Security Question | ' . config('app.system_name'))
@section('heading', 'Security Question')
@section('subheading', 'Answer your security question to continue')

@section('content')
    <form method="POST" action="{{ route('customer.password.security-question.store') }}" class="space-y-5">
        @csrf

        <input type="text" name="phone" value="{{ $phone }}" maxlength="12" inputmode="numeric" pattern="260[0-9]{9}" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40 zambian-phone-input" placeholder="260978232334">
        <input type="hidden" name="national_id" value="{{ $national_id }}">

        <div class="space-y-2">
            <label class="text-sm font-medium text-slate-700">Security Question</label>
            <div class="rounded-2xl bg-emerald-50 border border-emerald-200 p-4">
                <p class="text-sm font-medium text-emerald-900">{{ $securityQuestion->question }}</p>
            </div>
        </div>

        <div class="space-y-2">
            <label class="text-sm font-medium text-slate-700" for="security_answer">Your Answer</label>
            <input
                id="security_answer"
                name="security_answer"
                type="text"
                value="{{ old('security_answer') }}"
                required
                autofocus
                class="w-full rounded-2xl bg-white border border-slate-300 text-slate-900 placeholder:text-slate-400 focus:border-emerald-500 focus:ring-emerald-500/20 focus:outline-none px-4 py-3 transition"
                placeholder="Enter your answer"
            >
            @error('security_answer')
                <p class="text-sm text-rose-600 font-medium">{{ $message }}</p>
            @enderror
        </div>

        <button
            type="submit"
            class="w-full inline-flex justify-center items-center gap-2 rounded-2xl bg-gradient-to-r from-emerald-500 to-lime-500 px-4 py-3 font-semibold text-white shadow-lg shadow-emerald-500/40 transition hover:scale-[1.01] hover:shadow-xl hover:shadow-emerald-500/50"
        >
            Verify Answer
        </button>

        <div class="text-center">
            <a href="{{ route('customer.password.forgot') }}" class="text-sm text-slate-600 hover:text-slate-700">
                ← Start Over
            </a>
        </div>
    </form>
@endsection

