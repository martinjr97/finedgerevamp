@extends('layouts.admin')

@section('title', 'Markets | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Markets',
            'description' => 'Manage markets for the Marketeer loan product',
            'buttons' => [
                [
                    'action' => 'create',
                    'text' => 'Create Market',
                    'href' => route('admin.markets.create'),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>'
                ]
            ]
        ])

        <div class="rounded-3xl border border-white/10 bg-white/5 p-4 shadow-lg">
            <div class="overflow-x-auto">
                <table data-datatable="true" class="min-w-full w-full text-sm text-slate-300">
                    <thead>
                        <tr class="text-sm font-semibold uppercase tracking-[0.25em] text-white/80 text-center">
                            <th class="px-4 py-4 text-base">Name</th>
                            <th class="px-4 py-4 text-base">Code</th>
                            <th class="px-4 py-4 text-base">Location</th>
                            <th class="px-4 py-4 text-base">Contact Person</th>
                            <th class="px-4 py-4 text-base">Portfolio Manager</th>
                            <th class="px-4 py-4 text-base">Status</th>
                            <th class="px-4 py-4 text-base">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($markets as $market)
                            <tr class="border-t border-white/5 text-center">
                                <td class="px-4 py-3 font-medium text-white">{{ $market->name }}</td>
                                <td class="px-4 py-3">{{ $market->code }}</td>
                                <td class="px-4 py-3">
                                    <div class="text-xs">
                                        <div>{{ $market->province->name ?? '—' }}, {{ $market->district->name ?? '—' }}</div>
                                        @if($market->city)
                                            <div class="text-slate-400">{{ $market->city }}</div>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-xs">
                                        <div class="font-medium">{{ $market->contact_person_name }}</div>
                                        <div class="text-slate-400">{{ $market->contact_person_phone }}</div>
                                        @if($market->contact_person_email)
                                            <div class="text-slate-400">{{ $market->contact_person_email }}</div>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    @if($market->portfolioManager)
                                        <span class="text-xs">{{ $market->portfolioManager->first_name }} {{ $market->portfolioManager->last_name }}</span>
                                    @else
                                        <span class="text-slate-500 text-xs">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-1 text-xs {{ $market->is_active ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300' }}">
                                        {{ $market->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-center gap-2">
                                        <a href="{{ route('admin.markets.show', $market) }}" class="rounded-full bg-blue-500/20 border border-blue-500/50 px-3 py-1.5 text-xs font-medium text-blue-300 hover:bg-blue-500/30 hover:border-blue-500 transition">View</a>
                                        <a href="{{ route('admin.markets.edit', $market) }}" class="rounded-full bg-amber-500/20 border border-amber-500/50 px-3 py-1.5 text-xs font-medium text-amber-300 hover:bg-amber-500/30 hover:border-amber-500 transition">Edit</a>
                                        <form method="POST" action="{{ route('admin.markets.destroy', $market) }}" class="inline" onsubmit="return confirm('Are you sure you want to delete this market?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-full bg-rose-500/20 border border-rose-500/50 px-3 py-1.5 text-xs font-medium text-rose-300 hover:bg-rose-500/30 hover:border-rose-500 transition">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-slate-400">No markets found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

