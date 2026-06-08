@extends('layouts.customer')

@section('title', 'FAQs')

@section('content')
    <div class="max-w-3xl mx-auto space-y-6">
        <div class="space-y-1">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Frequently Asked Questions</h1>
            <p class="text-sm text-gray-600 dark:text-gray-300">
                Answers to common questions about your loans, repayments and using the portal.
            </p>
        </div>

        @if($faqs->isEmpty())
            <p class="text-center text-gray-500 dark:text-gray-400 py-8">
                There are no FAQs available at the moment. Please check back later.
            </p>
        @else
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 divide-y divide-gray-200 dark:divide-gray-700">
                @foreach($faqs as $faq)
                    <div class="p-4">
                        <p class="text-base font-semibold text-gray-900 dark:text-white">
                            {{ $faq->question }}
                        </p>
                        <p class="mt-2 text-sm text-gray-700 dark:text-gray-200 leading-relaxed">
                            {!! nl2br(e($faq->answer)) !!}
                        </p>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection


