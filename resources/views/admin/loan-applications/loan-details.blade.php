@extends('layouts.admin')

@section('title', 'Loan Details | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Loan Details',
            'description' => 'Enter loan amount and tenure, calculate repayments, then add disbursement details to continue',
            'buttons' => [
                [
                    'action' => 'secondary',
                    'text' => 'Back',
                    'href' => route('admin.loan-applications.search-customer', $loanProduct),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>'
                ]
            ]
        ])

        {{-- Step Indicator --}}
        <div class="flex items-center justify-center">
            <div class="flex items-center space-x-4">
                <div class="flex items-center">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-500 text-white font-semibold">✓</div>
                    <span class="ml-2 text-sm font-medium text-slate-400">Product Selected</span>
                </div>
                <div class="h-1 w-16 bg-emerald-500"></div>
                <div class="flex items-center">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-500 text-white font-semibold">✓</div>
                    <span class="ml-2 text-sm font-medium text-slate-400">Customer Selected</span>
                </div>
                <div class="h-1 w-16 bg-cyan-500"></div>
                <div class="flex items-center">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-cyan-500 text-white font-semibold">3</div>
                    <span class="ml-2 text-sm font-medium text-white">Loan Details</span>
                </div>
                <div class="h-1 w-16 bg-slate-600"></div>
                <div class="flex items-center">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-600 text-slate-300 font-semibold">4</div>
                    <span class="ml-2 text-sm font-medium text-slate-400">
                        {{ (isset($flowType) && in_array($flowType, ['mou', 'character', 'government', 'sme'])) ? 'Review' : 'Collateral' }}
                    </span>
                </div>
            </div>
        </div>

        @include('partials.admin.customer-loan-exposure', ['customer' => $customer])

        {{-- Customer Info --}}
        <div class="rounded-3xl border border-cyan-500/30 bg-cyan-950/30 p-4 shadow-lg">
            <div class="grid gap-4 md:grid-cols-4 items-center">
                <div>
                    <p class="text-xs uppercase tracking-wide text-cyan-400 mb-1">Borrower</p>
                    <p class="text-sm font-semibold text-white">{{ $customer->registered_name ?? ($customer->first_name.' '.$customer->last_name) }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-cyan-400 mb-1">Available Loan Amount</p>
                    <p class="text-sm font-semibold text-emerald-400">{{ number_format($availableLoanAmount, 2) }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-cyan-400 mb-1">{{ $contextLabel }}</p>
                    <p class="text-sm font-semibold text-white">{{ $contextName }}</p>
                </div>
                @if(!empty($representative))
                    <div class="rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-xs text-slate-200">
                        Requested by representative:
                        <span class="font-semibold text-white">{{ $representative->first_name }} {{ $representative->last_name }}</span>
                        @if($representative->phone)
                            <span class="text-slate-400"> | {{ $representative->phone }}</span>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        {{-- Loan Details Form --}}
        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <h2 class="mb-6 text-xl font-semibold text-white flex items-center gap-2">
                <span class="w-1 h-6 rounded-full bg-cyan-500"></span>Loan Details
            </h2>

            <form id="loanDetailsForm" class="space-y-6">

                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">
                            Loan Amount <span class="text-rose-400">*</span>
                        </label>
                        <input type="number"
                               id="loanAmount"
                               name="loan_amount"
                               step="0.01"
                               min="1"
                               max="{{ $maxLoanAmount }}"
                               value="{{ old('loan_amount', $sessionLoanData['loan_amount'] ?? '') }}"
                               required
                               class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                        <p class="mt-1 text-xs text-slate-400">Maximum: {{ number_format($maxLoanAmount, 2) }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">
                            Tenure (Months) <span class="text-rose-400">*</span>
                        </label>
                        <select id="tenureMonths"
                                name="tenure_months"
                                required
                                class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                            <option value="">Select Tenure</option>
                            @foreach($loanRates as $rate)
                                <option value="{{ $rate->tenure_months }}"
                                        @selected((string) old('tenure_months', $sessionLoanData['tenure_months'] ?? '') === (string) $rate->tenure_months)
                                        data-rate-id="{{ $rate->id }}"
                                        data-daily-rate="{{ $rate->daily_rate }}"
                                        data-weekly-rate="{{ $rate->weekly_rate }}"
                                        data-processing-fee="{{ $rate->processing_fee_percentage }}"
                                        data-term-interest="{{ $rate->term_interest_percentage }}"
                                        data-arrear-rate="{{ $rate->arrear_rate }}"
                                        data-min-principal="{{ $rate->min_principal }}"
                                        data-max-principal="{{ $rate->max_principal }}">
                                    {{ $rate->tenure_months }} months
                                    @if($rate->min_principal !== null || $rate->max_principal !== null)
                                        ({{ $rate->min_principal ? number_format($rate->min_principal, 0) : '0' }}–{{ $rate->max_principal ? number_format($rate->max_principal, 0) : '∞' }} principal)
                                    @endif
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="md:col-span-2 flex flex-col sm:flex-row sm:items-end gap-4">
                        <div class="flex-1 w-full sm:max-w-md">
                            <label class="block text-sm font-medium text-slate-300 mb-2">
                                Loan Start Date <span class="text-rose-400">*</span>
                            </label>
                            <input type="date"
                                   id="loanStartDate"
                                   name="loan_start_date"
                                   value="{{ old('loan_start_date', $sessionLoanData['loan_start_date'] ?? date('Y-m-d')) }}"
                                   required
                                   class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                            <p class="mt-1 text-xs text-slate-400">You can select a past date to backdate the loan start.</p>
                        </div>
                        <button type="button"
                                id="calculateBtn"
                                class="inline-flex w-full sm:w-auto shrink-0 items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-blue-500 to-blue-600 px-5 py-3 text-base font-semibold text-white shadow-lg shadow-blue-500/30 hover:from-blue-600 hover:to-blue-700 transition">
                            Calculate
                        </button>
                    </div>

                    {{-- Calculation + applicable rates (directly below loan inputs) --}}
                    <div id="calculationResults" class="hidden md:col-span-2 rounded-2xl border border-cyan-500/30 bg-cyan-950/30 p-6 space-y-5">
                        <div>
                            <h3 class="text-lg font-semibold text-white">Applicable rates</h3>
                            <p class="text-xs text-slate-400 mt-1">
                                {{ $rateType->name }} · {{ ucfirst(str_replace('_', ' ', $rateType->accrual_period)) }} accrual
                            </p>
                        </div>
                        <div id="ratesForTenureList" class="space-y-2"></div>
                        <div id="appliedRateSummary" class="rounded-xl border border-emerald-500/30 bg-emerald-950/20 p-4 text-sm text-slate-200 hidden"></div>

                        <h3 class="text-lg font-semibold text-white pt-2 border-t border-white/10">Loan calculation</h3>
                        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <p class="text-xs uppercase tracking-wide text-cyan-400 mb-1">Principal Amount</p>
                                <p id="calcPrincipal" class="text-lg font-semibold text-white">-</p>
                            </div>
                            <div>
                                <p class="text-xs uppercase tracking-wide text-cyan-400 mb-1">Processing Fee</p>
                                <p id="calcProcessingFee" class="text-lg font-semibold text-white">-</p>
                            </div>
                            <div>
                                <p class="text-xs uppercase tracking-wide text-cyan-400 mb-1">Interest</p>
                                <p id="calcInterest" class="text-lg font-semibold text-white">-</p>
                            </div>
                            <div>
                                <p class="text-xs uppercase tracking-wide text-cyan-400 mb-1">Total Amount</p>
                                <p id="calcTotal" class="text-lg font-semibold text-emerald-400">-</p>
                            </div>
                            <div>
                                <p class="text-xs uppercase tracking-wide text-cyan-400 mb-1">Amount Per Installment</p>
                                <p id="calcInstallmentAmount" class="text-lg font-semibold text-cyan-300">-</p>
                            </div>
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <p class="text-xs uppercase tracking-wide text-cyan-400 mb-1">Loan End Date</p>
                                <p id="calcEndDate" class="text-sm font-medium text-white">-</p>
                            </div>
                            <div>
                                <p class="text-xs uppercase tracking-wide text-cyan-400 mb-1">Duration</p>
                                <p id="calcDays" class="text-sm font-medium text-white">-</p>
                            </div>
                        </div>

                        <div id="repaymentScheduleSection" class="hidden space-y-3 pt-2 border-t border-white/10">
                            <h4 class="text-sm font-semibold text-white">Repayment schedule</h4>
                            <div class="overflow-x-auto rounded-xl border border-white/10">
                                <table class="min-w-full text-sm text-slate-300">
                                    <thead class="bg-white/[0.03] text-xs uppercase tracking-[0.2em] text-slate-400">
                                        <tr>
                                            <th class="px-4 py-3 text-left">Installment #</th>
                                            <th class="px-4 py-3 text-left">Due Date</th>
                                            <th class="px-4 py-3 text-left">Amount Due</th>
                                        </tr>
                                    </thead>
                                    <tbody id="repaymentScheduleBody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    @include('partials.loan-purpose-select', [
                        'loanPurposes' => $loanPurposes,
                        'selected' => $sessionLoanData['loan_purpose_id'] ?? null,
                        'wrapperClass' => 'md:col-span-2',
                    ])

                    @if($paymentDetailsPrefilled ?? false)
                        <div class="md:col-span-2 rounded-2xl border border-emerald-500/30 bg-emerald-950/20 px-4 py-3 text-sm text-emerald-100">
                            Loaded this customer’s saved payment details. Review and adjust if needed before continuing.
                        </div>
                    @endif

                    @include('partials.disbursement-destination-fields', [
                        'channels' => $channels,
                        'financialInstitutions' => $financialInstitutions,
                        'selectedChannelId' => old('channel_id', $disbursementDefaults['channel_id'] ?? ''),
                        'defaultPhone' => $customer->phone ?? '',
                        'disbursementPhoneNumber' => $disbursementDefaults['disbursement_phone_number'] ?? null,
                        'disbursementFinancialInstitutionId' => $disbursementDefaults['disbursement_financial_institution_id'] ?? null,
                        'disbursementFinancialInstitutionBranchId' => $disbursementDefaults['disbursement_financial_institution_branch_id'] ?? null,
                        'disbursementAccountHolderName' => $disbursementDefaults['disbursement_account_holder_name'] ?? null,
                        'disbursementAccountNumber' => $disbursementDefaults['disbursement_account_number'] ?? null,
                        'disbursementNotes' => $disbursementDefaults['disbursement_notes'] ?? null,
                        'channelSelectId' => 'disbursementChannelId',
                        'wrapperId' => 'loanApplicationDestinationFields',
                        'deferDestinationValidation' => true,
                    ])
                </div>

                <input type="hidden" id="hiddenLoanRateId" name="loan_rate_id" value="{{ $sessionLoanData['loan_rate_id'] ?? '' }}">
                <input type="hidden" id="hiddenDailyRate" name="daily_rate" value="{{ $sessionLoanData['daily_rate'] ?? '' }}">
                <input type="hidden" id="hiddenWeeklyRate" name="weekly_rate" value="{{ $sessionLoanData['weekly_rate'] ?? '' }}">
                <input type="hidden" id="hiddenAccrualPeriod" name="accrual_period" value="{{ $sessionLoanData['accrual_period'] ?? $rateType->accrual_period }}">

                <div class="flex justify-end gap-3">
                    <a href="{{ route('admin.loan-applications.search-customer', $loanProduct) }}"
                       class="inline-flex items-center gap-2 rounded-2xl border border-white/20 bg-white/5 px-4 py-3 text-base font-medium text-slate-300 hover:bg-white/10 hover:border-white/30 transition">
                        Back
                    </a>
                    <button type="button"
                            id="continueBtn"
                            style="display: none;"
                            class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-emerald-500 to-teal-600 px-4 py-3 text-base font-semibold text-white shadow-lg shadow-emerald-500/30 hover:from-emerald-600 hover:to-teal-700 transition">
                        {{ (isset($flowType) && in_array($flowType, ['mou', 'character', 'government', 'sme'])) ? 'Continue to Review' : 'Continue to Collateral' }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        const calculateUrl = "{{ route('admin.loan-applications.calculate-repayment', [$loanProduct, $customer]) }}";
        const storeCalculationUrl = "{{ route('admin.loan-applications.store-calculation', [$loanProduct, $customer]) }}";
        const accrualPeriod = "{{ $rateType->accrual_period }}";
        const savedCalculation = @json($sessionLoanData ?? []);
        const hasSavedPaymentDetails = @json($hasSavedPaymentDetails ?? false);
        let calculationSaved = false;

        function displayCalculationResults(data) {
            const principal = data.principal_amount ?? data.loan_amount ?? 0;

            document.getElementById('calcPrincipal').textContent = formatCurrency(principal);
            document.getElementById('calcProcessingFee').textContent = formatCurrency(data.processing_fee ?? 0);
            document.getElementById('calcInterest').textContent = formatCurrency(data.interest ?? 0);
            document.getElementById('calcTotal').textContent = formatCurrency(data.total_amount ?? 0);
            document.getElementById('calcInstallmentAmount').textContent = formatCurrency(
                data.installment_amount ?? data.repayment_schedule?.[0]?.expected_amount ?? 0
            );
            document.getElementById('calcEndDate').textContent = data.loan_end_date ?? '—';
            document.getElementById('calcDays').textContent = data.days != null ? data.days + ' days' : '—';

            if (data.rates_for_tenure) {
                renderRatesForTenure(data);
            }

            renderRepaymentSchedule(data.repayment_schedule || []);

            if (data.loan_rate_id) {
                document.getElementById('hiddenLoanRateId').value = data.loan_rate_id;
            }
            if (data.daily_rate !== undefined && data.daily_rate !== null) {
                document.getElementById('hiddenDailyRate').value = data.daily_rate;
            }
            if (data.weekly_rate !== undefined && data.weekly_rate !== null) {
                document.getElementById('hiddenWeeklyRate').value = data.weekly_rate;
            }
            if (data.accrual_period) {
                document.getElementById('hiddenAccrualPeriod').value = data.accrual_period;
            }
        }

        function showCalculationSavedState() {
            calculationSaved = true;
            document.getElementById('calculationResults').classList.remove('hidden');
            document.getElementById('continueBtn').style.display = 'inline-flex';
        }

        function restoreSavedCalculation() {
            if (!savedCalculation?.total_amount) {
                return;
            }

            displayCalculationResults(savedCalculation);
            showCalculationSavedState();
        }

        function collectDestinationPayload(wrapper) {
            const channelSelect = wrapper.querySelector('[data-disbursement-channel-select]');
            const selected = channelSelect?.selectedOptions[0];
            const type = selected?.dataset.channelType || 'mobile_wallet';
            const payload = {
                channel_id: channelSelect?.value || '',
            };

            if (type === 'mobile_wallet') {
                payload.disbursement_phone_number = wrapper.querySelector('[name="disbursement_phone_number"]')?.value?.trim() || '';
            } else if (type === 'bank') {
                payload.disbursement_financial_institution_id = wrapper.querySelector('[name="disbursement_financial_institution_id"]')?.value || '';
                payload.disbursement_financial_institution_branch_id = wrapper.querySelector('[name="disbursement_financial_institution_branch_id"]')?.value || '';
                payload.disbursement_account_holder_name = wrapper.querySelector('[name="disbursement_account_holder_name"]')?.value?.trim() || '';
                payload.disbursement_account_number = wrapper.querySelector('[name="disbursement_account_number"]')?.value?.trim() || '';
            } else if (type === 'cash') {
                payload.disbursement_notes = wrapper.querySelector('[name="disbursement_notes"]')?.value?.trim() || '';
            }

            return { type, payload };
        }

        function validateDestination(wrapper) {
            const { type, payload } = collectDestinationPayload(wrapper);

            if (!payload.channel_id) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Disbursement channel required',
                    text: 'Select how funds will be disbursed before continuing.',
                    confirmButtonColor: '#06b6d4',
                });
                return null;
            }

            if (type === 'mobile_wallet') {
                if (!payload.disbursement_phone_number) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Mobile number required',
                        text: 'Enter the customer mobile money number for disbursement.',
                        confirmButtonColor: '#06b6d4',
                    });
                    return null;
                }
            } else if (type === 'bank') {
                if (!payload.disbursement_financial_institution_id) {
                    Swal.fire({ icon: 'warning', title: 'Bank required', text: 'Select the financial institution.', confirmButtonColor: '#06b6d4' });
                    return null;
                }
                if (!payload.disbursement_financial_institution_branch_id) {
                    Swal.fire({ icon: 'warning', title: 'Branch required', text: 'Select the bank branch.', confirmButtonColor: '#06b6d4' });
                    return null;
                }
                if (!payload.disbursement_account_holder_name) {
                    Swal.fire({ icon: 'warning', title: 'Account holder required', text: 'Enter the account holder name.', confirmButtonColor: '#06b6d4' });
                    return null;
                }
                if (!payload.disbursement_account_number) {
                    Swal.fire({ icon: 'warning', title: 'Account number required', text: 'Enter the bank account number.', confirmButtonColor: '#06b6d4' });
                    return null;
                }
            }

            return { type, payload };
        }

        function formatPercent(value, decimals = 4) {
            if (value === null || value === undefined || value === '') {
                return '—';
            }
            return Number(value).toFixed(decimals) + '%';
        }

        function formatRateMultiplier(value) {
            if (value === null || value === undefined || value === '') {
                return '—';
            }
            return (Number(value) * 100).toFixed(4) + '%';
        }

        function formatPrincipalBand(min, max) {
            if (min === null && max === null) {
                return 'Any principal';
            }
            const minLabel = min !== null ? 'ZMW ' + Number(min).toLocaleString() : '0';
            const maxLabel = max !== null ? 'ZMW ' + Number(max).toLocaleString() : '∞';
            return minLabel + ' – ' + maxLabel;
        }

        function renderRatesForTenure(data) {
            const list = document.getElementById('ratesForTenureList');
            const applied = document.getElementById('appliedRateSummary');
            list.innerHTML = '';

            const rates = data.rates_for_tenure || [];
            if (rates.length === 0) {
                list.innerHTML = '<p class="text-sm text-slate-400">No rate rows found for this tenure.</p>';
                applied.classList.add('hidden');
                return;
            }

            rates.forEach((rate) => {
                const row = document.createElement('div');
                row.className = 'rounded-xl border px-4 py-3 text-sm ' + (rate.is_applied
                    ? 'border-emerald-500/50 bg-emerald-950/30 text-white'
                    : 'border-white/10 bg-white/5 text-slate-300');

                const rateLines = [];
                rateLines.push('<span class="font-semibold">' + rate.tenure_months + ' months</span>');
                if (rate.is_applied) {
                    rateLines.push('<span class="ml-2 text-xs uppercase tracking-wide text-emerald-400">Applied to this loan</span>');
                }
                rateLines.push('<div class="mt-2 grid gap-1 md:grid-cols-2 text-xs">');
                rateLines.push('<span>Processing fee: <strong>' + formatPercent(rate.processing_fee_percentage, 2) + '</strong></span>');
                rateLines.push('<span>Principal band: <strong>' + formatPrincipalBand(rate.min_principal, rate.max_principal) + '</strong></span>');

                if (data.accrual_period === 'daily' && rate.daily_rate !== null) {
                    rateLines.push('<span>Daily rate: <strong>' + formatRateMultiplier(rate.daily_rate) + '</strong></span>');
                }
                if (data.accrual_period === 'weekly' && rate.weekly_rate !== null) {
                    rateLines.push('<span>Weekly rate: <strong>' + formatRateMultiplier(rate.weekly_rate) + '</strong></span>');
                }
                if (rate.term_interest_percentage !== null) {
                    rateLines.push('<span>Term interest: <strong>' + formatPercent(rate.term_interest_percentage, 4) + '</strong></span>');
                }
                if (rate.arrear_rate !== null) {
                    rateLines.push('<span>Arrear rate: <strong>' + formatRateMultiplier(rate.arrear_rate) + '</strong></span>');
                }
                rateLines.push('</div>');

                row.innerHTML = rateLines.join('');
                list.appendChild(row);
            });

            if (data.applied_rate) {
                applied.classList.remove('hidden');
                applied.innerHTML = '<p class="text-emerald-300 font-semibold mb-1">Rate used for this calculation</p>' +
                    '<p>Processing fee ' + formatPercent(data.applied_rate.processing_fee_percentage, 2) +
                    ' · Principal band ' + formatPrincipalBand(data.applied_rate.min_principal, data.applied_rate.max_principal) + '</p>';
            } else {
                applied.classList.add('hidden');
            }
        }

        function formatDueDate(dateValue) {
            if (!dateValue) {
                return '—';
            }
            const parsed = new Date(dateValue + 'T00:00:00');
            if (Number.isNaN(parsed.getTime())) {
                return dateValue;
            }
            return parsed.toLocaleDateString('en-GB', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
            });
        }

        function renderRepaymentSchedule(schedule) {
            const section = document.getElementById('repaymentScheduleSection');
            const body = document.getElementById('repaymentScheduleBody');
            body.innerHTML = '';

            const rows = schedule || [];
            if (rows.length === 0) {
                section.classList.add('hidden');
                return;
            }

            rows.forEach((row) => {
                const tr = document.createElement('tr');
                tr.className = 'border-t border-white/5';
                tr.innerHTML =
                    '<td class="px-4 py-3">' + (row.period_number ?? '—') + '</td>' +
                    '<td class="px-4 py-3">' + formatDueDate(row.due_date) + '</td>' +
                    '<td class="px-4 py-3 font-medium text-white">' + formatCurrency(row.expected_amount) + '</td>';
                body.appendChild(tr);
            });

            section.classList.remove('hidden');
        }

        function resetCalculationState() {
            calculationSaved = false;
            document.getElementById('calculationResults').classList.add('hidden');
            document.getElementById('continueBtn').style.display = 'none';
            document.getElementById('repaymentScheduleSection')?.classList.add('hidden');
            document.getElementById('repaymentScheduleBody').innerHTML = '';
            document.getElementById('calcInstallmentAmount').textContent = '-';
        }

        ['loanAmount', 'tenureMonths', 'loanStartDate'].forEach((id) => {
            document.getElementById(id)?.addEventListener('input', resetCalculationState);
            document.getElementById(id)?.addEventListener('change', resetCalculationState);
        });

        document.getElementById('loanApplicationDestinationFields')?.addEventListener('change', resetCalculationState);
        document.getElementById('loanApplicationDestinationFields')?.addEventListener('input', resetCalculationState);

        function canOfferSavePaymentDetails(type) {
            return !hasSavedPaymentDetails && (type === 'mobile_wallet' || type === 'bank');
        }

        function promptSavePaymentDetails() {
            return Swal.fire({
                icon: 'question',
                title: 'Save payment details?',
                html: '<p class="text-sm">This customer has no saved payment details on file.</p><p class="text-sm mt-2">Save these disbursement details as their default for future applications?</p>',
                showCancelButton: true,
                confirmButtonText: 'Yes, save as default',
                cancelButtonText: 'No, continue without saving',
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#64748b',
            }).then((result) => result.isConfirmed);
        }

        function postContinue(destination, savePaymentDetails) {
            const continueBtn = document.getElementById('continueBtn');
            continueBtn.disabled = true;

            return fetch(storeCalculationUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}',
                },
                body: JSON.stringify({
                    include_destination: true,
                    save_customer_payment_details: savePaymentDetails,
                    loan_purpose_id: document.getElementById('loanPurposeId')?.value,
                    ...destination.payload,
                }),
            })
            .then((response) => response.json().then((data) => ({ ok: response.ok, data })))
            .then(({ ok, data }) => {
                if (!ok || data.error) {
                    const message = data.error
                        || (data.errors ? Object.values(data.errors).flat().join(' ') : 'Failed to save disbursement details.');
                    Swal.fire({ icon: 'error', title: 'Cannot continue', text: message, confirmButtonColor: '#ef4444' });
                    return;
                }

                if (data.redirect_url) {
                    window.location.href = data.redirect_url;
                }
            })
            .catch((error) => {
                console.error('Continue error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Request failed',
                    text: 'Could not save disbursement details. Please try again.',
                    confirmButtonColor: '#ef4444',
                });
            })
            .finally(() => {
                continueBtn.disabled = false;
            });
        }

        document.getElementById('continueBtn').addEventListener('click', function() {
            if (!calculationSaved) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Complete calculation first',
                    text: 'Enter loan amount, tenure, and start date, then click Calculate before continuing.',
                    confirmButtonColor: '#06b6d4',
                });
                return;
            }

            const loanPurposeId = document.getElementById('loanPurposeId')?.value;
            if (!loanPurposeId) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Loan purpose required',
                    text: 'Please select the purpose for this loan before continuing.',
                    confirmButtonColor: '#06b6d4',
                });
                return;
            }

            const destinationWrapper = document.getElementById('loanApplicationDestinationFields');
            const destination = validateDestination(destinationWrapper);
            if (!destination) {
                return;
            }

            if (canOfferSavePaymentDetails(destination.type)) {
                promptSavePaymentDetails().then((save) => postContinue(destination, save));
                return;
            }

            postContinue(destination, false);
        });

        document.getElementById('calculateBtn').addEventListener('click', function() {
            const loanAmount = parseFloat(document.getElementById('loanAmount').value);
            const tenureMonths = parseInt(document.getElementById('tenureMonths').value, 10);
            const loanStartDate = document.getElementById('loanStartDate').value;

            if (!loanAmount || !tenureMonths || !loanStartDate) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Missing Information',
                    text: 'Please fill in loan amount, tenure, and start date.',
                    confirmButtonColor: '#06b6d4',
                });
                return;
            }

            const btn = this;
            btn.disabled = true;

            fetch(calculateUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({
                    loan_amount: loanAmount,
                    tenure_months: tenureMonths,
                    loan_start_date: loanStartDate,
                }),
            })
            .then((response) => parseJsonResponse(response))
            .then((data) => {
                displayCalculationResults(data);

                const selectedOption = document.querySelector('#tenureMonths option:checked');
                if (!data.loan_rate_id && selectedOption?.dataset.rateId) {
                    document.getElementById('hiddenLoanRateId').value = selectedOption.dataset.rateId;
                }

                const sessionData = {
                    loan_amount: loanAmount,
                    tenure_months: tenureMonths,
                    loan_start_date: loanStartDate,
                    processing_fee: data.processing_fee,
                    interest: data.interest,
                    total_amount: data.total_amount,
                    loan_end_date: data.loan_end_date,
                    days: data.days,
                    loan_rate_id: data.loan_rate_id || selectedOption.dataset.rateId,
                    daily_rate: data.daily_rate || '',
                    weekly_rate: data.weekly_rate || '',
                    accrual_period: data.accrual_period,
                };

                return fetch(storeCalculationUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}',
                    },
                    body: JSON.stringify(sessionData),
                }).then((response) => parseJsonResponse(response));
            })
            .then((result) => {
                if (!result) {
                    return;
                }
                if (result.success) {
                    showCalculationSavedState();
                    document.getElementById('calculationResults').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            })
            .catch((error) => {
                console.error('Calculation error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Calculation Failed',
                    text: error.message || 'Failed to calculate loan. Please try again.',
                    confirmButtonColor: '#ef4444',
                });
                resetCalculationState();
            })
            .finally(() => {
                btn.disabled = false;
            });
        });

        function formatCurrency(amount) {
            return 'ZMW ' + new Intl.NumberFormat('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            }).format(amount);
        }

        async function parseJsonResponse(response) {
            const contentType = response.headers.get('content-type') || '';

            if (!contentType.includes('application/json')) {
                throw new Error('The server returned an unexpected response. Please refresh and try again.');
            }

            const data = await response.json();

            if (!response.ok) {
                const message = data.error
                    || data.message
                    || (data.errors ? Object.values(data.errors).flat().join(' ') : null)
                    || 'Request failed. Please check your inputs and try again.';

                throw new Error(message);
            }

            return data;
        }

        document.addEventListener('DOMContentLoaded', restoreSavedCalculation);
    </script>
    @endpush
@endsection
