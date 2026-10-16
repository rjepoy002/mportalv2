(() => {
    let submitting = false;
    const restore = () => {
        submitting = false;
        document.querySelectorAll('[data-account-link-form]').forEach((form) => {
            form.removeAttribute('aria-busy');
            form.querySelectorAll('[data-link-previous-text]').forEach((button) => {
                button.textContent = button.dataset.linkPreviousText;
                button.disabled = false;
                delete button.dataset.linkPreviousText;
            });
        });
    };
    const focusPanel = () => {
        const panel = document.getElementById?.('account-linking-panel');
        if (!panel) return;
        const target = panel.querySelector('[data-link-error]') || panel.querySelector('[data-link-step-heading]');
        target?.focus({ preventScroll: true });
        panel.scrollIntoView({ block: 'start', behavior: 'instant' });
    };
    const bind = () => {
      const forms = document.querySelectorAll('[data-account-link-form]');
      forms.forEach((form) => {
        form.addEventListener('submit', async (event) => {
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
            const panel = document.getElementById?.('account-linking-panel');
            if (typeof window.fetch !== 'function' || !panel) return;
            event.preventDefault();
            try {
                const response = await window.fetch('dashboard.php?page=account-settings', {
                    method: 'POST', body: new FormData(form), credentials: 'same-origin',
                    headers: { Accept: 'application/json' }, cache: 'no-store',
                });
                if (!response.ok) throw new Error('Request failed');
                const result = await response.json();
                if (result.linked) {
                    window.location.assign('dashboard.php?page=account-settings');
                    return;
                }
                if (typeof result.html !== 'string') throw new Error('Invalid response');
                panel.outerHTML = result.html;
                submitting = false;
                bind();
                focusPanel();
            } catch (error) {
                restore();
                let status = panel.querySelector('[data-link-client-error]');
                if (!status) {
                    status = document.createElement('p');
                    status.dataset.linkClientError = '';
                    status.setAttribute('role', 'alert');
                    status.tabIndex = -1;
                    status.className = 'mt-4 text-sm text-red-700';
                    panel.prepend(status);
                }
                status.textContent = 'We could not load the verification step. Please refresh this page to check your session before trying again.';
                status.focus({ preventScroll: true });
                panel.scrollIntoView({ block: 'start', behavior: 'instant' });
            }
        });
      });
    };
    bind();
    // Restore controls after browser back/forward cache navigation.
    window.addEventListener('pageshow', () => {
        restore();
        if (window.location?.hash === '#account-linking-panel') focusPanel();
    });
})();
