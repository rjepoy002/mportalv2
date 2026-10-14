document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('resetPasswordForm');
    const password = document.getElementById('password');
    const confirmation = document.getElementById('confirm_password');
    const button = document.getElementById('submitButton');
    if (!form || !password || !confirmation || !button) return;

    const initialButton = button.innerHTML;
    let submitting = false;
    let attempted = false;

    function validate() {
        const value = password.value;
        const length = Array.from(value).length >= 8;
        const letter = /[A-Za-z]/.test(value);
        const number = /[0-9]/.test(value);
        const passwordError = !value ? 'Please enter a new password.'
            : !length ? 'Password must be at least 8 characters.'
            : !letter ? 'Password must contain at least one letter.'
            : !number ? 'Password must contain at least one number.' : '';
        const confirmError = !confirmation.value ? 'Please confirm your password.'
            : confirmation.value !== value ? 'Passwords do not match.' : '';
        return { length, letter, number, passwordError, confirmError,
            valid: !passwordError && !confirmError };
    }

    function render() {
        const state = validate();
        [['lengthRequirement', state.length], ['letterRequirement', state.letter],
            ['numberRequirement', state.number]].forEach(function ([id, valid]) {
            const item = document.getElementById(id);
            item.classList.toggle('valid', valid);
            item.querySelector('i').className = valid ? 'bi bi-check-circle-fill' : 'bi bi-circle';
        });
        [[password, 'passwordError', state.passwordError],
            [confirmation, 'confirmPasswordError', state.confirmError]].forEach(function ([input, id, error]) {
            const showError = attempted || input.value !== '';
            document.getElementById(id).textContent = showError ? error : '';
            input.classList.toggle('input-error', showError && error !== '');
            input.setAttribute('aria-invalid', String(showError && error !== ''));
            input.closest('.input-wrapper').classList.toggle('input-valid', input.value !== '' && !error && !state.passwordError);
        });
        const mandatoryValid = !state.passwordError;
        const strong = mandatoryValid && Array.from(password.value).length >= 12 && /[^A-Za-z0-9]/.test(password.value);
        const strength = !password.value ? 0 : !mandatoryValid ? 1 : strong ? 4 : 3;
        document.querySelectorAll('.strength-bars span').forEach(function (bar, index) {
            bar.style.background = index < strength ? (mandatoryValid ? '#168454' : '#c65b39') : '#e5ebe8';
        });
        document.getElementById('strengthText').textContent = !password.value ? 'Enter a password'
            : !mandatoryValid ? 'Requirements missing' : strong ? 'Strong' : 'Good';
        button.disabled = submitting || !state.valid;
        if (!submitting) button.innerHTML = initialButton;
        return state;
    }

    [['passwordToggle', password], ['confirmPasswordToggle', confirmation]].forEach(function ([id, input]) {
        const toggle = document.getElementById(id);
        toggle.addEventListener('click', function () {
            const visible = input.type === 'password';
            input.type = visible ? 'text' : 'password';
            toggle.innerHTML = visible ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
            toggle.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
        });
    });
    password.addEventListener('input', render);
    confirmation.addEventListener('input', render);
    form.addEventListener('submit', function (event) {
        attempted = true;
        if (submitting || event.defaultPrevented || !render().valid) {
            event.preventDefault();
            return;
        }
        submitting = true;
        button.disabled = true;
        button.textContent = 'Resetting...';
    });
    window.addEventListener('pageshow', function () {
        submitting = false;
        render();
    });
    render();
});
