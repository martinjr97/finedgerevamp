{{-- Next of Kin fields for admin customer forms --}}
@php($customer = $customer ?? null)
<div class="rounded-3xl {{ $sectionClass }} p-6 shadow-lg">
    <h2 class="mb-6 text-xl font-semibold {{ $headingClass }} flex items-center gap-2">
        <span class="w-1 h-6 rounded-full bg-{{ $colors['input_focus_border'] }}"></span>Next of Kin Information</h2>
    <div class="grid gap-6 md:grid-cols-2">
        <div>
            <label class="text-sm font-medium {{ $labelClass }}">Name <span class="{{ $requiredClass }}">*</span></label>
            <input type="text" name="next_of_kin_name" value="{{ old('next_of_kin_name', optional($customer)->next_of_kin_name) }}" required class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
            @error('next_of_kin_name')
                <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label class="text-sm font-medium {{ $labelClass }}">Phone <span class="{{ $requiredClass }}">*</span></label>
            <input type="text" name="next_of_kin_phone" value="{{ old('next_of_kin_phone', optional($customer)->next_of_kin_phone) }}" maxlength="12" inputmode="numeric" pattern="260[0-9]{9}" class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }} zambian-phone-input" placeholder="260978232334" required>
            @error('next_of_kin_phone')
                <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label class="text-sm font-medium {{ $labelClass }}">Relationship <span class="{{ $requiredClass }}">*</span></label>
            <select name="next_of_kin_relationship" required class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
                <option value="">Select Relationship</option>
                @foreach (['spouse' => 'Spouse', 'parent' => 'Parent', 'sibling' => 'Sibling', 'child' => 'Child', 'relative' => 'Relative', 'friend' => 'Friend', 'other' => 'Other'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('next_of_kin_relationship', optional($customer)->next_of_kin_relationship) == $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('next_of_kin_relationship')
                <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
            @enderror
        </div>
        <div class="md:col-span-2">
            <label class="text-sm font-medium {{ $labelClass }}">Address Line 1</label>
            <input type="text" name="next_of_kin_address_line1" value="{{ old('next_of_kin_address_line1', optional($customer)->next_of_kin_address_line1) }}" class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
            @error('next_of_kin_address_line1')
                <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
            @enderror
        </div>
        <div class="md:col-span-2">
            <label class="text-sm font-medium {{ $labelClass }}">Address Line 2</label>
            <input type="text" name="next_of_kin_address_line2" value="{{ old('next_of_kin_address_line2', optional($customer)->next_of_kin_address_line2) }}" class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
            @error('next_of_kin_address_line2')
                <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label class="text-sm font-medium {{ $labelClass }}">City</label>
            <input type="text" name="next_of_kin_city" value="{{ old('next_of_kin_city', optional($customer)->next_of_kin_city) }}" class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
            @error('next_of_kin_city')
                <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label class="text-sm font-medium {{ $labelClass }}">Country</label>
            <input type="text" name="next_of_kin_country" value="{{ old('next_of_kin_country', optional($customer)->next_of_kin_country ?? 'Zambia') }}" class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
            @error('next_of_kin_country')
                <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
            @enderror
        </div>
    </div>
</div>
