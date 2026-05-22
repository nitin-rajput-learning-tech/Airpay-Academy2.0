// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Install CTA — Phase D.1.b (ES6 source for grunt).
 *
 * Captures the `beforeinstallprompt` event on browsers that support
 * PWA install (Chrome / Edge / Samsung Internet / Firefox 122+),
 * stashes the event, ALWAYS calls preventDefault to suppress Chrome's
 * native install bar, and conditionally reveals our custom CTA based
 * on the 7-day quarantine. Click → trigger prompt OR mark dismissed.
 *
 * 2026-05-22 bug fix — previous version returned early from init()
 * when quarantine was active, which left beforeinstallprompt
 * unhandled and let Chrome's native mini-info bar keep reappearing
 * on every page load.
 *
 * Hand-bundled ES5 with NAMED define in amd/build — see subscribe.js
 * for the lesson-learned context.
 *
 * @module local_sentientia_pwa/install_prompt
 */

const ALERT_SELECTOR  = '.sentientia-install-cta';
const BUTTON_SELECTOR = '.sentientia-install-cta-action';
const STORAGE_KEY     = 'sentientia_install_dismissed';

const isQuarantined = () => {
    const dismissedAt = window.localStorage.getItem(STORAGE_KEY);
    if (!dismissedAt) return false;
    const ageDays = (Date.now() - parseInt(dismissedAt, 10)) / 86400000;
    return ageDays < 7;
};

const showCta = () => {
    document.querySelectorAll(ALERT_SELECTOR).forEach((el) => {
        el.removeAttribute('hidden');
        el.classList.remove('d-none');
    });
};

const hideCta = () => {
    document.querySelectorAll(ALERT_SELECTOR).forEach((el) => {
        el.setAttribute('hidden', 'hidden');
        el.classList.add('d-none');
    });
};

const markDismissed = () => {
    try { window.localStorage.setItem(STORAGE_KEY, String(Date.now())); }
    catch (e) { /* private mode — silent */ }

    // Belt-and-braces: also POST to server so the user-pref record
    // persists across browsers + the server-side render suppresses
    // the CTA on subsequent page loads. Fire-and-forget.
    try {
        const wwwroot = (typeof M !== 'undefined' && M.cfg && M.cfg.wwwroot)
            ? M.cfg.wwwroot : '';
        const sesskey = (typeof M !== 'undefined' && M.cfg && M.cfg.sesskey)
            ? M.cfg.sesskey : '';
        if (sesskey && window.fetch) {
            window.fetch(wwwroot + '/local/sentientia_pwa/dismiss_install.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'sesskey=' + encodeURIComponent(sesskey),
            });
        }
    } catch (e) { /* silent */ }
};

const init = () => {
    let deferredPrompt = null;
    const quarantined = isQuarantined();

    // ALWAYS preventDefault on beforeinstallprompt — that's what
    // suppresses Chrome's native install bar. Only show OUR custom
    // CTA when not quarantined.
    window.addEventListener('beforeinstallprompt', (ev) => {
        ev.preventDefault();
        deferredPrompt = ev;
        if (!quarantined) {
            showCta();
        }
    });

    window.addEventListener('appinstalled', () => {
        markDismissed();
        hideCta();
    });

    document.addEventListener('click', async (ev) => {
        const btn = ev.target.closest(BUTTON_SELECTOR);
        if (!btn) return;
        ev.preventDefault();

        if (btn.dataset.action === 'dismiss') {
            markDismissed();
            hideCta();
            return;
        }

        if (!deferredPrompt) {
            hideCta();
            return;
        }
        deferredPrompt.prompt();
        const choice = await deferredPrompt.userChoice;
        if (choice.outcome === 'dismissed') {
            markDismissed();
        }
        deferredPrompt = null;
        hideCta();
    });
};

export {init};
