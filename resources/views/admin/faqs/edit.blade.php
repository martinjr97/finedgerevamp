@extends('layouts.admin')

@section('title', 'Edit FAQ | ' . config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Edit FAQ',
            'buttons' => [
                [
                    'action' => 'back',
                    'text' => 'Back to FAQs',
                    'href' => route('admin.faqs.index'),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>',
                ],
            ],
        ])

        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            @include('admin.faqs.form', ['faq' => $faq])
        </div>
    </div>
@endsection


