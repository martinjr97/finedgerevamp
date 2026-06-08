@php
    use App\Models\LoanRateType;
    use App\Services\LoanRateRowService;

    $rowService = app(LoanRateRowService::class);
    $interestBehavior = old('interest_behavior', $loanRateType->interest_behavior ?? LoanRateType::INTEREST_BEHAVIOR_UPFRONT_FLAT);
    $rateInputMode = old('rate_input_mode', $loanRateType->rate_input_mode ?? LoanRateType::RATE_INPUT_TERM_PERCENTAGE);
    $isExistingLegacy = $loanRateType->exists && $rowService->isLegacyRateEntryMethod($loanRateType->rate_input_mode);
@endphp

<div>
    <label class="text-sm font-medium {{ $labelClass }}">Interest Behavior <span class="{{ $requiredClass }}">*</span></label>
    <select name="interest_behavior" required class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
        <option value="{{ LoanRateType::INTEREST_BEHAVIOR_UPFRONT_FLAT }}" @selected($interestBehavior === LoanRateType::INTEREST_BEHAVIOR_UPFRONT_FLAT)>
            Upfront flat — full term interest at loan start
        </option>
        <option value="{{ LoanRateType::INTEREST_BEHAVIOR_DAILY_ACCRUAL }}" @selected($interestBehavior === LoanRateType::INTEREST_BEHAVIOR_DAILY_ACCRUAL)>
            Daily accrual — interest earns over time
        </option>
    </select>
    @error('interest_behavior')
        <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
    @enderror
</div>

@if($isExistingLegacy)
    <div>
        <label class="text-sm font-medium {{ $labelClass }}">Rate Entry Method</label>
        <p class="mt-2 rounded-2xl border border-amber-500/30 bg-amber-950/20 px-4 py-3 text-sm text-amber-100">
            {{ $rowService->rateEntryMethodLabel($loanRateType->rate_input_mode) }}
            <span class="block mt-1 text-xs text-amber-200/80">Legacy plan — kept for existing loans. New rate types use business term percentage only.</span>
        </p>
        <input type="hidden" name="rate_input_mode" value="{{ $rateInputMode }}">
    </div>
@else
    <input type="hidden" name="rate_input_mode" value="{{ LoanRateType::RATE_INPUT_TERM_PERCENTAGE }}">
@endif
