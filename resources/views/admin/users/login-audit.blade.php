@extends('layouts.admin')

@section('title', 'Login Audit | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_auto] lg:items-center">
            <div class="space-y-1 text-left">
                <p class="text-xs uppercase tracking-[0.4em] text-cyan-300">Team Management</p>
                <h1 class="text-3xl font-bold">Login Audit - {{ $user->full_name }}</h1>
                <p class="text-sm text-slate-400 mt-2">View all login attempts for this admin user</p>
            </div>
            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('admin.users.show', $user) }}" class="inline-flex items-center gap-2 rounded-2xl border border-white/20 bg-white/5 px-4 py-3 text-base font-medium text-slate-300 hover:bg-white/10 hover:border-white/30 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Back to User
                </a>
            </div>
        </div>

        <!-- Filters -->
        <div class="rounded-3xl border border-white/10 bg-gradient-to-br from-white/5 to-white/[0.02] p-6 shadow-lg">
            <form method="GET" action="{{ route('admin.users.login-audit', $user) }}" class="grid gap-4 md:grid-cols-4">
                <div>
                    <label for="status" class="block text-xs uppercase tracking-wide text-slate-400 mb-2">Status</label>
                    <select name="status" id="status" class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-white focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 transition">
                        <option value="">All</option>
                        <option value="success" {{ request('status') === 'success' ? 'selected' : '' }}>Success</option>
                        <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>Failed</option>
                    </select>
                </div>
                <div>
                    <label for="date_from" class="block text-xs uppercase tracking-wide text-slate-400 mb-2">Date From</label>
                    <input type="date" name="date_from" id="date_from" value="{{ request('date_from') }}" class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-white focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 transition">
                </div>
                <div>
                    <label for="date_to" class="block text-xs uppercase tracking-wide text-slate-400 mb-2">Date To</label>
                    <input type="date" name="date_to" id="date_to" value="{{ request('date_to') }}" class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-white focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 transition">
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-500 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-cyan-500/30 hover:from-cyan-600 hover:to-blue-600 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        Filter
                    </button>
                    <a href="{{ route('admin.users.login-audit', $user) }}" class="inline-flex items-center justify-center rounded-xl border border-white/20 bg-white/5 px-4 py-2.5 text-sm font-medium text-slate-300 hover:bg-white/10 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </a>
                </div>
            </form>
        </div>

        <!-- Login Audit Table -->
        <div class="rounded-3xl border border-white/10 bg-gradient-to-br from-white/5 to-white/[0.02] p-6 shadow-lg">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-white/10 text-left">
                            <th class="px-4 py-3 text-xs uppercase tracking-wide text-slate-400 font-semibold">Date & Time</th>
                            <th class="px-4 py-3 text-xs uppercase tracking-wide text-slate-400 font-semibold">Status</th>
                            <th class="px-4 py-3 text-xs uppercase tracking-wide text-slate-400 font-semibold">Device</th>
                            <th class="px-4 py-3 text-xs uppercase tracking-wide text-slate-400 font-semibold">Location</th>
                            <th class="px-4 py-3 text-xs uppercase tracking-wide text-slate-400 font-semibold">IP Address</th>
                            <th class="px-4 py-3 text-xs uppercase tracking-wide text-slate-400 font-semibold">Failure Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($loginAudits as $audit)
                            <tr class="border-b border-white/5 hover:bg-white/5 transition">
                                <td class="px-4 py-4">
                                    <div class="text-sm font-medium text-white">
                                        {{ $audit->attempted_at->format('d M Y') }}
                                    </div>
                                    <div class="text-xs text-slate-400 mt-1">
                                        {{ $audit->attempted_at->format('H:i:s') }}
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    @if ($audit->status === 'success')
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/20 px-3 py-1 text-xs font-medium text-emerald-300 border border-emerald-500/30">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                            </svg>
                                            Success
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-500/20 px-3 py-1 text-xs font-medium text-rose-300 border border-rose-500/30">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                            Failed
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-4">
                                    <div class="space-y-1">
                                        @if($audit->device_type)
                                            <div class="flex items-center gap-2">
                                                <span class="inline-flex items-center rounded-full bg-cyan-500/20 px-2 py-0.5 text-xs font-medium text-cyan-300 border border-cyan-500/30">
                                                    {{ ucfirst($audit->device_type) }}
                                                </span>
                                            </div>
                                        @endif
                                        @if($audit->device_name)
                                            <div class="text-sm font-medium text-white">{{ $audit->device_name }}</div>
                                        @endif
                                        @if($audit->os)
                                            <div class="text-xs text-slate-400">
                                                {{ $audit->os }}{{ $audit->os_version ? ' ' . $audit->os_version : '' }}
                                            </div>
                                        @endif
                                        @if($audit->browser)
                                            <div class="text-xs text-slate-400">
                                                {{ $audit->browser }}{{ $audit->browser_version ? ' ' . $audit->browser_version : '' }}
                                            </div>
                                        @endif
                                        @if(!$audit->device_type && !$audit->device_name && !$audit->os && !$audit->browser)
                                            <span class="text-sm text-slate-500">N/A</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="space-y-1">
                                        @if($audit->location_city)
                                            <div class="text-sm font-medium text-white">{{ $audit->location_city }}</div>
                                        @endif
                                        @if($audit->location_region)
                                            <div class="text-xs text-slate-400">{{ $audit->location_region }}</div>
                                        @endif
                                        @if($audit->location_country)
                                            <div class="text-xs text-slate-400">{{ $audit->location_country }}</div>
                                        @endif
                                        @if(!$audit->location_city && !$audit->location_region && !$audit->location_country)
                                            <span class="text-sm text-slate-500">Unknown</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="font-mono text-sm text-slate-300">{{ $audit->ip_address ?? 'N/A' }}</span>
                                </td>
                                <td class="px-4 py-4">
                                    @if ($audit->failure_reason)
                                        <span class="inline-flex items-center rounded-full bg-amber-500/20 px-2.5 py-1 text-xs font-medium text-amber-300 border border-amber-500/30">
                                            {{ ucfirst(str_replace('_', ' ', $audit->failure_reason)) }}
                                        </span>
                                    @else
                                        <span class="text-sm text-slate-500">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-12 text-center">
                                    <div class="flex flex-col items-center gap-3">
                                        <svg class="w-12 h-12 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                        <p class="text-base font-medium text-slate-400">No login attempts found</p>
                                        <p class="text-sm text-slate-500">This user has no recorded login attempts yet.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if ($loginAudits->hasPages())
                <div class="mt-6">
                    {{ $loginAudits->links() }}
                </div>
            @endif
        </div>

        <!-- Summary Stats -->
        <div class="grid gap-6 md:grid-cols-3">
            <div class="rounded-3xl border border-white/10 bg-gradient-to-br from-white/5 to-white/[0.02] p-6 shadow-lg">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-sm text-slate-400">Total Attempts</p>
                    <svg class="w-5 h-5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <p class="text-3xl font-semibold mt-2">{{ $loginAudits->total() }}</p>
            </div>
            <div class="rounded-3xl border border-white/10 bg-gradient-to-br from-white/5 to-white/[0.02] p-6 shadow-lg">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-sm text-slate-400">Successful Logins</p>
                    <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <p class="text-3xl font-semibold mt-2 text-emerald-300">{{ $loginAudits->where('status', 'success')->count() }}</p>
            </div>
            <div class="rounded-3xl border border-white/10 bg-gradient-to-br from-white/5 to-white/[0.02] p-6 shadow-lg">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-sm text-slate-400">Failed Attempts</p>
                    <svg class="w-5 h-5 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </div>
                <p class="text-3xl font-semibold mt-2 text-rose-300">{{ $loginAudits->where('status', 'failed')->count() }}</p>
            </div>
        </div>
    </div>
@endsection

