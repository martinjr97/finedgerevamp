const ZAMBIAN_MOBILE_PATTERN = /^260(95|96|97|75|76|77)\d{7}$/;

const INVALID_PHONE_MESSAGE =
    'Enter a valid Zambian mobile number in 260XXXXXXXXX format (e.g. 260978232334).';

function normalizePhoneValue(value) {
    const trimmed = (value || '').trim();
    if (trimmed.includes('@')) {
        return '';
    }

    return trimmed.replace(/\D/g, '').slice(0, 12);
}

function isValidZambianMobile(value) {
    return ZAMBIAN_MOBILE_PATTERN.test(normalizePhoneValue(value));
}

function syncPhoneValidity(input) {
    const digits = normalizePhoneValue(input.value);

    if (digits === '') {
        input.setCustomValidity('');

        return;
    }

    input.setCustomValidity(
        isValidZambianMobile(digits) ? '' : INVALID_PHONE_MESSAGE
    );
}

function bindZambianPhoneInput(input) {
    const applyNormalization = () => {
        input.value = normalizePhoneValue(input.value);
        syncPhoneValidity(input);
    };

    setTimeout(applyNormalization, 50);
    setTimeout(applyNormalization, 250);

    input.addEventListener('input', applyNormalization);
    input.addEventListener('change', applyNormalization);
    input.addEventListener('blur', applyNormalization);

    input.addEventListener('keypress', (event) => {
        if (!/[0-9]/.test(event.key)) {
            event.preventDefault();
        }
    });

    input.addEventListener('paste', (event) => {
        event.preventDefault();
        const paste = (event.clipboardData || window.clipboardData).getData('text');
        input.value = normalizePhoneValue(paste);
        syncPhoneValidity(input);
    });

    const form = input.closest('form');
    if (form && !form.dataset.zambianPhoneBound) {
        form.dataset.zambianPhoneBound = 'true';
        form.addEventListener('submit', (event) => {
            form.querySelectorAll('.zambian-phone-input').forEach((field) => {
                field.value = normalizePhoneValue(field.value);
                syncPhoneValidity(field);
            });

            const firstInvalid = form.querySelector('.zambian-phone-input:invalid');
            if (firstInvalid) {
                event.preventDefault();
                firstInvalid.reportValidity();
                firstInvalid.focus();
            }
        });
    }
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.zambian-phone-input').forEach(bindZambianPhoneInput);
});
