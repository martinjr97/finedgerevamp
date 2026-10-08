@props([
    'href',
    'label',
    'value',
    'subtitle',
    'decimals' => 0,
])

<a href="{{ $href }}"
   class="block rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg hover:bg-white/10 hover:border-white/20 transition-all cursor-pointer group focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-400/60">
    <div class="flex items-center justify-between mb-2">
        <p class="text-sm text-slate-400 group-hover:text-slate-300 transition">{{ $label }}</p>
        @if(isset($icon))
            <div class="text-slate-400 group-hover:text-white transition">
                {{ $icon }}
            </div>
        @endif
    </div>
    <p class="text-3xl font-semibold mt-2">{{ number_format((float) $value, $decimals) }}</p>
    <p class="text-slate-500 text-xs mt-2">{{ $subtitle }}</p>
    <p class="text-xs text-cyan-400/0 group-hover:text-cyan-400/90 mt-3 transition">View matching records →</p>
</a>
