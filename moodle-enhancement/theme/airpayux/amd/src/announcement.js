// This file is part of Airpay Academy / Sentientia LMS.
//
// theme_airpayux/announcement — toast wrapper with a11y option.
//
// Borrows the `visuallyHidden` parameter pattern from Moodle 5.2's
// core/toast module (per ADR-010 P0 #6). On 5.2 we'd just call
// core/toast directly; here we provide the same API surface for
// callers so they can opt into screen-reader-only announcements
// today, and the migration to direct core/toast usage is trivial
// when we upgrade.
//
// Why this matters: when a learner clicks "Mark complete" on an
// activity, Sentientia already shows a visual completion pill in
// the activity header — adding a green toast on top is visual
// noise, but a screen-reader user gets no signal at all. With
// `visuallyHidden: true`, the message goes into an aria-live
// region and is announced once, without a visible toast.
//
// API:
//   import {add} from 'theme_airpayux/announcement';
//   add('Activity marked complete', {type: 'success'});            // visible toast
//   add('Activity marked complete', {visuallyHidden: true});       // SR-only
//   add('Form saved', {type: 'success', visuallyHidden: true});    // both: toast + SR
//
// The aria-live region is created lazily on first call, lives at
// the document body level, and is polite (not assertive — assertive
// would interrupt the user's current reading flow which is rarely
// what we want).
//
// @module     theme_airpayux/announcement
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

import Toast from 'core/toast';

let liveRegion = null;

/**
 * Create (or return existing) the off-screen aria-live region.
 *
 * @returns {HTMLDivElement}
 */
const getLiveRegion = () => {
    if (liveRegion) {
        return liveRegion;
    }
    liveRegion = document.createElement('div');
    liveRegion.setAttribute('role', 'status');
    liveRegion.setAttribute('aria-live', 'polite');
    liveRegion.setAttribute('aria-atomic', 'true');
    liveRegion.id = 'ap-announcement-region';
    // Off-screen but readable by AT (the standard sr-only pattern).
    liveRegion.style.cssText = [
        'position: absolute',
        'width: 1px',
        'height: 1px',
        'padding: 0',
        'margin: -1px',
        'overflow: hidden',
        'clip: rect(0, 0, 0, 0)',
        'white-space: nowrap',
        'border: 0'
    ].join(';');
    document.body.appendChild(liveRegion);
    return liveRegion;
};

/**
 * Announce a message — toast, screen-reader, or both.
 *
 * @param {string} message - the message text
 * @param {object} [options]
 * @param {string} [options.type] - core/toast type: 'success' | 'info' | 'warning' | 'danger'
 * @param {boolean} [options.visuallyHidden] - if true, send to aria-live region
 * @param {boolean} [options.suppressToast] - if true with visuallyHidden, do NOT show the visible toast (SR-only)
 * @returns {Promise<void>}
 */
export const add = async (message, options = {}) => {
    const {type = 'info', visuallyHidden = false, suppressToast = false} = options;
    if (visuallyHidden) {
        const region = getLiveRegion();
        // Briefly clear then re-set so AT re-announces if the same
        // message fires twice (e.g., user clicks Mark complete twice).
        region.textContent = '';
        // setTimeout 50ms avoids the "same text suppressed" bug in
        // some screen readers (NVDA <2024 in particular).
        setTimeout(() => { region.textContent = message; }, 50);
    }
    if (!suppressToast) {
        await Toast.add(message, {type});
    }
};

/**
 * Convenience: SR-only announcement, never shows toast.
 *
 * @param {string} message
 * @returns {Promise<void>}
 */
export const announceOnly = (message) => add(message, {
    visuallyHidden: true,
    suppressToast: true
});

export default {add, announceOnly};
