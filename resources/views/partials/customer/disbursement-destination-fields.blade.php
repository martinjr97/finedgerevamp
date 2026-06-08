@php
    use App\Models\Channel;

    $channel = $channel ?? null;
    $financialInstitutions = $financialInstitutions ?? collect();
    $customerPhone = $customerPhone ?? null;
    $channelType = $channel?->type ?? Channel::TYPE_MOBILE_WALLET;
    $typeLabel = match ($channelType) {
        Channel::TYPE_BANK => 'Bank Transfer',
        Channel::TYPE_CASH => 'Cash',
        default => 'Mobile Money',
    };
    $institutionId = (string) old('disbursement_financial_institution_id', $disbursementFinancialInstitutionId ?? '');
    $branchId = (string) old('disbursement_financial_institution_branch_id', $disbursementFinancialInstitutionBranchId ?? '');
    $accountHolder = old('disbursement_account_holder_name', $disbursementAccountHolderName ?? '');
    $accountNumber = old('disbursement_account_number', $disbursementAccountNumber ?? '');
    $phoneValue = old('disbursement_phone_number', $disbursementPhoneNumber ?? $customerPhone);
    $notesValue = old('disbursement_notes', $disbursementNotes ?? null);
    $inputClass = $inputClass ?? 'w-full rounded-xl bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white px-4 py-3 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20';
    $labelClass = $labelClass ?? 'block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2';
    $wrapperId = $wrapperId ?? 'customerDisbursementDestinationFields';
@endphp

