@php
    use App\Support\NationalIdRules;
    use App\Rules\ZambianNrcNumber;

    $nationalIdType = old('national_id_type', $nationalIdType ?? NationalIdRules::TYPE_NRC);
    $nationalIdValue = old('national_id', $nationalIdValue ?? '');
    $tpinValue = old('tpin', $tpinValue ?? '');
    $inputClass = $inputClass ?? 'mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40';
    $labelClass = $labelClass ?? 'text-sm font-medium text-slate-200';
    $errorClass = $errorClass ?? 'mt-1 text-xs text-rose-400';
    $helpClass = $helpClass ?? 'mt-1 text-xs text-slate-400';
    $requiredClass = $requiredClass ?? 'text-rose-400';
    $typeHasError = $errors->has('national_id_type');
    $idHasError = $errors->has('national_id');
    $tpinHasError = $errors->has('tpin');
@endphp

<div class="customer-identity-fields grid gap-6 md:grid-cols-2" data-national-id-fields>
    <div>
        <label for="national_id_type" class="{{ $labelClass }}">
            National ID Type <span class="{{ $requiredClass }}">*</span>
        </label>
        <select
            id="national_id_type"
            name="national_id_type"
            required
            data-national-id-type-select
            class="{{ trim($inputClass.' '.($typeHasError ? 'border-rose-500 ring-1 ring-rose-500/50' : '')) }}"
            @if($typeHasError) aria-invalid="true" @endif
        >
            <option value="">Select ID type</option>
            @foreach (NationalIdRules::typeLabels() as $value => $label)
                <option value="{{ $value }}" @selected($nationalIdType === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('national_id_type')
            <p class="{{ $errorClass }} font-medium" role="alert">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="national_id" class="{{ $labelClass }}">
            National ID <span class="{{ $requiredClass }}">*</span>
        </label>
        <input
            type="text"
            id="national_id"
            name="national_id"
            value="{{ $nationalIdValue }}"
            required
            data-national-id-input
            placeholder="{{ $nationalIdType === NationalIdRules::TYPE_NRC ? ZambianNrcNumber::PLACEHOLDER : ($nationalIdType === NationalIdRules::TYPE_PASSPORT ? 'Passport number' : 'Driver\'s licence number') }}"
            @if($nationalIdType === NationalIdRules::TYPE_NRC)
                pattern="{{ ZambianNrcNumber::HTML_PATTERN }}"
                maxlength="11"
            @else
                maxlength="50"
            @endif
            class="{{ trim($inputClass.' '.($idHasError ? 'border-rose-500 ring-1 ring-rose-500/50' : '')) }}"
            @if($idHasError) aria-invalid="true" @endif
        />
        <p class="{{ $helpClass }}" data-national-id-help>
            @if ($nationalIdType === NationalIdRules::TYPE_NRC)
                Use Zambian NRC format with slashes, e.g. {{ ZambianNrcNumber::PLACEHOLDER }}.
            @elseif ($nationalIdType === NationalIdRules::TYPE_PASSPORT)
                Enter the passport number as shown on the document.
            @else
                Enter the driver’s licence number as shown on the document.
            @endif
        </p>
        @error('national_id')
            <p class="{{ $errorClass }} font-medium" role="alert">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="tpin" class="{{ $labelClass }}">TPIN (optional)</label>
        <input
            type="text"
            id="tpin"
            name="tpin"
            value="{{ $tpinValue }}"
            maxlength="50"
            placeholder="Tax payer ID if known"
            class="{{ trim($inputClass.' '.($tpinHasError ? 'border-rose-500 ring-1 ring-rose-500/50' : '')) }}"
            @if($tpinHasError) aria-invalid="true" @endif
        />
        <p class="{{ $helpClass }}">Leave blank if the customer does not have a TPIN.</p>
        @error('tpin')
            <p class="{{ $errorClass }} font-medium" role="alert">{{ $message }}</p>
        @enderror
    </div>
</div>

@once
    @push('scripts')
        <script>
            (function initNationalIdFields() {
                const containers = document.querySelectorAll('[data-national-id-fields]');
                const config = {
                    nrc: {
                        placeholder: @json(ZambianNrcNumber::PLACEHOLDER),
                        help: @json('Use Zambian NRC format with slashes, e.g. '.ZambianNrcNumber::PLACEHOLDER.'.'),
                        maxlength: '11',
                        pattern: @json(ZambianNrcNumber::HTML_PATTERN),
                    },
                    passport: {
                        placeholder: 'Passport number',
                        help: 'Enter the passport number as shown on the document.',
                        maxlength: '50',
                        pattern: '',
                    },
                    drivers_licence: {
                        placeholder: "Driver's licence number",
                        help: "Enter the driver's licence number as shown on the document.",
                        maxlength: '50',
                        pattern: '',
                    },
                };

                containers.forEach((container) => {
                    const select = container.querySelector('[data-national-id-type-select]');
                    const input = container.querySelector('[data-national-id-input]');
                    const help = container.querySelector('[data-national-id-help]');
                    if (!select || !input || !help) return;

                    const apply = () => {
                        const type = select.value || 'nrc';
                        const cfg = config[type] || config.nrc;
                        input.placeholder = cfg.placeholder;
                        help.textContent = cfg.help;
                        input.maxLength = cfg.maxlength;
                        if (cfg.pattern) {
                            input.setAttribute('pattern', cfg.pattern);
                        } else {
                            input.removeAttribute('pattern');
                        }
                    };

                    select.addEventListener('change', apply);
                    apply();
                });
            })();
        </script>
    @endpush
@endonce
