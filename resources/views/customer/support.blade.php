@extends('layouts.customer')

@section('title', 'Support & Help')

@section('content')
    <div class="max-w-3xl mx-auto space-y-6">
        <div class="bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600 rounded-2xl p-6 shadow-xl border-2 border-blue-500">
            <h1 class="text-2xl font-bold text-white mb-1">Support & Help</h1>
            <p class="text-sm text-blue-100">Submit a support ticket and our team will get back to you using your registered contact details.</p>
        </div>

        <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 p-6 shadow-md space-y-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Submit a Support Ticket</h2>

            <form method="POST" action="{{ route('customer.support.store') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf

                {{-- Hidden fields to reuse existing validation & logic --}}
                <input type="hidden" name="name" value="{{ $customer->full_name }}">
                <input type="hidden" name="email" value="{{ $customer->email }}">
                <input type="hidden" name="phone" value="{{ old('phone', $customer->phone) }}">

                <div class="space-y-1.5">
                    <label for="subject" class="block text-sm font-medium text-gray-900 dark:text-gray-100">Subject</label>
                    <input
                        type="text"
                        id="subject"
                        name="subject"
                        value="{{ old('subject') }}"
                        required
                        class="w-full rounded-2xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 placeholder:text-gray-400 focus:border-blue-500 focus:ring-blue-500/40 focus:outline-none px-4 py-2.5 text-sm"
                        placeholder="Brief summary of your issue or question"
                    >
                    @error('subject')
                        <p class="text-xs text-rose-500 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <div class="space-y-1.5">
                    <label for="message" class="block text-sm font-medium text-gray-900 dark:text-gray-100">Message</label>
                    <textarea
                        id="message"
                        name="message"
                        rows="4"
                        required
                        class="w-full rounded-2xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 placeholder:text-gray-400 focus:border-blue-500 focus:ring-blue-500/40 focus:outline-none px-4 py-2.5 text-sm resize-y"
                        placeholder="Describe your issue or question in detail"
                    >{{ old('message') }}</textarea>
                    @error('message')
                        <p class="text-xs text-rose-500 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <div class="space-y-1.5">
                    <label for="attachment" class="block text-sm font-medium text-gray-900 dark:text-gray-100">Supporting file (optional)</label>
                    <input type="file" id="attachment" name="attachment" accept=".pdf,image/jpeg,image/png,image/jpg"
                        class="w-full rounded-2xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-4 py-2 text-sm file:mr-4 file:rounded-lg file:border-0 file:bg-blue-100 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-blue-700 dark:file:bg-blue-900/40 dark:file:text-blue-200">
                    <p class="text-xs text-gray-500">{{ \App\Support\DocumentUploadRules::HINT_PDF_IMAGE }}</p>
                    @error('attachment')
                        <p class="text-xs text-rose-500 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <div class="pt-2 space-y-3">
                    <button
                        type="submit"
                        class="w-full inline-flex justify-center items-center gap-2 rounded-2xl bg-gradient-to-r from-blue-500 via-indigo-500 to-purple-600 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-500/40 hover:from-blue-600 hover:via-indigo-600 hover:to-purple-700 hover:shadow-xl transition"
                    >
                        Submit Support Ticket
                    </button>

                    <a
                        href="{{ route('customer.support-tickets.index') }}"
                        class="w-full inline-flex justify-center items-center gap-2 rounded-2xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-3 text-sm font-semibold text-gray-700 dark:text-gray-200 transition hover:bg-gray-50 dark:hover:bg-gray-700"
                    >
                        View Previous Support Tickets
                    </a>
                </div>
            </form>
        </div>
    </div>
@endsection

