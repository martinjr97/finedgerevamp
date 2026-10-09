@extends('legacy.migration-dashboard.layout')

@section('dashboard-content')
    @php
        $inbox = $detail['inbox'];
        $snapshot = $detail['snapshot'] ?? [];
        $context = $detail['context'] ?? [];
        $legacyUser = $snapshot['legacy_user'] ?? [];
        $review = $detail['customer_detail']['review'] ?? [];
        $legacy = $detail['customer_detail']['legacy'] ?? [];
    @endphp

    @include('partials.admin.page-header', [
        'title' => 'Review Legacy User #'.$legacyUserId,
        'description' => 'Promote this borrower into revamp before confirming parallel-run loan import.',
    ])

    <div class="mb-4">
        <a href="{{ route('legacy.migration-dashboard.customers.pending') }}" class="text-sm font-semibold text-primary hover:underline">← Back to pending customers</a>
    </div>

    @if(session('error'))
        <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">{{ session('error') }}</div>
    @endif

    <div class="mb-6 rounded-2xl border bg-white p-4 shadow-sm">
        <dl class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 text-sm">
            <div>
                <dt class="text-xs uppercase text-slate-500">Inbox status</dt>
                <dd class="mt-1">@include('legacy.migration-dashboard.partials.badge', ['label' => $inbox->status])</dd>
            </div>
            <div>
                <dt class="text-xs uppercase text-slate-500">Name</dt>
                <dd class="mt-1 font-semibold">{{ trim(($legacyUser['first_name'] ?? '').' '.($legacyUser['last_name'] ?? '')) ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase text-slate-500">NRC</dt>
                <dd class="mt-1">{{ $legacy['national_id'] ?? $snapshot['national_id'] ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase text-slate-500">Detected</dt>
                <dd class="mt-1">{{ $inbox->detected_at }}</dd>
            </div>
            @if(!empty($context['legacy_loan_ids']))
                <div class="sm:col-span-2">
                    <dt class="text-xs uppercase text-slate-500">Related active legacy loans</dt>
                    <dd class="mt-1 font-mono text-xs">{{ implode(', ', $context['legacy_loan_ids']) }}</dd>
                </div>
            @endif
            @if($inbox->block_reason)
                <div class="sm:col-span-2">
                    <dt class="text-xs uppercase text-slate-500">Block reason</dt>
                    <dd class="mt-1 text-rose-700">{{ $inbox->block_reason }}</dd>
                </div>
            @endif
        </dl>
    </div>

    @if($review['is_manual_review'] ?? false)
        <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
            <p class="font-semibold">{{ $review['title'] ?? 'Manual review required' }}</p>
            <p class="mt-1">{{ $review['description'] ?? '' }}</p>
            @if($review['identity_resolve_url'] ?? null)
                <a href="{{ $review['identity_resolve_url'] }}" class="mt-2 inline-block font-semibold text-primary hover:underline">Resolve identity →</a>
            @endif
            <a href="{{ route('legacy.migration-dashboard.customers.show', $legacyUserId) }}" class="mt-2 ml-3 inline-block font-semibold text-primary hover:underline">Full customer staging →</a>
        </div>
    @endif

    @if($canManage && ($detail['can_promote'] ?? false))
        <div class="mt-6 flex flex-wrap gap-3">
            <form method="POST" action="{{ route('legacy.migration-dashboard.customers.pending.promote', $legacyUserId) }}" onsubmit="return confirm('Promote this legacy user as a revamp customer using migration:customers rules?');">
                @csrf
                <button type="submit" class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:opacity-90">
                    Promote customer
                </button>
            </form>

            <form method="POST" action="{{ route('legacy.migration-dashboard.customers.pending.dismiss', $legacyUserId) }}" class="flex items-end gap-2" onsubmit="return confirm('Dismiss this user from the customer queue?');">
                @csrf
                <input type="text" name="notes" placeholder="Optional reason" class="rounded-xl border px-3 py-2 text-sm">
                <button type="submit" class="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Dismiss
                </button>
            </form>
        </div>
    @elseif($detail['customer_map'] ?? null)
        <p class="text-sm text-emerald-800">Customer is mapped — <a href="{{ route('legacy.migration-dashboard.customers.show', $legacyUserId) }}" class="font-semibold text-primary hover:underline">view staging detail</a> or import pending loans.</p>
    @endif
@endsection
