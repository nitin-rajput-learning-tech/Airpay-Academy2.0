// This file is part of Airpay Academy / Sentientia LMS.
//
// theme_airpayux/deprecated — emit graceful JS deprecation notices.
//
// @deprecated since Moodle 5.2 cutover — DELETE this file (and the
//   compiled amd/build/deprecated.min.js + .map) once airpay.academy
//   production is on Moodle 5.2. The 5.2 native module `core/deprecated`
//   ships the identical API. Phase B.3.f audit (2026-05-23) found ZERO
//   import callsites in our codebase, so deletion is a single-file
//   removal with no rewrite work. See
//   docs/5.2-merge/PHASE-B3F-AMD-CLEANUP.md.
//
// Borrows Moodle 5.2's core/deprecated module pattern (per ADR-010
// P0 #8). Replaces ad-hoc `console.warn` calls in AMD modules with
// a structured deprecation notice that:
//
//   1. Only fires when site is in developer-debug mode (or always
//      with `force: true`)
//   2. Includes the call-site (file + line) automatically via
//      Error().stack
//   3. De-duplicates — fires once per (api, callsite) pair, not
//      every invocation
//
// API:
//   import {deprecate} from 'theme_airpayux/deprecated';
//
//   // When a function is called:
//   export const oldApi = (...args) => {
//       deprecate('oldApi', 'newApi', 'theme_airpayux/widget');
//       // ... legacy implementation ...
//   };
//
//   // Force the warning regardless of debug setting:
//   deprecate('oldApi', 'newApi', 'theme_airpayux/widget', {force: true});
//
// Output format (matches Moodle 5.2 shape):
//   [DEPRECATED] theme_airpayux/widget#oldApi — use newApi
//   Called from: <stack frame>
//
// When we upgrade to Moodle 5.2, callers swap
// 'theme_airpayux/deprecated' → 'core/deprecated' (identical API).
//
// @module     theme_airpayux/deprecated
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

const fired = new Set();

/**
 * Detect whether the page is running with developer debug enabled.
 * Moodle exposes this via the `M.cfg.developerdebug` flag set in
 * `lib/configonlylib.php` / `core/yui` config.
 *
 * @returns {boolean}
 */
const isDeveloperDebug = () => {
    if (typeof M !== 'undefined' && M && M.cfg) {
        return Boolean(M.cfg.developerdebug);
    }
    return false;
};

/**
 * Extract the calling frame (skipping the deprecate() call itself).
 *
 * @returns {string}
 */
const getCallSite = () => {
    try {
        const stack = new Error().stack || '';
        const lines = stack.split('\n').filter(l => l.trim());
        // Skip frame 0 (Error), frame 1 (getCallSite), frame 2 (deprecate)
        // → frame 3 is the caller of deprecate().
        return (lines[3] || '<unknown>').trim();
    } catch (e) {
        return '<unknown>';
    }
};

/**
 * Emit a deprecation notice for a JS API.
 *
 * @param {string} oldName  - the deprecated function/property name
 * @param {string} newName  - the replacement API to use instead
 * @param {string} module   - the module owning the deprecation, e.g. "theme_airpayux/widget"
 * @param {object} [options]
 * @param {boolean} [options.force] - emit regardless of debug setting
 */
export const deprecate = (oldName, newName, module, options = {}) => {
    const {force = false} = options;
    if (!force && !isDeveloperDebug()) {
        return;
    }
    const callSite = getCallSite();
    const key = `${module}#${oldName}@${callSite}`;
    if (fired.has(key)) {
        return; // de-dupe
    }
    fired.add(key);
    /* eslint-disable no-console */
    console.warn(
        `[DEPRECATED] ${module}#${oldName} — use ${newName}\n` +
        `Called from: ${callSite}`
    );
    /* eslint-enable no-console */
};

/**
 * Test-only: clear the fired set so a test can re-verify warnings.
 * Not exported from the default object.
 */
export const _resetForTesting = () => fired.clear();

export default {deprecate};
