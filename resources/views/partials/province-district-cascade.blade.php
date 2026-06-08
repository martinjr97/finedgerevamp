{{-- Links province/district selects via matching data-province-district-pair attributes. --}}
@once
    @push('scripts')
        <script>
            (function initProvinceDistrictCascade() {
                const run = () => {
                    const provinceSelects = document.querySelectorAll('[data-province-select][data-province-district-pair]');

                    provinceSelects.forEach((provinceSelect) => {
                        const pairId = provinceSelect.dataset.provinceDistrictPair;
                        const districtSelect = document.querySelector(
                            `[data-district-select][data-province-district-pair="${pairId}"]`
                        );

                        if (!districtSelect || provinceSelect.dataset.provinceDistrictInit === 'true') {
                            return;
                        }

                        provinceSelect.dataset.provinceDistrictInit = 'true';
                        districtSelect.dataset.provinceDistrictInit = 'true';

                        const placeholderText =
                            districtSelect.dataset.placeholder?.trim() || 'Select District';
                        const allDistrictOptions = Array.from(districtSelect.options).filter(
                            (option) => option.value !== ''
                        );
                        const initialDistrictId = districtSelect.value;

                        const destroyTomSelect = (select) => {
                            if (select.tomselect) {
                                select.tomselect.destroy();
                                delete select.tomselect;
                            }
                            delete select.dataset.selectSearchInit;
                        };

                        const filterDistricts = () => {
                            const selectedProvinceId = String(provinceSelect.value || '');
                            const previousSelection = districtSelect.value;

                            destroyTomSelect(districtSelect);

                            districtSelect.innerHTML = '';
                            const placeholder = document.createElement('option');
                            placeholder.value = '';
                            placeholder.textContent = placeholderText;
                            districtSelect.appendChild(placeholder);

                            if (selectedProvinceId) {
                                allDistrictOptions.forEach((option) => {
                                    const optionProvinceId = String(
                                        option.getAttribute('data-province-id') || ''
                                    );
                                    if (optionProvinceId === selectedProvinceId) {
                                        districtSelect.appendChild(option.cloneNode(true));
                                    }
                                });
                            }

                            const restoreId = previousSelection || initialDistrictId;
                            if (
                                restoreId &&
                                Array.from(districtSelect.options).some(
                                    (option) => option.value === restoreId
                                )
                            ) {
                                districtSelect.value = restoreId;
                            } else {
                                districtSelect.value = '';
                            }
                        };

                        provinceSelect.addEventListener('change', filterDistricts);
                        filterDistricts();
                    });
                };

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', run);
                } else {
                    run();
                }

                window.addEventListener('load', run);
            })();
        </script>
    @endpush
@endonce
