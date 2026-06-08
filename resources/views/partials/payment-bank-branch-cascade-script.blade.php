@once
    @push('scripts')
        <script>
            window.initPaymentBankBranchCascade = window.initPaymentBankBranchCascade || function (root) {
                const scope = root || document;

                scope.querySelectorAll('[data-payment-bank-branch-fields]').forEach((wrapper) => {
                    if (wrapper.dataset.paymentBankBranchInit === '1') {
                        return;
                    }
                    wrapper.dataset.paymentBankBranchInit = '1';

                    const bankSelect = wrapper.querySelector('[data-bank-institution-id-select]');
                    const branchSelect = wrapper.querySelector('[data-bank-branch-id-select]');

                    if (!bankSelect || !branchSelect) {
                        return;
                    }

                    const institutionsEmpty = branchSelect.dataset.institutionsEmpty === '1';

                    const syncBranches = () => {
                        const institutionId = bankSelect.value || '';
                        let hasVisible = false;

                        branchSelect.querySelectorAll('option[data-financial-institution-id]').forEach((option) => {
                            const matches = String(option.dataset.financialInstitutionId) === String(institutionId);
                            option.hidden = !matches;
                            option.disabled = false;
                            if (matches) {
                                hasVisible = true;
                            }
                        });

                        branchSelect.disabled = institutionsEmpty || !institutionId;

                        const placeholder = branchSelect.querySelector('option[value=""]');
                        if (placeholder && !institutionsEmpty) {
                            placeholder.textContent = institutionId ? 'Select branch' : 'Select bank first';
                        }

                        const selected = branchSelect.selectedOptions[0];
                        if (!institutionId || !hasVisible) {
                            if (selected?.dataset?.financialInstitutionId) {
                                branchSelect.value = '';
                            }
                        } else if (selected?.hidden) {
                            branchSelect.value = '';
                        }

                        if (branchSelect.tomselect) {
                            branchSelect.tomselect.sync();
                        }
                    };

                    bankSelect.addEventListener('change', syncBranches);
                    syncBranches();
                });
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => window.initPaymentBankBranchCascade());
            } else {
                window.initPaymentBankBranchCascade();
            }
        </script>
    @endpush
@endonce
