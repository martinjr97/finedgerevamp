@extends('layouts.admin')

@section('title', 'Creditor Details | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Creditor Details',
            'buttons' => [
                [
                    'action' => 'edit',
                    'text' => 'Edit Creditor',
                    'href' => route('admin.creditors.edit', $creditor),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>'
                ]
            ]
        ])

        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
            <div class="grid grid-cols-2 gap-6">
                <div>
                    <label class="text-sm text-slate-400">Name</label>
                    <p class="text-white font-medium">{{ $creditor->name }}</p>
                </div>
                <div>
                    <label class="text-sm text-slate-400">Amount</label>
                    <p class="text-white font-semibold text-lg">{{ number_format($creditor->amount, 2) }}</p>
                </div>
                @if($creditor->due_date)
                <div>
                    <label class="text-sm text-slate-400">Due Date</label>
                    <p class="text-white font-medium">{{ $creditor->due_date->format('F d, Y') }}</p>
                </div>
                @endif
                <div>
                    <label class="text-sm text-slate-400">Status</label>
                    <p>
                        <span class="rounded-full px-2 py-1 text-xs {{ $creditor->is_active ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300' }}">
                            {{ $creditor->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </p>
                </div>
                @if($creditor->description)
                <div class="col-span-2">
                    <label class="text-sm text-slate-400">Description</label>
                    <p class="text-white">{{ $creditor->description }}</p>
                </div>
                @endif
                @if($creditor->notes)
                <div class="col-span-2">
                    <label class="text-sm text-slate-400">Notes</label>
                    <p class="text-white">{{ $creditor->notes }}</p>
                </div>
                @endif
            </div>
        </div>
    </div>
@endsection

