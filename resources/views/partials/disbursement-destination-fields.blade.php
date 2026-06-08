@php
    use App\Models\Channel;

    $channels = $channels ?? collect();
    $financialInstitutions = $financialInstitutions ?? collect();
    $selectedChannelId = (string) old('channel_id', $selectedChannelId ?? '');
    $defaultPhone = $defaultPhone ?? null;
    $notesValue = old('disbursement_notes', $disbursementNotes ?? null);
    $institutionId = (string) old('disbursement_financial_institution_id', $disbursementFinancialInstitutionId ?? '');
    $branchId = (string) old('disbursement_financial_institution_branch_id', $disbursementFinancialInstitutionBranchId ?? '');
    $accountHolder = old('disbursement_account_holder_name', $disbursementAccountHolderName ?? '');
    $accountNumber = old('disbursement_account_number', $disbursementAccountNumber ?? '');
    $phoneValue = old('disbursement_phone_number', $disbursementPhoneNumber ?? $defaultPhone);
    $channelSelectId = $channelSelectId ?? 'disbursementChannelId';
    $wrapperId = $wrapperId ?? 'disbursementDestinationFields';
    $deferDestinationValidation = (bool) ($deferDestinationValidation ?? false);
    $inputClass = $inputClass ?? 'w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40';
    $labelClass = $labelClass ?? 'block text-sm font-medium text-slate-300 mb-2';
    $requiredMark = $deferDestinationValidation ? '' : '<span class="text-rose-400">*</span>';
@endphp

<div id="{{ $wrapperId }}" class="space-y-4 md:col-span-2" data-disbursement-destination-fields>
    @if($deferDestinationValidation)
        <p class="text-xs text-slate-400 rounded-xl border border-white/10 bg-white/5 px-4 py-3">
            Disbursement details are required when you continue to the next step. You can calculate the loan first without filling them in.
        </p>
    @endif
    <div>
        <label class="{{ $labelClass }}" for="{{ $channelSelectId }}">
            Disbursement Channel {!! $requiredMark !!}
        </label>
        <select name="channel_id"
                id="{{ $channelSelectId }}"
                @unless($deferDestinationValidation) required @endunless
                data-disbursement-channel-select
                class="{{ $inputClass }}">
            <option value="">Select Channel</option>
            @foreach($channels as $channel)
                <option value="{{ $channel->id }}"
                        data-channel-type="{{ $channel->type ?? Channel::TYPE_MOBILE_WALLET }}"
                        @selected($selectedChannelId === (string) $channel->id)>
                    {{ $channel->name }}
                </option>
            @endforeach
        </select>
        @error('channel_id')
            <p class="mt-1 text-xs text-rose-400 font-medium">{{ $message }}</p>
        @enderror
    </div>

    <div data-destination-block="mobile_wallet" class="hidden space-y-4">
        @include('partials.zambian-phone-field', [
            'name' => 'disbursement_phone_number',
            'label' => 'Disbursement mobile number',
            'value' => $phoneValue,
            'required' => false,
            'inputClass' => $inputClass.' zambian-phone-input',
            'labelClass' => $labelClass,
            'errorClass' => 'mt-1 text-xs text-rose-400 font-medium',
            'helpClass' => 'mt-1 text-xs text-slate-400',
        ])
    </div>

    <div data-destination-block="bank" class="hidden space-y-4">
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="{{ $labelClass }}">Financial institution <span class="text-rose-400">*</span></label>
                <select name="disbursement_financial_institution_id"
                        data-disbursement-institution-select
                        data-bank-required
                        data-no-select-search="true"
                        class="{{ $inputClass }}">
                    <option value="">Select bank</option>
                    @foreach($financialInstitutions as $institution)
                        <option value="{{ $institution->id }}" @selected($institutionId === (string) $institution->id)>
                            {{ $institution->name }}
                        </option>
                    @endforeach
                </select>
                @error('disbursement_financial_institution_id')
                    <p class="mt-1 text-xs text-rose-400 font-medium">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="{{ $labelClass }}">Branch <span class="text-rose-400">*</span></label>
                <select name="disbursement_financial_institution_branch_id"
                        data-disbursement-branch-select
                        data-bank-required
                        data-no-select-search="true"
                        class="{{ $inputClass }}">
                    <option value="">Select branch</option>
                    @foreach($financialInstitutions as $institution)
                        @foreach($institution->branches as $branch)
                            <option value="{{ $branch->id }}"
                                    data-financial-institution-id="{{ $institution->id }}"
                                    @selected($branchId === (string) $branch->id)>
                                {{ $institution->name }} — {{ $branch->name }}
                            </option>
                        @endforeach
                    @endforeach
                </select>
                @error('disbursement_financial_institution_branch_id')
                    <p class="mt-1 text-xs text-rose-400 font-medium">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="{{ $labelClass }}">Account holder name <span class="text-rose-400">*</span></label>
                <input type="text"
                       name="disbursement_account_holder_name"
                       value="{{ $accountHolder }}"
                       data-bank-required
                       class="{{ $inputClass }}">
                @error('disbursement_account_holder_name')
                    <p class="mt-1 text-xs text-rose-400 font-medium">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="{{ $labelClass }}">Account number <span class="text-rose-400">*</span></label>
                <input type="text"
                       name="disbursement_account_number"
                       value="{{ $accountNumber }}"
                       maxlength="50"
                       data-bank-required
                       class="{{ $inputClass }}">
                @error('disbursement_account_number')
                    <p class="mt-1 text-xs text-rose-400 font-medium">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </div>

    <div data-destination-block="cash" class="hidden space-y-4">
        <div>
            <label class="{{ $labelClass }}">Disbursement notes</label>
            <textarea name="disbursement_notes"
                      rows="3"
                      class="{{ $inputClass }}"
                      placeholder="Optional instructions for cash disbursement">{{ $notesValue }}</textarea>
            @error('disbursement_notes')
                <p class="mt-1 text-xs text-rose-400 font-medium">{{ $message }}</p>
            @enderror
        </div>
    </div>
