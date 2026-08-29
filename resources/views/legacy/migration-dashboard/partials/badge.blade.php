@props(['label', 'tone' => 'slate'])

@php
    $tones = [
        'green' => 'bg-emerald-100 text-emerald-800 border-emerald-300',
        'blue' => 'bg-sky-100 text-sky-800 border-sky-300',
        'purple' => 'bg-violet-100 text-violet-800 border-violet-300',
        'amber' => 'bg-amber-100 text-amber-900 border-amber-300',
        'red' => 'bg-rose-100 text-rose-800 border-rose-300',
        'slate' => 'bg-slate-100 text-slate-800 border-slate-300',
    ];
    $class = $tones[$tone] ?? $tones['slate'];
@endphp

<span class="inline-flex items-center rounded-lg border px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide {{ $class }}">
    {{ $label }}
</span>
