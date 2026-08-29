@extends('layouts.admin')

@section('title', ($pageTitle ?? 'Migration Dashboard').' | '.config('app.system_name'))

@section('content')
    @php
        $env = \App\Migration\Dashboard\MigrationDashboardSupport::environmentLabel();
        $isProduction = \App\Migration\Dashboard\MigrationDashboardSupport::isProductionEnvironment();
    @endphp

    <div class="space-y-6">
        <div class="rounded-2xl border px-4 py-3 text-sm font-semibold {{ $isProduction ? 'border-rose-500 bg-rose-50 text-rose-900' : 'border-amber-400 bg-amber-50 text-amber-900' }}">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span>TEMPORARY LEGACY MIGRATION TOOL</span>
                <span class="rounded-lg px-2 py-0.5 text-xs uppercase tracking-wider {{ $isProduction ? 'bg-rose-200 text-rose-900' : 'bg-amber-200 text-amber-900' }}">
                    {{ $env }}
                </span>
            </div>
            @if($isProduction)
                <p class="mt-2 text-xs font-normal">Production environment — read-only monitoring only. Migration commands must be run via CLI.</p>
            @endif
        </div>

        @include('legacy.migration-dashboard.partials.subnav')

        @yield('dashboard-content')
    </div>
@endsection