</div>

@once
    @push('scripts')
        <script>
            window.initDisbursementDestinationFields = window.initDisbursementDestinationFields || function (root) {
                const scope = root || document;
                const wrappers = scope.querySelectorAll('[data-disbursement-destination-fields]');

                wrappers.forEach((wrapper) => {
                    if (wrapper.dataset.disbursementInitialized === '1') {
                        return;
                    }
                    wrapper.dataset.disbursementInitialized = '1';

                    const channelSelect = wrapper.querySelector('[data-disbursement-channel-select]');
                    const blocks = wrapper.querySelectorAll('[data-destination-block]');
                    const branchSelect = wrapper.querySelector('[data-disbursement-branch-select]');
                    const institutionSelect = wrapper.querySelector('[data-disbursement-institution-select]');

                    const syncBranches = () => {
                        if (!branchSelect || !institutionSelect) {
                            return;
                        }

                        const institutionId = institutionSelect.value;
                        let hasVisible = false;

                        branchSelect.querySelectorAll('option[data-financial-institution-id]').forEach((option) => {
                            const matches = String(option.dataset.financialInstitutionId) === String(institutionId);
                            option.hidden = !matches;
                            option.disabled = false;
                            if (matches) {
                                hasVisible = true;
                            }
                        });

                        branchSelect.disabled = !institutionId;

                        const placeholder = branchSelect.querySelector('option[value=""]');
                        if (placeholder) {
                            placeholder.textContent = institutionId ? 'Select branch' : 'Select bank first';
                        }

                        if (!institutionId || !hasVisible) {
                            const selected = branchSelect.selectedOptions[0];
                            if (selected?.dataset?.financialInstitutionId) {
                                branchSelect.value = '';
                            }
                        } else if (branchSelect.selectedOptions[0]?.hidden) {
                            branchSelect.value = '';
                        }

                        if (branchSelect.tomselect) {
                            branchSelect.tomselect.sync();
                        }
                    };

                    const toggleBlocks = () => {
                        const selected = channelSelect?.selectedOptions[0];
                        const type = selected?.dataset.channelType || 'mobile_wallet';

                        blocks.forEach((block) => {
                            const active = block.dataset.destinationBlock === type;
                            block.classList.toggle('hidden', !active);
                            block.querySelectorAll('input, select, textarea').forEach((field) => {
                                if (field.name === 'channel_id') {
                                    return;
                                }
                                field.disabled = !active;
                                if (!active) {
                                    field.removeAttribute('required');
                                }
                            });
                        });

                        const mobileBlock = wrapper.querySelector('[data-destination-block="mobile_wallet"]');
                        const phoneInput = mobileBlock?.querySelector('[name="disbursement_phone_number"]');
                        if (phoneInput) {
                            if (type === 'mobile_wallet') {
                                phoneInput.setAttribute('required', 'required');
                            } else {
                                phoneInput.removeAttribute('required');
                            }
                        }

                        const bankBlock = wrapper.querySelector('[data-destination-block="bank"]');
                        if (bankBlock && type === 'bank') {
                            bankBlock.querySelectorAll('[data-bank-required]').forEach((field) => {
                                field.setAttribute('required', 'required');
                            });
                            syncBranches();
                        }
                    };

                    channelSelect?.addEventListener('change', toggleBlocks);
                    institutionSelect?.addEventListener('change', syncBranches);
                    toggleBlocks();
                });
            };

            document.addEventListener('DOMContentLoaded', () => window.initDisbursementDestinationFields());
        </script>
    @endpush
@endonce
