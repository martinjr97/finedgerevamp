@props([
    'label',
    'value' => null,
])

<div class="rounded-2xl border border-white/10 bg-black/20 px-4 py-3">
    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</dt>
    <dd class="mt-1.5 text-sm font-medium text-white break-words">
        @if (trim((string) ($value ?? '')) !== '')
            {{ $value }}
        @else
            <span class="text-slate-500 font-normal">—</span>
        @endif
    </dd>
</div>
