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

        <div class="admin-data-table">
            <div class="admin-data-table__scroll">
                <table class="min-w-full w-full text-base text-slate-300">
                    <thead>
                        <tr class="font-semibold uppercase text-white/80 text-center">
                            <th>Question</th>
                            <th>Visibility</th>
                            <th>Status</th>
                            <th>Updated</th>
                            <th scope="col" class="admin-data-table__actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($faqs as $faq)
                            <tr class="text-center">
                                <td class="text-left">
                                    <span class="block text-base font-medium text-white">
                                        {{ \Illuminate\Support\Str::limit($faq->question, 80) }}
                                    </span>
                                </td>
                                <td>
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
                                <td>
                                    @if($faq->is_active)
                                        <span class="text-sm font-medium text-emerald-300">Active</span>
                                    @else
                                        <span class="text-sm font-medium text-slate-400">Inactive</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="flex flex-col items-start gap-1">
                                        <span class="text-sm text-slate-200">
                                            {{ $faq->updated_at?->format('d M Y') }}
                                        </span>
                                        <span class="text-xs text-slate-400">
                                            {{ $faq->updated_at?->format('H:i') }}
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    @can('faqs.update')
                                    <a href="{{ route('admin.faqs.edit', $faq) }}"
                                       class="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-indigo-500/40 to-purple-500/40 border-2 border-indigo-400/70 px-4 py-2 text-base font-semibold text-indigo-100 hover:from-indigo-500/60 hover:to-purple-500/60 hover:border-indigo-300 transition">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                                <td colspan="5" class="py-8 text-center text-slate-400">
                                    No FAQs have been created yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="admin-table-footer">
                {{ $faqs->withQueryString()->links() }}
            </div>
        </div>
    </div>
@endsection


