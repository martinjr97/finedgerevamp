@extends('layouts.customer')

@section('title', 'Collateral Details')

@section('content')
    <style>
        .collateral-value-input {
            padding-left: 4.75rem !important;
            padding-right: 1rem !important;
            line-height: 1.25rem;
        }

        .collateral-value-prefix {
            color: #6b7280 !important;
            pointer-events: none;
            z-index: 10;
        }

        .collateral-value-input::-webkit-outer-spin-button,
        .collateral-value-input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .collateral-value-input[type='number'] {
            -moz-appearance: textfield;
            appearance: textfield;
        }
    </style>

    <div class="space-y-6 max-w-2xl mx-auto">
        {{-- Header --}}
        <div class="card p-6 shadow">
            <h1 class="text-3xl font-bold mb-2 text-primary">Collateral Details</h1>
            <p class="text-muted font-semibold">Select collateral type and enter collateral information</p>
        </div>

        {{-- Step Indicator --}}
        <div class="card p-6 shadow">
            <div class="flex items-center justify-center flex-wrap gap-4">
                <div class="flex items-center">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full step-active font-semibold">✓</div>
                    <span class="ml-2 text-sm font-bold text-primary" style="font-size: 14px !important;">Loan Details</span>
                </div>
                <div class="h-1 w-12 bg-muted"></div>
                <div class="flex items-center">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full step-active font-semibold">2</div>
                    <span class="ml-2 text-sm font-bold text-primary" style="font-size: 14px !important;">Collateral</span>
                </div>
            </div>
        </div>

        {{-- Loan Summary --}}
        <div class="card p-6 shadow">
            <h3 class="mb-4 text-lg font-bold text-primary">Loan Summary</h3>
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <div>
                    <p class="text-xs uppercase tracking-wider text-muted mb-1 font-semibold">Loan Amount</p>
                    <p class="text-lg font-bold text-primary">ZMW {{ number_format($loanData['loan_amount'], 2) }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wider text-muted mb-1 font-semibold">Processing Fee</p>
                    <p class="text-lg font-bold text-primary">ZMW {{ number_format($loanData['processing_fee'], 2) }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wider text-muted mb-1 font-semibold">Interest</p>
                    <p class="text-lg font-bold text-primary">ZMW {{ number_format($loanData['interest'], 2) }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wider text-muted mb-1 font-semibold">Total Amount</p>
                    <p class="text-lg font-bold text-primary">ZMW {{ number_format($loanData['total_amount'], 2) }}</p>
                </div>
            </div>
        </div>

        {{-- Collateral Form --}}
        <form method="POST" action="{{ route('customer.collateral-loans.store') }}" enctype="multipart/form-data" class="space-y-6">
            @csrf
            
            <input type="hidden" name="loan_amount" value="{{ $loanData['loan_amount'] }}">
            <input type="hidden" name="tenure_months" value="{{ $loanData['tenure_months'] }}">
            <input type="hidden" name="loan_start_date" value="{{ $loanData['loan_start_date'] }}">
            <input type="hidden" name="channel_id" value="{{ $loanData['channel_id'] }}">

            <div class="card p-6 shadow">
                <h3 class="mb-4 text-lg font-bold text-primary">Disbursement destination</h3>
                @include('partials.customer.disbursement-destination-summary', [
                    'channel' => \App\Models\Channel::find($loanData['channel_id']),
                    'loanData' => $loanData,
                ])
            </div>

            <div class="card p-6 shadow">
                <h2 class="mb-6 text-xl font-bold text-primary flex items-center gap-2">
                    <span class="w-1 h-6 rounded-full bg-primary"></span>Collateral Information
                </h2>
                
                <div class="grid gap-6 md:grid-cols-2">
                    {{-- Collateral Type --}}
                    <div>
                        <label class="block text-sm font-semibold text-muted mb-2">
                            Collateral Type <span class="text-primary">*</span>
                        </label>
                        <select id="collateralType" 
                                name="collateral_type_id" 
                                required
                                class="w-full rounded-xl bg-white border-2 border-muted text-primary px-4 py-3 font-semibold focus:outline-none [&>option]:bg-white [&>option]:text-primary">
                            <option value="">Select Collateral Type</option>
                            @foreach($collateralTypes as $type)
                                <option value="{{ $type->id }}" 
                                        data-min-value="{{ $type->min_value }}"
                                        data-max-value="{{ $type->max_value }}"
                                        data-ltv-ratio="{{ $type->loan_to_value_ratio }}">
                                    {{ $type->name }} ({{ $type->category }})
                                </option>
                            @endforeach
                        </select>
                        <p id="collateralTypeInfo" class="mt-1 text-xs font-medium text-muted hidden"></p>
                    </div>

                    {{-- Collateral Value --}}
                    <div>
                        <label class="block text-sm font-semibold text-muted mb-2">
                            Collateral Value <span class="text-primary">*</span>
                        </label>
                        <div class="relative">
                            <span class="collateral-value-prefix absolute left-4 top-1/2 transform -translate-y-1/2 font-bold">ZMW</span>
                            <input type="number" 
                                   id="collateralValue" 
                                   name="collateral_value" 
                                   step="0.01" 
                                   min="0"
                                   required
                                   class="collateral-value-input w-full py-3 rounded-xl bg-white border-2 border-muted text-primary font-semibold focus:outline-none"
                                   placeholder="0.00">
                        </div>
                        <p id="collateralValueInfo" class="mt-1 text-xs font-medium text-muted"></p>
                    </div>
                </div>

                {{-- LTV Calculation --}}
                <div id="ltvCalculation" class="hidden mt-6 card p-6 shadow">
                    <h3 class="mb-4 text-lg font-bold text-primary">Loan-to-Value (LTV) Calculation</h3>
                    <div class="grid gap-4 md:grid-cols-3">
                        <div>
                            <p class="text-xs uppercase tracking-wider text-muted mb-1 font-semibold">Collateral Value</p>
                            <p id="ltvCollateralValue" class="text-lg font-bold text-primary">-</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wider text-muted mb-1 font-semibold">LTV Ratio</p>
                            <p id="ltvRatio" class="text-lg font-bold text-primary">-</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wider text-muted mb-1 font-semibold">Maximum Loan Amount (LTV)</p>
                            <p id="ltvAmount" class="text-lg font-bold text-primary">-</p>
                        </div>
                    </div>
                    <div id="ltvWarning" class="mt-4 hidden rounded-xl border border-muted p-4">
                        <p class="text-sm text-primary">
                            <strong>Warning:</strong> The requested loan amount exceeds the LTV amount. 
                            Maximum loan amount based on collateral is <span id="maxLtvAmount" class="font-semibold"></span>.
                        </p>
                    </div>
                </div>

                {{-- Additional Collateral Information --}}
                <div class="grid gap-6 md:grid-cols-2 mt-6">
                    {{-- Serial Number --}}
                    <div>
                        <label class="block text-sm font-semibold text-muted mb-2">
                            Serial Number (Optional)
                        </label>
                        <input type="text" 
                               name="serial_number" 
                               class="w-full rounded-xl bg-white border-2 border-muted text-primary px-4 py-3 font-semibold focus:outline-none"
                               placeholder="Enter serial number if applicable">
                    </div>

                    {{-- Item Quantity --}}
                    <div>
                        <label class="block text-sm font-semibold text-muted mb-2">
                            Item Quantity (Optional)
                        </label>
                        <input type="number" 
                               name="item_quantity" 
                               min="1"
                               value="1"
                               class="w-full rounded-xl bg-white border-2 border-muted text-primary px-4 py-3 font-semibold focus:outline-none"
                               placeholder="Number of items">
                    </div>

                    {{-- Item Condition --}}
                    <div>
                        <label class="block text-sm font-semibold text-muted mb-2">
                            Item Condition (Optional)
                        </label>
                        <select name="item_condition" 
                                class="w-full rounded-xl bg-white border-2 border-muted text-primary px-4 py-3 font-semibold focus:outline-none [&>option]:bg-white [&>option]:text-primary">
                            <option value="">Select Condition</option>
                            <option value="excellent">Excellent</option>
                            <option value="good">Good</option>
                            <option value="fair">Fair</option>
                            <option value="poor">Poor</option>
                        </select>
                    </div>

                    {{-- Location --}}
                    <div>
                        <label class="block text-sm font-semibold text-muted mb-2">
                            Location of Collateral (Optional)
                        </label>
                        <input type="text" 
                               name="location" 
                               class="w-full rounded-xl bg-white border-2 border-muted text-primary px-4 py-3 font-semibold focus:outline-none"
                               placeholder="Where is the collateral located?">
                    </div>
                </div>

                {{-- Collateral Images --}}
                <div class="mt-6">
                    <label class="block text-sm font-semibold text-muted mb-2">
                        Collateral Images (Optional)
                    </label>
                    <input type="file" 
                           name="images[]" 
                           id="collateralImages"
                           multiple
                           accept="image/*"
                           class="w-full rounded-xl bg-white border-2 border-muted text-primary px-4 py-3 font-semibold focus:outline-none file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border file:border-muted file:text-sm file:font-semibold file:bg-white file:text-primary">
                    <p class="mt-1 text-xs font-medium text-muted">You can upload multiple images. Supported formats: JPG, PNG, GIF (Max 15MB per image)</p>
                    <div id="imagePreview" class="mt-4 grid grid-cols-3 gap-4 hidden"></div>
                </div>

                {{-- Collateral Description --}}
                <div class="mt-6">
                    <label class="block text-sm font-semibold text-muted mb-2">
                        Collateral Description (Optional)
                    </label>
                    <textarea name="collateral_description" 
                              rows="3"
                              class="w-full rounded-xl bg-white border-2 border-muted text-primary px-4 py-3 font-semibold focus:outline-none"
                              placeholder="Additional details about the collateral..."></textarea>
                </div>

                <div class="flex justify-end gap-4 mt-6">
                    <a href="{{ route('customer.collateral-loans.loan-details') }}" 
                       class="inline-flex items-center justify-center rounded-xl px-6 py-3 text-sm font-semibold btn-secondary">
                        Back
                    </a>
                    <button type="submit" 
                            id="submitBtn"
                            class="inline-flex items-center justify-center rounded-xl px-6 py-3 text-sm font-semibold btn-primary">
                        Submit Application
                    </button>
                </div>
            </div>
        </form>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const calculateLtvUrl = "{{ route('customer.collateral-loans.calculate-ltv') }}";
        const loanAmount = {{ $loanData['loan_amount'] }};
        
        document.getElementById('collateralType').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const minValue = parseFloat(selectedOption.dataset.minValue);
            const maxValue = parseFloat(selectedOption.dataset.maxValue);
            const ltvRatio = parseFloat(selectedOption.dataset.ltvRatio);
            
            if (this.value) {
                document.getElementById('collateralTypeInfo').textContent = 
                    `Value range: ${formatCurrency(minValue)} - ${formatCurrency(maxValue)} | LTV: ${ltvRatio}%`;
                document.getElementById('collateralTypeInfo').classList.remove('hidden');
                
                document.getElementById('collateralValue').min = minValue;
                document.getElementById('collateralValue').max = maxValue;
                document.getElementById('collateralValue').placeholder = '0.00';
                document.getElementById('collateralValueInfo').textContent =
                    `Enter a value between ${formatCurrency(minValue)} and ${formatCurrency(maxValue)}.`;
            } else {
                document.getElementById('collateralTypeInfo').classList.add('hidden');
                document.getElementById('ltvCalculation').classList.add('hidden');
                document.getElementById('collateralValueInfo').textContent = '';
            }
        });
        
        document.getElementById('collateralValue').addEventListener('input', function() {
            const collateralTypeId = document.getElementById('collateralType').value;
            const collateralValue = parseFloat(this.value);
            
            if (!collateralTypeId || !collateralValue) {
                document.getElementById('ltvCalculation').classList.add('hidden');
                return;
            }
            
            const selectedOption = document.getElementById('collateralType').options[document.getElementById('collateralType').selectedIndex];
            const minValue = parseFloat(selectedOption.dataset.minValue);
            const maxValue = parseFloat(selectedOption.dataset.maxValue);
            
            // Validate range
            if (collateralValue < minValue || collateralValue > maxValue) {
                document.getElementById('collateralValueInfo').textContent = 
                    `Value must be between ${formatCurrency(minValue)} and ${formatCurrency(maxValue)}`;
                document.getElementById('collateralValueInfo').classList.add('text-primary');
                document.getElementById('ltvCalculation').classList.add('hidden');
                return;
            } else {
                document.getElementById('collateralValueInfo').textContent = '';
                document.getElementById('collateralValueInfo').classList.remove('text-primary');
            }
            
            // Calculate LTV
            fetch(calculateLtvUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    collateral_type_id: collateralTypeId,
                    collateral_value: collateralValue
                })
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(data => {
                        throw new Error(data.error || 'LTV calculation failed');
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Validation Error',
                        html: data.error.replace(/\n/g, '<br>'),
                        confirmButtonColor: '#0A2540',

                        iconColor: '#0A2540'
                    });
                    return;
                }
                
                document.getElementById('ltvCollateralValue').textContent = formatCurrency(data.collateral_value);
                document.getElementById('ltvRatio').textContent = data.ltv_ratio + '%';
                document.getElementById('ltvAmount').textContent = formatCurrency(data.ltv_amount);
                
                document.getElementById('ltvCalculation').classList.remove('hidden');
                
                // Check if loan amount exceeds LTV
                if (loanAmount > data.ltv_amount) {
                    document.getElementById('ltvWarning').classList.remove('hidden');
                    document.getElementById('maxLtvAmount').textContent = formatCurrency(data.ltv_amount);
                    document.getElementById('submitBtn').disabled = true;
                } else {
                    document.getElementById('ltvWarning').classList.add('hidden');
                    document.getElementById('submitBtn').disabled = false;
                }
            })
            .catch(error => {
                console.error('LTV calculation error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message || 'Failed to calculate LTV. Please try again.',
                    confirmButtonColor: '#0A2540',

                    iconColor: '#0A2540'
                });
            });
        });
        
        function formatCurrency(amount) {
            return 'ZMW ' + new Intl.NumberFormat('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(amount);
        }

        // Image preview
        document.getElementById('collateralImages').addEventListener('change', function(e) {
            const preview = document.getElementById('imagePreview');
            preview.innerHTML = '';
            
            if (this.files.length > 0) {
                preview.classList.remove('hidden');
                
                Array.from(this.files).forEach((file, index) => {
                    if (file.type.startsWith('image/')) {
                        if (file.size > 15 * 1024 * 1024) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'File Too Large',
                                text: `${file.name} is larger than 15MB. Please select a smaller image.`,
                                confirmButtonColor: '#0A2540',

                                iconColor: '#0A2540'
                            });
                            return;
                        }
                        
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            const div = document.createElement('div');
                            div.className = 'relative';
                            div.innerHTML = `
                                <img src="${e.target.result}" alt="Preview ${index + 1}" class="w-full h-32 object-cover rounded-lg border-2 border-muted">
                                <button type="button" onclick="this.parentElement.remove(); updateFileInput();" class="absolute top-1 right-1 bg-primary text-white rounded-full w-6 h-6 flex items-center justify-center text-xs hover:bg-primary">×</button>
                            `;
                            preview.appendChild(div);
                        };
                        reader.readAsDataURL(file);
                    }
                });
            } else {
                preview.classList.add('hidden');
            }
        });

        function updateFileInput() {
            const preview = document.getElementById('imagePreview');
            if (preview.children.length === 0) {
                preview.classList.add('hidden');
            }
        }
    </script>
    @endpush
@endsection
