@extends('layouts.admin')

@section('title', 'Collateral Details | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Collateral Details',
            'description' => 'Select collateral type and enter collateral value',
            'buttons' => [
                [
                    'action' => 'secondary',
                    'text' => 'Back',
                    'href' => route('admin.loan-applications.loan-details', [$loanProduct, $customer]),
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
                <div class="h-1 w-16 bg-emerald-500"></div>
                <div class="flex items-center">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-500 text-white font-semibold">✓</div>
                    <span class="ml-2 text-sm font-medium text-slate-400">Loan Details</span>
                </div>
                <div class="h-1 w-16 bg-cyan-500"></div>
                <div class="flex items-center">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-cyan-500 text-white font-semibold">4</div>
                    <span class="ml-2 text-sm font-medium text-white">Collateral</span>
                </div>
            </div>
        </div>

        {{-- Loan Summary --}}
        <div class="rounded-3xl border border-cyan-500/30 bg-cyan-950/30 p-3 shadow-lg">
            <div class="grid grid-cols-4 gap-4 items-center">
                <div>
                    <span class="text-xs uppercase tracking-wide text-cyan-400">Loan Amount: </span>
                    <span class="text-sm font-semibold text-white">ZMW {{ number_format($loanData['loan_amount'], 2) }}</span>
                </div>
                <div>
                    <span class="text-xs uppercase tracking-wide text-cyan-400">Processing Fee: </span>
                    <span class="text-sm font-semibold text-white">ZMW {{ number_format($loanData['processing_fee'], 2) }}</span>
                </div>
                <div>
                    <span class="text-xs uppercase tracking-wide text-cyan-400">Interest: </span>
                    <span class="text-sm font-semibold text-white">ZMW {{ number_format($loanData['interest'], 2) }}</span>
                </div>
                <div>
                    <span class="text-xs uppercase tracking-wide text-cyan-400">Total Amount: </span>
                    <span class="text-sm font-semibold text-emerald-400">ZMW {{ number_format($loanData['total_amount'], 2) }}</span>
                </div>
            </div>
        </div>

        {{-- Collateral Form --}}
        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <h2 class="mb-6 text-xl font-semibold text-white flex items-center gap-2">
                <span class="w-1 h-6 rounded-full bg-cyan-500"></span>Collateral Information
            </h2>
            
            <form method="POST" action="{{ route('admin.loan-applications.store', [$loanProduct, $customer]) }}" enctype="multipart/form-data" class="space-y-6">
                @csrf
                
                <input type="hidden" name="loan_amount" value="{{ $loanData['loan_amount'] }}">
                <input type="hidden" name="tenure_months" value="{{ $loanData['tenure_months'] }}">
                <input type="hidden" name="loan_start_date" value="{{ $loanData['loan_start_date'] }}">
                <input type="hidden" name="channel_id" value="{{ $loanData['channel_id'] }}">
                @foreach(['disbursement_phone_number','disbursement_channel_type','disbursement_financial_institution_id','disbursement_financial_institution_branch_id','disbursement_account_holder_name','disbursement_account_number','disbursement_notes'] as $destinationField)
                    @if(!empty($loanData[$destinationField]))
                        <input type="hidden" name="{{ $destinationField }}" value="{{ $loanData[$destinationField] }}">
                    @endif
                @endforeach
                
                <div class="grid gap-6 md:grid-cols-2">
                    {{-- Collateral Type --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">
                            Collateral Type <span class="text-rose-400">*</span>
                        </label>
                        <select id="collateralType" 
                                name="collateral_type_id" 
                                required
                                class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
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
                        <p id="collateralTypeInfo" class="mt-1 text-xs text-slate-400 hidden"></p>
                    </div>

                    {{-- Collateral Value --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">
                            Collateral Value <span class="text-rose-400">*</span>
                        </label>
                        <input type="number" 
                               id="collateralValue" 
                               name="collateral_value" 
                               step="0.01" 
                               min="0"
                               required
                               class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                        <p id="collateralValueInfo" class="mt-1 text-xs text-slate-400"></p>
                    </div>
                </div>

                {{-- LTV Calculation --}}
                <div id="ltvCalculation" class="hidden rounded-2xl border border-cyan-500/30 bg-cyan-950/30 p-6">
                    <h3 class="mb-4 text-lg font-semibold text-white">Loan-to-Value (LTV) Calculation</h3>
                    <div class="grid gap-4 md:grid-cols-3">
                        <div>
                            <p class="text-xs uppercase tracking-wide text-cyan-400 mb-1">Collateral Value</p>
                            <p id="ltvCollateralValue" class="text-lg font-semibold text-white">-</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wide text-cyan-400 mb-1">LTV Ratio</p>
                            <p id="ltvRatio" class="text-lg font-semibold text-white">-</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wide text-cyan-400 mb-1">Maximum Loan Amount (LTV)</p>
                            <p id="ltvAmount" class="text-lg font-semibold text-emerald-400">-</p>
                        </div>
                    </div>
                    <div id="ltvWarning" class="mt-4 hidden rounded-xl border border-amber-500/30 bg-amber-950/30 p-4">
                        <p class="text-sm text-amber-300">
                            <strong>Warning:</strong> The requested loan amount exceeds the LTV amount. 
                            Maximum loan amount based on collateral is <span id="maxLtvAmount" class="font-semibold"></span>.
                        </p>
                    </div>
                </div>

                {{-- Additional Collateral Information --}}
                <div class="grid gap-6 md:grid-cols-2">
                    {{-- Serial Number --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">
                            Serial Number (Optional)
                        </label>
                        <input type="text" 
                               name="serial_number" 
                               class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40"
                               placeholder="Enter serial number if applicable">
                    </div>

                    {{-- Item Quantity --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">
                            Item Quantity (Optional)
                        </label>
                        <input type="number" 
                               name="item_quantity" 
                               min="1"
                               value="1"
                               class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40"
                               placeholder="Number of items">
                    </div>

                    {{-- Item Condition --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">
                            Item Condition/Neatness (Optional)
                        </label>
                        <select name="item_condition" 
                                class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                            <option value="">Select Condition</option>
                            <option value="excellent">Excellent</option>
                            <option value="good">Good</option>
                            <option value="fair">Fair</option>
                            <option value="poor">Poor</option>
                        </select>
                    </div>

                    {{-- Location --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">
                            Location of Collateral (Optional)
                        </label>
                        <input type="text" 
                               name="location" 
                               class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40"
                               placeholder="Where is the collateral located?">
                    </div>
                </div>

                {{-- Inspection Information --}}
                <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                    <div class="flex items-center gap-4 mb-4">
                        <input type="checkbox" 
                               id="is_inspected" 
                               name="is_inspected" 
                               value="1"
                               class="w-5 h-5 rounded border-white/20 bg-white/10 text-cyan-500 focus:ring-cyan-500">
                        <label for="is_inspected" class="text-sm font-medium text-slate-300">
                            Collateral has been inspected
                        </label>
                    </div>
                    <div id="inspectionDetails" class="hidden grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-2">
                                Inspected By
                            </label>
                            <select name="inspected_by" 
                                    required
                                    class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                                <option value="">Select Relationship Manager</option>
                                @foreach($relationshipManagers as $manager)
                                    <option value="{{ $manager->id }}" {{ auth('admin')->id() == $manager->id ? 'selected' : '' }}>
                                        {{ $manager->first_name }} {{ $manager->last_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-2">
                                Inspection Date
                            </label>
                            <input type="date" 
                                   name="inspected_at" 
                                   value="{{ date('Y-m-d') }}"
                                   class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                        </div>
                    </div>
                </div>

                {{-- Collateral Images --}}
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">
                        Collateral Images (Optional)
                    </label>
                    <input type="file" 
                           name="images[]" 
                           id="collateralImages"
                           multiple
                           accept="image/*"
                           class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                    <p class="mt-1 text-xs text-slate-400">You can upload multiple images. Supported formats: JPG, PNG, GIF</p>
                    <div id="imagePreview" class="mt-4 grid grid-cols-3 gap-4 hidden"></div>
                </div>

                {{-- Collateral Description --}}
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">
                        Collateral Description (Optional)
                    </label>
                    <textarea name="collateral_description" 
                              rows="3"
                              class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40"
                              placeholder="Additional details about the collateral..."></textarea>
                </div>

                <div class="flex justify-end gap-3">
                    <a href="{{ route('admin.loan-applications.loan-details', [$loanProduct, $customer]) }}" 
                       class="inline-flex items-center gap-2 rounded-2xl border border-white/20 bg-white/5 px-4 py-3 text-base font-medium text-slate-300 hover:bg-white/10 hover:border-white/30 transition">
                        Back
                    </a>
                    <button type="submit" 
                            id="submitBtn"
                            class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-blue-500 to-blue-600 px-4 py-3 text-base font-semibold text-white shadow-lg shadow-blue-500/30 hover:from-blue-600 hover:to-blue-700 transition">
                        Submit Application
                    </button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        const calculateLtvUrl = "{{ route('admin.loan-applications.calculate-ltv') }}";
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
                document.getElementById('collateralValue').placeholder = 
                    `Enter value between ${formatCurrency(minValue)} and ${formatCurrency(maxValue)}`;
            } else {
                document.getElementById('collateralTypeInfo').classList.add('hidden');
                document.getElementById('ltvCalculation').classList.add('hidden');
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
                document.getElementById('collateralValueInfo').classList.add('text-rose-400');
                document.getElementById('ltvCalculation').classList.add('hidden');
                return;
            } else {
                document.getElementById('collateralValueInfo').textContent = '';
                document.getElementById('collateralValueInfo').classList.remove('text-rose-400');
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
                        confirmButtonColor: '#ef4444'
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
            });
        });
        
        function formatCurrency(amount) {
            return 'ZMW ' + new Intl.NumberFormat('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(amount);
        }

        // Toggle inspection details
        document.getElementById('is_inspected').addEventListener('change', function() {
            const inspectionDetails = document.getElementById('inspectionDetails');
            if (this.checked) {
                inspectionDetails.classList.remove('hidden');
            } else {
                inspectionDetails.classList.add('hidden');
            }
        });

        // Image preview
        document.getElementById('collateralImages').addEventListener('change', function(e) {
            const preview = document.getElementById('imagePreview');
            preview.innerHTML = '';
            
            if (this.files.length > 0) {
                preview.classList.remove('hidden');
                
                Array.from(this.files).forEach((file, index) => {
                    if (file.type.startsWith('image/')) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            const div = document.createElement('div');
                            div.className = 'relative';
                            div.innerHTML = `
                                <img src="${e.target.result}" alt="Preview ${index + 1}" class="w-full h-32 object-cover rounded-lg border border-white/10">
                                <button type="button" onclick="this.parentElement.remove(); updateFileInput();" class="absolute top-1 right-1 bg-rose-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs hover:bg-rose-600">×</button>
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

