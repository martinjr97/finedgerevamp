@php
    use App\Models\SupportTicketComment;

    $isAdminView = $isAdminView ?? true;
    $isCustomer = $comment->author_type === SupportTicketComment::AUTHOR_CUSTOMER;
    $isSystem = $comment->author_type === SupportTicketComment::AUTHOR_SYSTEM;
    $isInternal = $isAdminView && (bool) $comment->is_internal;
    $isOutgoing = ! $isCustomer && ! $isSystem && ! $isInternal;
    $isIncoming = $isCustomer;

    if ($isAdminView) {
        $rowClass = match (true) {
            $isSystem => 'justify-center',
            $isIncoming => 'justify-start',
            $isInternal => 'justify-end',
            default => 'justify-end',
        };
        $bubbleClass = match (true) {
            $isSystem => 'max-w-[92%] border border-slate-500/40 bg-slate-800/80 text-slate-200 rounded-2xl px-4 py-3',
            $isIncoming => 'max-w-[85%] border border-blue-400/40 bg-blue-500/15 text-blue-50 rounded-2xl rounded-tl-md px-4 py-3 shadow-sm',
            $isInternal => 'max-w-[85%] border border-dashed border-amber-400/60 bg-amber-500/10 text-amber-50 rounded-2xl rounded-tr-md px-4 py-3 shadow-sm',
            default => 'max-w-[85%] border border-emerald-400/40 bg-emerald-500/15 text-emerald-50 rounded-2xl rounded-tr-md px-4 py-3 shadow-sm',
        };
        $metaClass = 'text-[11px] text-slate-400';
        $bodyClass = 'whitespace-pre-line text-sm leading-relaxed text-slate-100';
    } else {
        $rowClass = match (true) {
            $isSystem => 'justify-center',
            $isIncoming => 'justify-end',
            default => 'justify-start',
        };
        $bubbleClass = match (true) {
            $isSystem => 'max-w-[92%] rounded-2xl border border-gray-300 bg-gray-100 px-4 py-3 text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200',
            $isIncoming => 'max-w-[85%] rounded-2xl rounded-tr-md border border-blue-200 bg-blue-600 px-4 py-3 text-white shadow-sm',
            default => 'max-w-[85%] rounded-2xl rounded-tl-md border border-gray-200 bg-gray-100 px-4 py-3 text-gray-800 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 shadow-sm',
        };
        $metaClass = $isIncoming ? 'text-[11px] text-blue-100/80' : 'text-[11px] text-gray-500 dark:text-gray-400';
        $bodyClass = $isIncoming
            ? 'whitespace-pre-line text-sm leading-relaxed text-white'
            : 'whitespace-pre-line text-sm leading-relaxed text-gray-800 dark:text-gray-100';
    }

    $directionLabel = match (true) {
        $isSystem => 'System update',
        $isIncoming => $isAdminView ? 'Customer message' : 'You',
        $isInternal => 'Internal note',
        default => $isAdminView ? 'Reply to customer' : 'Support team',
    };
@endphp

<div class="flex {{ $rowClass }}">
    <article class="{{ $bubbleClass }}">
        <div class="mb-2 flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
            <div class="flex flex-wrap items-center gap-2 min-w-0">
                <span @class([
                    'inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide',
                    $isAdminView && $isIncoming => 'border-blue-400/50 bg-blue-500/20 text-blue-100',
                    $isAdminView && $isInternal => 'border-amber-400/60 bg-amber-500/20 text-amber-100',
                    $isAdminView && $isOutgoing => 'border-emerald-400/50 bg-emerald-500/20 text-emerald-100',
                    $isAdminView && $isSystem => 'border-slate-400/50 bg-slate-500/20 text-slate-200',
                    ! $isAdminView && $isIncoming => 'border-blue-300/60 bg-blue-500/30 text-white',
                    ! $isAdminView && ! $isIncoming => 'border-gray-300 bg-white/70 text-gray-700 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100',
                ])>
                    {{ $directionLabel }}
                </span>
                <span @class(['text-sm font-semibold truncate', $isAdminView ? 'text-white' : ($isIncoming ? 'text-white' : 'text-gray-900 dark:text-white')])>
                    {{ $comment->authorName() }}
                </span>
            </div>
            <time class="{{ $metaClass }} shrink-0" datetime="{{ $comment->created_at?->toIso8601String() }}">
                {{ $comment->created_at?->format('d M Y, H:i') }}
            </time>
        </div>
        <p class="{{ $bodyClass }}">{{ $comment->comment }}</p>
    </article>
</div>
