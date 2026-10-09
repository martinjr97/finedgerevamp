@php
    $formDefaults = $formDefaults ?? [];
    $fd = fn (string $key, $default = '') => old($key, $formDefaults[$key] ?? $default);
@endphp
<form method="POST" action="{{ $formAction }}" class="space-y-6 rounded-3xl border border-white/10 bg-white/5 p-6">
    @csrf
    <input type="hidden" name="loan_rate_id" x-model="loanRateId" value="{{ $fd('loan_rate_id') }}">

    <div class="grid md:grid-cols-2 gap-4">
        <div>
            <label class="text-sm text-slate-300">Employee</label>
            <select name="employee_id" required class="mt-1 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2" x-model="employeeId" @change="syncEmployee()">
                <option value="">Select employee</option>
                @foreach ($employees as $employee)
                    <option value="{{ $employee->id }}" @selected($fd('employee_id') === (string) $employee->id) data-dept="{{ $employee->hrDepartment?->name ?? $employee->department }}" data-pay="{{ $employee->currentCompensation?->basic_pay }}" data-number="{{ $employee->employee_number }}">{{ $employee->full_name }} ({{ $employee->employee_number }})</option>
                @endforeach
            </select>
        </div>
        <div class="text-sm text-slate-400 space-y-1 pt-6">
            <div>Employee #: <span x-text="employeeNumber || '—'"></span></div>
            <div>Department: <span x-text="department || '—'"></span></div>
            <div>Basic pay (info): <span x-text="basicPay ? 'K '+basicPay : '—'"></span></div>
        </div>
        <div>
            <label class="text-sm text-slate-300">Principal</label>
            <input type="number" step="0.01" min="1" name="principal_amount" required value="{{ $fd('principal_amount') }}" class="mt-1 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2" x-model="principal" @input.debounce.400ms="syncRateAndPreview()">
        </div>
        <div>
            <label class="text-sm text-slate-300">Tenure (months) <span class="text-rose-400">*</span></label>
            <select name="tenure_months" required class="mt-1 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2" x-model="tenureMonths" @change="syncRateAndPreview()">
                <option value="">Select tenure</option>
                @foreach ($rates as $rate)
                    <option value="{{ $rate->tenure_months }}" @selected($fd('tenure_months') === (string) $rate->tenure_months)
                            data-rate-id="{{ $rate->id }}">
                        {{ $rate->tenure_months }} {{ $rate->tenure_months === 1 ? 'month' : 'months' }}
                        @if($rate->term_interest_percentage !== null)
                            — {{ $rate->term_interest_percentage }}% term
                        @endif
                        @if($rate->min_principal !== null || $rate->max_principal !== null)
                            ({{ $rate->min_principal ? number_format((float) $rate->min_principal, 0) : '0' }}–{{ $rate->max_principal ? number_format((float) $rate->max_principal, 0) : '∞' }} principal)
                        @endif
                    </option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-slate-500" x-show="appliedRateLabel" x-text="appliedRateLabel"></p>
        </div>
        <div>
            <label class="text-sm text-slate-300">Repayment frequency</label>
            <select name="repayment_frequency" class="mt-1 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2">
                <option value="monthly" @selected($fd('repayment_frequency', 'monthly') === 'monthly')>Monthly</option>
                <option value="weekly" @selected($fd('repayment_frequency') === 'weekly')>Weekly</option>
            </select>
        </div>
        <div>
            <label class="text-sm text-slate-300">First repayment date</label>
            <input type="date" name="first_payment_date" required value="{{ $fd('first_payment_date') }}" class="mt-1 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2">
        </div>
        <div>
            @include('partials.loan-purpose-select', [
                'loanPurposes' => $loanPurposes,
                'selected' => $fd('loan_purpose_id'),
                'inputId' => 'employeeLoanPurposeId',
                'labelClass' => 'text-sm text-slate-300',
                'selectClass' => 'mt-1 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2',
            ])
        </div>
        @include('admin.hr.employee-loans.partials.payout-destination-fields')

        <div class="md:col-span-2">
            <label class="text-sm text-slate-300">Notes</label>
            <textarea name="notes" rows="2" class="mt-1 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2">{{ $fd('notes') }}</textarea>
        </div>
    </div>

    <div class="rounded-2xl border border-cyan-500/20 bg-cyan-500/5 p-4 text-sm" x-show="previewData">
        <h3 class="font-semibold text-cyan-200 mb-2">Pricing preview</h3>
        <template x-if="previewData">
            <dl class="grid md:grid-cols-3 gap-2">
                <div>Interest: <span x-text="previewData.interest"></span></div>
                <div>Processing fee: <span x-text="previewData.processing_fee"></span></div>
                <div>Total repayable: <span x-text="previewData.total_repayable"></span></div>
                <div>Installments: <span x-text="previewData.installment_count"></span></div>
                <div>Term rate: <span x-text="previewData.applied_rate?.term_interest_percentage ?? previewData.quoted_term_rate ?? '—'"></span>%</div>
                <div>Arrear rate: <span x-text="previewData.arrear_rate ?? previewData.applied_rate?.arrear_rate ?? '—'"></span></div>
            </dl>
        </template>
        <p class="mt-2 text-rose-300 text-xs" x-show="previewError" x-text="previewError"></p>
    </div>

    <div class="flex flex-wrap gap-3 items-center">
        <button type="submit" class="rounded-2xl bg-emerald-600 px-6 py-2.5 font-semibold text-white shadow-lg hover:bg-emerald-500 transition" :disabled="!loanRateId">
            {{ $submitLabel ?? 'Review loan' }}
        </button>
        <span class="text-xs text-slate-500">{{ $submitHint ?? 'Opens a full breakdown to confirm before the loan is saved.' }}</span>
        <a href="{{ $cancelHref }}" class="rounded-2xl border border-white/20 px-6 py-2 text-slate-200">Cancel</a>
    </div>
