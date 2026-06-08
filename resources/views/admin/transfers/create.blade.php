@extends('layouts.admin')

@section('title', 'New Transfer | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        <div class="space-y-2 text-left">
            <p class="text-xs uppercase tracking-[0.4em] text-cyan-300">Financial Management</p>
            <h1 class="text-3xl font-bold">New Transfer</h1>
        </div>

        <form action="{{ route('admin.transfers.store') }}" method="POST" class="space-y-6" id="transferForm">
            @csrf

            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="text-sm font-medium text-slate-300">Transfer Date <span class="text-rose-400">*</span></label>
                        <input type="date" name="transaction_date" value="{{ old('transaction_date', now()->toDateString()) }}" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-3 py-2 text-sm focus:border-cyan-400 focus:ring-cyan-400/40" style="min-width: 0;">
                        @error('transaction_date')
                            <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="text-sm font-medium text-slate-300">Reference Number</label>
                        <input type="text" name="reference_number" value="{{ old('reference_number') }}" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-3 py-2 text-sm focus:border-cyan-400 focus:ring-cyan-400/40" style="min-width: 0;">
                        @error('reference_number')
                            <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="text-sm font-medium text-slate-300">Description <span class="text-rose-400">*</span></label>
                        <input type="text" name="description" value="{{ old('description') }}" required placeholder="e.g., Transfer to operational account" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-3 py-2 text-sm focus:border-cyan-400 focus:ring-cyan-400/40" style="min-width: 0;">
                        @error('description')
                            <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-medium text-slate-300">From <span class="text-rose-400">*</span></label>
                        <div class="mt-2 space-y-3">
                            <div>
                                <label class="text-xs text-slate-400 mb-1 block">Type</label>
                                <select name="source_type" id="source_type" required class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-3 py-2 text-sm focus:border-cyan-400 focus:ring-cyan-400/40">
                                    <option value="bank" @selected(old('source_type') === 'bank')>Bank</option>
                                    <option value="wallet" @selected(old('source_type') === 'wallet')>Wallet</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-xs text-slate-400 mb-1 block">Account</label>
                                <select name="source_id" id="source_id" required class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-3 py-2 text-sm focus:border-cyan-400 focus:ring-cyan-400/40">
                                    <option value="">Select Account</option>
                                    @foreach($banks as $bank)
                                        <option value="{{ $bank->id }}" data-type="bank" data-balance="{{ $bank->current_balance }}" @selected(old('source_id') == $bank->id)>{{ $bank->name }} - {{ $bank->account_number }} ({{ number_format($bank->current_balance, 2) }})</option>
                                    @endforeach
                                    @foreach($wallets as $wallet)
                                        <option value="{{ $wallet->id }}" data-type="wallet" data-balance="{{ $wallet->current_balance }}" @selected(old('source_id') == $wallet->id)>{{ $wallet->name }} - {{ $wallet->wallet_number }} ({{ number_format($wallet->current_balance, 2) }})</option>
                                    @endforeach
                                </select>
                                <div id="source_balance_display" class="mt-1 text-xs text-slate-400 hidden">
                                    Available Balance: <span id="source_balance_amount" class="font-semibold text-emerald-400"></span>
                                </div>
                            </div>
                        </div>
                        @error('source_type')
                            <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                        @enderror
                        @error('source_id')
                            <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="text-sm font-medium text-slate-300">To <span class="text-rose-400">*</span></label>
                        <div class="mt-2 space-y-3">
                            <div>
                                <label class="text-xs text-slate-400 mb-1 block">Type</label>
                                <select name="destination_type" id="destination_type" required class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-3 py-2 text-sm focus:border-cyan-400 focus:ring-cyan-400/40">
                                    <option value="bank" @selected(old('destination_type') === 'bank')>Bank</option>
                                    <option value="wallet" @selected(old('destination_type') === 'wallet')>Wallet</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-xs text-slate-400 mb-1 block">Account</label>
                                <select name="destination_id" id="destination_id" required class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-3 py-2 text-sm focus:border-cyan-400 focus:ring-cyan-400/40">
                                    <option value="">Select Account</option>
                                    @foreach($banks as $bank)
                                        <option value="{{ $bank->id }}" data-type="bank" @selected(old('destination_id') == $bank->id)>{{ $bank->name }} - {{ $bank->account_number }}</option>
                                    @endforeach
                                    @foreach($wallets as $wallet)
                                        <option value="{{ $wallet->id }}" data-type="wallet" @selected(old('destination_id') == $wallet->id)>{{ $wallet->name }} - {{ $wallet->wallet_number }}</option>
                                    @endforeach
                                </select>
                                <div id="destination_error" class="mt-1 text-xs text-rose-400 hidden"></div>
                            </div>
                        </div>
                        @error('destination_type')
                            <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                        @enderror
                        @error('destination_id')
                            <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div id="amount_section" class="hidden">
                    <label class="text-sm font-medium text-slate-300">Amount <span class="text-rose-400">*</span></label>
                    <div class="mt-2">
                        <input type="number" name="amount" id="amount" value="{{ old('amount') }}" step="0.01" min="0.01" required class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                        <div id="amount_error" class="mt-1 text-xs text-rose-400 hidden"></div>
                        <div id="amount_info" class="mt-1 text-xs text-slate-400"></div>
                    </div>
                    @error('amount')
                        <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-300">Notes</label>
                    <textarea name="notes" rows="2" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-3 py-2 text-sm focus:border-cyan-400 focus:ring-cyan-400/40">{{ old('notes') }}</textarea>
                    @error('notes')
                        <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-2xl bg-gradient-to-r from-blue-500 to-cyan-600 px-6 py-3 font-semibold text-white shadow-lg shadow-blue-500/40 transition hover:scale-[1.01] hover:shadow-xl hover:shadow-blue-500/50">
                    Process Transfer
                </button>
                <a href="{{ route('admin.transfers.index') }}" class="rounded-2xl border border-white/10 px-6 py-3 text-sm font-medium text-white/80 hover:bg-white/10 transition">
                    Cancel
                </a>
            </div>
        </form>
    </div>

    <script>
        let sourceBalance = 0;

        function filterAccountOptions(selectId, typeId) {
            const type = document.getElementById(typeId).value;
            const select = document.getElementById(selectId);
            const options = select.querySelectorAll('option');
            
            options.forEach(option => {
                if (option.value === '') {
                    // Keep the placeholder option visible
                    option.style.display = 'block';
                    option.disabled = false;
                } else {
                    const optionType = option.getAttribute('data-type');
                    if (optionType === type) {
                        option.style.display = 'block';
                        option.disabled = false;
                    } else {
                        option.style.display = 'none';
                        option.disabled = true;
                    }
                }
            });
        }

        // Handle source type change
        document.getElementById('source_type').addEventListener('change', function() {
            filterAccountOptions('source_id', 'source_type');
            document.getElementById('source_id').value = '';
            sourceBalance = 0;
            hideSourceBalance();
            hideAmountSection();
        });

        // Handle source account selection
        document.getElementById('source_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption && selectedOption.value && !selectedOption.disabled) {
                sourceBalance = parseFloat(selectedOption.getAttribute('data-balance')) || 0;
                showSourceBalance();
                showAmountSection();
            } else {
                sourceBalance = 0;
                hideSourceBalance();
                hideAmountSection();
            }
        });

        // Handle destination type change
        document.getElementById('destination_type').addEventListener('change', function() {
            filterAccountOptions('destination_id', 'destination_type');
            document.getElementById('destination_id').value = '';
            validateSourceDestination();
        });

        // Handle destination account selection
        document.getElementById('destination_id').addEventListener('change', function() {
            validateSourceDestination();
        });

        // Validate that source and destination are different
        function validateSourceDestination() {
            const sourceType = document.getElementById('source_type').value;
            const sourceId = document.getElementById('source_id').value;
            const destinationType = document.getElementById('destination_type').value;
            const destinationId = document.getElementById('destination_id').value;
            
            const destinationSelect = document.getElementById('destination_id');
            const errorMessage = document.getElementById('destination_error');
            
            // Remove any existing error styling
            if (destinationSelect) {
                destinationSelect.classList.remove('border-rose-500');
                destinationSelect.classList.add('border-white/10');
            }
            
            // Check if source and destination are the same
            if (sourceType && sourceId && destinationType && destinationId) {
                if (sourceType === destinationType && sourceId === destinationId) {
                    if (destinationSelect) {
                        destinationSelect.classList.add('border-rose-500');
                        destinationSelect.classList.remove('border-white/10');
                    }
                    if (errorMessage) {
                        errorMessage.textContent = 'Source and destination must be different.';
                        errorMessage.classList.remove('hidden');
                    }
                } else {
                    if (errorMessage) {
                        errorMessage.classList.add('hidden');
                    }
                }
            }
        }

        // Also validate when source changes
        document.getElementById('source_id').addEventListener('change', function() {
            validateSourceDestination();
        });

        // Handle amount input validation
        document.getElementById('amount')?.addEventListener('input', function() {
            validateAmount();
        });

        // Form submission validation
        document.getElementById('transferForm')?.addEventListener('submit', function(e) {
            const amount = parseFloat(document.getElementById('amount').value);
            const sourceType = document.getElementById('source_type').value;
            const sourceId = document.getElementById('source_id').value;
            const destinationType = document.getElementById('destination_type').value;
            const destinationId = document.getElementById('destination_id').value;
            
            // Validate amount
            if (amount > sourceBalance) {
                e.preventDefault();
                showAmountError('Amount cannot exceed available balance of ' + formatCurrency(sourceBalance));
                return false;
            }
            
            // Validate source and destination are different
            if (sourceType === destinationType && sourceId === destinationId) {
                e.preventDefault();
                validateSourceDestination();
                const errorDiv = document.getElementById('destination_error');
                if (errorDiv) {
                    errorDiv.textContent = 'Source and destination must be different.';
                    errorDiv.classList.remove('hidden');
                }
                return false;
            }
        });

        function showSourceBalance() {
            const display = document.getElementById('source_balance_display');
            const amount = document.getElementById('source_balance_amount');
            if (display && amount) {
                amount.textContent = formatCurrency(sourceBalance);
                display.classList.remove('hidden');
            }
        }

        function hideSourceBalance() {
            const display = document.getElementById('source_balance_display');
            if (display) {
                display.classList.add('hidden');
            }
        }

        function showAmountSection() {
            const section = document.getElementById('amount_section');
            if (section) {
                section.classList.remove('hidden');
            }
        }

        function hideAmountSection() {
            const section = document.getElementById('amount_section');
            if (section) {
                section.classList.add('hidden');
            }
        }

        function validateAmount() {
            const amountInput = document.getElementById('amount');
            const amount = parseFloat(amountInput.value);
            const errorDiv = document.getElementById('amount_error');
            const infoDiv = document.getElementById('amount_info');

            if (amountInput.value === '') {
                errorDiv.classList.add('hidden');
                infoDiv.textContent = '';
                return;
            }

            if (amount > sourceBalance) {
                showAmountError('Amount exceeds available balance of ' + formatCurrency(sourceBalance));
                amountInput.classList.add('border-rose-500');
                amountInput.classList.remove('border-white/10');
            } else if (amount <= 0) {
                showAmountError('Amount must be greater than 0');
                amountInput.classList.add('border-rose-500');
                amountInput.classList.remove('border-white/10');
            } else {
                hideAmountError();
                amountInput.classList.remove('border-rose-500');
                amountInput.classList.add('border-white/10');
                const remaining = sourceBalance - amount;
                infoDiv.textContent = 'Remaining balance: ' + formatCurrency(remaining);
                infoDiv.classList.remove('hidden');
            }
        }

        function showAmountError(message) {
            const errorDiv = document.getElementById('amount_error');
            const infoDiv = document.getElementById('amount_info');
            if (errorDiv) {
                errorDiv.textContent = message;
                errorDiv.classList.remove('hidden');
            }
            if (infoDiv) {
                infoDiv.classList.add('hidden');
            }
        }

        function hideAmountError() {
            const errorDiv = document.getElementById('amount_error');
            if (errorDiv) {
                errorDiv.classList.add('hidden');
            }
        }

        function formatCurrency(amount) {
            return new Intl.NumberFormat('en-ZM', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(amount);
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Filter options based on initial selections
            filterAccountOptions('source_id', 'source_type');
            filterAccountOptions('destination_id', 'destination_type');
            
            // If source is already selected, trigger change event
            const sourceId = document.getElementById('source_id');
            if (sourceId && sourceId.value) {
                sourceId.dispatchEvent(new Event('change'));
            }
        });
    </script>
@endsection

