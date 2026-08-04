@extends('layouts.admin')

@section('title', 'Loan Rate Types | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Loan Rate Types',
            'description' => 'Manage interest rates and fees for loan products',
            'buttons' => array_filter([
                auth('admin')->user()?->can('loan-rate-types.create') ? [
                    'action' => 'create',
                    'text' => 'Create Rate Type',
                    'href' => route('admin.loan-rate-types.create'),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>'
                ] : null,
            ])
        ])

        <div class="admin-data-table">
            <table
                data-datatable="true"
                data-datatable-per-page="10"
                data-datatable-search-placeholder="Search rate types…"
                class="min-w-full w-full"
            >
                    <thead>
                        <tr class="font-semibold uppercase text-white/80 text-center">
                            <th scope="col">Name</th>
                            <th scope="col">Code</th>
                            <th scope="col">Loan Product</th>
                            <th scope="col">Rate Entry</th>
                            <th scope="col">Rates Count</th>
                            <th scope="col">Status</th>
                            <th data-sortable="false" scope="col" class="admin-data-table__actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rateTypes as $rateType)
                            <tr class="text-center">
                                <td class="font-medium text-white">{{ $rateType->name }}</td>
                                <td>{{ $rateType->code }}</td>
                                <td>
                                    <span class="text-sm text-cyan-300">
                                        {{ $rateType->loanProduct->name }}
                                    </span>
                                </td>
                                <td>
                                    <span class="text-sm text-purple-300">
                                        {{ app(\App\Services\LoanRateRowService::class)->rateEntryMethodLabel($rateType->rate_input_mode) }}
                                    </span>
                                </td>
                                <td>{{ $rateType->loanRates->count() }}</td>
                                <td>
                                    <span class="text-sm font-medium {{ $rateType->is_active ? 'text-emerald-400' : 'text-rose-400' }}">
                                        {{ $rateType->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="inline-flex items-center gap-3">
                                        @can('loan-rate-types.view')
                                            <a href="{{ route('admin.loan-rate-types.show', $rateType) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-blue-400/50 bg-blue-500/10 px-2.5 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-500/20 transition">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                </svg>
                                                View
                                            </a>
                                        @endcan
                                        @can('loan-rate-types.update')
                                            <a href="{{ route('admin.loan-rate-types.edit', $rateType) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-purple-400/50 bg-purple-500/10 px-2.5 py-1 text-xs font-semibold text-purple-700 hover:bg-purple-500/20 transition">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                </svg>
                                                Edit
                                            </a>
                                        @endcan
                                        @can('loan-rate-types.delete')
                                            @if(($rateTypeDeletable[$rateType->id]['allowed'] ?? false))
                                                <form method="POST" action="{{ route('admin.loan-rate-types.destroy', $rateType) }}" class="inline" onsubmit="return confirm('Delete this rate type and all its rate rows?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl border-2 border-rose-400/70 bg-rose-500/20 px-4 py-2 text-base font-semibold text-rose-200 hover:bg-rose-500/40 transition">
                                                        Delete
                                                    </button>
                                                </form>
                                            @endif
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-8 text-center text-slate-400">No loan rate types found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
        </div>
    </div>
@endsection

