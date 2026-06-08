@extends('layouts.admin')

@section('title', 'Fraud & Abuse Protection | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Fraud & Abuse Protection',
            'description' => 'Duplicate Detection Engine - Identify potential duplicate customers and fraud risks.',
        ])

        {{-- Statistics Cards --}}
        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-slate-400 uppercase tracking-wider">Total Customers</p>
                        <p class="text-2xl font-bold text-white mt-2">{{ number_format($statistics['total_customers']) }}</p>
                    </div>
                    <div class="rounded-full bg-blue-500/20 p-3">
                        <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-slate-400 uppercase tracking-wider">With Duplicates</p>
                        <p class="text-2xl font-bold text-rose-400 mt-2">{{ number_format($statistics['customers_with_duplicates']) }}</p>
                    </div>
                    <div class="rounded-full bg-rose-500/20 p-3">
                        <svg class="w-6 h-6 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-slate-400 uppercase tracking-wider">Total Matches</p>
                        <p class="text-2xl font-bold text-orange-400 mt-2">{{ number_format($statistics['total_duplicate_matches']) }}</p>
                    </div>
                    <div class="rounded-full bg-orange-500/20 p-3">
                        <svg class="w-6 h-6 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-slate-400 uppercase tracking-wider">Risk Level</p>
                        <p class="text-2xl font-bold {{ $statistics['customers_with_duplicates'] > 10 ? 'text-rose-400' : ($statistics['customers_with_duplicates'] > 5 ? 'text-orange-400' : 'text-emerald-400') }} mt-2">
                            {{ $statistics['customers_with_duplicates'] > 10 ? 'High' : ($statistics['customers_with_duplicates'] > 5 ? 'Medium' : 'Low') }}
                        </p>
                    </div>
                    <div class="rounded-full {{ $statistics['customers_with_duplicates'] > 10 ? 'bg-rose-500/20' : ($statistics['customers_with_duplicates'] > 5 ? 'bg-orange-500/20' : 'bg-emerald-500/20') }} p-3">
                        <svg class="w-6 h-6 {{ $statistics['customers_with_duplicates'] > 10 ? 'text-rose-400' : ($statistics['customers_with_duplicates'] > 5 ? 'text-orange-400' : 'text-emerald-400') }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        {{-- Duplicate Type Breakdown --}}
        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <h2 class="text-xl font-semibold text-white mb-4">Duplicate Detection by Type</h2>
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                    <p class="text-xs text-slate-400 mb-1">Same NRC/ID</p>
                    <p class="text-lg font-bold text-white">{{ number_format($statistics['by_type']['same_nrc']) }}</p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                    <p class="text-xs text-slate-400 mb-1">Same Phone</p>
                    <p class="text-lg font-bold text-white">{{ number_format($statistics['by_type']['same_phone']) }}</p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                    <p class="text-xs text-slate-400 mb-1">Same Bank Account</p>
                    <p class="text-lg font-bold text-white">{{ number_format($statistics['by_type']['same_bank_account']) }}</p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                    <p class="text-xs text-slate-400 mb-1">Same Device/IP</p>
                    <p class="text-lg font-bold text-white">{{ number_format($statistics['by_type']['same_device_ip']) }}</p>
                </div>
            </div>
        </div>

        {{-- Customers with Duplicates Table --}}
        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <h2 class="text-xl font-semibold text-white mb-4">Possible Duplicate Customers</h2>
            
            @if($customersWithDuplicates->isEmpty())
                <div class="text-center py-12">
                    <svg class="w-16 h-16 text-slate-500 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                    <p class="text-slate-400">No duplicate customers detected. All clear!</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full w-full text-sm text-slate-300">
                        <thead>
                            <tr class="text-xs font-semibold uppercase tracking-[0.25em] text-white/80 text-left border-b border-white/10">
                                <th class="px-4 py-3">Customer</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Duplicate Matches</th>
                                <th class="px-4 py-3">Match Types</th>
                                <th class="px-4 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($customersWithDuplicates as $item)
                                @php
                                    $customer = $item['customer'];
                                    $duplicateInfo = $item['duplicate_info'];
                                    $matchTypes = [];
                                    if (!empty($duplicateInfo['duplicates']['same_nrc'])) $matchTypes[] = 'NRC';
                                    if (!empty($duplicateInfo['duplicates']['same_phone'])) $matchTypes[] = 'Phone';
                                    if (!empty($duplicateInfo['duplicates']['same_bank_account'])) $matchTypes[] = 'Bank';
                                    if (!empty($duplicateInfo['duplicates']['same_device_ip'])) {
                                        // Check if it's IP or device match
                                        $deviceIpMatches = $duplicateInfo['duplicates']['same_device_ip'];
                                        $hasIpMatch = collect($deviceIpMatches)->contains(function($match) {
                                            return isset($match['match_details']['ip']);
                                        });
                                        $hasDeviceMatch = collect($deviceIpMatches)->contains(function($match) {
                                            return isset($match['match_details']['device_name']);
                                        });
                                        if ($hasIpMatch) $matchTypes[] = 'IP';
                                        if ($hasDeviceMatch) $matchTypes[] = 'Device';
                                    }
                                @endphp
                                <tr class="border-t border-white/10 hover:bg-white/5 transition">
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-white">{{ $customer->full_name }}</div>
                                        <div class="text-xs text-slate-400">{{ $customer->email }}</div>
                                        @if($customer->phone)
                                            <div class="text-xs text-slate-400">{{ $customer->phone }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-semibold {{ $customer->status === 'active' ? 'bg-emerald-500/20 text-emerald-300' : ($customer->status === 'pending' ? 'bg-amber-500/20 text-amber-300' : 'bg-slate-500/20 text-slate-300') }}">
                                            {{ ucfirst($customer->status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold bg-rose-500/20 text-rose-300 border border-rose-500/50">
                                            {{ $duplicateInfo['total_count'] }} {{ $duplicateInfo['total_count'] === 1 ? 'match' : 'matches' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-1">
                                            @foreach($matchTypes as $type)
                                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-orange-500/20 text-orange-300 border border-orange-500/50">
                                                    {{ $type }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <a href="{{ route('admin.fraud-protection.show', $customer) }}" class="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-cyan-500/40 to-blue-600/40 border-2 border-cyan-400/70 px-3 py-1.5 text-xs font-semibold text-cyan-200 hover:from-cyan-500/60 hover:to-blue-600/60 hover:border-cyan-400 hover:text-white transition shadow-md shadow-cyan-500/20">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                            View Details
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection

