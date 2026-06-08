@extends('layouts.admin')

@section('title', 'Backups | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Database Backups',
            'description' => 'Generate SQL backup archives and download them for safe off-site storage.',
        ])

        <div class="rounded-3xl border border-white/10 bg-white p-6 shadow-lg space-y-4">
            <h2 class="text-lg font-semibold text-primary">Create Backup</h2>
            <p class="text-sm text-muted">
                This action creates a full database SQL dump and packages it into a ZIP archive.
            </p>

            @if(auth('admin')->user()?->can('backups.create'))
                <form method="POST" action="{{ route('admin.backups.store') }}">
                    @csrf
                    <button
                        type="submit"
                        class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white hover:opacity-90 transition"
                    >
                        Generate Backup
                    </button>
                </form>
            @else
                <p class="text-sm text-muted">You do not have permission to generate backups.</p>
            @endif
        </div>

        <div class="rounded-3xl border border-white/10 bg-white p-6 shadow-lg space-y-4">
            <h2 class="text-lg font-semibold text-primary">Uploads Backup</h2>
            <p class="text-sm text-muted">
                Download a ZIP archive containing all uploaded documents and media files.
            </p>

            @if(auth('admin')->user()?->can('backups.download'))
                <a
                    href="{{ route('admin.system.backup.uploads') }}"
                    class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white hover:opacity-90 transition"
                >
                    Download Uploads Backup
                </a>
            @else
                <p class="text-sm text-muted">You do not have permission to download upload backups.</p>
            @endif
        </div>

        <div class="rounded-3xl border border-white/10 bg-white p-6 shadow-lg">
            <h2 class="text-lg font-semibold text-primary mb-4">Available Backup Archives</h2>

            @if($backups->isEmpty())
                <p class="text-sm text-muted">No backup archives are available yet.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full w-full text-sm">
                        <thead>
                            <tr class="bg-slate-100 border-b border-slate-300 text-left">
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.2em] text-slate-800">Filename</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.2em] text-slate-800">Created</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.2em] text-slate-800">Size</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.2em] text-slate-800">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($backups as $backup)
                                <tr class="border-t border-slate-200">
                                    <td class="px-4 py-3 font-medium text-primary">{{ $backup['filename'] }}</td>
                                    <td class="px-4 py-3 text-primary">
                                        {{ $backup['created_at']->format('d M Y H:i:s') }}
                                    </td>
                                    <td class="px-4 py-3 text-primary">{{ $backup['size_human'] }}</td>
                                    <td class="px-4 py-3">
                                        @if(auth('admin')->user()?->can('backups.download'))
                                            <div class="flex items-center gap-2">
                                                <a
                                                    href="{{ route('admin.backups.download', ['filename' => $backup['filename']]) }}"
                                                    class="inline-flex items-center gap-2 rounded-xl border border-primary/30 bg-primary/5 px-3 py-1.5 text-xs font-semibold text-primary hover:bg-primary/10 transition"
                                                >
                                                    Download ZIP
                                                </a>
                                                <form
                                                    method="POST"
                                                    action="{{ route('admin.backups.destroy', ['filename' => $backup['filename']]) }}"
                                                    onsubmit="return confirm('Delete this backup archive?');"
                                                >
                                                    @csrf
                                                    @method('DELETE')
                                                    <button
                                                        type="submit"
                                                        class="inline-flex items-center gap-2 rounded-xl border border-red-300 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100 transition"
                                                    >
                                                        Delete
                                                    </button>
                                                </form>
                                            </div>
                                        @else
                                            <span class="text-xs text-muted">No download permission</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
