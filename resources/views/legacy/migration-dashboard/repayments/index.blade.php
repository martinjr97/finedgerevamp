@extends('legacy.migration-dashboard.layout')

@section('dashboard-content')
    @include('partials.admin.page-header', ['title' => 'Repayment Migration', 'description' => 'Attribution classes and allocation detail.'])

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        @foreach($classifications as $class)
            <a href="{{ route('legacy.migration-dashboard.repayments.index', ['classification' => $class]) }}" class="rounded-2xl border bg-white p-4 shadow-sm hover:border-primary">
                <p class="text-xs uppercase text-slate-500">{{ $class }}</p>
                <p class="text-xl font-bold text-primary">{{ number_format($classificationCounts[$class] ?? 0) }}</p>
            </a>
        @endforeach
    </div>

    @if(!empty($dManualBreakdown))
        <div class="mb-6 rounded-2xl border bg-white p-6 shadow-sm">
            <h2 class="font-semibold text-primary mb-3">D_MANUAL breakdown</h2>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 text-sm">
                @foreach($dManualBreakdown as $subclass => $stats)
                    <div class="rounded-xl bg-slate-50 p-3">
                        <p class="text-xs font-semibold uppercase">{{ $subclass }}</p>
                        <p>{{ number_format($stats['count']) }} records</p>
                        <p>{{ \App\Migration\Dashboard\MigrationDashboardSupport::formatZmw($stats['amount']) }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <form method="GET" class="mb-4 flex flex-wrap gap-2">
        <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Legacy repayment / user / target ID" class="rounded-xl border px-3 py-2 text-sm">
        <select name="classification" class="rounded-xl border px-3 py-2 text-sm">
            <option value="">All classes</option>
            @foreach($classifications as $class)
                <option value="{{ $class }}" @selected(($filters['classification'] ?? '') === $class)>{{ $class }}</option>
            @endforeach
        </select>
        <button class="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white">Filter</button>
    </form>

    <div class="rounded-2xl border bg-white p-6 shadow-sm overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-b bg-slate-100 text-left text-xs uppercase tracking-wide text-slate-700">
                    <th class="px-3 py-2">Legacy ID</th>
                    <th class="px-3 py-2">User</th>
                    <th class="px-3 py-2">Amount</th>
                    <th class="px-3 py-2">Class</th>
                    <th class="px-3 py-2">Status</th>
                    <th class="px-3 py-2">Target</th>
                    <th class="px-3 py-2">Exception</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($repayments as $row)
                    <tr class="border-b hover:bg-slate-50 {{ $row->attribution_class === 'C_AMBIGUOUS' ? 'bg-amber-50' : '' }}">
                        <td class="px-3 py-2">{{ $row->legacy_repayment_id }}</td>
                        <td class="px-3 py-2">{{ $row->legacy_user_id }}</td>
                        <td class="px-3 py-2">{{ \App\Migration\Dashboard\MigrationDashboardSupport::formatZmw($row->repayment_amount) }}</td>
                        <td class="px-3 py-2">{{ $row->attribution_class }}</td>
                        <td class="px-3 py-2">{{ $row->migration_status }}</td>
                        <td class="px-3 py-2">{{ $row->mapped_repayment_id ?? '—' }}</td>
                        <td class="px-3 py-2 text-xs">{{ $row->exception ?? '—' }}</td>
                        <td class="px-3 py-2"><a href="{{ route('legacy.migration-dashboard.repayments.show', $row->legacy_repayment_id) }}" class="font-semibold text-primary hover:underline">Detail</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-3 py-6 text-center text-slate-500">No repayment records.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $repayments->withQueryString()->links() }}</div>
    </div>
@endsection
