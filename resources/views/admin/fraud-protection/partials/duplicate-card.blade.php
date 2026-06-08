<div class="rounded-2xl border border-orange-500/30 bg-orange-500/10 p-4">
    <div class="flex items-start justify-between">
        <div class="flex-1">
            <div class="flex items-center gap-3 mb-2">
                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold bg-orange-500/20 text-orange-300 border border-orange-500/50">
                    {{ $duplicate['match_reason'] }}
                </span>
                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $duplicate['status'] === 'active' ? 'bg-emerald-500/20 text-emerald-300' : ($duplicate['status'] === 'pending' ? 'bg-amber-500/20 text-amber-300' : 'bg-slate-500/20 text-slate-300') }}">
                    {{ ucfirst($duplicate['status']) }}
                </span>
            </div>
            <h4 class="font-semibold text-white mb-2">{{ $duplicate['name'] }}</h4>
            <div class="grid gap-2 text-sm text-slate-300">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    <span>{{ $duplicate['email'] }}</span>
                </div>
                @if($duplicate['phone'])
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 011.7.7l1.72 2.56a1 1 0 01-.01 1.4l-1.72 2.56a1 1 0 01-1.7.7H5a2 2 0 00-2 2v1a2 2 0 002 2h.28a1 1 0 01.7.7l2.56 1.72a1 1 0 01.01 1.4l-2.56 1.72a1 1 0 01-.7.7H5a2 2 0 01-2 2v1a2 2 0 002 2h14a2 2 0 002-2v-1a2 2 0 00-2-2h-1.28a1 1 0 01-.7-.7l-2.56-1.72a1 1 0 01-.01-1.4l2.56-1.72a1 1 0 01.7-.7H19a2 2 0 002-2V9a2 2 0 00-2-2h-1.28a1 1 0 01-.7-.7l-2.56-1.72a1 1 0 01.01-1.4l2.56-1.72a1 1 0 01.7-.7H19z"/>
                        </svg>
                        <span>{{ $duplicate['phone'] }}</span>
                    </div>
                @endif
                @if($duplicate['national_id'])
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/>
                        </svg>
                        <span>{{ $duplicate['national_id'] }}</span>
                    </div>
                @endif
                @if($duplicate['created_at'])
                    <div class="flex items-center gap-2 text-xs text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <span>Created: {{ $duplicate['created_at'] }}</span>
                    </div>
                @endif
                @if(isset($duplicate['match_details']))
                    <div class="mt-3 pt-3 border-t border-orange-500/20 space-y-2">
                        @if(isset($duplicate['match_details']['ip']))
                            <div class="flex items-center gap-2 text-xs">
                                <svg class="w-4 h-4 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/>
                                </svg>
                                <span class="text-cyan-300 font-mono">{{ $duplicate['match_details']['ip'] }}</span>
                            </div>
                        @endif
                        @if(isset($duplicate['match_details']['device_name']))
                            <div class="flex items-center gap-2 text-xs">
                                <svg class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                </svg>
                                <span class="text-purple-300">{{ $duplicate['match_details']['device_name'] }}</span>
                                @if(isset($duplicate['match_details']['device_type']))
                                    <span class="text-slate-400">({{ ucfirst($duplicate['match_details']['device_type']) }})</span>
                                @endif
                            </div>
                        @endif
                        @if(isset($duplicate['match_details']['os']) || isset($duplicate['match_details']['browser']))
                            <div class="flex items-center gap-2 text-xs text-slate-400">
                                @if(isset($duplicate['match_details']['os']))
                                    <span>{{ $duplicate['match_details']['os'] }}</span>
                                @endif
                                @if(isset($duplicate['match_details']['browser']))
                                    <span>•</span>
                                    <span>{{ $duplicate['match_details']['browser'] }}</span>
                                @endif
                            </div>
                        @endif
                        @if(isset($duplicate['match_details']['location']))
                            <div class="flex items-center gap-2 text-xs text-slate-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                <span>{{ $duplicate['match_details']['location'] }}</span>
                            </div>
                        @endif
                        @if(isset($duplicate['match_details']['last_seen']))
                            <div class="flex items-center gap-2 text-xs text-slate-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span>Last seen: {{ $duplicate['match_details']['last_seen'] }}</span>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
        <div class="ml-4 flex flex-col gap-2">
            <a href="{{ route('admin.customers.show', $duplicate['id']) }}" class="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-cyan-500/40 to-blue-600/40 border-2 border-cyan-400/70 px-3 py-1.5 text-xs font-semibold text-cyan-200 hover:from-cyan-500/60 hover:to-blue-600/60 hover:border-cyan-400 hover:text-white transition shadow-md shadow-cyan-500/20">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                View
            </a>
            @can('fraud-protection.clear')
            <button 
                type="button"
                onclick="showClearModal({{ $duplicate['id'] }}, {{ json_encode($duplicate['match_reason']) }}, {{ json_encode(isset($duplicate['match_details']['ip']) ? $duplicate['match_details']['ip'] : (isset($duplicate['match_details']['device_name']) ? $duplicate['match_details']['device_name'] : '')) }})"
                class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-500/20 border border-emerald-500/50 px-3 py-1.5 text-xs font-semibold text-emerald-300 hover:bg-emerald-500/30 transition"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Clear
            </button>
            @endcan
        </div>
    </div>
</div>

