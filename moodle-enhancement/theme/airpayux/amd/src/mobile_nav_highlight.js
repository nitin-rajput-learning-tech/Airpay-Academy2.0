// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Mobile nav active-item highlighter.
 *
 * Inspects the current URL pathname and applies the
 * `ap-mobile-nav__item--active` class to whichever bottom-nav item
 * matches. Replaces an inline `<script>` in navbar.mustache that
 * CSP-hardened deployments would block.
 *
 * Born from B9 / F-064 stabilization fix (2026-05-28).
 *
 * @module     theme_airpayux/mobile_nav_highlight
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const PAGE_PATTERNS = {
    dashboard: (path) => path.indexOf('/my/') !== -1
        || path.indexOf('/my/dashboard') !== -1,
    catalog:   (path) => path.indexOf('airpay_catalog/index') !== -1,
    courses:   (path) => path.indexOf('mycourses') !== -1,
    profile:   (path) => path.indexOf('profile') !== -1,
};

/**
 * Highlight the matching mobile-nav item for the current URL.
 *
 * Defensive: short-circuits if the mobile nav isn't on the page
 * (admin-only surfaces don't render it). Safe to call multiple times
 * (idempotent — we always add the class to the first matching item).
 */
export const init = () => {
    const path = window.location.pathname;
    const items = document.querySelectorAll('.ap-mobile-nav__item');
    if (!items.length) {
        return;
    }
    items.forEach((item) => {
        const page = item.getAttribute('data-page');
        if (page && PAGE_PATTERNS[page] && PAGE_PATTERNS[page](path)) {
            item.classList.add('ap-mobile-nav__item--active');
        }
    });
};
