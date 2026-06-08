@extends('layouts.admin')

@section('title', 'Bulk Repayment Results | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Bulk Repayment Results',
            'buttons' => [
                [
                    'action' => 'create',
                    'text' => 'Upload Another File',
                    'href' => route('admin.bulk-repayments.index'),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>'
                ]
            ]
        ])

        <div class="grid gap-6 md:grid-cols-3">
            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                <div class="text-center">
                    <p class="text-sm text-slate-400 mb-1">Total Records</p>
                    <p class="text-3xl font-bold text-white">{{ $results['total'] }}</p>
                </div>
            </div>
            <div class="rounded-3xl border border-emerald-500/30 bg-emerald-500/10 p-6 shadow-lg">
                <div class="text-center">
                    <p class="text-sm text-emerald-300 mb-1">Successful</p>
                    <p class="text-3xl font-bold text-emerald-400">{{ count($results['success']) }}</p>
                    @if(isset($results['total_successful_amount']) && $results['total_successful_amount'] > 0)
                        <p class="text-sm text-emerald-200 mt-2">Total Amount: ZMW {{ number_format($results['total_successful_amount'], 2) }}</p>
                    @endif
                </div>
            </div>
            <div class="rounded-3xl border border-rose-500/30 bg-rose-500/10 p-6 shadow-lg">
                <div class="text-center">
                    <p class="text-sm text-rose-300 mb-1">Failed</p>
                    <p class="text-3xl font-bold text-rose-400">{{ count($results['failed']) }}</p>
                    @if(isset($results['total_failed_amount']) && $results['total_failed_amount'] > 0)
                        <p class="text-sm text-rose-200 mt-2">Total Amount: ZMW {{ number_format($results['total_failed_amount'], 2) }}</p>
                    @endif
                </div>
            </div>
        </div>

        @if(!empty($results['success']))
            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-white">Successful Repayments</h3>
                    @if(isset($results['total_successful_amount']) && $results['total_successful_amount'] > 0)
                        <div class="text-right">
                            <p class="text-sm text-slate-400">Total Amount</p>
                            <p class="text-xl font-bold text-emerald-400">ZMW {{ number_format($results['total_successful_amount'], 2) }}</p>
                        </div>
                    @endif
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full w-full text-sm text-slate-300">
                        <thead>
                            <tr class="text-sm font-semibold uppercase tracking-[0.25em] text-white/80 text-center border-b border-white/10">
                                <th class="px-4 py-3">Row</th>
                                <th class="px-4 py-3">Customer</th>
                                <th class="px-4 py-3">National ID</th>
                                <th class="px-4 py-3">Phone</th>
                                <th class="px-4 py-3">Amount</th>
                                <th class="px-4 py-3">Repayment Number</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($results['success'] as $success)
                                <tr class="border-b border-white/5 text-center">
                                    <td class="px-4 py-3">{{ $success['row'] }}</td>
                                    <td class="px-4 py-3 font-medium text-white">{{ $success['customer'] }}</td>
                                    <td class="px-4 py-3">{{ $success['national_id'] }}</td>
                                    <td class="px-4 py-3">{{ $success['phone'] }}</td>
                                    <td class="px-4 py-3">{{ number_format($success['amount'], 2) }}</td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full bg-emerald-500/20 px-2 py-1 text-xs text-emerald-300">
                                            {{ $success['repayment_number'] }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if(!empty($results['failed']))
            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-white">Failed Repayments</h3>
                    @if(isset($results['total_failed_amount']) && $results['total_failed_amount'] > 0)
                        <div class="text-right">
                            <p class="text-sm text-slate-400">Total Amount</p>
                            <p class="text-xl font-bold text-rose-400">ZMW {{ number_format($results['total_failed_amount'], 2) }}</p>
                        </div>
                    @endif
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full w-full text-sm text-slate-300">
                        <thead>
                            <tr class="text-sm font-semibold uppercase tracking-[0.25em] text-white/80 text-center border-b border-white/10">
                                <th class="px-4 py-3">Row</th>
                                <th class="px-4 py-3">National ID</th>
                                <th class="px-4 py-3">Phone</th>
                                <th class="px-4 py-3">Amount</th>
                                <th class="px-4 py-3">Error</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($results['failed'] as $failed)
                                <tr class="border-b border-white/5 text-center">
                                    <td class="px-4 py-3">{{ $failed['row'] }}</td>
                                    <td class="px-4 py-3">{{ $failed['national_id'] }}</td>
                                    <td class="px-4 py-3">{{ $failed['phone'] }}</td>
                                    <td class="px-4 py-3">
                                        @if(is_numeric($failed['amount']) && $failed['amount'] > 0)
                                            {{ number_format($failed['amount'], 2) }}
                                        @else
                                            {{ $failed['amount'] ?? '—' }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full bg-rose-500/20 px-2 py-1 text-xs text-rose-300">
                                            {{ $failed['error'] }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
@endsection

