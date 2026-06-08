@extends('layouts.admin')

@section('title', 'Wallet Details | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Wallet Details',
            'buttons' => [
                [
                    'action' => 'edit',
                    'text' => 'Edit Wallet',
                    'href' => route('admin.wallets.edit', $wallet),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>'
                ]
            ]
        ])

        <div class="grid gap-6 md:grid-cols-2">
            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
                <h2 class="text-xl font-semibold text-white mb-4">Wallet Information</h2>
                
                <div>
                    <label class="text-sm text-slate-400">Name</label>
                    <p class="text-white font-medium">{{ $wallet->name }}</p>
                </div>

                <div>
                    <label class="text-sm text-slate-400">Wallet Number</label>
                    <p class="text-white font-medium">{{ $wallet->wallet_number }}</p>
                </div>

                <div>
                    <label class="text-sm text-slate-400">Provider</label>
                    <p class="text-white font-medium capitalize">{{ $wallet->provider }}</p>
                </div>

                <div>
                    <label class="text-sm text-slate-400">Currency</label>
                    <p class="text-white font-medium">{{ $wallet->currency }}</p>
                </div>

                <div>
                    <label class="text-sm text-slate-400">Status</label>
                    <p>
                        <span class="rounded-full px-2 py-1 text-xs {{ $wallet->is_active ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300' }}">
                            {{ $wallet->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </p>
                </div>

                @if($wallet->notes)
                <div>
                    <label class="text-sm text-slate-400">Notes</label>
                    <p class="text-white">{{ $wallet->notes }}</p>
                </div>
                @endif
            </div>

            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
                <h2 class="text-xl font-semibold text-white mb-4">Balance Information</h2>
                
                <div>
                    <label class="text-sm text-slate-400">Opening Balance</label>
                    <p class="text-white font-semibold text-lg">{{ number_format($wallet->opening_balance, 2) }} {{ $wallet->currency }}</p>
                </div>

                <div>
                    <label class="text-sm text-slate-400">Current Balance</label>
                    <p class="text-white font-semibold text-2xl {{ $wallet->current_balance >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                        {{ number_format($wallet->current_balance, 2) }} {{ $wallet->currency }}
                    </p>
                </div>

                <div>
                    <label class="text-sm text-slate-400">Net Change</label>
                    @php
                        $netChange = $wallet->current_balance - $wallet->opening_balance;
                    @endphp
                    <p class="text-white font-semibold {{ $netChange >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                        {{ $netChange >= 0 ? '+' : '' }}{{ number_format($netChange, 2) }} {{ $wallet->currency }}
                    </p>
                </div>
            </div>
        </div>

        @if($wallet->sourceTransactions()->count() > 0 || $wallet->destinationTransactions()->count() > 0)
        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <h2 class="text-xl font-semibold text-white mb-4">Recent Transactions</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full w-full text-sm text-slate-300">
                    <thead>
                        <tr class="text-xs font-semibold uppercase tracking-[0.25em] text-white/80 text-center border-b border-white/10">
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3">Description</th>
                            <th class="px-4 py-3">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($wallet->sourceTransactions()->orderBy('transaction_date', 'desc')->limit(10)->get() as $transaction)
                            <tr class="border-b border-white/5 text-center">
                                <td class="px-4 py-3">{{ $transaction->transaction_date->format('M d, Y') }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-1 text-xs bg-rose-500/20 text-rose-300">Outgoing</span>
                                </td>
                                <td class="px-4 py-3">{{ $transaction->description }}</td>
                                <td class="px-4 py-3 text-rose-400">-{{ number_format($transaction->amount, 2) }}</td>
                            </tr>
                        @endforeach
                        @foreach($wallet->destinationTransactions()->orderBy('transaction_date', 'desc')->limit(10)->get() as $transaction)
                            <tr class="border-b border-white/5 text-center">
                                <td class="px-4 py-3">{{ $transaction->transaction_date->format('M d, Y') }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-1 text-xs bg-emerald-500/20 text-emerald-300">Incoming</span>
                                </td>
                                <td class="px-4 py-3">{{ $transaction->description }}</td>
                                <td class="px-4 py-3 text-emerald-400">+{{ number_format($transaction->amount, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>
@endsection

