@extends('layouts.admin')

@section('title', 'Transaction Details | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Transaction Details',
            'buttons' => []
        ])

        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
            <div class="grid grid-cols-2 gap-6">
                <div>
                    <label class="text-sm text-slate-400">Transaction Number</label>
                    <p class="text-white font-mono font-semibold">{{ $financialTransaction->transaction_number }}</p>
                </div>
                <div>
                    <label class="text-sm text-slate-400">Date</label>
                    <p class="text-white font-medium">{{ $financialTransaction->transaction_date->format('F d, Y') }}</p>
                </div>
                <div>
                    <label class="text-sm text-slate-400">Type</label>
                    <p>
                        <span class="rounded-full px-2 py-1 text-xs {{ $financialTransaction->type === 'income' ? 'bg-emerald-500/20 text-emerald-300' : ($financialTransaction->type === 'expense' ? 'bg-rose-500/20 text-rose-300' : 'bg-blue-500/20 text-blue-300') }}">
                            {{ ucfirst($financialTransaction->type) }}
                        </span>
                    </p>
                </div>
                <div>
                    <label class="text-sm text-slate-400">Category</label>
                    <p class="text-white capitalize">{{ str_replace('_', ' ', $financialTransaction->category ?? '—') }}</p>
                </div>
                <div>
                    <label class="text-sm text-slate-400">Amount</label>
                    <p class="text-white font-semibold text-2xl {{ $financialTransaction->type === 'income' ? 'text-emerald-400' : ($financialTransaction->type === 'expense' ? 'text-rose-400' : 'text-blue-400') }}">
                        {{ $financialTransaction->type === 'expense' ? '-' : ($financialTransaction->type === 'transfer' ? '±' : '+') }}{{ number_format($financialTransaction->amount, 2) }}
                    </p>
                </div>
                @if($financialTransaction->reference_number)
                <div>
                    <label class="text-sm text-slate-400">Reference Number</label>
                    <p class="text-white font-mono">{{ $financialTransaction->reference_number }}</p>
                </div>
                @endif
                @if($financialTransaction->source)
                <div>
                    <label class="text-sm text-slate-400">Source</label>
                    <p class="text-white">{{ $financialTransaction->source->name ?? '—' }}</p>
                </div>
                @endif
                @if($financialTransaction->destination)
                <div>
                    <label class="text-sm text-slate-400">Destination</label>
                    <p class="text-white">{{ $financialTransaction->destination->name ?? '—' }}</p>
                </div>
                @endif
                <div class="col-span-2">
                    <label class="text-sm text-slate-400">Description</label>
                    <p class="text-white">{{ $financialTransaction->description }}</p>
                </div>
                @if($financialTransaction->notes)
                <div class="col-span-2">
                    <label class="text-sm text-slate-400">Notes</label>
                    <p class="text-white">{{ $financialTransaction->notes }}</p>
                </div>
                @endif
                @if($financialTransaction->creator)
                <div>
                    <label class="text-sm text-slate-400">Created By</label>
                    <p class="text-white">{{ $financialTransaction->creator->full_name ?? $financialTransaction->creator->email }}</p>
                </div>
                @endif
                <div>
                    <label class="text-sm text-slate-400">Created At</label>
                    <p class="text-white">{{ $financialTransaction->created_at->format('F d, Y H:i') }}</p>
                </div>
            </div>
        </div>
    </div>
@endsection

