(() => {
    const forms = document.querySelectorAll('[data-account-link-form]');
    let submitting = false;
    forms.forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (submitting) {
                event.preventDefault();
                return;
            }
            submitting = true;
            form.setAttribute('aria-busy', 'true');
            forms.forEach((other) => other.querySelectorAll('button:not(:disabled)').forEach((button) => {
                button.dataset.linkPreviousText = button.textContent;
                button.disabled = true;
            }));
            const submit = form.querySelector('[data-link-submit]');
            if (submit) submit.textContent = form.dataset.loadingText;
        });
    });
    // Restore controls after browser back/forward cache navigation.
    window.addEventListener('pageshow', () => {
        submitting = false;
        forms.forEach((form) => {
            form.removeAttribute('aria-busy');
            form.querySelectorAll('[data-link-previous-text]').forEach((button) => {
                button.textContent = button.dataset.linkPreviousText;
                button.disabled = false;
                delete button.dataset.linkPreviousText;
            });
        });
    });
})();
