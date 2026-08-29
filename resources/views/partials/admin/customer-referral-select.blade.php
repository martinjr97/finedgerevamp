@props([
    'referredByCustomers',
    'selected' => null,
    'inputClass' => 'bg-white/10 border border-white/10',
    'inputFocusClass' => 'focus:border-cyan-400 focus:ring-cyan-400/40',
    'labelClass' => 'text-slate-300',
    'errorClass' => 'text-rose-400',
    'selectClass' => 'mt-2 w-full rounded-2xl text-white px-4 py-3',
])

@php
    $selectedValue = $selected ?? old('referred_by');
@endphp

<div>
    <label class="text-sm font-medium {{ $labelClass }}">Referred By</label>
    <select
        name="referred_by"
        data-select-search-force="true"
        data-search-placeholder="Search by name, phone, NRC, or email…"
        class="{{ $selectClass }} {{ $inputClass }} {{ $inputFocusClass }}"
    >
        <option value="">No referral</option>
        @foreach ($referredByCustomers as $referrer)
            <option value="{{ $referrer->id }}" @selected((string) $selectedValue === (string) $referrer->id)>
                {{ $referrer->full_name }}
                @if ($referrer->phone)
                    · {{ $referrer->phone }}
                @endif
                @if ($referrer->national_id)
                    · {{ $referrer->national_id }}
                @endif
                @if ($referrer->email)
                    · {{ $referrer->email }}
                @endif
            </option>
        @endforeach
    </select>
    @error('referred_by')
        <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
    @enderror
</div>
