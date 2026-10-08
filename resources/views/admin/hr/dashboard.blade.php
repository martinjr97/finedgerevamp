@extends('layouts.admin')

@section('title', 'HR Dashboard | '.config('app.system_name'))

@section('content')
<div class="space-y-8">
    @include('partials.admin.page-header', ['title' => 'HR Dashboard', 'description' => 'Workforce overview, contracts, and leave activity.'])

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ([
            ['Total Employees', $totalEmployees, 'text-white'],
            ['Active Employees', $activeEmployees, 'text-emerald-300'],
            ['On Leave Today', $onLeaveToday, 'text-cyan-300'],
            ['Departments', $departmentCount, 'text-white'],
            ['Pending Leave', $pendingLeave, 'text-amber-300'],
            ['Contracts ≤30 days', $expiring30, 'text-rose-300'],
        ] as [$label, $value, $color])
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4 shadow-lg">
                <p class="text-xs uppercase tracking-wide text-slate-400">{{ $label }}</p>
                <p class="mt-2 text-2xl font-bold {{ $color }}">{{ number_format($value) }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <h2 class="text-lg font-semibold text-white mb-4">Contracts expiring (30 / 60 / 90 days)</h2>
            <p class="text-sm text-slate-300">{{ $expiring30 }} / {{ $expiring60 }} / {{ $expiring90 }}</p>
            <ul class="mt-4 space-y-2 text-sm">
                @forelse ($expiringContracts as $contract)
                    <li class="flex justify-between border-b border-white/5 pb-2">
                        <span>{{ $contract->employee?->full_name }} · {{ $contract->contractType?->name }}</span>
                        <span class="text-rose-300">{{ $contract->end_date?->format('M d, Y') }}</span>
                    </li>
                @empty
                    <li class="text-slate-400">No contracts expiring in the next 90 days.</li>
                @endforelse
            </ul>
        </div>
        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <h2 class="text-lg font-semibold text-white mb-4">Pending leave approvals</h2>
            <ul class="space-y-2 text-sm">
                @forelse ($pendingApplications as $app)
                    <li>{{ $app->employee?->full_name }} · {{ $app->leaveType?->name }} · {{ $app->days_requested }} days</li>
                @empty
                    <li class="text-slate-400">No pending applications.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection
