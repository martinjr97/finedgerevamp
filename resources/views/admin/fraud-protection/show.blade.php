@extends('layouts.admin')

@section('title', 'Duplicate Detection - '.$customer->full_name.' | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Duplicate Detection',
            'description' => 'Possible duplicate customers for ' . $customer->full_name,
        ])

        {{-- Customer Info Card --}}
        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <h2 class="text-xl font-semibold text-white mb-4">Customer Information</h2>
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <div>
                    <p class="text-xs text-slate-400 mb-1">Name</p>
                    <p class="font-medium text-white">{{ $customer->full_name }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-400 mb-1">Email</p>
                    <p class="font-medium text-white">{{ $customer->email }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-400 mb-1">Phone</p>
                    <p class="font-medium text-white">{{ $customer->phone ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-400 mb-1">National ID</p>
                    <p class="font-medium text-white">{{ $customer->national_id ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-400 mb-1">Status</p>
                    <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-semibold {{ $customer->status === 'active' ? 'bg-emerald-500/20 text-emerald-300' : ($customer->status === 'pending' ? 'bg-amber-500/20 text-amber-300' : 'bg-slate-500/20 text-slate-300') }}">
                        {{ ucfirst($customer->status) }}
                    </span>
                </div>
                <div>
                    <p class="text-xs text-slate-400 mb-1">Last Login IP</p>
                    <p class="font-medium text-white">{{ $customer->last_login_ip ?? '—' }}</p>
                </div>
                @php
                    $recentLoginAudit = \App\Models\CustomerLoginAudit::where('customer_id', $customer->id)
                        ->where('status', 'success')
                        ->orderBy('attempted_at', 'desc')
                        ->first();
                @endphp
                @if($recentLoginAudit)
                    <div>
                        <p class="text-xs text-slate-400 mb-1">Recent Device</p>
                        <p class="font-medium text-white">
                            {{ $recentLoginAudit->device_name ?? ($recentLoginAudit->device_type ?? 'Unknown') }}
                        </p>
                        @if($recentLoginAudit->os)
                            <p class="text-xs text-slate-400">{{ $recentLoginAudit->os }}</p>
                        @endif
                    </div>
                    @if($recentLoginAudit->location_city || $recentLoginAudit->location_country)
                        <div>
                            <p class="text-xs text-slate-400 mb-1">Recent Location</p>
                            <p class="font-medium text-white">
                                {{ $recentLoginAudit->location_city ?? '' }}{{ $recentLoginAudit->location_city && $recentLoginAudit->location_country ? ', ' : '' }}{{ $recentLoginAudit->location_country ?? '' }}
                            </p>
                        </div>
                    @endif
                @endif
            </div>
            <div class="mt-4 pt-4 border-t border-white/10">
                <a href="{{ route('admin.customers.show', $customer) }}" class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-blue-500 to-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-blue-500/30 hover:from-blue-600 hover:to-blue-700 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    View Full Customer Profile
                </a>
            </div>
        </div>

        {{-- Duplicate Summary --}}
        @if($duplicateInfo['has_duplicates'])
            <div class="alert alert-warning rounded-3xl p-6 shadow-lg flex-col !items-stretch">
                <div class="flex items-center justify-between mb-4 w-full">
                    <div class="flex items-center gap-3">
                        <svg class="w-6 h-6 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <h2 class="text-xl font-semibold">Possible Duplicate Customer Detected</h2>
                    </div>
                    @can('fraud-protection.clear')
                    <button 
                        type="button" 
                        onclick="showClearAllModal()"
                        class="inline-flex items-center gap-2 rounded-2xl bg-emerald-500/20 border border-emerald-500/50 px-4 py-2 text-sm font-semibold text-emerald-300 hover:bg-emerald-500/30 transition"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Clear All as Legitimate
                    </button>
                    @endcan
                </div>
                <p>
                    This customer has <strong>{{ $duplicateInfo['total_count'] }}</strong> potential duplicate match{{ $duplicateInfo['total_count'] === 1 ? '' : 'es' }}. 
                    Please review the matches below and take appropriate action.
                </p>
            </div>

            {{-- Duplicate Matches by Type --}}
            @if(!empty($duplicateInfo['duplicates']['same_nrc']))
                <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                    <h3 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                        <span class="w-1 h-6 rounded-full bg-orange-500"></span>
                        Same NRC/National ID
                    </h3>
                    <div class="space-y-3">
                        @foreach($duplicateInfo['duplicates']['same_nrc'] as $duplicate)
                            @include('admin.fraud-protection.partials.duplicate-card', ['duplicate' => $duplicate])
                        @endforeach
                    </div>
                </div>
            @endif

            @if(!empty($duplicateInfo['duplicates']['same_phone']))
                <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                    <h3 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                        <span class="w-1 h-6 rounded-full bg-orange-500"></span>
                        Same Phone Number
                    </h3>
                    <div class="space-y-3">
                        @foreach($duplicateInfo['duplicates']['same_phone'] as $duplicate)
                            @include('admin.fraud-protection.partials.duplicate-card', ['duplicate' => $duplicate])
                        @endforeach
                    </div>
                </div>
            @endif

            @if(!empty($duplicateInfo['duplicates']['same_bank_account']))
                <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                    <h3 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                        <span class="w-1 h-6 rounded-full bg-orange-500"></span>
                        Same Bank Account
                    </h3>
                    <div class="space-y-3">
                        @foreach($duplicateInfo['duplicates']['same_bank_account'] as $duplicate)
                            @include('admin.fraud-protection.partials.duplicate-card', ['duplicate' => $duplicate])
                        @endforeach
                    </div>
                </div>
            @endif

            @if(!empty($duplicateInfo['duplicates']['same_device_ip']))
                <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                    <h3 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                        <span class="w-1 h-6 rounded-full bg-orange-500"></span>
                        Same Device/IP Address
                        <span class="text-xs text-slate-400 font-normal">
                            (@php
                                $hasIp = collect($duplicateInfo['duplicates']['same_device_ip'])->contains(function($match) {
                                    return isset($match['match_details']['ip']);
                                });
                                $hasDevice = collect($duplicateInfo['duplicates']['same_device_ip'])->contains(function($match) {
                                    return isset($match['match_details']['device_name']);
                                });
                                $types = [];
                                if ($hasIp) $types[] = 'IP';
                                if ($hasDevice) $types[] = 'Device';
                                echo implode(' & ', $types);
                            @endphp)
                        </span>
                    </h3>
                    <div class="space-y-3">
                        @foreach($duplicateInfo['duplicates']['same_device_ip'] as $duplicate)
                            @include('admin.fraud-protection.partials.duplicate-card', ['duplicate' => $duplicate])
                        @endforeach
                    </div>
                </div>
            @endif
        @else
            <div class="rounded-3xl border border-emerald-500/50 bg-emerald-500/10 p-6 shadow-lg text-center">
                <svg class="w-16 h-16 text-emerald-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                <p class="text-emerald-300 text-lg font-semibold">No duplicates detected for this customer.</p>
                <p class="text-emerald-200 text-sm mt-2">This customer appears to be unique in the system.</p>
            </div>
        @endif
    </div>

    {{-- Clear Duplicate Modal --}}
    @can('fraud-protection.clear')
    <div id="clearDuplicateModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden items-center justify-center" style="display: none;">
        <div class="bg-slate-900 rounded-3xl border border-white/10 p-6 max-w-md w-full mx-4 shadow-2xl">
            <h3 class="text-xl font-semibold text-white mb-4">Clear Duplicate Alert</h3>
            <p class="text-sm text-slate-400 mb-4">
                Mark this duplicate match as legitimate/false positive. This will prevent it from appearing in future duplicate detection scans.
            </p>
            
            <form id="clearDuplicateForm" method="POST" action="{{ route('admin.fraud-protection.clear-duplicate', $customer) }}">
                @csrf
                <input type="hidden" name="duplicate_customer_id" id="clear_duplicate_customer_id">
                <input type="hidden" name="match_type" id="clear_match_type">
                <input type="hidden" name="match_value" id="clear_match_value">
                
                <div class="mb-4">
                    <label for="clear_notes" class="block text-sm font-medium text-slate-300 mb-2">Notes (Optional)</label>
                    <textarea 
                        id="clear_notes" 
                        name="notes" 
                        rows="3" 
                        class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-white placeholder:text-slate-500 focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 transition"
                        placeholder="Add any notes about why this is a false positive..."
                    ></textarea>
                </div>
                
                <div class="flex items-center gap-3">
                    <button 
                        type="submit" 
                        class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-emerald-500 to-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-emerald-500/30 hover:from-emerald-600 hover:to-emerald-700 transition"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Clear Alert
                    </button>
                    <button 
                        type="button" 
                        onclick="closeClearModal()"
                        class="inline-flex items-center justify-center rounded-xl border border-white/20 bg-white/5 px-4 py-2.5 text-sm font-medium text-slate-300 hover:bg-white/10 transition"
                    >
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Clear All Duplicates Modal --}}
    <div id="clearAllModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden items-center justify-center" style="display: none;">
        <div class="bg-slate-900 rounded-3xl border border-white/10 p-6 max-w-md w-full mx-4 shadow-2xl">
            <h3 class="text-xl font-semibold text-white mb-4">Clear All Duplicate Alerts</h3>
            <p class="text-sm text-slate-400 mb-4">
                Mark all duplicate matches for this customer as legitimate/false positives. This will prevent them from appearing in future duplicate detection scans.
            </p>
            
            <form id="clearAllForm" method="POST" action="{{ route('admin.fraud-protection.clear-all', $customer) }}">
                @csrf
                
                <div class="mb-4">
                    <label for="clear_all_notes" class="block text-sm font-medium text-slate-300 mb-2">Notes (Optional)</label>
                    <textarea 
                        id="clear_all_notes" 
                        name="notes" 
                        rows="3" 
                        class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-white placeholder:text-slate-500 focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 transition"
                        placeholder="Add any notes about why these are false positives..."
                    ></textarea>
                </div>
                
                <div class="flex items-center gap-3">
                    <button 
                        type="submit" 
                        class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-emerald-500 to-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-emerald-500/30 hover:from-emerald-600 hover:to-emerald-700 transition"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Clear All Alerts
                    </button>
                    <button 
                        type="button" 
                        onclick="closeClearAllModal()"
                        class="inline-flex items-center justify-center rounded-xl border border-white/20 bg-white/5 px-4 py-2.5 text-sm font-medium text-slate-300 hover:bg-white/10 transition"
                    >
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showClearModal(customerId, matchReason, matchValue) {
            // Determine match type from match reason
            let matchType = 'same_device_ip';
            if (matchReason.includes('NRC') || matchReason.includes('National ID')) {
                matchType = 'same_nrc';
            } else if (matchReason.includes('Phone')) {
                matchType = 'same_phone';
            } else if (matchReason.includes('Bank')) {
                matchType = 'same_bank_account';
            }
            
            document.getElementById('clear_duplicate_customer_id').value = customerId;
            document.getElementById('clear_match_type').value = matchType;
            document.getElementById('clear_match_value').value = matchValue || '';
            document.getElementById('clearDuplicateModal').style.display = 'flex';
        }

        function closeClearModal() {
            document.getElementById('clearDuplicateModal').style.display = 'none';
            document.getElementById('clear_notes').value = '';
        }

        function showClearAllModal() {
            document.getElementById('clearAllModal').style.display = 'flex';
        }

        function closeClearAllModal() {
            document.getElementById('clearAllModal').style.display = 'none';
            document.getElementById('clear_all_notes').value = '';
        }

        // Close modals when clicking outside
        document.getElementById('clearDuplicateModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeClearModal();
            }
        });

        document.getElementById('clearAllModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeClearAllModal();
            }
        });
    </script>
    @endcan
@endsection

