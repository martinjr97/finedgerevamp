@extends('layouts.admin')

@section('title', 'Create Customer | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        {{-- Pending Failed Uploads Notification --}}
        @if(isset($pendingFailedBatches) && $pendingFailedBatches->isNotEmpty())
            <div class="rounded-3xl border border-amber-500/40 bg-gradient-to-r from-amber-500/25 to-amber-500/15 p-4 shadow-lg shadow-amber-500/10">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 mt-0.5">
                        <svg class="h-5 w-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-amber-300 mb-2">
                            You have {{ $pendingFailedBatches->count() }} upload batch{{ $pendingFailedBatches->count() > 1 ? 'es' : '' }} with failed records that need attention
                        </p>
                        <div class="flex flex-wrap items-center gap-2">
                            @foreach($pendingFailedBatches as $batch)
                                <a href="{{ route('admin.customers.upload-batch.show', $batch) }}" 
                                   class="inline-flex items-center gap-2 rounded-xl bg-amber-500/20 border border-amber-500/50 px-3 py-1.5 text-xs font-semibold text-amber-300 hover:bg-amber-500/30 transition shadow-md shadow-amber-500/20">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    Batch #{{ $batch->id }} ({{ $batch->failed_records }} failed)
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @include('partials.validation-errors-summary')

        <div class="flex items-center justify-between">
            <div class="space-y-2 text-left">
                <h1 class="text-3xl font-bold">Create Customer</h1>
                <p class="text-sm text-slate-400">Product: <span class="font-semibold text-white">{{ $product->name }} ({{ $product->code }})</span></p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.customers.upload.template', $product) }}" class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-emerald-500 to-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-emerald-500/30 hover:from-emerald-600 hover:to-emerald-700 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Download Template
                </a>
                <button 
                    type="button" 
                    onclick="showBulkUploadModal()"
                    class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-blue-500 to-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-blue-500/30 hover:from-blue-600 hover:to-blue-700 transition"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                    </svg>
                    Bulk Upload
                </button>
            </div>
        </div>

        @if ($product->category === 'government')
            @include('admin.customers.forms.government')
        @elseif ($product->category === 'mou')
            @include('admin.customers.forms.mou')
        @elseif ($product->category === 'character')
            @include('admin.customers.forms.character')
        @elseif ($product->category === 'collateral')
            @include('admin.customers.forms.collateral')
        @elseif ($product->category === 'marketeer')
            @include('admin.customers.forms.marketeer')
        @elseif ($product->category === 'sme')
            @include('admin.customers.forms.sme')
        @elseif ($product->category === 'group_loans')
            @include('admin.customers.forms.group-loans')
        @else
            <div class="rounded-3xl border border-amber-500/30 bg-amber-500/10 p-6">
                <p class="text-amber-300">Form for {{ $product->category }} product type is not yet implemented.</p>
            </div>
        @endif
    </div>

    {{-- Bulk Upload Modal --}}
    <div id="bulkUploadModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden items-center justify-center" style="display: none;">
        <div class="bg-slate-900 rounded-3xl border border-white/10 p-6 max-w-2xl w-full mx-4 shadow-2xl max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-semibold text-white">Bulk Upload Customers</h3>
                <button 
                    type="button" 
                    onclick="closeBulkUploadModal()"
                    class="text-slate-400 hover:text-white transition"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            
            <form id="bulkUploadForm" method="POST" action="{{ route('admin.customers.upload') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="loan_product_id" value="{{ $product->id }}">
                
                @if($product->category === 'mou' || $product->category === 'marketeer')
                    <div class="mb-4">
                        <label for="upload_company_id" class="block text-sm font-medium text-slate-300 mb-2">
                            Company <span class="text-red-400">*</span>
                        </label>
                        <select 
                            name="company_id" 
                            id="upload_company_id" 
                            required
                            class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-white focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 transition"
                        >
                            <option value="">Select Company</option>
                            @foreach ($companies as $company)
                                <option value="{{ $company->id }}">{{ $company->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if($product->category === 'character' || $product->category === 'collateral')
                    <div class="mb-4">
                        <label for="upload_customer_group_id" class="block text-sm font-medium text-slate-300 mb-2">
                            Customer Group <span class="text-red-400">*</span>
                        </label>
                        <select 
                            name="customer_group_id" 
                            id="upload_customer_group_id" 
                            required
                            class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-white focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 transition"
                        >
                            <option value="">Select Customer Group</option>
                            @foreach ($customerGroups as $group)
                                <option value="{{ $group->id }}">{{ $group->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="mb-4">
                    <label for="excel_file" class="block text-sm font-medium text-slate-300 mb-2">
                        Excel File <span class="text-red-400">*</span>
                    </label>
                    <input 
                        type="file" 
                        name="excel_file" 
                        id="excel_file" 
                        accept=".xlsx,.xls"
                        required
                        class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-white file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-500 file:text-white hover:file:bg-blue-600 transition"
                    >
                    <p class="mt-2 text-xs text-slate-400">
                        Supported formats: .xlsx, .xls (Max: 10MB)
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <button 
                        type="submit" 
                        class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-blue-500 to-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-blue-500/30 hover:from-blue-600 hover:to-blue-700 transition"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                        </svg>
                        Upload & Process
                    </button>
                    <button 
                        type="button" 
                        onclick="closeBulkUploadModal()"
                        class="inline-flex items-center justify-center rounded-xl border border-white/20 bg-white/5 px-4 py-2.5 text-sm font-medium text-slate-300 hover:bg-white/10 transition"
                    >
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showBulkUploadModal() {
            document.getElementById('bulkUploadModal').style.display = 'flex';
        }

        function closeBulkUploadModal() {
            document.getElementById('bulkUploadModal').style.display = 'none';
            document.getElementById('excel_file').value = '';
        }

        // Close modal when clicking outside
        document.getElementById('bulkUploadModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeBulkUploadModal();
            }
        });

        // Lightweight form persistence to avoid retyping after errors
        (function persistForm() {
            const form = document.querySelector('form[action="{{ route('admin.customers.store') }}"]');
            if (!form) return;

            const storageKey = `customer-form-{{ $product->id }}`;
            const isInput = (el) => ['INPUT','SELECT','TEXTAREA'].includes(el.tagName) && el.type !== 'file';

            // Restore saved values if fields are empty (old() still takes priority)
            try {
                const saved = JSON.parse(sessionStorage.getItem(storageKey) || '{}');
                Object.entries(saved).forEach(([name, value]) => {
                    const field = form.elements[name];
                    if (field && isInput(field) && !field.value) {
                        field.value = value;
                        field.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                });
            } catch (_) {}

            // Save on input changes
            form.addEventListener('input', (e) => {
                const el = e.target;
                if (!isInput(el) || !el.name) return;
                const data = JSON.parse(sessionStorage.getItem(storageKey) || '{}');
                data[el.name] = el.value;
                sessionStorage.setItem(storageKey, JSON.stringify(data));
            });
            form.addEventListener('change', (e) => {
                const el = e.target;
                if (!isInput(el) || !el.name) return;
                const data = JSON.parse(sessionStorage.getItem(storageKey) || '{}');
                data[el.name] = el.value;
                sessionStorage.setItem(storageKey, JSON.stringify(data));
            });

            // Clear saved state on successful submit
            form.addEventListener('submit', () => sessionStorage.removeItem(storageKey));

            @if ($errors->any())
            document.getElementById('form-validation-errors')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            const firstInvalid = form.querySelector('[aria-invalid="true"], .border-rose-500');
            firstInvalid?.focus();
            @endif
        })();

        (function enforceCustomerRules() {
            const form = document.querySelector('form[action="{{ route('admin.customers.store') }}"]');
            if (!form) return;

            const phoneInputs = form.querySelectorAll('input[name="phone"], input[name="next_of_kin_phone"]');
            const grossSalaryInput = form.querySelector('input[name="gross_salary"]');
            const netSalaryInput = form.querySelector('input[name="net_salary"]');
            const employmentDateInput = form.querySelector('input[name="date_of_employment"]');
            const contractEndDateInput = form.querySelector('input[name="contract_end_date"]');

            phoneInputs.forEach((input) => {
                input.setAttribute('inputmode', 'numeric');
                input.setAttribute('maxlength', '12');
                input.setAttribute('pattern', '260[0-9]{9}');
                if (!input.placeholder) {
                    input.placeholder = '260978232334';
                }
                input.addEventListener('input', () => {
                    input.value = input.value.replace(/\D/g, '').slice(0, 12);
                });
            });

            const validateSalaryFields = () => {
                if (!grossSalaryInput || !netSalaryInput) return;

                if (grossSalaryInput.value !== '' && netSalaryInput.value !== '') {
                    if (Number(netSalaryInput.value) > Number(grossSalaryInput.value)) {
                        netSalaryInput.setCustomValidity('Net Salary must be less than or equal to Gross Salary.');
                        return;
                    }
                }

                netSalaryInput.setCustomValidity('');
            };

            const validateContractDates = () => {
                if (!contractEndDateInput || !employmentDateInput) return;

                if (contractEndDateInput.value && employmentDateInput.value) {
                    if (contractEndDateInput.value <= employmentDateInput.value) {
                        contractEndDateInput.setCustomValidity('Contract End Date must be greater than Date of Employment.');
                        return;
                    }
                }

                contractEndDateInput.setCustomValidity('');
            };

            grossSalaryInput?.addEventListener('input', validateSalaryFields);
            netSalaryInput?.addEventListener('input', validateSalaryFields);
            employmentDateInput?.addEventListener('change', validateContractDates);
            contractEndDateInput?.addEventListener('change', validateContractDates);

            form.addEventListener('submit', () => {
                validateSalaryFields();
                validateContractDates();
            });
        })();
    </script>
@endsection
