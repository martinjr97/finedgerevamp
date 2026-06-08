@extends('layouts.auth')

@section('title', 'Support | ' . config('app.system_name'))
@section('heading', 'Support & Help')
@section('subheading', 'Share your question and our team will follow up with you')

@section('content')
    <div class="grid gap-6 md:grid-cols-2">
        {{-- Support Information --}}
        <div class="space-y-4 text-left">
            <h2 class="text-xl font-semibold text-slate-900">Contact Information</h2>
            <p class="text-sm sm:text-base text-slate-600 leading-relaxed">
                For questions about your loans, repayments, or access to the system, you can reach our support team
                using the contact details below or by submitting the support form.
            </p>

            <div class="space-y-3 text-sm sm:text-base">
                <div class="flex items-start gap-3">
                    <span class="mt-0.5 text-blue-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.129a11.042 11.042 0 005.516 5.516l1.129-2.257a1 1 0 011.21-.502l4.493 1.498A1 1 0 0121 18.72V22a2 2 0 01-2 2h-1C9.82 24 4 18.18 4 11V10a2 2 0 012-2h0"/>
                        </svg>
                    </span>
                    <div>
                        <p class="font-semibold text-slate-800">Phone</p>
                        <p class="text-slate-600">{{ config('app.support_phone', '+260 000 000 000') }}</p>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <span class="mt-0.5 text-blue-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M16 12a4 4 0 10-8 0 4 4 0 008 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 2a10 10 0 00-7.94 16.03L12 22l7.94-3.97A10 10 0 0012 2z"/>
                        </svg>
                    </span>
                    <div>
                        <p class="font-semibold text-slate-800">Address</p>
                        <p class="text-slate-600">
                            {{ config('app.support_address_line1', 'Customer Support Office') }}<br>
                            {{ config('app.support_city', 'Lusaka') }}, {{ config('app.support_country', 'Zambia') }}
                        </p>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <span class="mt-0.5 text-blue-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M16 12H8m8 0l-3-3m3 3l-3 3M4 6h16M4 18h16"/>
                        </svg>
                    </span>
                    <div>
                        <p class="font-semibold text-slate-800">Email</p>
                        <p class="text-slate-600">{{ config('app.support_email', config('mail.from.address')) }}</p>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <span class="mt-0.5 text-blue-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </span>
                    <div>
                        <p class="font-semibold text-slate-800">Support Hours</p>
                        <p class="text-slate-600">Monday to Friday, 08:00 – 17:00 (local time)</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Support Form --}}
        <div>
            <h2 class="text-xl font-semibold text-slate-900 mb-3">Send us a message</h2>
            <form method="POST" action="{{ route('support.store') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf

                <div class="space-y-1.5">
                    <label for="name" class="block text-sm font-medium text-slate-800">Full Name</label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        value="{{ old('name') }}"
                        required
                        class="w-full rounded-2xl bg-white border border-slate-300 text-slate-900 placeholder:text-slate-400 focus:border-blue-500 focus:ring-blue-500/25 focus:outline-none px-4 py-3 text-base transition"
                        placeholder="Enter your full name"
                    >
                    @error('name')
                        <p class="text-sm text-rose-600 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <label for="email" class="block text-sm font-medium text-slate-800">Email (optional)</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="{{ old('email') }}"
                            class="w-full rounded-2xl bg-white border border-slate-300 text-slate-900 placeholder:text-slate-400 focus:border-blue-500 focus:ring-blue-500/25 focus:outline-none px-4 py-3 text-base transition"
                            placeholder="you@example.com"
                        >
                        @error('email')
                            <p class="text-sm text-rose-600 font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-1.5">
                        <label for="phone" class="block text-sm font-medium text-slate-800">Phone (optional)</label>
                        <input type="text" name="phone" value="{{ old('phone') }}" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40" placeholder="Optional contact number">
                        @error('phone')
                            <p class="text-sm text-rose-600 font-medium">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label for="subject" class="block text-sm font-medium text-slate-800">Subject</label>
                    <input
                        type="text"
                        id="subject"
                        name="subject"
                        value="{{ old('subject') }}"
                        required
                        class="w-full rounded-2xl bg-white border border-slate-300 text-slate-900 placeholder:text-slate-400 focus:border-blue-500 focus:ring-blue-500/25 focus:outline-none px-4 py-3 text-base transition"
                        placeholder="Brief summary of your question"
                    >
                    @error('subject')
                        <p class="text-sm text-rose-600 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <div class="space-y-1.5">
                    <label for="message" class="block text-sm font-medium text-slate-800">Message</label>
                    <textarea
                        id="message"
                        name="message"
                        rows="4"
                        required
                        class="w-full rounded-2xl bg-white border border-slate-300 text-slate-900 placeholder:text-slate-400 focus:border-blue-500 focus:ring-blue-500/25 focus:outline-none px-4 py-3 text-base transition resize-y"
                        placeholder="Describe your issue or question with as much detail as possible"
                    >{{ old('message') }}</textarea>
                    @error('message')
                        <p class="text-sm text-rose-600 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <div class="space-y-1.5">
                    <label for="attachment" class="block text-sm font-medium text-slate-800">Supporting file (optional)</label>
                    <input type="file" id="attachment" name="attachment" accept=".pdf,image/jpeg,image/png,image/jpg"
                        class="w-full rounded-2xl bg-white border border-slate-300 text-slate-900 px-4 py-2 text-sm file:mr-4 file:rounded-lg file:border-0 file:bg-blue-100 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-blue-700">
                    <p class="text-xs text-slate-500">{{ \App\Support\DocumentUploadRules::HINT_PDF_IMAGE }}</p>
                    @error('attachment')
                        <p class="text-sm text-rose-600 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <div class="pt-2">
                    <button
                        type="submit"
                        class="w-full inline-flex justify-center items-center gap-2 rounded-2xl bg-gradient-to-r from-blue-500 to-blue-600 px-4 py-3 text-base font-semibold text-white shadow-lg shadow-blue-500/40 transition hover:scale-[1.01] hover:shadow-xl hover:shadow-blue-500/50"
                    >
                        Submit Support Request
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection


