@php
    $inputClass = $inputClass ?? 'mt-2 w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 focus:border-blue-500 focus:ring-blue-500/25';
    $pairId = $pairId ?? 'home';
@endphp

<div class="space-y-4 rounded-3xl border border-slate-200 bg-white/90 px-5 py-4 shadow-md">
    <h2 class="text-lg font-semibold text-slate-900">{{ $heading ?? 'Your address' }}</h2>
    @if(!empty($description))
        <p class="text-sm text-slate-600">{{ $description }}</p>
    @endif

    <div>
        <label for="address_line1" class="block text-sm font-medium text-slate-800">Address line 1 <span class="text-red-500">*</span></label>
        <input id="address_line1" name="address_line1" type="text" value="{{ old('address_line1') }}" required class="{{ $inputClass }}">
        @error('address_line1')<p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="address_line2" class="block text-sm font-medium text-slate-800">Address line 2 (optional)</label>
        <input id="address_line2" name="address_line2" type="text" value="{{ old('address_line2') }}" class="{{ $inputClass }}">
        @error('address_line2')<p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>@enderror
    </div>
    <div class="grid gap-4 md:grid-cols-2">
        <div>
            <label for="city" class="block text-sm font-medium text-slate-800">City / town <span class="text-red-500">*</span></label>
            <input id="city" name="city" type="text" value="{{ old('city') }}" required class="{{ $inputClass }}">
            @error('city')<p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="postal_code" class="block text-sm font-medium text-slate-800">Postal code (optional)</label>
            <input id="postal_code" name="postal_code" type="text" value="{{ old('postal_code') }}" class="{{ $inputClass }}">
            @error('postal_code')<p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="province_id" class="block text-sm font-medium text-slate-800">Province <span class="text-red-500">*</span></label>
            <select
                id="province_id"
                name="province_id"
                required
                data-province-select
                data-province-district-pair="{{ $pairId }}"
                class="{{ $inputClass }}"
            >
                <option value="">Select province</option>
                @foreach($provinces as $province)
                    <option value="{{ $province->id }}" @selected((int) old('province_id') === $province->id)>{{ $province->name }}</option>
                @endforeach
            </select>
            @error('province_id')<p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="district_id" class="block text-sm font-medium text-slate-800">District <span class="text-red-500">*</span></label>
            <select
                id="district_id"
                name="district_id"
                required
                data-district-select
                data-province-district-pair="{{ $pairId }}"
                data-placeholder="Select district"
                class="{{ $inputClass }}"
            >
                <option value="">Select district</option>
                @foreach($districts as $district)
                    <option
                        value="{{ $district->id }}"
                        data-province-id="{{ $district->province_id }}"
                        @selected((int) old('district_id') === $district->id)
                    >{{ $district->name }}</option>
                @endforeach
            </select>
            @error('district_id')<p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>@enderror
        </div>
        <div class="md:col-span-2">
            <label for="country" class="block text-sm font-medium text-slate-800">Country <span class="text-red-500">*</span></label>
            <input id="country" name="country" type="text" value="{{ old('country', 'Zambia') }}" required class="{{ $inputClass }}">
            @error('country')<p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>@enderror
        </div>
    </div>
</div>
