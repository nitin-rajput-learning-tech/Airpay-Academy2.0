// This file is part of Airpay Academy / Sentientia LMS.
//
// theme_sentientia/page_title — clean document.title manipulation.
//
// Borrows Moodle 5.2's core/page_title module pattern (per ADR-010
// P0 #7). Provides a centralised API for mutating the browser tab
// title — useful for:
//
//   - Unread-counter suffix: "(3) Dashboard | airpay"
//   - Per-flow status: "Saving... | Course Settings | airpay"
//   - Returning focus context: "▶ Verbal Communication | airpay"
//
// Why centralise: scattered `document.title = '...'` mutations
// across AMD modules makes it hard to reason about title state.
// Two modules racing can erase each other's prefixes/suffixes.
// This module owns the title, exposes prefix/suffix/reset API.
//
// API:
//   import PageTitle from 'theme_sentientia/page_title';
//
//   PageTitle.setPrefix('(3) ');         // "(3) Dashboard | airpay"
//   PageTitle.setPrefix('');             // clear prefix
//   PageTitle.setSuffix(' — Saving');    // "Dashboard — Saving | airpay"
//   PageTitle.reset();                   // back to original PHP-rendered title
//   PageTitle.get();                     // returns current full title
//
// When we upgrade to Moodle 5.2, migration is trivial — swap
// 'theme_sentientia/page_title' → 'core/page_title' since the API
// surface is intentionally aligned.
//
// @module     theme_sentientia/page_title
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

let originalTitle = null;
let prefix = '';
let suffix = '';

/**
 * Capture the original title once, so reset() always works.
 *
 * @returns {string}
 */
const captureOriginal = () => {
    if (originalTitle === null) {
        originalTitle = document.title;
    }
    return originalTitle;
};

/**
 * Recompute and apply document.title from prefix + original + suffix.
 */
const apply = () => {
    const base = captureOriginal();
    document.title = `${prefix}${base}${suffix}`;
};

/**
 * Set (or clear) the prefix that precedes the page's natural title.
 * Common use: unread-counter, e.g. setPrefix('(3) ').
 *
 * @param {string} value
 */
export const setPrefix = (value) => {
    prefix = String(value || '');
    apply();
};

/**
 * Set (or clear) the suffix that follows the page's natural title.
 * Common use: in-flight status, e.g. setSuffix(' — Saving').
 *
 * @param {string} value
 */
export const setSuffix = (value) => {
    suffix = String(value || '');
    apply();
};

/**
 * Restore the original page title from the moment this module was
 * first invoked. Clears both prefix and suffix.
 */
export const reset = () => {
    prefix = '';
    suffix = '';
    document.title = captureOriginal();
};

/**
 * Return the current full title.
 *
 * @returns {string}
 */
export const get = () => document.title;

export default {setPrefix, setSuffix, reset, get};
