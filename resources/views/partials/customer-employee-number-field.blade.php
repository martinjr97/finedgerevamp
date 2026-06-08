@php
    $employeeNumberValue = old('employee_number', $employeeNumberValue ?? '');
    $toggleWhenEmployed = $toggleWhenEmployed ?? false;
    $isEmployed = (bool) old('is_employed', $isEmployed ?? false);
    $labelClass = $labelClass ?? 'text-sm font-medium text-slate-200';
    $inputClass = $inputClass ?? 'mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40';
    $errorClass = $errorClass ?? 'mt-1 text-xs text-rose-400';
    $requiredClass = $requiredClass ?? 'text-rose-400';
    $showField = ! $toggleWhenEmployed || $isEmployed;
@endphp

<div
    data-employee-number-wrapper
    @if($toggleWhenEmployed)
        class="{{ $showField ? '' : 'hidden' }}"
    @endif
>
    <label for="employee_number" class="{{ $labelClass }}">
        Employee Number
        @if ($toggleWhenEmployed)
            <span class="{{ $requiredClass }}" data-employee-number-required-marker> *</span>
        @else
            <span class="{{ $requiredClass }}">*</span>
        @endif
    </label>
    <input
        type="text"
        id="employee_number"
        name="employee_number"
        value="{{ $employeeNumberValue }}"
        maxlength="50"
        placeholder="e.g. EMP-12345"
        data-employee-number-input
        @if (! $toggleWhenEmployed) required @endif
        class="{{ trim($inputClass.' '.($errors->has('employee_number') ? 'border-rose-500 ring-1 ring-rose-500/50' : '')) }}"
        @if($errors->has('employee_number')) aria-invalid="true" @endif
    />
    @error('employee_number')
        <p class="{{ $errorClass }} font-medium" role="alert">{{ $message }}</p>
    @enderror
</div>

@if ($toggleWhenEmployed)
    @once
        @push('scripts')
            <script>
                (function initEmployeeNumberToggle() {
                    const select = document.querySelector('select[name="is_employed"]');
                    if (!select) return;

                    const sync = () => {
                        const employed = select.value === '1' || select.value === 'true';
                        document.querySelectorAll('[data-employee-number-wrapper]').forEach((wrapper) => {
                            wrapper.classList.toggle('hidden', !employed);
                            const input = wrapper.querySelector('[data-employee-number-input]');
                            if (input) {
                                if (employed) {
                                    input.setAttribute('required', 'required');
                                } else {
                                    input.removeAttribute('required');
                                    input.value = '';
                                }
                            }
                        });
                    };

                    select.addEventListener('change', sync);
                    sync();
                })();
            </script>
        @endpush
    @endonce
@endif
