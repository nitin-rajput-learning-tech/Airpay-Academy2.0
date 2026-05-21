// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * iOS install hint — Phase B.3.d (ES6 source for grunt).
 *
 * See amd/build/ios_install_hint.min.js for the bundled ES5 named-define
 * version (which is what Moodle actually serves until grunt is wired up).
 *
 * Built with createElement + textContent only — never innerHTML — so the
 * passed-in labels are treated as plain text and cannot inject markup.
 *
 * @module     local_sentientia_pwa/ios_install_hint
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const DISMISS_KEY = 'sentientia_pwa_ios_hint_dismissed';
const BANNER_ID = 'sentientia-pwa-ios-hint';

const isIosSafari = () => {
    const ua = window.navigator.userAgent || '';
    const isIos = /iPhone|iPad|iPod/i.test(ua) ||
        (/Macintosh/i.test(ua) && 'ontouchend' in document);
    if (!isIos) {
        return false;
    }
    if (/CriOS|FxiOS|EdgiOS|OPiOS/i.test(ua)) {
        return false;
    }
    return /Safari/i.test(ua);
};

const isStandalone = () =>
    (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches)
    || window.navigator.standalone === true;

const wasDismissed = () => {
    try {
        return window.localStorage.getItem(DISMISS_KEY) === '1';
    } catch {
        return false;
    }
};

const markDismissed = () => {
    try {
        window.localStorage.setItem(DISMISS_KEY, '1');
    } catch {
        // localStorage disabled in private mode — silent fail
    }
};

const createTextNode = (tag, text, className = null) => {
    const el = document.createElement(tag);
    el.textContent = text;  // SAFE — never use innerHTML
    if (className) {
        el.className = className;
    }
    return el;
};

const buildBanner = (labels) => {
    const div = document.createElement('div');
    div.id = BANNER_ID;
    div.className = 'alert alert-info d-flex align-items-start mb-3';
    div.setAttribute('role', 'alert');

    const content = document.createElement('div');
    content.className = 'flex-grow-1';
    content.appendChild(createTextNode('strong', labels.title));
    content.appendChild(createTextNode('p', labels.body, 'mb-1 mt-1'));

    const ol = document.createElement('ol');
    ol.className = 'mb-0 small';
    ol.appendChild(createTextNode('li', labels.step1));
    ol.appendChild(createTextNode('li', labels.step2));
    ol.appendChild(createTextNode('li', labels.step3));
    content.appendChild(ol);

    div.appendChild(content);

    const dismissBtn = document.createElement('button');
    dismissBtn.type = 'button';
    dismissBtn.className = 'btn-close ms-2';
    dismissBtn.setAttribute('aria-label', labels.dismiss || 'Dismiss');
    dismissBtn.addEventListener('click', () => {
        markDismissed();
        div.remove();
    });
    div.appendChild(dismissBtn);

    return div;
};

export const init = (labels = null) => {
    if (!isIosSafari() || isStandalone() || wasDismissed()) {
        return;
    }
    labels = labels || {
        title: 'Install Sentientia LMS to enable push notifications',
        body: 'On iOS Safari, push notifications only work when this site is added to your home screen:',
        step1: 'Tap the Share button at the bottom of the screen.',
        step2: 'Scroll down and choose "Add to Home Screen".',
        step3: 'Open Sentientia LMS from your home screen and try Enable notifications again.',
        dismiss: 'Dismiss',
    };
    const anchor = document.querySelector('.sentientia-pwa-subscribe')
        || document.querySelector('#region-main')
        || document.querySelector('main')
        || document.body;
    if (!anchor) {
        return;
    }
    anchor.parentNode.insertBefore(buildBanner(labels), anchor);
};
