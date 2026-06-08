@extends('layouts.admin')

@section('title', 'Edit Customer | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.validation-errors-summary')

        <div class="space-y-2 text-left">
            <h1 class="text-3xl font-bold">Edit Customer</h1>
            <p class="text-sm text-slate-400">Product: <span class="font-semibold text-white">{{ $product->name ?? 'N/A' }} ({{ $product->code ?? 'N/A' }})</span></p>
        </div>

        @if ($product && $product->category === 'government')
            @include('admin.customers.forms.government-edit', ['customer' => $customer])
        @elseif ($product && $product->category === 'mou')
            @include('admin.customers.forms.mou-edit', ['customer' => $customer, 'companies' => $companies ?? collect(), 'relationshipManagers' => $relationshipManagers ?? collect()])
        @elseif ($product && $product->category === 'character')
            @include('admin.customers.forms.character-edit', ['customer' => $customer, 'customerGroups' => $customerGroups])
        @elseif ($product && $product->category === 'collateral')
            @include('admin.customers.forms.collateral-edit', ['customer' => $customer, 'customerGroups' => $customerGroups, 'referredByCustomers' => $referredByCustomers ?? collect()])
        @elseif ($product && $product->category === 'marketeer')
            @include('admin.customers.forms.marketeer-edit', ['customer' => $customer, 'markets' => $markets ?? collect()])
        @elseif ($product && $product->category === 'sme')
            @include('admin.customers.forms.sme-edit', ['customer' => $customer, 'companies' => $companies, 'companyCustomers' => $companyCustomers])
        @elseif ($product && $product->category === 'group_loans')
            @include('admin.customers.forms.group-loans-edit', ['customer' => $customer, 'customerGroups' => $customerGroups])
        @else
            <div class="rounded-3xl border border-amber-500/30 bg-amber-500/10 p-6">
                <p class="text-amber-300">Edit form for {{ $product->category ?? 'unknown' }} product type is not yet implemented.</p>
            </div>
        @endif
    </div>
@endsection
