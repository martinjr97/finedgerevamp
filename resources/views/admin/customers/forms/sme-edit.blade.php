@php
    $hasCompanyCustomers = isset($companyCustomers) && $companyCustomers->isNotEmpty();
    $type = old('customer_type', $customer->customer_type ?? 'company');
    $canSelectRepresentative = $hasCompanyCustomers || $type === 'representative';
    $smeQualificationPercentage = old('qualification_percentage', data_get($customer->metadata ?? [], 'sme_qualification_percentage', 60));
@endphp

<form method="POST" action="{{ route('admin.customers.update', $customer) }}" class="space-y-6">
    @csrf
    @method('PUT')
    <input type="hidden" name="loan_product_id" value="{{ $product->id }}">

    <div class="grid gap-4 md:grid-cols-2">
        <div class="rounded-2xl border border-muted bg-soft-white p-4 space-y-3">
            <p class="text-sm font-semibold text-primary">Customer Type</p>
            <label class="flex items-center gap-2 text-sm text-primary">
                <input type="radio" name="customer_type" value="company" {{ $type === 'company' ? 'checked' : '' }}>
                Company (borrower)
            </label>
            <label class="flex items-center gap-2 text-sm text-primary">
                <input
                    type="radio"
                    name="customer_type"
                    value="representative"
                    {{ $type === 'representative' ? 'checked' : '' }}
                    {{ $canSelectRepresentative ? '' : 'disabled' }}>
                Representative (acts for a company)
            </label>
            <p class="text-xs text-muted leading-relaxed">
                <strong>Company</strong> is the borrowing entity. <strong>Representative</strong> is a contact/signatory linked to an existing company customer. Create or select the company customer first, then add representatives. {{ $canSelectRepresentative ? '' : ' (No company customers available, so representative is disabled.)' }}
            </p>
            @error('customer_type') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
        </div>

        <div class="rounded-2xl border border-muted bg-soft-white p-4 space-y-2">
            <label class="block text-sm font-semibold text-primary">Company (MOU / borrower) <span class="text-primary">*</span></label>
            <select name="company_id" class="w-full rounded-xl border border-muted px-3 py-2 text-primary">
                <option value="">Select company</option>
                @foreach($companies as $company)
                    <option value="{{ $company->id }}" {{ old('company_id', $customer->company_id) == $company->id ? 'selected' : '' }}>
                        {{ $company->name }}
                    </option>
                @endforeach
            </select>
            @error('company_id') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
        </div>
    </div>

    <div id="company-fields" class="space-y-4">
        <div>
            <label class="block text-sm font-semibold text-primary">Registered Name <span class="text-primary">*</span></label>
            <input type="text" name="registered_name" value="{{ old('registered_name', $customer->registered_name) }}" class="w-full rounded-xl border border-muted px-3 py-2 text-primary">
            @error('registered_name') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
        </div>
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="block text-sm font-semibold text-primary">Monthly Net Revenue <span class="text-primary">*</span></label>
                <input type="number" name="monthly_net_revenue" value="{{ old('monthly_net_revenue', $customer->net_salary) }}" step="0.01" min="0" required class="w-full rounded-xl border border-muted px-3 py-2 text-primary" placeholder="0.00">
                @error('monthly_net_revenue') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-semibold text-primary">Qualification Percentage (%) <span class="text-primary">*</span></label>
                <input type="number" name="qualification_percentage" value="{{ $smeQualificationPercentage }}" step="0.01" min="0" max="100" required class="w-full rounded-xl border border-muted px-3 py-2 text-primary" placeholder="60">
                <p class="text-xs text-muted mt-1">Example: 40, 60, 100</p>
                @error('qualification_percentage') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    <div id="representative-fields" class="space-y-4 hidden">
        <div>
            <label class="block text-sm font-semibold text-primary">Parent Company Customer <span class="text-primary">*</span></label>
            <select name="parent_customer_id" class="w-full rounded-xl border border-muted px-3 py-2 text-primary">
                <option value="">Select company customer</option>
                @foreach($companyCustomers as $cc)
                    <option value="{{ $cc->id }}" {{ old('parent_customer_id', $customer->parent_customer_id) == $cc->id ? 'selected' : '' }}>
                        {{ $cc->registered_name ?? $cc->full_name }}
                    </option>
                @endforeach
            </select>
            @error('parent_customer_id') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
        </div>
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="block text-sm font-semibold text-primary">First Name <span class="text-primary">*</span></label>
                <input type="text" name="first_name" value="{{ old('first_name', $customer->first_name) }}" class="w-full rounded-xl border border-muted px-3 py-2 text-primary">
            </div>
            <div>
                <label class="block text-sm font-semibold text-primary">Last Name <span class="text-primary">*</span></label>
                <input type="text" name="last_name" value="{{ old('last_name', $customer->last_name) }}" class="w-full rounded-xl border border-muted px-3 py-2 text-primary">
            </div>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <div>
            <label class="block text-sm font-semibold text-primary">Email <span class="text-primary">*</span></label>
            <input type="email" name="email" value="{{ old('email', $customer->email) }}" required class="w-full rounded-xl border border-muted px-3 py-2 text-primary">
        </div>
        <div>
            <label class="block text-sm font-semibold text-primary">Phone</label>
            <input type="text" name="phone" value="{{ old('phone') }}" maxlength="12" inputmode="numeric" pattern="260[0-9]{9}" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40 zambian-phone-input" placeholder="260978232334">
        </div>
    </div>

    @include('partials.customer-identity-fields', [
        'nationalIdType' => old('national_id_type', $customer->national_id_type ?? null),
        'nationalIdValue' => old('national_id', $customer->national_id ?? ''),
        'tpinValue' => old('tpin', $customer->tpin ?? ''),
        'labelClass' => 'block text-sm font-semibold text-primary',
        'inputClass' => 'w-full rounded-xl border border-muted px-3 py-2 text-primary',
        'errorClass' => 'text-xs text-red-500',
        'helpClass' => 'mt-1 text-xs text-slate-500',
        'requiredClass' => 'text-primary',
    ])

    <div class="grid gap-4 md:grid-cols-2">
        <div>
            <label class="block text-sm font-semibold text-primary">Address Line 1</label>
            <input type="text" name="address_line1" value="{{ old('address_line1', $customer->address_line1) }}" class="w-full rounded-xl border border-muted px-3 py-2 text-primary">
        </div>
        <div>
            <label class="block text-sm font-semibold text-primary">Address Line 2</label>
            <input type="text" name="address_line2" value="{{ old('address_line2', $customer->address_line2) }}" class="w-full rounded-xl border border-muted px-3 py-2 text-primary">
        </div>
        <div>
            <label class="block text-sm font-semibold text-primary">City</label>
            <input type="text" name="city" value="{{ old('city', $customer->city) }}" class="w-full rounded-xl border border-muted px-3 py-2 text-primary">
        </div>
        <div>
            <label class="block text-sm font-semibold text-primary">Country</label>
            <input type="text" name="country" value="{{ old('country', $customer->country) }}" class="w-full rounded-xl border border-muted px-3 py-2 text-primary">
        </div>
    </div>

    <div class="flex justify-end gap-3">
        <a href="{{ route('admin.customers.show', $customer) }}" class="btn-secondary inline-flex items-center justify-center rounded-xl px-4 py-2">Cancel</a>
        <button type="submit" class="btn-primary inline-flex items-center justify-center rounded-xl px-5 py-2 font-semibold">Save Changes</button>
    </div>
