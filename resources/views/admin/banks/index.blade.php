@extends('layouts.admin')

@section('title', 'Banks | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Banks',
            'buttons' => [
                [
                    'action' => 'create',
                    'text' => 'Add Bank',
                    'href' => route('admin.banks.create'),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>',
                    'can' => auth('admin')->user()?->can('banks.create')
                ]
            ]
        ])

        <div class="rounded-3xl border border-white/10 bg-white/5 p-4 shadow-lg">
            <div class="overflow-x-auto">
                <table data-datatable="true" data-datatable-per-page="10" class="min-w-full w-full text-base text-slate-300">
                    <thead>
                        <tr class="text-base font-semibold uppercase tracking-[0.25em] text-white/80 text-center border-b-2 border-white/20">
                            <th class="px-4 py-4 text-lg border-r border-white/10">Name</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Account Number</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Bank Name</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Currency</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Current Balance</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Status</th>
                            <th class="px-4 py-4 text-lg">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($banks as $bank)
                            <tr class="border-t border-white/40 text-center hover:bg-white/5 transition">
                                <td class="px-4 py-4 font-medium text-white border-r border-white/5">{{ $bank->name }}</td>
                                <td class="px-4 py-4 border-r border-white/5">{{ $bank->account_number }}</td>
                                <td class="px-4 py-4 border-r border-white/5">{{ $bank->bank_name }}</td>
                                <td class="px-4 py-4 border-r border-white/5">{{ $bank->currency }}</td>
                                <td class="px-4 py-4 font-semibold border-r border-white/5 {{ $bank->current_balance >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                                    {{ number_format($bank->current_balance, 2) }}
                                </td>
                                <td class="px-4 py-4 border-r border-white/5">
                                    <span class="text-sm font-medium {{ $bank->is_active ? 'text-emerald-400' : 'text-rose-400' }}">
                                        {{ $bank->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="inline-flex items-center gap-3">
                                        @can('banks.view')
                                        <a href="{{ route('admin.banks.show', $bank) }}" class="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-blue-500/40 to-purple-500/40 border-2 border-blue-400/70 px-4 py-2 text-base font-semibold text-blue-200 hover:from-blue-500/60 hover:to-purple-500/60 hover:border-blue-400 hover:text-white transition shadow-md shadow-blue-500/20">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                            View
                                        </a>
                                        @endcan
                                        @can('banks.update')
                                        <a href="{{ route('admin.banks.edit', $bank) }}" class="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-purple-500/40 to-indigo-500/40 border-2 border-purple-400/70 px-4 py-2 text-base font-semibold text-purple-200 hover:from-purple-500/60 hover:to-indigo-500/60 hover:border-purple-400 hover:text-white transition shadow-md shadow-purple-500/20">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                            Edit
                                        </a>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-slate-400">No banks found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

