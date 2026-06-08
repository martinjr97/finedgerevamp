@extends('layouts.admin')

@section('title', 'Transfer Details | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Transfer Details',
            'buttons' => []
        ])

        @if($transfer->approval_status === 'pending')
        <div class="rounded-3xl border border-amber-500/30 bg-amber-500/10 p-6 shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-amber-300 mb-1">Pending Approval</h3>
                    <p class="text-sm text-amber-200/80">This transfer requires approval before it can be processed.</p>
                </div>
                <span class="rounded-full bg-amber-500/20 px-4 py-2 text-sm font-medium text-amber-300 border border-amber-500/30">
                    Awaiting Approval
                </span>
            </div>
        </div>
        @endif

        @if($transfer->approval_status === 'rejected')
        <div class="rounded-3xl border border-rose-500/30 bg-rose-500/10 p-6 shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-rose-300 mb-1">Transfer Rejected</h3>
                    @if($transfer->approval_notes)
                        <p class="text-sm text-rose-200/80 mt-1">{{ $transfer->approval_notes }}</p>
                    @endif
                </div>
                <span class="rounded-full bg-rose-500/20 px-4 py-2 text-sm font-medium text-rose-300 border border-rose-500/30">
                    Rejected
                </span>
            </div>
        </div>
        @endif

        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
            <div class="grid grid-cols-2 gap-6">
                <div>
                    <label class="text-sm text-slate-400">Transaction Number</label>
                    <p class="text-white font-mono font-semibold">{{ $transfer->transaction_number }}</p>
                </div>
                <div>
                    <label class="text-sm text-slate-400">Date</label>
                    <p class="text-white font-medium">{{ $transfer->transaction_date->format('F d, Y') }}</p>
                </div>
                <div>
                    <label class="text-sm text-slate-400">From</label>
                    <p class="text-white font-medium">{{ $transfer->source->name ?? '—' }}</p>
                </div>
                <div>
                    <label class="text-sm text-slate-400">To</label>
                    <p class="text-white font-medium">{{ $transfer->destination->name ?? '—' }}</p>
                </div>
                <div>
                    <label class="text-sm text-slate-400">Amount</label>
                    <p class="text-white font-semibold text-2xl text-blue-400">{{ number_format($transfer->amount, 2) }}</p>
                </div>
                @if($transfer->approval_status)
                <div>
                    <label class="text-sm text-slate-400">Approval Status</label>
                    <p>
                        <span class="rounded-full px-2 py-1 text-xs {{ $transfer->approval_status === 'approved' ? 'bg-emerald-500/20 text-emerald-300' : ($transfer->approval_status === 'pending' ? 'bg-amber-500/20 text-amber-300' : 'bg-rose-500/20 text-rose-300') }}">
                            {{ ucfirst($transfer->approval_status) }}
                        </span>
                    </p>
                </div>
                @endif
                @if($transfer->reference_number)
                <div>
                    <label class="text-sm text-slate-400">Reference Number</label>
                    <p class="text-white font-mono">{{ $transfer->reference_number }}</p>
                </div>
                @endif
                <div class="col-span-2">
                    <label class="text-sm text-slate-400">Description</label>
                    <p class="text-white">{{ $transfer->description }}</p>
                </div>
                @if($transfer->notes)
                <div class="col-span-2">
                    <label class="text-sm text-slate-400">Notes</label>
                    <p class="text-white">{{ $transfer->notes }}</p>
                </div>
                @endif
                @if($transfer->approval_notes)
                <div class="col-span-2">
                    <label class="text-sm text-slate-400">Approval Notes</label>
                    <p class="text-white">{{ $transfer->approval_notes }}</p>
                </div>
                @endif
                @if($transfer->creator)
                <div>
                    <label class="text-sm text-slate-400">Created By</label>
                    <p class="text-white">{{ $transfer->creator->full_name ?? $transfer->creator->email }}</p>
                </div>
                @endif
                @if($transfer->approver)
                <div>
                    <label class="text-sm text-slate-400">{{ $transfer->approval_status === 'approved' ? 'Approved By' : 'Rejected By' }}</label>
                    <p class="text-white">{{ $transfer->approver->full_name ?? $transfer->approver->email }}</p>
                </div>
                @endif
                @if($transfer->approved_at)
                <div>
                    <label class="text-sm text-slate-400">{{ $transfer->approval_status === 'approved' ? 'Approved At' : 'Rejected At' }}</label>
                    <p class="text-white">{{ $transfer->approved_at->format('F d, Y H:i') }}</p>
                </div>
                @endif
                <div>
                    <label class="text-sm text-slate-400">Created At</label>
                    <p class="text-white">{{ $transfer->created_at->format('F d, Y H:i') }}</p>
                </div>
            </div>
        </div>

        @if($transfer->approval_status === 'pending')
        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <h3 class="text-lg font-semibold text-white mb-4">Approve or Reject Transfer</h3>
            <div class="grid grid-cols-2 gap-4">
                @can('transfers.approve')
                <form method="POST" action="{{ route('admin.transfers.approve', $transfer) }}">
                    @csrf
                    <div class="mb-3">
                        <label class="text-sm text-slate-300 mb-2 block">Approval Notes (Optional)</label>
                        <textarea name="notes" rows="2" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-3 py-2 text-sm focus:border-cyan-400 focus:ring-cyan-400/40" placeholder="Add any notes about this approval..."></textarea>
                    </div>
                    <button type="submit" class="w-full rounded-2xl bg-gradient-to-r from-emerald-500 to-lime-600 px-6 py-3 font-semibold text-white shadow-lg shadow-emerald-500/40 transition hover:scale-[1.01] hover:shadow-xl hover:shadow-emerald-500/50">
                        Approve Transfer
                    </button>
                </form>
                @else
                <div class="rounded-2xl border border-white/10 bg-white/5 p-6 flex items-center justify-center">
                    <p class="text-sm text-slate-400">You don't have permission to approve transfers</p>
                </div>
                @endcan
                @can('transfers.reject')
                <form method="POST" action="{{ route('admin.transfers.reject', $transfer) }}">
                    @csrf
                    <div class="mb-3">
                        <label class="text-sm text-slate-300 mb-2 block">Rejection Notes (Optional)</label>
                        <textarea name="notes" rows="2" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-3 py-2 text-sm focus:border-rose-400 focus:ring-rose-400/40" placeholder="Add reason for rejection..."></textarea>
                    </div>
                    <button type="submit" class="w-full rounded-2xl bg-gradient-to-r from-rose-500 to-pink-600 px-6 py-3 font-semibold text-white shadow-lg shadow-rose-500/40 transition hover:scale-[1.01] hover:shadow-xl hover:shadow-rose-500/50">
                        Reject Transfer
                    </button>
                </form>
                @else
                <div class="rounded-2xl border border-white/10 bg-white/5 p-6 flex items-center justify-center">
                    <p class="text-sm text-slate-400">You don't have permission to reject transfers</p>
                </div>
                @endcan
            </div>
        </div>
        @endif
    </div>
@endsection

