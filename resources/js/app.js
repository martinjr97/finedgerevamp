import './bootstrap';
import './zambian-phone-input';

// Prevent accidental double-submit on state-changing forms.
// Applies globally to all pages that load app.js.
document.addEventListener(
    'submit',
    (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        if (form.dataset.preventDoubleSubmit === 'false') {
            return;
        }

        const method = (form.getAttribute('method') || 'get').toLowerCase();
        if (method === 'get') {
            return;
        }

        if (form.dataset.submitting === 'true') {
            event.preventDefault();
            return;
        }

        form.dataset.submitting = 'true';

        const submitControls = form.querySelectorAll(
            'button[type="submit"], input[type="submit"], input[type="image"]'
        );

        submitControls.forEach((control) => {
            control.setAttribute('disabled', 'disabled');
            control.setAttribute('aria-disabled', 'true');
            control.classList.add('opacity-60', 'cursor-not-allowed');
        });
    },
    true
);
