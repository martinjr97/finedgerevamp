@extends('layouts.auth')

@section('title', 'Frequently Asked Questions | ' . config('app.system_name'))
@section('heading', 'Frequently Asked Questions')
@section('subheading', 'Quick answers to common questions')
@section('auth_top', true)

@section('content')
    @if($faqs->isEmpty())
        <p class="text-center text-slate-600 text-base">
            No FAQs have been published yet. Please check back later.
        </p>
    @else
        <div class="space-y-4 text-left">
            <p class="text-sm sm:text-base text-slate-600">
                Browse the common questions below. These answers are available to all users.
            </p>

            <div class="divide-y divide-slate-200">
                @foreach($faqs as $faq)
                    <div class="py-3">
                        <button type="button"
                                class="w-full flex items-center justify-between gap-2 text-left">
                            <span class="text-base sm:text-lg font-semibold text-slate-900">
                                {{ $faq->question }}
                            </span>
                        </button>
                        <div class="mt-1 text-sm sm:text-base text-slate-700 leading-relaxed">
                            {!! nl2br(e($faq->answer)) !!}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
@endsection


