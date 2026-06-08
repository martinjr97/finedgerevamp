@extends('layouts.admin')

@section('title', 'Group Loan Details | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Group Loan Application',
            'description' => 'Step 2: capture group-level loan details and rates',
            'buttons' => [
                [
                    'action' => 'secondary',
                    'text' => 'Back to Members',
                    'href' => route('admin.loan-applications.group-loans.members', ['loanProduct' => $loanProduct, 'customer_group_id' => $wizard['customer_group_id'] ?? null]),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>'
                ]
            ]
        ])

        <div class="rounded-3xl border border-cyan-500/30 bg-cyan-950/30 p-4 shadow-lg">
            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <p class="text-xs uppercase tracking-wide text-cyan-300 mb-1">Product</p>
                    <p class="text-white font-semibold">{{ $loanProduct->name }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-cyan-300 mb-1">Selected Group</p>
                    <p class="text-white font-semibold">{{ $group->name }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-cyan-300 mb-1">Selected Members</p>
                    <p class="text-white font-semibold">{{ $members->count() }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <form method="POST" action="{{ route('admin.loan-applications.group-loans.store-details', $loanProduct) }}" class="space-y-6">
                @csrf

                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Group Loan Name <span class="text-red-400">*</span></label>
                        <input type="text" name="loan_name" value="{{ old('loan_name', $wizard['loan_name'] ?? '') }}" required
                               class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                        @error('loan_name')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Repayment Structure <span class="text-red-400">*</span></label>
                        <select name="repayment_structure" required class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                            <option value="">Select structure</option>
                            <option value="weekly" @selected(old('repayment_structure', $wizard['repayment_structure'] ?? '') === 'weekly')>Weekly</option>
                            <option value="monthly" @selected(old('repayment_structure', $wizard['repayment_structure'] ?? '') === 'monthly')>Monthly</option>
                        </select>
                        @error('repayment_structure')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Start Date <span class="text-red-400">*</span></label>
                        <input type="date" id="groupLoanStartDate" name="start_date" value="{{ old('start_date', $wizard['start_date'] ?? '') }}" required
                               class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                        @error('start_date')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Loan Term Value</label>
                        <input type="number" id="groupLoanTermValue" name="loan_term_value" min="1" step="1" value="{{ old('loan_term_value', $wizard['loan_term_value'] ?? '') }}"
                               class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40"
                               placeholder="e.g. 1, 4, 6">
                        @error('loan_term_value')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Loan Term Unit</label>
                        <select id="groupLoanTermUnit" name="loan_term_unit" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                            <option value="">Manual due date entry</option>
                            <option value="weeks" @selected(old('loan_term_unit', $wizard['loan_term_unit'] ?? '') === 'weeks')>Weeks</option>
                            <option value="months" @selected(old('loan_term_unit', $wizard['loan_term_unit'] ?? '') === 'months')>Months</option>
                        </select>
                        @error('loan_term_unit')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Due Date</label>
                        <input type="date" id="groupLoanDueDate" name="due_date" value="{{ old('due_date', $wizard['due_date'] ?? '') }}"
                               class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                        <p class="mt-1 text-xs text-slate-400">Auto-calculated when both loan term value and unit are set.</p>
                        @error('due_date')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                    </div>

                    @if ($canAssignRelationshipManager)
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-2">Relationship Manager</label>
                            <select name="relationship_manager_id" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                                <option value="">Select relationship manager</option>
                                @foreach ($relationshipManagers as $manager)
                                    <option value="{{ $manager->id }}" @selected((int) old('relationship_manager_id', $selectedRelationshipManagerId) === $manager->id)>
                                        {{ $manager->full_name }} ({{ $manager->email }})
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-slate-400">If no selection is made and your account is a relationship manager, the loan will default to you.</p>
                            @error('relationship_manager_id')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                        </div>
                    @else
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-2">Relationship Manager</label>
                            <div class="rounded-2xl bg-white/10 border border-white/10 px-4 py-3 text-white">
                                {{ $currentAdmin->full_name }} ({{ $currentAdmin->email }})
                            </div>
                            <p class="mt-1 text-xs text-slate-400">Assigned automatically to your account.</p>
                            @error('relationship_manager_id')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                        </div>
                    @endif

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Processing Fee (%) <span class="text-red-400">*</span></label>
                        <input type="number" name="processing_fee_percentage" min="0" step="0.0001" value="{{ old('processing_fee_percentage', $wizard['processing_fee_percentage'] ?? '') }}" required
                               class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                        @error('processing_fee_percentage')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Interest Rate for Full Period (%) <span class="text-red-400">*</span></label>
                        <input type="number" name="monthly_interest_rate" min="0" step="0.0001" value="{{ old('monthly_interest_rate', $wizard['monthly_interest_rate'] ?? '') }}" required
                               class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                        <p class="mt-1 text-xs text-slate-400">This rate is applied once to the principal for the entire loan period.</p>
                        @error('monthly_interest_rate')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Arrears Rate (%) <span class="text-red-400">*</span></label>
                        <input type="number" name="arrears_rate" min="0" step="0.0001" value="{{ old('arrears_rate', $wizard['arrears_rate'] ?? '') }}" required
                               class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                        @error('arrears_rate')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                    </div>

                    @unless ($canProceedWithRelationshipManager)
                        <div class="md:col-span-2 rounded-2xl border border-red-400/40 bg-red-500/10 p-4">
                            <p class="text-sm font-semibold text-red-300">Cannot Proceed</p>
                            <p class="mt-1 text-sm text-red-200/90">Your account is not marked as a relationship manager and you do not have permission to assign one. Contact an administrator with the <code>can assign relationship manager to group</code> permission.</p>
                        </div>
                    @endunless
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Terms and Conditions</label>
                    <textarea name="terms_and_conditions" rows="5" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40" placeholder="Enter terms and conditions for this group loan application...">{{ old('terms_and_conditions', $wizard['terms_and_conditions'] ?? '') }}</textarea>
                    @error('terms_and_conditions')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>

                <div class="flex justify-end gap-3">
                    <a href="{{ route('admin.loan-applications.group-loans.members', ['loanProduct' => $loanProduct, 'customer_group_id' => $wizard['customer_group_id'] ?? null]) }}" class="inline-flex items-center rounded-2xl border border-white/20 px-4 py-3 text-sm font-medium text-slate-300 hover:bg-white/10 transition">Back</a>
                    <button type="submit" @disabled(! $canProceedWithRelationshipManager) class="inline-flex items-center rounded-2xl px-4 py-3 text-sm font-semibold text-white transition {{ $canProceedWithRelationshipManager ? 'bg-cyan-500 hover:bg-cyan-600' : 'bg-slate-600/70 cursor-not-allowed' }}">Continue to Principal Amounts</button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const startDateInput = document.getElementById('groupLoanStartDate');
                const loanTermValueInput = document.getElementById('groupLoanTermValue');
                const loanTermUnitSelect = document.getElementById('groupLoanTermUnit');
                const dueDateInput = document.getElementById('groupLoanDueDate');

                if (!startDateInput || !loanTermValueInput || !loanTermUnitSelect || !dueDateInput) {
                    return;
                }

                const formatDate = (date) => {
                    const year = date.getFullYear();
                    const month = String(date.getMonth() + 1).padStart(2, '0');
                    const day = String(date.getDate()).padStart(2, '0');

                    return `${year}-${month}-${day}`;
                };

                const computeDueDate = (startDateValue, loanTermValue, loanTermUnit) => {
                    if (!startDateValue || !loanTermValue || !loanTermUnit) {
                        return '';
                    }

                    const [year, month, day] = startDateValue.split('-').map(Number);
                    const dueDate = new Date(year, month - 1, day);

                    switch (loanTermUnit) {
                        case 'weeks':
                            dueDate.setDate(dueDate.getDate() + (loanTermValue * 7));
                            break;
                        case 'months':
                            dueDate.setMonth(dueDate.getMonth() + loanTermValue);
                            break;
                        default:
                            return '';
                    }

                    return formatDate(dueDate);
                };

                const syncDueDate = () => {
                    const termValue = Number(loanTermValueInput.value);
                    const termUnit = loanTermUnitSelect.value;
                    const autoCompute = Number.isInteger(termValue) && termValue > 0 && termUnit !== '';

                    dueDateInput.readOnly = autoCompute;
                    dueDateInput.classList.toggle('cursor-not-allowed', autoCompute);
                    dueDateInput.classList.toggle('opacity-80', autoCompute);

                    if (!autoCompute) {
                        return;
                    }

                    const computed = computeDueDate(startDateInput.value, termValue, termUnit);
                    if (computed) {
                        dueDateInput.value = computed;
                    }
                };

                loanTermValueInput.addEventListener('input', syncDueDate);
                loanTermUnitSelect.addEventListener('change', syncDueDate);
                startDateInput.addEventListener('change', syncDueDate);
                syncDueDate();
            });
        </script>
    @endpush
@endsection
