@extends('layouts.admin')

@section('title', 'FAQs | ' . config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Frequently Asked Questions',
            'buttons' => [
                [
                    'action' => 'create',
                    'text' => 'Create FAQ',
                    'href' => route('admin.faqs.create'),
                    'icon' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>',
                    'can' => auth('admin')->user()?->can('faqs.create')
                ],
            ],
        ])

        <div class="rounded-3xl border border-white/10 bg-white/5 p-4 shadow-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full w-full text-base text-slate-300">
                    <thead>
                        <tr class="text-base font-semibold uppercase tracking-[0.25em] text-white/80 text-center border-b-2 border-white/20">
                            <th class="px-4 py-4 text-lg border-r border-white/10">Question</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Visibility</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Status</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Updated</th>
                            <th class="px-4 py-4 text-lg">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($faqs as $faq)
                            <tr class="text-center hover:bg-white/5 transition">
                                <td class="px-4 py-4 border-r border-white/5 text-left">
                                    <span class="block text-base font-medium text-white">
                                        {{ \Illuminate\Support\Str::limit($faq->question, 80) }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 border-r border-white/5">
                                    @php
                                        $visibilityLabels = [
                                            \App\Models\Faq::VISIBILITY_PUBLIC => 'Public (everyone)',
                                            \App\Models\Faq::VISIBILITY_AUTHENTICATED => 'Customers only',
                                            \App\Models\Faq::VISIBILITY_BOTH => 'Public & Customers',
                                        ];
                                        $visibilityColors = [
                                            \App\Models\Faq::VISIBILITY_PUBLIC => 'text-emerald-300',
                                            \App\Models\Faq::VISIBILITY_AUTHENTICATED => 'text-blue-300',
                                            \App\Models\Faq::VISIBILITY_BOTH => 'text-violet-300',
                                        ];
                                    @endphp
                                    <span class="text-sm font-medium {{ $visibilityColors[$faq->visibility] ?? 'text-slate-300' }}">
                                        {{ $visibilityLabels[$faq->visibility] ?? ucfirst($faq->visibility) }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 border-r border-white/5">
                                    @if($faq->is_active)
                                        <span class="text-sm font-medium text-emerald-300">Active</span>
                                    @else
                                        <span class="text-sm font-medium text-slate-400">Inactive</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 border-r border-white/5">
                                    <div class="flex flex-col items-start gap-1">
                                        <span class="text-sm text-slate-200">
                                            {{ $faq->updated_at?->format('d M Y') }}
                                        </span>
                                        <span class="text-xs text-slate-400">
                                            {{ $faq->updated_at?->format('H:i') }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    @can('faqs.update')
                                    <a href="{{ route('admin.faqs.edit', $faq) }}"
                                       class="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-indigo-500/40 to-purple-500/40 border-2 border-indigo-400/70 px-4 py-2 text-base font-semibold text-indigo-100 hover:from-indigo-500/60 hover:to-purple-500/60 hover:border-indigo-300 transition">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M11 5h2m-1 0v14m0 0H9m2 0h2" />
                                        </svg>
                                        Edit
                                    </a>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-slate-400">
                                    No FAQs have been created yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-6">
                {{ $faqs->links() }}
            </div>
        </div>
    </div>
@endsection


