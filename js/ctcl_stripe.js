(() => {
    const init = () => {
        const form = document.querySelector('#ctcl-checkout-from');
        const panel = document.querySelector('.ctcl-stripe-checkout');
        const option = document.querySelector('#ctcl_stripe');
        if (!form || !panel || !option) return;
        const params = window.ctclStripeParams || {};
        const error = panel.querySelector('#card-errors');
        const status = panel.querySelector('.ctcl-stripe-payment-status');
        const prepare = panel.querySelector('.ctcl-stripe-prepare');
        const instructions = panel.querySelector('.ctcl-stripe-instructions');
        const total = panel.querySelector('.ctcl-stripe-verified-total');
        const mount = panel.querySelector('#ctcl-stripe-payment-el');
        const container = panel.closest('#ctcl_stripe_container');
        const controls = Array.from(form.querySelectorAll('[type="submit"]'));
        let checkout, paymentElement, actions, response, preparedSnapshot, loadTimeout;
        let busy = false;
        let ready = false;
        let previousAttempt = '';
        const storageKey = `ctclStripeAttempt:${location.pathname}`;
        const newAttempt = () => Array.from(crypto.getRandomValues(new Uint8Array(24)), byte => byte.toString(16).padStart(2, '0')).join('');
        const snapshot = () => JSON.stringify(Array.from(new FormData(form).entries()).filter(([name]) => !['payment_option','payment_type'].includes(name)));
        let attempt = newAttempt();
        let attemptSnapshot = '';
        try {
            const saved = JSON.parse(sessionStorage.getItem(storageKey) || 'null');
            if (saved?.attempt && /^[a-f0-9]{48}$/.test(saved.attempt)) { attempt = saved.attempt; attemptSnapshot = saved.snapshot; }
        } catch { /* Storage can be disabled. Checkout still works in this page. */ }
        const setBusy = (value, message = '') => {
            busy = value;
            panel.setAttribute('aria-busy', String(value));
            prepare.disabled = value;
            controls.forEach(control => {
                if (value) { control.dataset.stripeWasDisabled = String(control.disabled); control.disabled = true; }
                else if ('stripeWasDisabled' in control.dataset) { control.disabled = control.dataset.stripeWasDisabled === 'true'; delete control.dataset.stripeWasDisabled; }
            });
            status.textContent = message;
        };
        const invalidate = () => {
            if (busy || !checkout) return;
            if (snapshot() === preparedSnapshot) return;
            clearTimeout(loadTimeout);
            total.hidden = true;
            ready = false;
            actions = null;
            paymentElement?.destroy();
            paymentElement = null;
            checkout = null;
            mount.replaceChildren();
            prepare.hidden = false;
            instructions.hidden = false;
            error.textContent = '';
            status.textContent = params.changed;
        };
        const showOption = () => { if (container) container.style.display = option.checked ? 'block' : ''; };
        document.querySelectorAll('.ctcl-payment-option').forEach(input => input.addEventListener('change', showOption));
        showOption();
        if (typeof window.Stripe !== 'function' || !params.stripePubKey) {
            error.textContent = params.loadError || 'Stripe could not load. Please choose another payment method.';
            prepare.disabled = true;
            form.addEventListener('submit', event => { if (option.checked) { event.preventDefault(); error.scrollIntoView({ block: 'center' }); } });
            return;
        }
        let stripe;
        try { stripe = Stripe(params.stripePubKey); } catch {
            error.textContent = params.loadError;
            prepare.disabled = true;
            form.addEventListener('submit', event => { if (option.checked) event.preventDefault(); });
            return;
        }
        const start = async () => {
            if (busy || !option.checked || !form.reportValidity()) return;
            error.textContent = '';
            setBusy(true, params.loading);
            try {
                const current = snapshot();
                if (attemptSnapshot && attemptSnapshot !== current) { previousAttempt = attempt; attempt = newAttempt(); }
                attemptSnapshot = current;
                try { sessionStorage.setItem(storageKey, JSON.stringify({ attempt, snapshot: current })); } catch { /* Optional storage. */ }
                const body = new FormData(form);
                body.set('action', 'ctcl_stripe_session');
                body.set('nonce', params.nonce);
                body.set('attempt', attempt);
                body.set('previous_attempt', previousAttempt);
                body.set('return_page', form.action);
                const request = await fetch(params.ajaxUrl, { method: 'POST', body, credentials: 'same-origin' });
                const result = await request.json();
                if (!request.ok || !result.success) {
                    if (result.data?.expired) {
                        previousAttempt = attempt; attempt = newAttempt(); attemptSnapshot = '';
                        try { sessionStorage.removeItem(storageKey); } catch { /* Optional storage. */ }
                    }
                    throw new Error(result.data?.message || params.loadError);
                }
                response = result.data;
                if (response.complete) {
                    try { sessionStorage.removeItem(storageKey); } catch { /* Optional storage. */ }
                    location.assign(response.returnUrl); return;
                }
                total.textContent = response.total;
                total.hidden = false;
                preparedSnapshot = current;
                checkout = stripe.initCheckoutElementsSdk({ clientSecret: response.clientSecret, elementsOptions: {
                    appearance: { theme: 'stripe', variables: { colorPrimary: '#635bff', colorText: '#28243b', colorDanger: '#b33143', borderRadius: '8px', fontFamily: 'system-ui, sans-serif', fontSizeBase: '14px' } }
                } });
                const loaded = await checkout.loadActions();
                if (loaded.type !== 'success') throw new Error(loaded.error?.message || params.loadError);
                actions = loaded.actions;
                paymentElement = checkout.createPaymentElement({ layout: 'tabs' });
                paymentElement.on('ready', () => { clearTimeout(loadTimeout); ready = true; setBusy(false, params.ready); });
                const loadFailed = () => {
                    clearTimeout(loadTimeout);
                    total.hidden = true;
                    ready = false;
                    paymentElement?.destroy(); checkout = null; actions = null;
                    prepare.hidden = false; instructions.hidden = false;
                    error.textContent = params.loadError; setBusy(false);
                };
                paymentElement.on('loaderror', loadFailed);
                loadTimeout = setTimeout(loadFailed, 30000);
                paymentElement.mount(mount);
                prepare.hidden = true;
                instructions.hidden = true;
                // A contact/cart change during the network request must not charge the old snapshot.
                if (snapshot() !== preparedSnapshot) { setBusy(false); invalidate(); }
            } catch (exception) {
                clearTimeout(loadTimeout);
                total.hidden = true;
                error.textContent = exception.message || params.loadError;
                paymentElement?.destroy(); paymentElement = null; checkout = null; actions = null;
                prepare.hidden = false;
                instructions.hidden = false;
                setBusy(false);
            }
        };
        prepare.addEventListener('click', start);
        form.addEventListener('input', invalidate);
        form.addEventListener('change', invalidate);
        // CTCL rebuilds its hidden totals when a coupon, quantity or shipping choice changes.
        const totals = form.querySelector('#ctcl-checkout-product-list');
        if (totals) new MutationObserver(invalidate).observe(totals, { childList: true, subtree: true, attributes: true, attributeFilter: ['value'] });
        form.addEventListener('submit', async event => {
            if (!option.checked) return;
            event.preventDefault();
            if (busy) return;
            if (!form.reportValidity()) return;
            invalidate();
            if (!actions || !ready) { await start(); return; }
            // Lock the validated CTCL snapshot while Stripe performs authentication.
            error.textContent = '';
            setBusy(true, params.paying);
            const inputs = Array.from(form.querySelectorAll('input,select,textarea,button')).filter(input => !panel.contains(input) && input.type !== 'submit');
            const original = inputs.map(input => input.disabled);
            inputs.forEach(input => { input.disabled = true; });
            try {
                const result = await actions.confirm({ redirect: 'if_required' });
                if (result.type === 'error') throw new Error(result.error?.message || params.paymentError);
                status.textContent = params.confirmed;
                // The return page retrieves the Session from Stripe and shares the webhook's idempotent fulfillment.
                location.assign(response.returnUrl);
            } catch (exception) {
                inputs.forEach((input, index) => { input.disabled = original[index]; });
                error.textContent = exception.message || params.paymentError;
                setBusy(false);
            }
        });
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
