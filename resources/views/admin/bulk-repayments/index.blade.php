@extends('layouts.admin')

@section('title', 'Bulk Repayment | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Bulk Repayment',
            'buttons' => []
        ])

        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <div class="space-y-6">
                <div>
                    <h2 class="text-xl font-semibold text-white mb-2">Upload Excel File</h2>
                    <p class="text-slate-400 text-sm mb-4">
                        Upload an Excel file containing customer repayment information. The file should have the following columns:
                    </p>
                    <div class="bg-white/5 rounded-2xl p-4 mb-4">
                        <ul class="list-disc list-inside space-y-2 text-slate-300 text-sm mb-4">
                            <li><strong class="text-white">National ID</strong> - Customer's National ID number</li>
                            <li><strong class="text-white">Phone</strong> - Customer's phone number</li>
                            <li><strong class="text-white">Amount</strong> - Repayment amount</li>
                        </ul>
                        <div class="pt-3 border-t border-white/10">
                            <a href="{{ route('admin.bulk-repayments.sample') }}" class="inline-flex items-center gap-2 rounded-xl bg-emerald-500/20 border border-emerald-500/50 px-4 py-2 text-sm font-medium text-emerald-300 hover:bg-emerald-500/30 hover:border-emerald-500 transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                Download Sample Excel File
                            </a>
                        </div>
                    </div>
                    <div class="bg-cyan-500/10 border border-cyan-500/30 rounded-2xl p-4 mb-4">
                        <p class="text-sm text-cyan-300">
                            <strong>Note:</strong> The system will process repayments starting with the oldest loan first. If a customer has multiple loans, payments will be applied to the oldest loan until it's fully paid before moving to the next loan.
                        </p>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.bulk-repayments.process') }}" enctype="multipart/form-data" class="space-y-6">
                    @csrf

                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-slate-300">Excel File</label>
                        <input
                            type="file"
                            name="file"
                            accept=".xlsx,.xls"
                            required
                            class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-cyan-500/20 file:text-cyan-300 hover:file:bg-cyan-500/30"
                        >
                        @error('file')
                            <p class="text-sm text-rose-400">{{ $message }}</p>
                        @enderror
                        <p class="text-xs text-slate-400">Accepted formats: .xlsx, .xls (Max size: 10MB)</p>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-slate-300">Received Via <span class="text-rose-400">*</span></label>
                        <select
                            name="received_via_type"
                            id="received_via_type"
                            required
                            class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40"
                        >
                            <option value="">Select type</option>
                            <option value="bank">Bank</option>
                            <option value="wallet">Wallet</option>
                        </select>
                        @error('received_via_type')
                            <p class="text-sm text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-2" id="account_selection" style="display: none;">
                        <label class="block text-sm font-medium text-slate-300">Account <span class="text-rose-400">*</span></label>
                        <select
                            name="received_via_id"
                            id="received_via_id"
                            required
                            class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40"
                        >
                            <option value="">Select account</option>
                        </select>
                        @error('received_via_id')
                            <p class="text-sm text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center gap-3">
                        <button
                            type="submit"
                            class="rounded-2xl bg-gradient-to-r from-cyan-500 to-blue-600 px-6 py-3 font-semibold text-white shadow-lg shadow-cyan-500/40 transition hover:scale-[1.01] hover:shadow-xl hover:shadow-cyan-500/50"
                        >
                            Process Bulk Repayment
                        </button>
                        <a href="{{ route('admin.bulk-repayments.index') }}" class="rounded-2xl border border-white/10 px-6 py-3 text-sm font-medium text-white/80 hover:bg-white/10 transition">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const receivedViaType = document.getElementById('received_via_type');
            const receivedViaId = document.getElementById('received_via_id');
            const accountSelection = document.getElementById('account_selection');
            
            const banks = @json($banks ?? []);
            const wallets = @json($wallets ?? []);
            
            receivedViaType.addEventListener('change', function() {
                const type = this.value;
                receivedViaId.innerHTML = '<option value="">Select account</option>';
                
                if (type === 'bank') {
                    accountSelection.style.display = 'block';
                    receivedViaId.required = true;
                    banks.forEach(bank => {
                        const option = document.createElement('option');
                        option.value = bank.id;
                        const displayName = bank.bank_name || bank.name;
                        const code = bank.bank_name && bank.name !== bank.bank_name 
                            ? ' (' + bank.bank_name.substring(0, 4).toUpperCase() + ')' 
                            : '';
                        option.textContent = displayName + code;
                        receivedViaId.appendChild(option);
                    });
                } else if (type === 'wallet') {
                    accountSelection.style.display = 'block';
                    receivedViaId.required = true;
                    wallets.forEach(wallet => {
                        const option = document.createElement('option');
                        option.value = wallet.id;
                        option.textContent = wallet.name + ' (' + wallet.provider.toUpperCase() + ')';
                        receivedViaId.appendChild(option);
                    });
                } else {
                    accountSelection.style.display = 'none';
                    receivedViaId.required = false;
                }
            });
        });
    </script>
@endsection

