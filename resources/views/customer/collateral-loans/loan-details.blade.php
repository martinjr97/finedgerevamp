@extends('layouts.customer')

@section('title', 'Loan Details')

@section('content')
    <style>
        .collateral-loan-amount-input {
            padding-left: 4.75rem !important;
            padding-right: 1rem !important;
            line-height: 1.25rem;
        }

        .collateral-loan-amount-prefix {
            color: #6b7280 !important;
            pointer-events: none;
            z-index: 10;
        }

        .collateral-loan-amount-input::-webkit-outer-spin-button,
        .collateral-loan-amount-input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .collateral-loan-amount-input[type='number'] {
            -moz-appearance: textfield;
            appearance: textfield;
        }
    </style>

    <div class="space-y-6 max-w-2xl mx-auto">
        {{-- Header --}}
        <div class="card p-6 shadow">
            <h1 class="text-3xl font-bold mb-2 text-primary">Loan Application</h1>
            <p class="text-muted font-semibold">Enter loan amount, tenure, and disbursement channel</p>
        </div>

        {{-- Step Indicator --}}
        <div class="card p-6 shadow">
            <div class="flex items-center justify-center flex-wrap gap-4">
                <div class="flex items-center">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full step-active font-semibold">1</div>
                    <span class="ml-2 text-sm font-bold text-primary">Loan Details</span>
                </div>
                <div class="h-1 w-12 bg-muted"></div>
                <div class="flex items-center">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full step-upcoming font-semibold">2</div>
                    <span class="ml-2 text-sm font-bold text-primary">Collateral</span>
                </div>
            </div>
        </div>

        {{-- Customer Info --}}
        <div class="card p-4 shadow">
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <p class="text-xs uppercase tracking-wider text-muted mb-1 font-semibold">Available Loan Amount</p>
                    <p class="text-lg font-bold text-primary">ZMW {{ number_format($availableLoanAmount, 2) }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wider text-muted mb-1 font-semibold">Customer Group</p>
                    <p class="text-lg font-bold text-primary">{{ $customerGroup->name }}</p>
                </div>
            </div>
        </div>

        {{-- Loan Details Form --}}
        <form id="loanDetailsForm" class="space-y-6">
            <div class="card p-6 shadow">
                <h2 class="mb-6 text-xl font-bold text-primary flex items-center gap-2">
                    <span class="w-1 h-6 rounded-full bg-primary"></span>Loan Details
                </h2>
                
                <div class="grid gap-6 md:grid-cols-2">
                    {{-- Loan Amount --}}
                    <div>
                        <label class="block text-sm font-semibold text-muted mb-2">
                            Loan Amount <span class="text-primary">*</span>
                        </label>
                        <div class="relative">
                            <span class="collateral-loan-amount-prefix absolute left-4 top-1/2 transform -translate-y-1/2 font-bold">ZMW</span>
                            <input type="number" 
                                   id="loanAmount" 
                                   name="loan_amount" 
                                   step="0.01" 
                                   min="1" 
                                   max="{{ $maxLoanAmount }}"
                                   required
                                   class="collateral-loan-amount-input w-full py-3 rounded-xl bg-white border-2 border-muted text-primary font-semibold focus:outline-none"
                                   placeholder="0.00">
                        </div>
                        <p class="mt-1 text-xs text-muted">Maximum: ZMW {{ number_format($maxLoanAmount, 2) }}</p>
                    </div>

                    {{-- Tenure --}}
                    <div>
                        <label class="block text-sm font-semibold text-muted mb-2">
                            Tenure (Months) <span class="text-primary">*</span>
                        </label>
                        <select id="tenureMonths" 
                                name="tenure_months" 
                                required
                                class="w-full rounded-xl bg-white border-2 border-muted text-primary px-4 py-3 font-semibold focus:outline-none">
                            <option value="">Select Tenure</option>
                            @foreach($loanRates as $rate)
                                <option value="{{ $rate->tenure_months }}" 
                                        data-rate-id="{{ $rate->id }}"
                                        data-daily-rate="{{ $rate->daily_rate }}"
                                        data-weekly-rate="{{ $rate->weekly_rate }}"
                                        data-processing-fee="{{ $rate->processing_fee_percentage }}">
                                    {{ $rate->tenure_months }} months
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Loan Start Date --}}
                    <div>
                        <label class="block text-sm font-semibold text-muted mb-2">
                            Loan Start Date <span class="text-primary">*</span>
                        </label>
                        <input type="date" 
                               id="loanStartDate" 
                               name="loan_start_date" 
                               value="{{ date('Y-m-d') }}"
                               min="{{ date('Y-m-d') }}"
                               required
                               class="w-full rounded-xl bg-white border-2 border-muted text-primary px-4 py-3 font-semibold focus:outline-none">
                    </div>

                    @include('partials.loan-purpose-select', [
                        'loanPurposes' => $loanPurposes,
                        'labelClass' => 'block text-sm font-semibold text-muted mb-2',
                        'selectClass' => 'w-full rounded-xl bg-white border-2 border-muted text-primary px-4 py-3 font-semibold focus:outline-none',
                        'wrapperClass' => 'md:col-span-2',
                    ])

                    @include('partials.disbursement-destination-fields', [
                        'channels' => $channels,
                        'financialInstitutions' => $financialInstitutions,
                        'defaultPhone' => auth('customer')->user()->phone ?? '',
                        'channelSelectId' => 'disbursementChannelId',
                        'wrapperId' => 'collateralLoanDestinationFields',
                        'inputClass' => 'w-full rounded-xl bg-white border-2 border-muted text-primary px-4 py-3 font-semibold focus:outline-none',
                        'labelClass' => 'block text-sm font-semibold text-muted mb-2',
                    ])
                </div>

                {{-- Calculation Results --}}
                <div id="calculationResults" class="hidden mt-6 card p-6 shadow">
                    <h3 class="mb-4 text-lg font-bold text-primary">Loan Calculation</h3>
                    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <p class="text-xs uppercase tracking-wider text-muted mb-1 font-semibold">Principal Amount</p>
                            <p id="calcPrincipal" class="text-lg font-bold text-primary">-</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wider text-muted mb-1 font-semibold">Processing Fee</p>
                            <p id="calcProcessingFee" class="text-lg font-bold text-primary">-</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wider text-muted mb-1 font-semibold">Interest</p>
                            <p id="calcInterest" class="text-lg font-bold text-primary">-</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wider text-muted mb-1 font-semibold">Total Amount</p>
                            <p id="calcTotal" class="text-lg font-bold text-primary">-</p>
                        </div>
                    </div>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <div>
                            <p class="text-xs uppercase tracking-wider text-muted mb-1 font-semibold">Loan End Date</p>
                            <p id="calcEndDate" class="text-sm font-medium text-primary">-</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wider text-muted mb-1 font-semibold">Duration (Days)</p>
                            <p id="calcDays" class="text-sm font-medium text-primary">-</p>
                        </div>
                    </div>
                </div>

                {{-- Hidden fields for calculation data --}}
                <input type="hidden" id="hiddenLoanRateId" name="loan_rate_id">
                <input type="hidden" id="hiddenDailyRate" name="daily_rate">
                <input type="hidden" id="hiddenWeeklyRate" name="weekly_rate">
                <input type="hidden" id="hiddenAccrualPeriod" name="accrual_period" value="{{ $rateType->accrual_period }}">

                <div class="flex justify-end gap-4 mt-6">
                    <a href="{{ route('customer.dashboard') }}" 
                       class="inline-flex items-center justify-center rounded-xl px-6 py-3 text-sm font-semibold btn-secondary">
                        Back
                    </a>
                    <button type="button" 
                            id="calculateBtn" 
                            class="inline-flex items-center justify-center rounded-xl px-6 py-3 text-sm font-semibold btn-primary">
                        Calculate
                    </button>
                    <a href="#" 
                       id="continueBtn" 
                       style="display: none;"
                       class="inline-flex items-center justify-center rounded-xl px-6 py-3 text-sm font-semibold btn-primary">
                        Continue to Collateral
                    </a>
                </div>
            </div>
        </form>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const calculateUrl = "{{ route('customer.collateral-loans.calculate-repayment') }}";
        const accrualPeriod = "{{ $rateType->accrual_period }}";

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

        document.getElementById('calculateBtn').addEventListener('click', function() {
            const loanAmount = parseFloat(document.getElementById('loanAmount').value);
            const tenureMonths = parseInt(document.getElementById('tenureMonths').value);
            const loanStartDate = document.getElementById('loanStartDate').value;
            
            if (!loanAmount || !tenureMonths || !loanStartDate) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Missing Information',
                    text: 'Please fill in all required fields.',
                    confirmButtonColor: '#0A2540',

                    iconColor: '#0A2540'
                });
                return;
            }
            
            fetch(calculateUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                body: JSON.stringify({
                    loan_amount: loanAmount,
                    tenure_months: tenureMonths,
                    loan_start_date: loanStartDate
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.error,
                        confirmButtonColor: '#0A2540',

                        iconColor: '#0A2540'
                    });
                    return;
                }
                
                // Display calculation results
                document.getElementById('calcPrincipal').textContent = formatCurrency(data.principal_amount);
                document.getElementById('calcProcessingFee').textContent = formatCurrency(data.processing_fee);
                document.getElementById('calcInterest').textContent = formatCurrency(data.interest);
                document.getElementById('calcTotal').textContent = formatCurrency(data.total_amount);
                document.getElementById('calcEndDate').textContent = data.loan_end_date;
                document.getElementById('calcDays').textContent = data.days + ' days';
                
                // Store calculation data in hidden fields
                const selectedOption = document.querySelector('#tenureMonths option:checked');
                document.getElementById('hiddenLoanRateId').value = selectedOption.dataset.rateId;
                document.getElementById('hiddenDailyRate').value = data.daily_rate || '';
                document.getElementById('hiddenWeeklyRate').value = data.weekly_rate || '';
                document.getElementById('hiddenAccrualPeriod').value = data.accrual_period;
                
                // Store in session
                const destinationWrapper = document.getElementById('collateralLoanDestinationFields');
                const { type, payload: destinationPayload } = collectDestinationPayload(destinationWrapper);

                if (!destinationPayload.channel_id) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Missing Channel',
                        text: 'Please select a disbursement channel.',
                        confirmButtonColor: '#0A2540',
                        iconColor: '#0A2540'
                    });
                    return;
                }

                if (type === 'mobile_wallet' && !destinationPayload.disbursement_phone_number) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Missing mobile number',
                        text: 'Please enter a valid mobile money number.',
                        confirmButtonColor: '#0A2540',
                        iconColor: '#0A2540'
                    });
                    return;
                }

                const loanPurposeId = document.getElementById('loanPurposeId')?.value;
                if (!loanPurposeId) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Loan purpose required',
                        text: 'Please select the purpose for this loan.',
                        confirmButtonColor: '#0A2540',
                        iconColor: '#0A2540'
                    });
                    return;
                }

                const sessionData = {
                    loan_amount: loanAmount,
                    loan_purpose_id: loanPurposeId,
                    tenure_months: tenureMonths,
                    loan_start_date: loanStartDate,
                    ...destinationPayload,
                    processing_fee: data.processing_fee,
                    interest: data.interest,
                    total_amount: data.total_amount,
                    loan_end_date: data.loan_end_date,
                    days: data.days,
                    loan_rate_id: selectedOption.dataset.rateId,
                    daily_rate: data.daily_rate || '',
                    weekly_rate: data.weekly_rate || '',
                    accrual_period: data.accrual_period
                };
                
                fetch("{{ route('customer.collateral-loans.store-calculation') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}'
                    },
                    body: JSON.stringify(sessionData)
                }).then(response => response.json()).then(result => {
                    if (result.success) {
                        document.getElementById('calculationResults').classList.remove('hidden');
                        const continueBtn = document.getElementById('continueBtn');
                        continueBtn.style.display = 'inline-block';
                        continueBtn.href = "{{ route('customer.collateral-loans.collateral') }}";
                    }
                }).catch(error => {
                    console.error('Session storage error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to save calculation. Please try again.',
                        confirmButtonColor: '#0A2540',

                        iconColor: '#0A2540'
                    });
                });
            })
            .catch(error => {
                console.error('Calculation error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Calculation Failed',
                    text: error.message || 'Failed to calculate loan. Please try again.',
                    confirmButtonColor: '#0A2540',

                    iconColor: '#0A2540'
                });
                document.getElementById('calculationResults').classList.add('hidden');
                document.getElementById('continueBtn').style.display = 'none';
            });
        });
        
        function formatCurrency(amount) {
            return 'ZMW ' + new Intl.NumberFormat('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(amount);
        }
    </script>
    @endpush
@endsection
