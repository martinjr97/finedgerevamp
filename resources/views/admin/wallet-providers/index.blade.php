@extends('layouts.admin')

@section('title', 'Wallet Providers | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Wallet Providers',
            'description' => 'Mobile wallet provider list used for customer default payment details.',
            'buttons' => [
                [
                    'action' => 'create',
                    'text' => 'Add Provider',
                    'href' => route('admin.wallet-providers.create'),
                    'can' => auth('admin')->user()?->can('wallet-providers.create'),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>',
                ],
            ],
        ])

        @if(session('status'))
            <div class="rounded-2xl border border-emerald-400/60 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">
                {{ session('status') }}
            </div>
        @endif
        @if(session('error'))
            <div class="rounded-2xl border border-rose-500/40 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
                {{ session('error') }}
            </div>
        @endif

        <div class="rounded-3xl border border-white/10 bg-white/5 p-4 shadow-lg">
            <div class="overflow-x-auto">
                <table data-datatable="true" data-datatable-per-page="10" class="min-w-full w-full text-base text-slate-300">
                    <thead>
                        <tr class="text-base font-semibold uppercase tracking-[0.25em] text-white/80 text-center border-b-2 border-white/20">
                            <th class="px-4 py-4 text-lg border-r border-white/10">Name</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Code</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Status</th>
                            <th class="px-4 py-4 text-lg">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($providers as $provider)
                            <tr class="border-t border-white/40 text-center hover:bg-white/5 transition">
                                <td class="px-4 py-4 font-medium text-white border-r border-white/5">{{ $provider->name }}</td>
                                <td class="px-4 py-4 border-r border-white/5">
                                    <span class="text-sm text-cyan-300 font-mono">{{ $provider->code ?? '—' }}</span>
                                </td>
                                <td class="px-4 py-4 border-r border-white/5">
                                    <span class="text-sm font-medium {{ $provider->is_active ? 'text-emerald-400' : 'text-rose-400' }}">
                                        {{ $provider->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="inline-flex flex-wrap items-center justify-center gap-2">
                                        @can('wallet-providers.update')
                                            <a href="{{ route('admin.wallet-providers.edit', $provider) }}" class="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-purple-500/40 to-indigo-500/40 border-2 border-purple-400/70 px-3 py-1.5 text-sm font-semibold text-purple-200 hover:text-white transition">
                                                Edit
                                            </a>
                                        @endcan
                                        @can('wallet-providers.delete')
                                            <form method="POST" action="{{ route('admin.wallet-providers.destroy', $provider) }}" onsubmit="return confirm('Delete this wallet provider?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-rose-500/30 to-rose-500/20 border-2 border-rose-400/70 px-3 py-1.5 text-sm font-semibold text-rose-200 hover:text-white transition">
                                                    Delete
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-slate-400">No wallet providers found. Run the seeder or add one manually.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

