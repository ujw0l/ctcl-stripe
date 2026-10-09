(() => {
    const init = () => {
        const panel = document.querySelector('.ctcl-stripe-settings');
        const form = panel?.closest('form');
        if (!form) return;
        form.classList.add('ctcl-st-form');
        const save = form.querySelector('[type="submit"]');
        if (save) save.value = panel.dataset.saveLabel;
        const status = panel.querySelector('.ctcl-st-save-status');
        const snapshot = () => JSON.stringify(Array.from(new FormData(form).entries()));
        const saved = snapshot();
        const mode = panel.querySelector('#ctcl-stripe-test-mode');
        const label = panel.querySelector('#ctcl-stripe-display-label');
        const update = () => {
            panel.querySelector('.ctcl-st-preview-label').textContent = label.value.trim() || label.placeholder;
            panel.querySelectorAll('[data-environment]').forEach(el => {
                el.dataset.active = String(el.dataset.environment === (mode.checked ? 'test' : 'live'));
            });
            const environment = panel.querySelector('.ctcl-st-env-label');
            environment.textContent = mode.checked ? environment.dataset.test : environment.dataset.live;
            const dirty = snapshot() !== saved;
            status.textContent = dirty ? status.dataset.unsaved : status.dataset.saved;
            status.classList.toggle('is-dirty', dirty);
        };
        panel.querySelectorAll('.ctcl-st-reveal').forEach(button => {
            button.addEventListener('click', () => {
                const input = document.getElementById(button.getAttribute('aria-controls'));
                const showing = input.type === 'password';
                input.type = showing ? 'text' : 'password';
                button.textContent = showing ? button.dataset.hide : button.dataset.show;
                button.setAttribute('aria-pressed', String(showing));
                button.setAttribute('aria-label', `${button.textContent} ${input.closest('.ctcl-st-field').querySelector('label').textContent}`);
            });
        });
        form.addEventListener('input', update);
        form.addEventListener('change', update);
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
