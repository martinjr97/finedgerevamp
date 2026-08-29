@props([
    'panelId' => 'admin-filters-panel',
    'filterKeys' => [],
    'expandedHint' => 'Refine the list below.',
])

@php
    $activeFilterCount = collect($filterKeys)->filter(fn (string $key) => filled(request($key)))->count();
@endphp

<div
    {{ $attributes->merge(['class' => 'rounded-3xl border border-white/10 bg-white/5 shadow-lg']) }}
    x-data="{ open: false }"
>
    <button
        type="button"
        class="flex w-full items-center justify-between gap-4 rounded-3xl p-6 text-left transition hover:bg-white/5"
        @click="open = !open"
        :aria-expanded="open.toString()"
        aria-controls="{{ $panelId }}"
    >
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-3">
                <h2 class="text-lg font-semibold text-white">Filters</h2>
                @if ($activeFilterCount > 0)
                    <span class="inline-flex items-center rounded-full border border-cyan-400/40 bg-cyan-500/20 px-2.5 py-0.5 text-xs font-semibold text-cyan-200">
                        {{ $activeFilterCount }} active
                    </span>
                @endif
            </div>
            <p class="mt-1 text-sm text-slate-400">
                <span x-show="!open">Click to expand search and filter options.</span>
                <span x-show="open" x-cloak>{{ $expandedHint }}</span>
            </p>
        </div>
        <svg
            class="h-5 w-5 shrink-0 text-slate-400 transition-transform duration-200"
            :class="{ 'rotate-180': open }"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
            aria-hidden="true"
        >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    <div
        id="{{ $panelId }}"
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-1"
        class="border-t border-white/10 px-6 pb-6 pt-4"
    >
        {{ $slot }}
    </div>
</div>
