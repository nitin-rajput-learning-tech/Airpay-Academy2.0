// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Install CTA — Phase D.1.b (ES6 source for grunt).
 *
 * Captures the `beforeinstallprompt` event on browsers that support
 * PWA install (Chrome / Edge / Samsung Internet / Firefox 122+),
 * stashes the event, and reveals the .sentientia-install-cta button
 * mounted by the theme. Click → trigger prompt → log outcome.
 *
 * Hand-bundled ES5 with NAMED define in amd/build — see subscribe.js
 * for the lesson-learned context.
 *
 * @module local_sentientia_pwa/install_prompt
 */

const CTA_SELECTOR = '.sentientia-install-cta';
const STORAGE_KEY = 'sentientia_install_dismissed';

const init = () => {
    let deferredPrompt = null;

    // If the user already dismissed and we set the localStorage flag,
    // suppress the prompt for a quarantine window (7d). Browser will
    // re-fire beforeinstallprompt anyway after enough engagement, but
    // we don't want to nag.
    const dismissedAt = window.localStorage.getItem(STORAGE_KEY);
    if (dismissedAt) {
        const ageDays = (Date.now() - parseInt(dismissedAt, 10)) / 86400000;
        if (ageDays < 7) {
            return;
        }
    }

    window.addEventListener('beforeinstallprompt', (ev) => {
        ev.preventDefault();   // we'll trigger manually
        deferredPrompt = ev;
        showCta();
    });

    // Already-installed PWAs fire `appinstalled`. Use it to permanently
    // hide the CTA in that browser.
    window.addEventListener('appinstalled', () => {
        window.localStorage.setItem(STORAGE_KEY, String(Date.now()));
        hideCta();
    });

    document.addEventListener('click', async (ev) => {
        const cta = ev.target.closest(CTA_SELECTOR);
        if (!cta) {
            return;
        }
        ev.preventDefault();

        // Dismiss button — store flag + hide
        if (cta.dataset.action === 'dismiss') {
            window.localStorage.setItem(STORAGE_KEY, String(Date.now()));
            hideCta();
            return;
        }

        if (!deferredPrompt) {
            return;
        }
        deferredPrompt.prompt();
        const choice = await deferredPrompt.userChoice;
        // outcome: 'accepted' | 'dismissed'
        if (choice.outcome === 'dismissed') {
            window.localStorage.setItem(STORAGE_KEY, String(Date.now()));
        }
        deferredPrompt = null;
        hideCta();
    });
};

const showCta = () => {
    const ctas = document.querySelectorAll(CTA_SELECTOR);
    ctas.forEach((el) => {
        el.removeAttribute('hidden');
        el.classList.remove('d-none');
    });
};

const hideCta = () => {
    const ctas = document.querySelectorAll(CTA_SELECTOR);
    ctas.forEach((el) => {
        el.setAttribute('hidden', 'hidden');
        el.classList.add('d-none');
    });
};

export {init};
