@extends('layouts.admin')

@section('title', 'Collateral Types | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Collateral Types - ' . $loanProduct->name,
            'description' => 'Manage collateral types and value ranges for this loan product',
            'buttons' => [
                [
                    'action' => 'secondary',
                    'text' => 'Back to Product',
                    'href' => route('admin.loan-products.show', $loanProduct),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>'
                ],
                [
                    'action' => 'primary',
                    'text' => 'Add Collateral Type',
                    'href' => route('admin.loan-products.collateral-types.create', $loanProduct),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>'
                ]
            ]
        ])

        @if($collateralTypes->count() > 0)
            <div class="admin-data-table">
                <table
                    data-datatable="true"
                    data-datatable-per-page="10"
                    data-datatable-search-placeholder="Search collateral types…"
                    class="min-w-full w-full"
                >
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Code</th>
                            <th scope="col">Category</th>
                            <th scope="col">Value Range</th>
                            <th scope="col">Loan to Value</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="admin-data-table__actions" data-sortable="false">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($collateralTypes as $collateralType)
                            <tr>
                                <td class="font-medium">{{ $collateralType->name }}</td>
                                <td class="font-mono text-sm">{{ $collateralType->code }}</td>
                                <td>
                                    <span class="rounded-full bg-cyan-500/20 px-2 py-1 text-xs text-cyan-700">
                                        {{ $collateralType->category }}
                                    </span>
                                </td>
                                <td>
                                    @if($collateralType->min_value || $collateralType->max_value)
                                        <div class="text-xs">
                                            @if($collateralType->min_value)
                                                <div>Min: ZMW {{ number_format($collateralType->min_value, 2) }}</div>
                                            @endif
                                            @if($collateralType->max_value)
                                                <div>Max: ZMW {{ number_format($collateralType->max_value, 2) }}</div>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-[var(--color-muted)]">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($collateralType->loan_to_value_ratio)
                                        <span>{{ number_format($collateralType->loan_to_value_ratio, 2) }}%</span>
                                    @else
                                        <span class="text-[var(--color-muted)]">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="{{ $collateralType->is_active ? 'status-pill status-pill-active' : 'status-pill status-pill-inactive' }}">
                                        {{ $collateralType->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="admin-data-table__actions text-center">
                                    <div class="inline-flex items-center justify-center gap-1.5">
                                        <a href="{{ route('admin.loan-products.collateral-types.show', [$loanProduct, $collateralType]) }}"
                                           class="inline-flex items-center rounded-lg border border-blue-400/50 bg-blue-500/10 px-2.5 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-500/20 transition">
                                            View
                                        </a>
                                        <a href="{{ route('admin.loan-products.collateral-types.edit', [$loanProduct, $collateralType]) }}"
                                           class="inline-flex items-center rounded-lg border border-amber-400/50 bg-amber-500/10 px-2.5 py-1 text-xs font-semibold text-amber-700 hover:bg-amber-500/20 transition">
                                            Edit
                                        </a>
                                        <form method="POST"
                                              action="{{ route('admin.loan-products.collateral-types.destroy', [$loanProduct, $collateralType]) }}"
                                              class="inline"
                                              onsubmit="return confirm('Are you sure you want to delete this collateral type?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="inline-flex items-center rounded-lg border border-rose-400/50 bg-rose-500/10 px-2.5 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-500/20 transition">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="admin-data-table">
                <div class="px-4 py-8 text-center text-[var(--color-muted)]">
                    <p class="mb-4">No collateral types have been created for this product yet.</p>
                    <a href="{{ route('admin.loan-products.collateral-types.create', $loanProduct) }}"
                       class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-brand)] px-4 py-2 text-sm font-semibold text-white hover:opacity-90 transition">
                        Create First Collateral Type
                    </a>
                </div>
            </div>
        @endif
    </div>
@endsection

