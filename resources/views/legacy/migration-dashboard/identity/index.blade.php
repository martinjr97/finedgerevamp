@extends('legacy.migration-dashboard.layout')

@section('dashboard-content')
    @include('partials.admin.page-header', [
        'title' => 'Identity Resolution',
        'description' => 'Resolve duplicate legacy NRC groups before customer migration. Clean groups can proceed; ambiguous groups need a decision here.',
    ])

    @if (session('status'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900 mb-6">{{ session('status') }}</div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <div class="rounded-2xl border bg-white p-4 shadow-sm">
            <p class="text-xs uppercase text-slate-500">Duplicate NRC groups</p>
            <p class="text-2xl font-bold text-primary">{{ number_format($summary['duplicate_nrc_groups']) }}</p>
        </div>
        <div class="rounded-2xl border bg-white p-4 shadow-sm">
            <p class="text-xs uppercase text-slate-500">Pending resolution</p>
            <p class="text-2xl font-bold {{ $summary['pending_groups'] > 0 ? 'text-amber-600' : 'text-emerald-600' }}">{{ number_format($summary['pending_groups']) }}</p>
        </div>
        <div class="rounded-2xl border bg-white p-4 shadow-sm">
            <p class="text-xs uppercase text-slate-500">Approved resolutions</p>
            <p class="text-2xl font-bold text-primary">{{ number_format($summary['approved_resolutions']) }}</p>
        </div>
        <div class="rounded-2xl border bg-white p-4 shadow-sm">
            <p class="text-xs uppercase text-slate-500">Customer promote gate</p>
            <p class="text-sm font-bold {{ $summary['ready_for_customer_promote'] ? 'text-emerald-600' : 'text-rose-600' }}">
                {{ $summary['ready_for_customer_promote'] ? 'Ready' : 'Blocked — resolve pending groups' }}
            </p>
        </div>
    </div>

    <div class="rounded-2xl border bg-white p-6 shadow-sm mb-6">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <h2 class="text-lg font-semibold text-primary">Pending duplicate NRC groups</h2>
            @unless($canManage)
                <span class="text-xs text-slate-500">Requires <code class="text-slate-700">migration.manage</code> to resolve</span>
            @endunless
        </div>

        @if (count($pendingGroups) === 0)
            <p class="text-sm text-slate-600">No pending duplicate NRC groups. Customer promotion is not blocked by identity resolution.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b bg-slate-100 text-left text-xs uppercase tracking-wide text-slate-700">
                            <th class="px-3 py-2">NRC</th>
                            <th class="px-3 py-2">Legacy users</th>
                            <th class="px-3 py-2">Members</th>
                            <th class="px-3 py-2">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pendingGroups as $group)
                            <tr class="border-b hover:bg-slate-50 align-top">
                                <td class="px-3 py-3 font-mono">{{ $group['nrc_masked'] }}</td>
                                <td class="px-3 py-3">{{ implode(', ', $group['legacy_user_ids']) }}</td>
                                <td class="px-3 py-3">
                                    <ul class="space-y-1 text-xs text-slate-600">
                                        @foreach ($group['members'] as $member)
                                            <li>
                                                <span class="font-semibold text-slate-800">#{{ $member['legacy_user_id'] }}</span>
                                                {{ $member['name'] }}
                                                · {{ $member['loan_count'] }} loans
                                            </li>
                                        @endforeach
                                    </ul>
                                </td>
                                <td class="px-3 py-3">
                                    @if ($canManage)
                                        <a href="{{ route('legacy.migration-dashboard.identity.resolve', $group['nrc_key']) }}"
                                           class="inline-flex items-center rounded-lg border border-cyan-400/50 bg-cyan-500/10 px-3 py-1.5 text-xs font-semibold text-cyan-800 hover:bg-cyan-500/20 transition">
                                            Resolve
                                        </a>
                                    @else
                                        <span class="text-xs text-slate-400">View only</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="rounded-2xl border bg-white p-6 shadow-sm overflow-x-auto">
        <h2 class="text-lg font-semibold text-primary mb-4">Approved resolutions</h2>
        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-b bg-slate-100 text-left text-xs uppercase tracking-wide text-slate-700">
                    <th class="px-3 py-2">NRC (masked)</th>
                    <th class="px-3 py-2">Legacy Users</th>
                    <th class="px-3 py-2">Primary</th>
                    <th class="px-3 py-2">Target Customer</th>
                    <th class="px-3 py-2">Decision</th>
                    <th class="px-3 py-2">Source</th>
                </tr>
            </thead>
            <tbody>
                @forelse($resolutions as $row)
                    <tr class="border-b hover:bg-slate-50">
                        <td class="px-3 py-2 font-mono">{{ $row['nrc'] }}</td>
                        <td class="px-3 py-2">{{ implode(', ', $row['legacy_users']) }}</td>
                        <td class="px-3 py-2">{{ $row['primary_legacy_user_id'] }}</td>
                        <td class="px-3 py-2">
                            @if($row['target_customer_id'])
                                <a href="{{ route('admin.customers.show', $row['target_customer_id']) }}" class="text-primary hover:underline">#{{ $row['target_customer_id'] }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-3 py-2 text-xs">{{ $row['classification_label'] }}</td>
                        <td class="px-3 py-2 text-xs text-slate-500">{{ $row['source'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-3 py-6 text-center text-slate-500">No approved identity resolutions yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
