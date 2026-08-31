@php
    $links = [
        ['label' => 'Overview', 'route' => 'legacy.migration-dashboard.index'],
        ['label' => 'Runs', 'route' => 'legacy.migration-dashboard.runs.index'],
        ['label' => 'Mappings', 'route' => 'legacy.migration-dashboard.mappings.index'],
        ['label' => 'Companies', 'route' => 'legacy.migration-dashboard.companies.index'],
        ['label' => 'Marketeers', 'route' => 'legacy.migration-dashboard.marketeers.index'],
        ['label' => 'Customers', 'route' => 'legacy.migration-dashboard.customers.index'],
        ['label' => 'Identity', 'route' => 'legacy.migration-dashboard.identity.index'],
        ['label' => 'Loans', 'route' => 'legacy.migration-dashboard.loans.index'],
        ['label' => 'Repayments', 'route' => 'legacy.migration-dashboard.repayments.index'],
        ['label' => 'Exceptions', 'route' => 'legacy.migration-dashboard.exceptions.index'],
        ['label' => 'Reconciliation', 'route' => 'legacy.migration-dashboard.reconciliation.index'],
        ['label' => 'Commands', 'route' => 'legacy.migration-dashboard.commands.index'],
    ];
@endphp

<nav class="flex flex-wrap gap-2 border-b border-slate-200 pb-4" aria-label="Migration dashboard sections">
    @foreach($links as $link)
        @php $active = request()->routeIs($link['route'].'*') || request()->routeIs($link['route']); @endphp
        <a
            href="{{ route($link['route']) }}"
            class="rounded-xl px-3 py-1.5 text-xs font-semibold transition {{ $active ? 'bg-primary text-white' : 'bg-slate-100 text-primary hover:bg-slate-200' }}"
        >
            {{ $link['label'] }}
        </a>
    @endforeach
    <a href="{{ url()->current() }}?{{ http_build_query(array_merge(request()->query(), ['_refresh' => time()])) }}"
       class="ml-auto rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-primary hover:bg-slate-50">
        Refresh
    </a>
</nav>
