@php
    // Button styles: use dark text on light backgrounds so wording is always visible in content area
    $buttonColors = [
        'create' => 'bg-primary hover:opacity-90 text-white border border-primary',
        'add' => 'bg-primary hover:opacity-90 text-white border border-primary',
        'new' => 'bg-primary hover:opacity-90 text-white border border-primary',
        'register' => 'bg-primary hover:opacity-90 text-white border border-primary',
        'export' => 'bg-primary hover:opacity-90 text-white border border-primary',
        'import' => 'bg-primary hover:opacity-90 text-white border border-primary',
        'primary' => 'bg-primary hover:opacity-90 text-white border border-primary',
        'edit' => 'border border-muted bg-white hover:bg-slate-50 text-primary',
        'delete' => 'border border-rose-400 bg-white hover:bg-rose-50 text-rose-700',
        'reject' => 'border border-rose-400 bg-white hover:bg-rose-50 text-rose-700',
        'secondary' => 'border border-muted bg-white hover:bg-slate-50 text-primary',
    ];
@endphp

<div class="flex items-start justify-between gap-6 pb-6 border-b border-white/10">
    <div class="space-y-2 flex-1">
        @if(isset($title))
            <h1 class="text-3xl font-bold">{{ $title }}</h1>
        @endif
        @if(isset($description))
            <p class="text-sm text-slate-400">{{ $description }}</p>
        @endif
    </div>
    
    @if(isset($buttons) && count($buttons) > 0)
        <div class="flex flex-wrap items-center gap-3">
            @foreach($buttons as $button)
                @php
                    $action = strtolower($button['action'] ?? 'secondary');
                    $colorClass = $buttonColors[$action] ?? $buttonColors['secondary'];
                    $icon = $button['icon'] ?? '';
                    $text = $button['text'] ?? '';
                    $href = $button['href'] ?? '#';
                    $class = $button['class'] ?? '';
                    $can = $button['can'] ?? true; // Default to true if not specified
                    $attributes = $button['attributes'] ?? [];
                @endphp
                @if($can)
                <a 
                    href="{{ $href }}" 
                    class="inline-flex items-center gap-2 rounded-2xl px-4 py-2.5 text-sm font-semibold shadow-lg transition {{ $colorClass }} {{ $class }}"
                    @foreach($attributes as $attribute => $value)
                        @if(is_bool($value))
                            @if($value)
                                {{ $attribute }}
                            @endif
                        @else
                            {{ $attribute }}="{{ $value }}"
                        @endif
                    @endforeach
                >
                    @if($icon)
                        <span class="[&>svg]:w-3.5 [&>svg]:h-3.5">{!! $icon !!}</span>
                    @endif
                    {{ $text }}
                </a>
                @endif
            @endforeach
        </div>
    @endif
</div>
