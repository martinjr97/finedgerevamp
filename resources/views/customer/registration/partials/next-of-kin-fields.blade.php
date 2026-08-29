@php
    $inputClass = $inputClass ?? 'mt-2 w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 focus:border-blue-500 focus:ring-blue-500/25';
@endphp

<div class="space-y-4 rounded-3xl border border-slate-200 bg-white/90 px-5 py-4 shadow-md">
    <h2 class="text-lg font-semibold text-slate-900">Next of kin information</h2>
    <p class="text-sm text-slate-600">Provide contact details for someone we can reach in case of emergency.</p>

    <div class="grid gap-4 md:grid-cols-2">
        <div>
            <label for="next_of_kin_name" class="block text-sm font-medium text-slate-800">
                Name <span class="text-red-500">*</span>
            </label>
            <input
                id="next_of_kin_name"
                name="next_of_kin_name"
                type="text"
                value="{{ old('next_of_kin_name') }}"
                required
                class="{{ $inputClass }}"
            >
            @error('next_of_kin_name')<p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="next_of_kin_phone" class="block text-sm font-medium text-slate-800">
                Phone <span class="text-red-500">*</span>
            </label>
            <input
                id="next_of_kin_phone"
                name="next_of_kin_phone"
                type="text"
                value="{{ old('next_of_kin_phone') }}"
                maxlength="12"
                inputmode="numeric"
                pattern="260[0-9]{9}"
                placeholder="260970000000"
                required
                class="{{ $inputClass }} zambian-phone-input"
            >
            @error('next_of_kin_phone')<p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="next_of_kin_relationship" class="block text-sm font-medium text-slate-800">
                Relationship <span class="text-red-500">*</span>
            </label>
            <select id="next_of_kin_relationship" name="next_of_kin_relationship" required class="{{ $inputClass }}">
                <option value="">Select relationship</option>
                @foreach (['spouse' => 'Spouse', 'parent' => 'Parent', 'sibling' => 'Sibling', 'child' => 'Child', 'relative' => 'Relative', 'friend' => 'Friend', 'other' => 'Other'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('next_of_kin_relationship') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('next_of_kin_relationship')<p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>@enderror
        </div>
        <div class="md:col-span-2">
            <label for="next_of_kin_address_line1" class="block text-sm font-medium text-slate-800">Address line 1 (optional)</label>
            <input
                id="next_of_kin_address_line1"
                name="next_of_kin_address_line1"
                type="text"
                value="{{ old('next_of_kin_address_line1') }}"
                class="{{ $inputClass }}"
            >
            @error('next_of_kin_address_line1')<p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="next_of_kin_city" class="block text-sm font-medium text-slate-800">City (optional)</label>
            <input
                id="next_of_kin_city"
                name="next_of_kin_city"
                type="text"
                value="{{ old('next_of_kin_city') }}"
                class="{{ $inputClass }}"
            >
            @error('next_of_kin_city')<p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="next_of_kin_country" class="block text-sm font-medium text-slate-800">Country (optional)</label>
            <input
                id="next_of_kin_country"
                name="next_of_kin_country"
                type="text"
                value="{{ old('next_of_kin_country', 'Zambia') }}"
                class="{{ $inputClass }}"
            >
            @error('next_of_kin_country')<p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>@enderror
        </div>
    </div>
</div>
