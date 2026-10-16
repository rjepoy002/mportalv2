(() => {
    const dialog = document.getElementById('session-action-dialog');
    if (!dialog) return;
    const form = dialog.querySelector('[data-session-form]');
    const intent = dialog.querySelector('[data-session-intent]');
    const cancel = dialog.querySelector('[data-session-cancel]');
    const confirm = dialog.querySelector('[data-session-confirm]');
    const error = dialog.querySelector('[data-session-error]');
    const actions = {
        reset: { title: 'Reset your password?', message: 'You will be signed out of your MPortalV2 account to securely reset your password. You will need to sign in again after completing the password reset.', confirm: 'Continue to Reset' },
        logout: { title: 'Sign out of MPortalV2?', message: 'Are you sure you want to sign out of your account?', confirm: 'Sign Out' },
    };
    let trigger, submitting = false, completed = false;
    document.querySelectorAll('[data-session-action]').forEach((button) => button.addEventListener('click', () => {
        if (submitting || completed) return;
        const action = actions[button.dataset.sessionAction];
        if (!action) return;
        trigger = button;
        intent.value = button.dataset.sessionAction;
        dialog.querySelector('#session-action-title').textContent = action.title;
        dialog.querySelector('#session-action-description').textContent = action.message;
        confirm.textContent = action.confirm;
        error.classList.add('hidden');
        dialog.showModal();
        cancel.focus();
    }));
    cancel.addEventListener('click', () => { if (!submitting) dialog.close(); });
    dialog.addEventListener('cancel', (event) => { if (submitting) event.preventDefault(); });
    dialog.addEventListener('close', () => { intent.value = ''; trigger?.focus(); });
    form.addEventListener('submit', async (event) => {
        if (submitting || completed || !actions[intent.value]) { event.preventDefault(); return; }
        if (typeof window.fetch !== 'function') { submitting = true; return; }
        event.preventDefault();
        submitting = true;
        cancel.disabled = confirm.disabled = true;
        form.setAttribute('aria-busy', 'true');
        confirm.textContent = 'Signing out…';
        try {
            const response = await window.fetch('logout.php', { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { Accept: 'application/json' }, cache: 'no-store' });
            const result = await response.json();
            if (!response.ok || !result.ok) throw new Error(result.message || 'We could not complete this action. Please refresh and try again.');
            if (!['index.php', 'forgot-password.php'].includes(result.redirect)) throw new Error('Please refresh this page to check your session.');
            completed = true;
            window.location.assign(result.redirect);
        } catch (failure) {
            error.textContent = failure.message || 'We could not complete this action. Please refresh to check your session.';
            error.classList.remove('hidden');
            error.focus();
            submitting = false;
            cancel.disabled = confirm.disabled = false;
            confirm.textContent = actions[intent.value].confirm;
            form.removeAttribute('aria-busy');
        }
    });
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) window.location.reload();
    });
})();
