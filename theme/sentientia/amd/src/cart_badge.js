// This file is part of Airpay Academy / Sentientia LMS.
//
// theme_sentientia/cart_badge — extracted from the inline <script> block
// that previously lived at templates/navbar.mustache:119-136 (P0 #9 in
// docs/audits/PLATFORM-VISUAL-AUDIT-2026-05-24.md, finding F-02).
//
// Why an AMD pass and not server-side render?
//   The cart count lives in PHP session state owned by
//   local_sentientia_catalog. A server-side render-time read would
//   couple the theme to a specific cart-provider plugin. Instead,
//   the cart provider injects a hidden
//   `<span id="ap-cart-count-data">N</span>` into the page (this
//   is the existing contract — see navbar.mustache comment line
//   125 in the prior version), and this AMD module reads that
//   element to paint the badge. When no cart provider is loaded,
//   the data element is absent and this module gracefully no-ops.
//
// Why pull this out of the inline script tag?
//   - A strict Content Security Policy that forbids
//     `script-src 'unsafe-inline'` would silently kill the badge.
//     CSP-strict deployments (Sentientia LMS customer-N) are a
//     near-term requirement.
//   - Inline JS in mustache templates is invisible to bundling /
//     minification and to JS audits — discoverability matters.
//   - Idempotency: re-running this module (e.g. if the page wires
//     it from multiple places) is safe — the read+paint is pure.
//
// Server contract:
//   - Container element: `<span id="ap-cart-badge">` somewhere in
//     the navbar.
//   - Data element: `<span id="ap-cart-count-data">N</span>` where
//     N is a non-negative integer. Optional; if absent or zero, the
//     badge stays hidden.
//
// Module entry: call `init()` once per page from PHP via
//   `$PAGE->requires->js_call_amd('theme_sentientia/cart_badge', 'init');`
//
// @module     theme_sentientia/cart_badge
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Read the cart count from the hidden data element.
 *
 * @returns {number} The cart count, or 0 if the data element is
 *                   absent or unparseable.
 */
const readCount = () => {
    const el = document.getElementById('ap-cart-count-data');
    if (!el) {
        return 0;
    }
    const n = parseInt(el.textContent || '0', 10);
    return Number.isFinite(n) && n > 0 ? n : 0;
};

/**
 * Paint the badge if there's a positive count, otherwise hide it.
 *
 * @param {HTMLElement} badge The badge element.
 * @param {number}      count Non-negative cart count.
 */
const paintBadge = (badge, count) => {
    if (count > 0) {
        badge.textContent = String(count);
        badge.style.display = 'flex';
        badge.removeAttribute('hidden');
    } else {
        badge.textContent = '0';
        badge.style.display = 'none';
        badge.setAttribute('hidden', 'hidden');
    }
};

/**
 * Module entry point. Called once per page from PHP.
 *
 * Safe to call multiple times — each call re-reads the data and
 * re-paints. Use this when the cart provider pushes a count update
 * via DOM mutation; just call `init()` again.
 */
export const init = () => {
    const badge = document.getElementById('ap-cart-badge');
    if (!badge) {
        return;
    }
    paintBadge(badge, readCount());
};

export default {init};
