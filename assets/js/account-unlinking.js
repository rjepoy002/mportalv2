(() => {
    const dialog = document.getElementById('unlink-account-dialog');
    if (!dialog) return;
    const form = dialog.querySelector('[data-unlink-form]');
    const token = dialog.querySelector('[data-unlink-token]');
    const accountNumber = dialog.querySelector('[data-unlink-account-number]');
    const cancel = dialog.querySelector('[data-unlink-cancel]');
    const confirm = dialog.querySelector('[data-unlink-confirm]');
    let trigger = null, submitting = false;
    document.querySelectorAll('[data-unlink-open]').forEach((button) => button.addEventListener('click', () => {
        trigger = button;
        token.value = button.dataset.accountToken;
        accountNumber.textContent = button.dataset.accountNumber;
        dialog.showModal();
    }));
    cancel.addEventListener('click', () => { if (!submitting) dialog.close(); });
    dialog.addEventListener('cancel', (event) => { if (submitting) event.preventDefault(); });
    dialog.addEventListener('close', () => { token.value = ''; trigger?.focus(); });
    form.addEventListener('submit', (event) => {
        if (submitting || !token.value) { event.preventDefault(); return; }
        submitting = true;
        cancel.disabled = confirm.disabled = true;
        confirm.textContent = 'Unlinking…';
        form.setAttribute('aria-busy', 'true');
    });
    window.addEventListener('pageshow', () => {
        submitting = false;
        cancel.disabled = confirm.disabled = false;
        confirm.textContent = 'Confirm Unlink';
        form.removeAttribute('aria-busy');
        if (dialog.open) dialog.close();
    });
})();
