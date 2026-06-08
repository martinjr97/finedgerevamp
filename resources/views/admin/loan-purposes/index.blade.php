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

        <div class="rounded-3xl border border-white/10 bg-white/5 p-4 shadow-lg">
            <div class="overflow-x-auto">
                <table data-datatable="true" class="min-w-full w-full text-base text-slate-300">
                    <thead>
                        <tr class="text-base font-semibold uppercase tracking-[0.25em] text-white/80 text-center border-b-2 border-white/20">
                            <th class="px-4 py-4 text-lg border-r border-white/10">Name</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Sort Order</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Status</th>
                            <th class="px-4 py-4 text-lg">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($loanPurposes as $loanPurpose)
                            <tr class="border-t border-white/40 text-center hover:bg-white/5 transition">
                                <td class="px-4 py-4 font-medium text-white border-r border-white/5">{{ $loanPurpose->name }}</td>
                                <td class="px-4 py-4 border-r border-white/5">{{ $loanPurpose->sort_order }}</td>
                                <td class="px-4 py-4 border-r border-white/5">
                                    <span class="text-sm font-medium {{ $loanPurpose->is_active ? 'text-emerald-400' : 'text-rose-400' }}">
                                        {{ $loanPurpose->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="inline-flex items-center gap-3">
                                        <a href="{{ route('admin.loan-purposes.edit', $loanPurpose) }}" class="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-purple-500/40 to-indigo-500/40 border-2 border-purple-400/70 px-4 py-2 text-base font-semibold text-purple-200 hover:from-purple-500/60 hover:to-indigo-500/60 hover:border-purple-400 hover:text-white transition shadow-md shadow-purple-500/20">
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
                                <td colspan="4" class="px-4 py-8 text-center text-slate-400">No loan purposes found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
