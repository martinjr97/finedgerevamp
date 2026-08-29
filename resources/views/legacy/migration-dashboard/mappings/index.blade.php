@extends('legacy.migration-dashboard.layout')

@section('dashboard-content')
    @include('partials.admin.page-header', ['title' => 'Reference Mappings', 'description' => 'Entity maps from migration runs.'])

    <div class="mb-4 flex flex-wrap gap-2">
        @foreach($types as $typeKey => $typeLabel)
            <a href="{{ route('legacy.migration-dashboard.mappings.index', ['type' => $typeKey]) }}"
               class="rounded-xl px-3 py-1.5 text-xs font-semibold {{ $type === $typeKey ? 'bg-primary text-white' : 'bg-slate-100 text-primary' }}">
                {{ $typeLabel }}
            </a>
        @endforeach
    </div>

    <form method="GET" class="mb-4 flex gap-2">
        <input type="hidden" name="type" value="{{ $type }}">
        <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Legacy or target ID" class="rounded-xl border px-3 py-2 text-sm">
        <button class="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white">Search</button>
    </form>

    <div class="rounded-2xl border bg-white p-6 shadow-sm overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-b bg-slate-100 text-left text-xs uppercase tracking-wide text-slate-700">
                    <th class="px-3 py-2">Legacy ID</th>
                    <th class="px-3 py-2">Secondary</th>
                    <th class="px-3 py-2">Target ID</th>
                    <th class="px-3 py-2">Target Name</th>
                    <th class="px-3 py-2">Method</th>
                    <th class="px-3 py-2">Confidence</th>
                    <th class="px-3 py-2">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($maps as $map)
                    <tr class="border-b hover:bg-slate-50">
                        <td class="px-3 py-2">{{ $map->legacy_id }}</td>
                        <td class="px-3 py-2">{{ $map->legacy_secondary ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $map->target_id }}</td>
                        <td class="px-3 py-2">{{ $map->target_name ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $map->mapping_method }}</td>
                        <td class="px-3 py-2">{{ $map->confidence }}</td>
                        <td class="px-3 py-2">{{ $map->status }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-3 py-6 text-center text-slate-500">No maps for this entity type.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $maps->withQueryString()->links() }}</div>
    </div>

    <p class="mt-4 text-xs text-slate-500">Treasury bank and wallet provider records are reference mappings only — not customer account numbers.</p>
@endsection
