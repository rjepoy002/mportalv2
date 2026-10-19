(() => {
    const form = document.querySelector('[data-profile-settings]');
    if (!form) return;
    const field = form.querySelector('[data-profile-contact]');
    const error = document.getElementById('profile-contact-error');
    const message = 'Enter a valid Philippine mobile number: 09XXXXXXXXX, +639XXXXXXXXX, 639XXXXXXXXX or 9XXXXXXXXX. Spaces and hyphens are allowed.';
    const showError = text => {
        field.setCustomValidity(text);
        field.setAttribute('aria-invalid', String(Boolean(text)));
        error.textContent = text;
        error.hidden = !text;
    };
    const normalize = () => {
        const number = field.value.replace(/[ -]/g, '');
        const empty = field.value.replace(/ /g, '') === '';
        if (!empty && /^(?:0|\+63|63)?9[0-9]{9}$/.exec(number)?.[0] !== number) {
            showError(message);
            return false;
        }
        field.value = number === '' ? '' : '0' + number.slice(-10);
        showError('');
        return true;
    };
    field.addEventListener('blur', normalize);
    // Clear stale validity during editing without rewriting the typed value.
    field.addEventListener('input', () => showError(''));
    field.addEventListener('invalid', () => showError(message));
    form.addEventListener('submit', event => {
        if (!normalize()) {
            event.preventDefault();
            field.focus();
        }
    });
})();
