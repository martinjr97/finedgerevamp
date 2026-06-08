@php
    use App\Models\LoanRateType;
    use App\Services\LoanRateRowService;

    $loanRate = $loanRate ?? null;
    $rateInputMode = $loanRateType->rate_input_mode
        ?? ($loanRateType->accrual_period === 'weekly'
            ? LoanRateType::RATE_INPUT_WEEKLY_MULTIPLIER
            : LoanRateType::RATE_INPUT_DAILY_MULTIPLIER);
    $interestBehavior = $loanRateType->interest_behavior ?? LoanRateType::INTEREST_BEHAVIOR_DAILY_ACCRUAL;
    $previewTermDays = app(LoanRateRowService::class)->previewTermDays((int) old('tenure_months', $loanRate?->tenure_months ?? 1));
    $derivedPreview = null;
    if ($rateInputMode === LoanRateType::RATE_INPUT_TERM_PERCENTAGE
        && $interestBehavior === LoanRateType::INTEREST_BEHAVIOR_DAILY_ACCRUAL) {
        $termPct = old('term_interest_percentage', $loanRate?->term_interest_percentage);
        if ($termPct !== null && $termPct !== '') {
            $derivedPreview = app(\App\Services\LoanPricingService::class)
                ->calculateDerivedDailyRateFromTerm((float) $termPct, $previewTermDays);
        } elseif ($loanRate?->derived_daily_rate !== null) {
            $derivedPreview = $loanRate->derived_daily_rate;
        }
    }
@endphp

<div
    x-data="{
        mode: @js($rateInputMode),
        behavior: @js($interestBehavior),
        tenure: @js((int) old('tenure_months', $loanRate?->tenure_months ?? 1)),
        termPct: @js(old('term_interest_percentage', $loanRate?->term_interest_percentage ?? '')),
        previewDaysPerMonth: {{ LoanRateRowService::PREVIEW_TERM_DAYS_PER_MONTH }},
        derivedPreview: @js($derivedPreview),
        updateDerived() {
            if (this.mode !== 'term_percentage' || this.behavior !== 'daily_accrual') {
                this.derivedPreview = null;
                return;
            }
            const pct = parseFloat(this.termPct);
            const tenure = parseInt(this.tenure, 10) || 1;
            const termDays = Math.max(1, tenure * this.previewDaysPerMonth);
            if (!isNaN(pct) && pct >= 0) {
                this.derivedPreview = ((pct / 100) / termDays).toFixed(8);
            } else {
                this.derivedPreview = null;
            }
        }
    }"
    x-init="updateDerived()"
>
    <div class="grid gap-6 md:grid-cols-2">
        <div>
            <label class="text-sm font-medium {{ $labelClass }}">Tenure (Months) <span class="{{ $requiredClass }}">*</span></label>
            <input type="number" name="tenure_months" x-model.number="tenure" @input="updateDerived()" value="{{ old('tenure_months', $loanRate?->tenure_months) }}" required min="1" step="1" class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}" placeholder="e.g., 1, 2, 3">
            @error('tenure_months')
                <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label class="text-sm font-medium {{ $labelClass }}">Processing Fee (%) <span class="{{ $requiredClass }}">*</span></label>
            <input type="number" name="processing_fee_percentage" value="{{ old('processing_fee_percentage', $loanRate?->processing_fee_percentage) }}" required min="0" max="100" step="0.01" class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}" placeholder="e.g., 5.00">
            @error('processing_fee_percentage')
                <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
            @enderror
            <p class="mt-1 text-xs {{ $helpClass }}">Separate from interest (e.g. 5% processing fee with 27.8% term interest).</p>
        </div>

        <div x-show="mode === 'term_percentage'" x-cloak>
            <label class="text-sm font-medium {{ $labelClass }}">Term Interest (%) <span class="{{ $requiredClass }}">*</span></label>
            <input type="number" name="term_interest_percentage" x-model="termPct" @input="updateDerived()" value="{{ old('term_interest_percentage', $loanRate?->term_interest_percentage) }}" min="0" step="0.0001" :required="mode === 'term_percentage'" class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}" placeholder="e.g., 27.8">
            @error('term_interest_percentage')
                <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
            @enderror
        </div>

        <div x-show="mode === 'daily_multiplier'" x-cloak>
            <label class="text-sm font-medium {{ $labelClass }}">Daily Rate <span class="{{ $requiredClass }}">*</span></label>
            <input type="number" name="daily_rate" value="{{ old('daily_rate', $loanRate?->daily_rate) }}" min="0" step="0.00001" :required="mode === 'daily_multiplier'" class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}" placeholder="e.g., 0.03">
            @error('daily_rate')
                <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
            @enderror
        </div>

        <div x-show="mode === 'weekly_multiplier'" x-cloak>
            <label class="text-sm font-medium {{ $labelClass }}">Weekly Rate <span class="{{ $requiredClass }}">*</span></label>
            <input type="number" name="weekly_rate" value="{{ old('weekly_rate', $loanRate?->weekly_rate) }}" min="0" step="0.00001" :required="mode === 'weekly_multiplier'" class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}" placeholder="e.g., 0.05">
            @error('weekly_rate')
                <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
            @enderror
        </div>

        <div x-show="mode === 'term_percentage' && behavior === 'daily_accrual'" x-cloak>
            <label class="text-sm font-medium {{ $labelClass }}">Derived Daily Rate (preview)</label>
            <input type="text" readonly class="mt-2 w-full rounded-2xl {{ $inputClass }} text-slate-300 px-4 py-3 opacity-80" :value="derivedPreview ?? '—'">
            <p class="mt-1 text-xs {{ $helpClass }}">
                Preview uses tenure × {{ LoanRateRowService::PREVIEW_TERM_DAYS_PER_MONTH }} days. Loan quotes use calendar months.
            </p>
        </div>

        <div>
            <label class="text-sm font-medium {{ $labelClass }}">Min Principal (optional)</label>
            <input type="number" name="min_principal" value="{{ old('min_principal', $loanRate?->min_principal) }}" min="0" step="0.01" class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}" placeholder="Open band if empty">
            @error('min_principal')
                <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label class="text-sm font-medium {{ $labelClass }}">Max Principal (optional)</label>
            <input type="number" name="max_principal" value="{{ old('max_principal', $loanRate?->max_principal) }}" min="0" step="0.01" class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}" placeholder="Open band if empty">
            @error('max_principal')
                <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="text-sm font-medium {{ $labelClass }}">Arrear Rate <span class="{{ $requiredClass }}">*</span></label>
            <input type="number" name="arrear_rate" value="{{ old('arrear_rate', $loanRate?->arrear_rate) }}" required min="0" step="0.00001" class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}" placeholder="e.g., 0.01">
            @error('arrear_rate')
                <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label class="text-sm font-medium {{ $labelClass }}">Status</label>
            <select name="is_active" class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
                <option value="1" @selected(old('is_active', $loanRate?->is_active ?? true) == true)>Active</option>
                <option value="0" @selected(old('is_active', $loanRate?->is_active ?? true) == false)>Inactive</option>
            </select>
            @error('is_active')
                <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
            @enderror
        </div>
    </div>
</div>