<div id="{{ $wrapperId }}" class="space-y-4" data-disbursement-destination-fields data-fixed-channel-type="{{ $channelType }}">
    <input type="hidden" name="channel_id" value="{{ $channel?->id }}">

    <div class="rounded-xl border-2 border-blue-200 dark:border-blue-700 bg-blue-50 dark:bg-blue-900/30 p-4">
        <p class="text-xs uppercase tracking-wider text-blue-700 dark:text-blue-300 font-semibold">Disbursement method</p>
        <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $channel?->name ?? '—' }}</p>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ $typeLabel }}</p>
    </div>

    @if($channelType === Channel::TYPE_MOBILE_WALLET)
        <div data-destination-block="mobile_wallet" class="space-y-4">
            @if($customerPhone)
                <div class="space-y-3">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="radio"
                               name="use_profile_phone"
                               value="1"
                               id="use_profile_phone"
                               class="w-5 h-5 text-blue-600"
                               @checked(old('use_profile_phone', '1') === '1')
                               onchange="window.toggleCustomerProfilePhone?.()">
                        <span class="text-gray-900 dark:text-white font-medium">Use my profile number ({{ $customerPhone }})</span>
                    </label>
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="radio"
                               name="use_profile_phone"
                               value="0"
                               id="use_other_phone"
                               class="w-5 h-5 text-blue-600"
                               @checked(old('use_profile_phone') === '0')
                               onchange="window.toggleCustomerProfilePhone?.()">
                        <span class="text-gray-900 dark:text-white font-medium">Use a different mobile money number</span>
                    </label>
                </div>
            @endif

            <div id="customer_phone_input_container" class="{{ old('use_profile_phone', $customerPhone ? '1' : '0') === '1' && $customerPhone ? 'hidden' : '' }}">
                @include('partials.zambian-phone-field', [
                    'name' => 'disbursement_phone_number',
                    'label' => 'Mobile money number',
                    'value' => $phoneValue,
                    'required' => ! $customerPhone,
                    'inputClass' => $inputClass.' zambian-phone-input',
                    'labelClass' => $labelClass,
                    'errorClass' => 'mt-2 text-red-500 text-sm',
                    'helpClass' => 'mt-2 text-xs text-gray-500 dark:text-gray-400',
                ])
            </div>
        </div>
    @elseif($channelType === Channel::TYPE_BANK)
        <div data-destination-block="bank" class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="{{ $labelClass }}">Bank <span class="text-red-500">*</span></label>
                    <select name="disbursement_financial_institution_id"
                            data-disbursement-institution-select
                            required
                            class="{{ $inputClass }}">
                        <option value="">Select bank</option>
                        @foreach($financialInstitutions as $institution)
                            <option value="{{ $institution->id }}" @selected($institutionId === (string) $institution->id)>
                                {{ $institution->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('disbursement_financial_institution_id')
                        <p class="mt-2 text-red-500 text-sm">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="{{ $labelClass }}">Branch <span class="text-red-500">*</span></label>
                    <select name="disbursement_financial_institution_branch_id"
                            data-disbursement-branch-select
                            required
                            class="{{ $inputClass }}">
                        <option value="">Select branch</option>
                        @foreach($financialInstitutions as $institution)
                            @foreach($institution->branches as $branch)
                                <option value="{{ $branch->id }}"
                                        data-financial-institution-id="{{ $institution->id }}"
                                        @selected($branchId === (string) $branch->id)>
                                    {{ $branch->name }}
                                </option>
                            @endforeach
                        @endforeach
                    </select>
                    @error('disbursement_financial_institution_branch_id')
                        <p class="mt-2 text-red-500 text-sm">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="{{ $labelClass }}">Account holder name <span class="text-red-500">*</span></label>
                    <input type="text"
                           name="disbursement_account_holder_name"
                           value="{{ $accountHolder }}"
                           required
                           class="{{ $inputClass }}">
                    @error('disbursement_account_holder_name')
                        <p class="mt-2 text-red-500 text-sm">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="{{ $labelClass }}">Account number <span class="text-red-500">*</span></label>
                    <input type="text"
                           name="disbursement_account_number"
                           value="{{ $accountNumber }}"
                           maxlength="50"
                           required
                           class="{{ $inputClass }}">
                    @error('disbursement_account_number')
                        <p class="mt-2 text-red-500 text-sm">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>
    @else
        <div data-destination-block="cash" class="space-y-4">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                No electronic destination is required. Your loan will be disbursed as cash when approved.
            </p>
            <div>
                <label class="{{ $labelClass }}">Notes (optional)</label>
                <textarea name="disbursement_notes"
                          rows="3"
                          class="{{ $inputClass }}"
                          placeholder="Optional pickup or collection instructions">{{ $notesValue }}</textarea>
                @error('disbursement_notes')
                    <p class="mt-2 text-red-500 text-sm">{{ $message }}</p>
                @enderror
            </div>
        </div>
    @endif
</div>

@once
    @push('scripts')
        <script>
            window.toggleCustomerProfilePhone = window.toggleCustomerProfilePhone || function () {
                const useProfile = document.getElementById('use_profile_phone')?.checked;
                const container = document.getElementById('customer_phone_input_container');
                const phoneInput = container?.querySelector('[name="disbursement_phone_number"]');
                if (!container) {
                    return;
                }
                if (useProfile) {
                    container.classList.add('hidden');
                    phoneInput?.removeAttribute('required');
                } else {
                    container.classList.remove('hidden');
                    phoneInput?.setAttribute('required', 'required');
                }
            };

            window.initCustomerDisbursementDestinationFields = window.initCustomerDisbursementDestinationFields || function (root) {
                const scope = root || document;
                scope.querySelectorAll('[data-disbursement-destination-fields][data-fixed-channel-type]').forEach((wrapper) => {
                    if (wrapper.dataset.customerDisbursementInitialized === '1') {
                        return;
                    }
                    wrapper.dataset.customerDisbursementInitialized = '1';

                    const branchSelect = wrapper.querySelector('[data-disbursement-branch-select]');
                    const institutionSelect = wrapper.querySelector('[data-disbursement-institution-select]');

                    const syncBranches = () => {
                        if (!branchSelect || !institutionSelect) {
                            return;
                        }
                        const institutionId = institutionSelect.value;
                        branchSelect.querySelectorAll('option[data-financial-institution-id]').forEach((option) => {
                            const matches = option.dataset.financialInstitutionId === institutionId;
                            option.hidden = !matches;
                            option.disabled = !matches;
                        });
                        if (branchSelect.selectedOptions[0]?.disabled) {
                            branchSelect.value = '';
                        }
                    };

                    institutionSelect?.addEventListener('change', syncBranches);
                    syncBranches();
                    window.toggleCustomerProfilePhone?.();
                });
            };

            document.addEventListener('DOMContentLoaded', () => {
                window.initCustomerDisbursementDestinationFields();
            });
        </script>
    @endpush
@endonce
