document.addEventListener('DOMContentLoaded', function () {

    const form = document.getElementById('forgotPasswordForm');
    const email = document.getElementById('email');
    const submitButton = document.getElementById('submitButton');

    if (!form || !email || !submitButton) {
        return;
    }
    let submitting = false;
    const initialButton = submitButton.innerHTML;

    form.addEventListener('submit', function (event) {

        if (submitting || event.defaultPrevented) {
            event.preventDefault();
            return;
        }

        const emailValue = email.value.trim();

        if (emailValue === '') {
            event.preventDefault();

            email.focus();

            return;
        }

        if (!email.checkValidity()) {
            event.preventDefault();

            email.focus();

            return;
        }

        // Prevent multiple submissions
        submitting = true;
        submitButton.disabled = true;
        submitButton.textContent = 'Sending...';

    });

    window.addEventListener('pageshow', function () {
        submitting = false;
        submitButton.disabled = false;
        submitButton.innerHTML = initialButton;
    });

});
