@extends('layouts.admin')

@section('title', 'Loan Purposes | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Loan Purposes',
            'description' => 'Manage the list of purposes customers can select when applying for a loan.',
            'buttons' => [
                [
                    'action' => 'create',
                    'text' => 'Add Loan Purpose',
                    'href' => route('admin.loan-purposes.create'),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>',
                ],
            ],
        ])

        <div class="admin-data-table">
            <table
                data-datatable="true"
                data-datatable-per-page="10"
                data-datatable-search-placeholder="Search purposes…"
                class="min-w-full w-full"
            >
                    <thead>
                        <tr class="font-semibold uppercase text-white/80 text-center">
                            <th scope="col">Name</th>
                            <th scope="col">Sort Order</th>
                            <th scope="col">Status</th>
                            <th data-sortable="false" scope="col" class="admin-data-table__actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($loanPurposes as $loanPurpose)
                            <tr class="text-center">
                                <td class="font-medium text-white">{{ $loanPurpose->name }}</td>
                                <td>{{ $loanPurpose->sort_order }}</td>
                                <td>
                                    <span class="text-sm font-medium {{ $loanPurpose->is_active ? 'text-emerald-400' : 'text-rose-400' }}">
                                        {{ $loanPurpose->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="inline-flex items-center gap-3">
                                        <a href="{{ route('admin.loan-purposes.edit', $loanPurpose) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-purple-400/50 bg-purple-500/10 px-2.5 py-1 text-xs font-semibold text-purple-700 hover:bg-purple-500/20 transition">
                                            Edit
                                        </a>
                                        @can('loan-purposes.delete')
                                            <form action="{{ route('admin.loan-purposes.destroy', $loanPurpose) }}" method="POST" onsubmit="return confirm('Delete this loan purpose?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl border border-rose-400/50 bg-rose-500/10 px-4 py-2 text-base font-semibold text-rose-200 hover:bg-rose-500/20 transition">
                                                    Delete
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-8 text-center text-slate-400">No loan purposes found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
        </div>
    </div>
@endsection
