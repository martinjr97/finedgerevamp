<div class="space-y-4 rounded-3xl border border-slate-200 bg-white/90 px-5 py-4 shadow-md">
    <h2 class="text-lg font-semibold text-slate-900">Your details</h2>

    <div class="grid gap-4 md:grid-cols-2">
        <div>
            <label for="first_name" class="block text-sm font-medium text-slate-800">First name <span class="text-red-500">*</span></label>
            <input id="first_name" name="first_name" type="text" value="{{ old('first_name') }}" required
                class="mt-2 w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 focus:border-blue-500 focus:ring-blue-500/25">
            @error('first_name')<p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="last_name" class="block text-sm font-medium text-slate-800">Last name <span class="text-red-500">*</span></label>
            <input id="last_name" name="last_name" type="text" value="{{ old('last_name') }}" required
                class="mt-2 w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 focus:border-blue-500 focus:ring-blue-500/25">
            @error('last_name')<p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <div>
            <label for="phone" class="block text-sm font-medium text-slate-800">Mobile number <span class="text-red-500">*</span></label>
            <input type="text" name="phone" id="phone" value="{{ old('phone') }}" maxlength="12" inputmode="numeric" pattern="260[0-9]{9}" required
                class="mt-2 w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 focus:border-blue-500 focus:ring-blue-500/25 zambian-phone-input"
                placeholder="260978232334">
            @error('phone')<p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="email" class="block text-sm font-medium text-slate-800">Email <span class="text-red-500">*</span></label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required
                class="mt-2 w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 focus:border-blue-500 focus:ring-blue-500/25">
            @error('email')<p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>@enderror
        </div>
    </div>

    <div>
        @include('partials.customer-identity-fields', [
            'nationalIdType' => old('national_id_type'),
            'nationalIdValue' => old('national_id'),
            'tpinValue' => old('tpin'),
            'labelClass' => 'block text-sm font-medium text-slate-800',
            'inputClass' => 'mt-2 w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 focus:border-blue-500 focus:ring-blue-500/25',
            'errorClass' => 'mt-1 text-xs text-red-600 font-medium',
            'helpClass' => 'mt-1 text-xs text-slate-500',
            'requiredClass' => 'text-red-500',
        ])
    </div>

    <div>
        <label for="requested_loan_amount" class="block text-sm font-medium text-slate-800">Requested loan amount <span class="text-red-500">*</span></label>
        <input id="requested_loan_amount" name="requested_loan_amount" type="number" step="0.01" min="1" value="{{ old('requested_loan_amount') }}" required
            class="mt-2 w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 focus:border-blue-500 focus:ring-blue-500/25">
        @error('requested_loan_amount')<p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>@enderror
    </div>
</div>
