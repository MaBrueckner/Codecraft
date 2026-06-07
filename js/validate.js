(function () {
    "use strict";

    const form = document.querySelector('.php-email-form');
    if (!form) return;

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        const action      = form.getAttribute('action');
        const formData    = new FormData(form);
        const loading     = form.querySelector('.loading');
        const errorMsg    = form.querySelector('.error-message');
        const sentMsg     = form.querySelector('.sent-message');

        try {
            if (window.turnstile && typeof window.turnstile.getResponse === 'function') {
                const token = window.turnstile.getResponse();
                if (token) formData.set('cf-turnstile-response', token);
            }
        } catch (_) { /* ignore */ }

        if (loading)  loading.style.display  = 'block';
        if (errorMsg) errorMsg.style.display = 'none';
        if (sentMsg)  sentMsg.style.display  = 'none';

        fetch(action, {
            method: 'POST',
            body: formData,
            headers: { 'Accept': 'text/plain' },
            credentials: 'same-origin',
            cache: 'no-store'
        })
        .then(async response => {
            if (loading) loading.style.display = 'none';

            const body = (response.status === 204) ? '' : (await response.text()).trim();
            const isRealSuccess =
                response.status === 200 &&
                /vielen dank|verschickt|thank you|sent/i.test(body);

            if (isRealSuccess) {
                if (sentMsg) {
                    sentMsg.textContent = body || 'Ihre Nachricht wurde verschickt. Vielen Dank!';
                    sentMsg.style.display = 'block';
                }
                form.reset();
                const hp = form.querySelector('input[name="hp_time"]');
                if (hp) hp.value = String(Math.floor(Date.now() / 1000));
                try { window.turnstile && window.turnstile.reset(); } catch (_) {}

                setTimeout(() => {
                    if (sentMsg) sentMsg.style.display = 'none';
                }, 10000);
                return;
            }

            // Alles andere -> Fehler anzeigen
            const msg = body
                ? body
                : (response.status === 204
                    ? 'Anfrage abgelehnt (Bot-Schutz hat angeschlagen). Bitte Seite neu laden.'
                    : `Fehler ${response.status}.`);
            throw new Error(msg);
        })
        .catch(error => {
            if (loading)  loading.style.display  = 'none';
            if (errorMsg) {
                errorMsg.style.display = 'block';
                errorMsg.textContent   = error.message || 'Unbekannter Fehler.';
            }
            setTimeout(() => {
                if (errorMsg) errorMsg.style.display = 'none';
            }, 10000);
        });
    });
})();
