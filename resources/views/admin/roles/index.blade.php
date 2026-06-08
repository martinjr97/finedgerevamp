@extends('layouts.admin')

@section('title', 'Roles | '.config('app.system_name'))
@section('page-title', 'Roles & Permissions')

@section('content')
    <div class="flex flex-col gap-6">
        @include('partials.admin.page-header', [
            'title' => 'Roles Overview',
            'buttons' => [
                [
                    'action' => 'create',
                    'text' => 'New Role',
                    'href' => route('admin.roles.create'),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>'
                ]
            ]
        ])

        <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($roles as $role)
                <article class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-[13px] uppercase tracking-[0.3em] text-slate-500">{{ $role->guard_name }}</p>
                            <h2 class="text-2xl font-semibold text-white">{{ $role->name }}</h2>
                        </div>
                        <span class="rounded-full bg-white/10 px-3 py-1 text-xs text-slate-300">
                            {{ $role->users_count }} admins
                        </span>
                    </div>
                    <p class="text-sm text-slate-400">
                        {{ $role->permissions()->count() }} permissions
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('admin.roles.edit', $role) }}" class="inline-flex items-center gap-2 rounded-2xl border border-white/10 px-3 py-2 text-sm text-white hover:bg-white/10">
                            Manage
                        </a>
                        @if ($role->name !== \App\Support\PermissionMatrix::SUPER_ADMIN_ROLE)
                            <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" onsubmit="return confirm('Delete this role?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="inline-flex items-center gap-2 rounded-2xl border border-rose-500/40 px-3 py-2 text-sm text-rose-300 hover:bg-rose-500/10">
                                    Delete
                                </button>
                            </form>
                        @endif
                    </div>
                </article>
            @empty
                <div class="col-span-full flex flex-col items-center justify-center rounded-3xl border border-dashed border-white/10 bg-white/5 p-10 text-center">
                    <p class="text-lg text-slate-300">No roles yet.</p>
                    <p class="text-sm text-slate-500 mt-2">Create your first role to start assigning permissions.</p>
                </div>
            @endforelse
        </div>
    </div>
@endsection