</form>

<script>
    (function() {
        const companySelect = document.querySelector('select[name="company_id"]');
        const registeredInput = document.querySelector('input[name="registered_name"]');
        const repRadio = document.querySelector('input[name="customer_type"][value="representative"]');
        const companyRadio = document.querySelector('input[name="customer_type"][value="company"]');
        let manuallyEdited = registeredInput && registeredInput.value.trim().length > 0;

        const toggleFields = () => {
            const checked = document.querySelector('input[name="customer_type"]:checked');

            // If representative is disabled but selected, revert to company
            if (checked && checked.value === 'representative' && repRadio && repRadio.disabled && companyRadio) {
                companyRadio.checked = true;
            }

            const type = document.querySelector('input[name="customer_type"]:checked')?.value;
            const companyBlock = document.getElementById('company-fields');
            const repBlock = document.getElementById('representative-fields');
            const enable = (block, on) => block.querySelectorAll('input,select,textarea').forEach(el => el.disabled = !on);
            if (companyBlock && repBlock) {
                companyBlock.style.display = type === 'company' ? 'block' : 'none';
                repBlock.style.display = type === 'representative' ? 'block' : 'none';
                enable(companyBlock, type === 'company');
                enable(repBlock, type === 'representative');
            }

            if (type === 'company') {
                syncRegisteredName();
            }
        };

        const syncRegisteredName = () => {
            if (!companySelect || !registeredInput) return;
            const type = document.querySelector('input[name="customer_type"]:checked')?.value;
            if (type !== 'company') return;
            const selected = companySelect.options[companySelect.selectedIndex];
            if (!selected || !selected.value) return;
            if (manuallyEdited) return;
            registeredInput.value = selected.text.trim();
        };

        companySelect?.addEventListener('change', syncRegisteredName);
        registeredInput?.addEventListener('input', () => {
            manuallyEdited = registeredInput.value.trim().length > 0;
        });

        document.querySelectorAll('input[name="customer_type"]').forEach(r => r.addEventListener('change', toggleFields));

        toggleFields();
        syncRegisteredName();
    })();
</script>
