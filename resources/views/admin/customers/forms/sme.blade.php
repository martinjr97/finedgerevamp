@php
    $hasCompanyCustomers = isset($companyCustomers) && $companyCustomers->isNotEmpty();
    $initialMode = old('customer_type', 'company');
@endphp

<form method="POST" action="{{ route('admin.customers.store') }}" class="space-y-6">
    @csrf
    <input type="hidden" name="loan_product_id" value="{{ $product->id }}">
    <input type="hidden" name="customer_type" id="customer_type_input" value="{{ $initialMode }}">

    <div class="rounded-2xl border border-muted bg-soft-white p-4 space-y-3">
        <div class="flex flex-wrap gap-3">
            <button type="button" class="mode-tab btn-secondary" data-mode="company">
                Company borrower
            </button>
            <button type="button" class="mode-tab btn-secondary" data-mode="representative" {{ $hasCompanyCustomers ? '' : 'disabled' }}>
                Representative (acts for a company)
            </button>
        </div>
        <p class="text-xs text-muted leading-relaxed">
            Company = borrowing entity. Representative = contact/signatory linked to an existing company customer. Create the company first, then add representatives.
            {{ $hasCompanyCustomers ? '' : ' (No company customers yet, so representative is disabled.)' }}
        </p>
        @error('customer_type') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
    </div>

    {{-- Company borrower panel --}}
    <div id="company-panel" class="space-y-4 rounded-2xl border border-muted bg-soft-white p-4">
        <div>
            <label class="block text-sm font-semibold text-primary">
                Company (MOU / borrower) <span class="text-primary">*</span>
            </label>
            <select name="company_id" id="company_select" class="w-full rounded-xl border border-muted px-3 py-2 text-primary">
                <option value="">Select company</option>
                @foreach($companies as $company)
                    <option value="{{ $company->id }}" {{ old('company_id') == $company->id ? 'selected' : '' }}>
                        {{ $company->name }}
                    </option>
                @endforeach
            </select>
            @error('company_id') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="block text-sm font-semibold text-primary">Registered Name <span class="text-primary">*</span></label>
                <input type="text" name="registered_name" id="registered_name" value="{{ old('registered_name') }}" class="w-full rounded-xl border border-muted px-3 py-2 text-primary">
                @error('registered_name') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
            </div>
        </div>
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="block text-sm font-semibold text-primary">Monthly Net Revenue <span class="text-primary">*</span></label>
                <input type="number" name="monthly_net_revenue" value="{{ old('monthly_net_revenue') }}" step="0.01" min="0" required class="w-full rounded-xl border border-muted px-3 py-2 text-primary" placeholder="0.00">
                @error('monthly_net_revenue') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-semibold text-primary">Qualification Percentage (%) <span class="text-primary">*</span></label>
                <input type="number" name="qualification_percentage" value="{{ old('qualification_percentage', 60) }}" step="0.01" min="0" max="100" required class="w-full rounded-xl border border-muted px-3 py-2 text-primary" placeholder="60">
                <p class="text-xs text-muted mt-1">Example: 40, 60, 100</p>
                @error('qualification_percentage') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
            </div>
        </div>
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="block text-sm font-semibold text-primary">Email <span class="text-primary">*</span></label>
                <input type="email" name="email" value="{{ old('email') }}" required class="w-full rounded-xl border border-muted px-3 py-2 text-primary">
                @error('email') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-semibold text-primary">Phone</label>
                                                <input type="text" name="phone" value="{{ old('phone') }}" maxlength="12" inputmode="numeric" pattern="260[0-9]{9}" class="w-full rounded-xl border border-muted px-3 py-2 text-primary zambian-phone-input" placeholder="260978232334">
                @error('phone') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    {{-- Representative panel --}}
    <div id="representative-panel" class="space-y-4 rounded-2xl border border-muted bg-soft-white p-4 hidden">
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="block text-sm font-semibold text-primary">Parent Company Customer <span class="text-primary">*</span></label>
                <select name="parent_customer_id" id="parent_customer_id" class="w-full rounded-xl border border-muted px-3 py-2 text-primary">
                    <option value="">Select company customer</option>
                    @foreach($companyCustomers as $cc)
                        <option value="{{ $cc->id }}" data-company-id="{{ $cc->company_id }}" {{ old('parent_customer_id') == $cc->id ? 'selected' : '' }}>
                            {{ $cc->registered_name ?? $cc->full_name }}
                        </option>
                    @endforeach
                </select>
                @error('parent_customer_id') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
                <p class="text-xs text-muted mt-1">Representative inherits product and company from this parent.</p>
            </div>
            <div>
                <label class="block text-sm font-semibold text-primary">Company (auto-set)</label>
                <div class="w-full rounded-xl border border-dashed border-muted px-3 py-2 text-sm text-primary bg-soft-white" id="rep-company-name">
                    @if(old('company_id'))
                        {{ optional($companies->firstWhere('id', old('company_id')))->name ?? 'Auto-selected after you pick a parent' }}
                    @else
                        Auto-selected after you pick a parent
                    @endif
                </div>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="block text-sm font-semibold text-primary">First Name <span class="text-primary">*</span></label>
                <input type="text" name="first_name" value="{{ old('first_name') }}" class="w-full rounded-xl border border-muted px-3 py-2 text-primary">
                @error('first_name') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-semibold text-primary">Last Name <span class="text-primary">*</span></label>
                <input type="text" name="last_name" value="{{ old('last_name') }}" class="w-full rounded-xl border border-muted px-3 py-2 text-primary">
                @error('last_name') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
            </div>
        </div>
        @include('partials.customer-identity-fields', [
            'nationalIdType' => old('national_id_type', isset($customer) ? $customer->national_id_type : null),
            'nationalIdValue' => old('national_id', isset($customer) ? ($customer->national_id ?? '') : ''),
            'tpinValue' => old('tpin', isset($customer) ? ($customer->tpin ?? '') : ''),
            'labelClass' => 'block text-sm font-semibold text-primary',
            'inputClass' => 'w-full rounded-xl border border-muted px-3 py-2 text-primary',
            'errorClass' => 'text-xs text-red-500',
            'helpClass' => 'mt-1 text-xs text-slate-500',
            'requiredClass' => 'text-primary',
        ])
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="block text-sm font-semibold text-primary">Email <span class="text-primary">*</span></label>
                <input type="email" name="email" value="{{ old('email') }}" required class="w-full rounded-xl border border-muted px-3 py-2 text-primary">
                @error('email') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-semibold text-primary">Phone</label>
                                                <input type="text" name="phone" value="{{ old('phone') }}" maxlength="12" inputmode="numeric" pattern="260[0-9]{9}" class="w-full rounded-xl border border-muted px-3 py-2 text-primary zambian-phone-input" placeholder="260978232334">
                @error('phone') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    <div>
        <label class="block text-sm font-semibold text-primary">Referred By</label>
        <select name="referred_by" class="w-full rounded-xl border border-muted px-3 py-2 text-primary">
            <option value="">No referral</option>
            @foreach($referredByCustomers as $referrer)
                <option value="{{ $referrer->id }}" {{ old('referred_by') == $referrer->id ? 'selected' : '' }}>
                    {{ $referrer->full_name }}{{ $referrer->phone ? ' - '.$referrer->phone : '' }}
                </option>
            @endforeach
        </select>
        @error('referred_by') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <div>
            <label class="block text-sm font-semibold text-primary">Address Line 1</label>
            <input type="text" name="address_line1" value="{{ old('address_line1') }}" class="w-full rounded-xl border border-muted px-3 py-2 text-primary">
        </div>
        <div>
            <label class="block text-sm font-semibold text-primary">Address Line 2</label>
            <input type="text" name="address_line2" value="{{ old('address_line2') }}" class="w-full rounded-xl border border-muted px-3 py-2 text-primary">
        </div>
        <div>
            <label class="block text-sm font-semibold text-primary">City</label>
            <input type="text" name="city" value="{{ old('city') }}" class="w-full rounded-xl border border-muted px-3 py-2 text-primary">
        </div>
        <div>
            <label class="block text-sm font-semibold text-primary">Country</label>
            <input type="text" name="country" value="{{ old('country') }}" class="w-full rounded-xl border border-muted px-3 py-2 text-primary">
        </div>
    </div>

    <div class="flex justify-end gap-3">
        <a href="{{ route('admin.customers.index') }}" class="btn-secondary inline-flex items-center justify-center rounded-xl px-4 py-2">Cancel</a>
        <button type="submit" class="btn-primary inline-flex items-center justify-center rounded-xl px-5 py-2 font-semibold">Create Customer</button>
    </div>
