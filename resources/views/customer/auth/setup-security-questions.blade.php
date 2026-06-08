@extends('layouts.auth')

@section('title', 'Setup Security Question | ' . config('app.system_name'))
@section('heading', 'Setup Security Question')
@section('subheading', 'Choose a security question for password recovery')

@section('content')
    <form method="POST" action="{{ route('customer.security-questions.store') }}" class="space-y-5">
        @csrf

        <div class="space-y-2">
            <label class="text-sm font-medium text-slate-700" for="security_question_id">Select a Security Question</label>
            <select
                id="security_question_id"
                name="security_question_id"
                required
                autofocus
                class="w-full rounded-2xl bg-white border border-slate-300 text-slate-900 focus:border-emerald-500 focus:ring-emerald-500/20 focus:outline-none px-4 py-3 transition"
            >
                <option value="">-- Select a question --</option>
                @foreach($securityQuestions as $question)
                    <option value="{{ $question->id }}" {{ old('security_question_id') == $question->id ? 'selected' : '' }}>
                        {{ $question->question }}
                    </option>
                @endforeach
            </select>
            @error('security_question_id')
                <p class="text-sm text-rose-600 font-medium">{{ $message }}</p>
            @enderror
        </div>

        <div class="space-y-2">
            <label class="text-sm font-medium text-slate-700" for="security_answer">Your Answer</label>
            <input
                id="security_answer"
                name="security_answer"
                type="text"
                value="{{ old('security_answer') }}"
                required
                class="w-full rounded-2xl bg-white border border-slate-300 text-slate-900 placeholder:text-slate-400 focus:border-emerald-500 focus:ring-emerald-500/20 focus:outline-none px-4 py-3 transition"
                placeholder="Enter your answer"
            >
            @error('security_answer')
                <p class="text-sm text-rose-600 font-medium">{{ $message }}</p>
            @enderror
            <p class="text-xs text-slate-500">This answer will be used to verify your identity when resetting your PIN</p>
        </div>

        <button
            type="submit"
            class="w-full inline-flex justify-center items-center gap-2 rounded-2xl bg-gradient-to-r from-emerald-500 to-lime-500 px-4 py-3 font-semibold text-white shadow-lg shadow-emerald-500/40 transition hover:scale-[1.01] hover:shadow-xl hover:shadow-emerald-500/50"
        >
            Save Security Question
        </button>
    </form>
@endsection

