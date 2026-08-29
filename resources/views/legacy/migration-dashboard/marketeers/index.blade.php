@extends('legacy.migration-dashboard.layout')

@section('dashboard-content')
    @include('partials.admin.page-header', ['title' => 'Marketeer Migration', 'description' => 'MARK-001 product, MRKT-LEGACY group, and market mappings.'])

    <div class="grid gap-4 md:grid-cols-3 mb-6">
        <div class="rounded-2xl border bg-white p-4 shadow-sm">
            <p class="text-xs uppercase text-slate-500">Product</p>
            <p class="font-bold text-primary">{{ $data['product']->code ?? 'MARK-001' }} — {{ $data['product']->name ?? '—' }}</p>
        </div>
        <div class="rounded-2xl border bg-white p-4 shadow-sm">
            <p class="text-xs uppercase text-slate-500">Group</p>
            <p class="font-bold text-primary">{{ $data['group']->code ?? 'MRKT-LEGACY' }} — {{ $data['group']->name ?? '—' }}</p>
        </div>
        <div class="rounded-2xl border bg-white p-4 shadow-sm">
            <p class="text-xs uppercase text-slate-500">Exceptions</p>
            <p class="font-bold text-primary">{{ $data['exceptions']->count() }}</p>
        </div>
    </div>

    <div class="rounded-2xl border bg-white p-6 shadow-sm mb-6 overflow-x-auto">
        <h2 class="font-semibold text-primary mb-3">Markets</h2>
        <table class="min-w-full text-sm">
            <thead><tr class="border-b bg-slate-100 text-xs uppercase"><th class="px-3 py-2 text-left">Legacy Market ID</th><th class="px-3 py-2 text-left">Target Market</th><th class="px-3 py-2 text-left">Method</th></tr></thead>
            <tbody>
                @forelse($data['markets'] as $market)
                    <tr class="border-b">
                        <td class="px-3 py-2">{{ $market['legacy_market_id'] }}</td>
                        <td class="px-3 py-2">#{{ $market['target_market_id'] }} {{ $market['target_market_name'] }}</td>
                        <td class="px-3 py-2">{{ $market['mapping_method'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="py-4 text-center text-slate-500">No market maps yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="rounded-2xl border bg-white p-6 shadow-sm overflow-x-auto">
        <h2 class="font-semibold text-primary mb-3">Marketeer customers</h2>
        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-b bg-slate-100 text-xs uppercase">
                    <th class="px-3 py-2 text-left">Customer</th>
                    <th class="px-3 py-2 text-left">company_id</th>
                    <th class="px-3 py-2 text-left">Market mapped</th>
                    <th class="px-3 py-2 text-left">Group mapped</th>
                    <th class="px-3 py-2 text-left">Legacy market</th>
                    <th class="px-3 py-2 text-left">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['customers'] as $customer)
                    <tr class="border-b {{ ($customer['company_linked_incorrectly'] || ! $customer['market_mapped']) ? 'bg-amber-50' : '' }}">
                        <td class="px-3 py-2">#{{ $customer['customer_id'] }} {{ $customer['name'] }}</td>
                        <td class="px-3 py-2">{{ $customer['company_id'] === null ? 'null ✓' : $customer['company_id'].' ⚠' }}</td>
                        <td class="px-3 py-2">{{ $customer['market_mapped'] ? 'Yes' : 'No' }}</td>
                        <td class="px-3 py-2">{{ $customer['group_mapped'] ? 'Yes' : 'No' }}</td>
                        <td class="px-3 py-2">{{ $customer['legacy_market_id'] ?? '—' }}</td>
                        <td class="px-3 py-2">
                            @if($customer['company_linked_incorrectly'])
                                @include('legacy.migration-dashboard.partials.badge', ['label' => 'COMPANY_LINKED', 'tone' => 'amber'])
                            @elseif(! $customer['market_mapped'])
                                @include('legacy.migration-dashboard.partials.badge', ['label' => 'MARKET_MISSING', 'tone' => 'amber'])
                            @else
                                @include('legacy.migration-dashboard.partials.badge', ['label' => 'OK', 'tone' => 'green'])
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
