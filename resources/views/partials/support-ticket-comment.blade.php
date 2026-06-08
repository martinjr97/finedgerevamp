@php
    $isAdminView = $isAdminView ?? true;
    $badgeClasses = match ($comment->author_type) {
        \App\Models\SupportTicketComment::AUTHOR_CUSTOMER => 'border-blue-400/50 bg-blue-500/15 text-blue-200',
        \App\Models\SupportTicketComment::AUTHOR_STAFF => 'border-cyan-400/50 bg-cyan-500/15 text-cyan-200',
        \App\Models\SupportTicketComment::AUTHOR_ADMIN => 'border-indigo-400/50 bg-indigo-500/15 text-indigo-200',
        \App\Models\SupportTicketComment::AUTHOR_SYSTEM => 'border-slate-400/50 bg-slate-500/15 text-slate-200',
        default => 'border-white/20 bg-white/10 text-slate-200',
    };
@endphp

<article @class([
    'rounded-2xl border p-4',
    $isAdminView && $comment->is_internal ? 'border-amber-400/40 bg-amber-500/10' : 'border-white/10 bg-black/20',
    !$isAdminView ? 'border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800/80' : '',
])>
    <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
        <div class="flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold {{ $badgeClasses }}">
                {{ $comment->authorBadge() }}
            </span>
            <span @class(['text-sm font-semibold', $isAdminView ? 'text-white' : 'text-gray-900 dark:text-white'])>
                {{ $comment->authorName() }}
            </span>
            @if ($isAdminView && $comment->is_internal)
                <span class="inline-flex items-center rounded-full border border-amber-400/60 bg-amber-500/20 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-200">
                    Internal only
                </span>
            @elseif ($isAdminView && $comment->author_type !== \App\Models\SupportTicketComment::AUTHOR_SYSTEM && ! $comment->is_internal)
                <span class="inline-flex items-center rounded-full border border-emerald-400/50 bg-emerald-500/15 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-emerald-200">
                    Visible to customer
                </span>
            @endif
        </div>
        <time @class(['text-xs', $isAdminView ? 'text-slate-400' : 'text-gray-500']) datetime="{{ $comment->created_at?->toIso8601String() }}">
            {{ $comment->created_at?->format('d M Y, H:i') }}
        </time>
    </div>
    <p @class(['whitespace-pre-line text-sm', $isAdminView ? 'text-slate-200' : 'text-gray-800 dark:text-gray-200'])>
        {{ $comment->comment }}
    </p>
</article>
