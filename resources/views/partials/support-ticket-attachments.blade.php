@php
    $isAdminView = $isAdminView ?? true;
    $downloadRoute = $isAdminView ? 'admin.support-tickets.attachments.download' : 'customer.support-tickets.attachments.download';
@endphp

@if ($attachments->isNotEmpty())
    <div @class([
        'rounded-3xl border p-5 shadow-lg space-y-4',
        $isAdminView ? 'border-white/10 bg-white/5' : 'border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900',
    ])>
        <h2 @class(['text-lg font-semibold', $isAdminView ? 'text-white' : 'text-gray-900 dark:text-white'])>
            Supporting files
        </h2>
        <ul class="space-y-3">
            @foreach ($attachments as $attachment)
                <li @class([
                    'flex flex-wrap items-center justify-between gap-3 rounded-2xl border px-4 py-3',
                    $isAdminView ? 'border-white/10 bg-black/20' : 'border-gray-200 bg-gray-50 dark:border-gray-600 dark:bg-gray-800/80',
                ])>
                    <div class="min-w-0 flex-1">
                        <p @class(['text-sm font-semibold truncate', $isAdminView ? 'text-white' : 'text-gray-900 dark:text-white'])>
                            {{ $attachment->original_name }}
                        </p>
                        <p @class(['text-xs mt-0.5', $isAdminView ? 'text-slate-400' : 'text-gray-500'])>
                            {{ $attachment->formattedSize() }}
                            · {{ $attachment->uploaderLabel() }}
                            · {{ $attachment->created_at?->format('d M Y, H:i') }}
                            @if ($isAdminView && ! $attachment->is_visible_to_customer)
                                · <span class="text-amber-300">Internal only</span>
                            @endif
                        </p>
                    </div>
                    <a href="{{ route($downloadRoute, [$ticket, $attachment]) }}"
                       class="inline-flex shrink-0 items-center gap-1.5 rounded-xl border px-3 py-1.5 text-xs font-semibold transition {{ $isAdminView ? 'border-cyan-400/50 bg-cyan-500/20 text-cyan-100 hover:bg-cyan-500/30' : 'border-blue-300 bg-blue-50 text-blue-700 hover:bg-blue-100 dark:border-blue-500/40 dark:bg-blue-500/20 dark:text-blue-200' }}"
                       target="_blank" rel="noopener">
                        @if ($attachment->isImage())
                            View
                        @else
                            Download
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
        <p @class(['text-xs', $isAdminView ? 'text-slate-500' : 'text-gray-500'])>
            {{ \App\Support\DocumentUploadRules::HINT_PDF_IMAGE }}
        </p>
    </div>
@endif