</form>

<script>
function employeeLoanForm() {
    return {
        employeeId: @js($fd('employee_id')),
        department: '', basicPay: '', employeeNumber: '',
        tenureMonths: @js($fd('tenure_months')),
        principal: @js($fd('principal_amount')),
        loanRateId: @js($fd('loan_rate_id')),
        appliedRateLabel: '',
        previewData: null, previewError: '',
        payoutDestinationMode: @js($fd('payout_destination_mode', 'employee_account')),
        payoutMethod: @js($fd('payout_method', 'bank')),
        employeeAccounts: [],
        employeeAccountsLoading: false,
        selectedEmployeeAccountId: @js($fd('payout_employee_bank_account_id')),
        init() {
            if (this.employeeId) {
                this.syncEmployee();
            }
            if (this.tenureMonths && this.principal) {
                this.syncRateAndPreview();
            }
        },
        syncEmployee() {
            const opt = this.$el.querySelector(`select[name="employee_id"] option[value="${this.employeeId}"]`);
            if (!opt) return;
            this.department = opt.dataset.dept || '';
            this.basicPay = opt.dataset.pay || '';
            this.employeeNumber = opt.dataset.number || '';
            this.loadEmployeeAccounts();
        },
        loadEmployeeAccounts() {
            const preserveSelection = this.selectedEmployeeAccountId;
            this.employeeAccounts = [];
            if (!this.employeeId) {
                return;
            }
            this.employeeAccountsLoading = true;
            fetch(`{{ route('admin.hr.employee-loans.employee-payment-accounts') }}?employee_id=${this.employeeId}`)
                .then(r => r.json())
                .then(data => {
                    this.employeeAccounts = data.accounts || [];
                    if (preserveSelection && this.employeeAccounts.some(a => String(a.id) === String(preserveSelection))) {
                        this.selectedEmployeeAccountId = String(preserveSelection);
                    } else if (this.employeeAccounts.length) {
                        const primary = this.employeeAccounts.find(a => a.is_primary);
                        this.selectedEmployeeAccountId = String((primary || this.employeeAccounts[0]).id);
                    }
                    if (! this.employeeAccounts.length) {
                        this.payoutDestinationMode = 'alternative';
                    }
                })
                .catch(() => {
                    this.payoutDestinationMode = 'alternative';
                })
                .finally(() => { this.employeeAccountsLoading = false; });
        },
        syncRateAndPreview() {
            this.previewError = '';
            this.previewData = null;
            if (!this.tenureMonths || !this.principal) {
                this.loanRateId = '';
                this.appliedRateLabel = '';
                return;
            }
            const params = new URLSearchParams({
                tenure_months: this.tenureMonths,
                principal_amount: this.principal,
            });
            fetch(`{{ route('admin.hr.employee-loans.pricing-preview') }}?${params.toString()}`)
                .then(async (r) => {
                    const data = await r.json();
                    if (!r.ok) {
                        this.loanRateId = '';
                        this.appliedRateLabel = '';
                        this.previewError = data.message || data.errors?.tenure_months?.[0] || 'Unable to price this loan.';
                        return;
                    }
                    this.previewData = data;
                    this.loanRateId = data.loan_rate_id ? String(data.loan_rate_id) : '';
                    const rate = data.applied_rate;
                    if (rate) {
                        this.appliedRateLabel = `Employee Rate: ${rate.tenure_months} months at ${rate.term_interest_percentage ?? '—'}% term interest`;
                    }
                })
                .catch(() => {
                    this.loanRateId = '';
                    this.previewError = 'Pricing preview failed.';
                });
        }
    }
}
</script>