</form>

<script>
    (function() {
        const companySelect = document.querySelector('#company_select');
        const registeredInput = document.querySelector('#registered_name');
        const parentSelect = document.querySelector('#parent_customer_id');
        const repCompanyDisplay = document.querySelector('#rep-company-name');
        const customerTypeInput = document.querySelector('#customer_type_input');
        const companyPanel = document.querySelector('#company-panel');
        const repPanel = document.querySelector('#representative-panel');
        const tabs = document.querySelectorAll('.mode-tab');
        let manuallyEdited = registeredInput && registeredInput.value.trim().length > 0;

        const companyOptionsById = {};
        @foreach($companies as $company)
            companyOptionsById[{{ $company->id }}] = "{{ addslashes($company->name) }}";
        @endforeach

        const setEnabled = (panel, on, keepIds = []) => {
            if (!panel) return;
            panel.querySelectorAll('input,select,textarea').forEach(el => {
                if (keepIds.includes(el.id)) return;
                el.disabled = !on;
            });
        };

        const setMode = (mode) => {
            customerTypeInput.value = mode;
            tabs.forEach(btn => {
                if (btn.dataset.mode === mode) {
                    btn.classList.add('bg-primary','text-white');
                } else {
                    btn.classList.remove('bg-primary','text-white');
                }
            });

            if (mode === 'company') {
                companyPanel.classList.remove('hidden');
                repPanel.classList.add('hidden');
                setEnabled(companyPanel, true);
                setEnabled(repPanel, false);
                syncRegisteredName();
            } else {
                companyPanel.classList.add('hidden');
                repPanel.classList.remove('hidden');
                setEnabled(repPanel, true);
                // Keep company_id enabled so validation passes, disable the other company fields
                setEnabled(companyPanel, false, ['company_select']);
                companySelect.disabled = false;
                syncRepCompany();
            }
        };

        const syncRepCompany = () => {
            if (!parentSelect || !companySelect) return;
            const parentOption = parentSelect.options[parentSelect.selectedIndex];
            const companyId = parentOption ? parentOption.dataset.companyId : '';
            companySelect.value = companyId || '';
            if (repCompanyDisplay) {
                repCompanyDisplay.textContent = companyId ? (companyOptionsById[companyId] || 'Selected company') : 'Auto-selected after you pick a parent';
            }
        };

        const syncRegisteredName = () => {
            if (!companySelect || !registeredInput) return;
            const selected = companySelect.options[companySelect.selectedIndex];
            if (!selected || !selected.value) return;
            if (manuallyEdited) return;
            registeredInput.value = selected.text.trim();
        };

        companySelect?.addEventListener('change', syncRegisteredName);
        registeredInput?.addEventListener('input', () => {
            manuallyEdited = registeredInput.value.trim().length > 0;
        });

        tabs.forEach(btn => {
            btn.addEventListener('click', () => setMode(btn.dataset.mode));
        });

        parentSelect?.addEventListener('change', syncRepCompany);

        setMode('{{ $initialMode }}');
        if ('{{ $initialMode }}' === 'representative') {
            syncRepCompany();
        } else {
            syncRegisteredName();
        }
    })();
</script>
