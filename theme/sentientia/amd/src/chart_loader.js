// This file is part of Airpay Academy / Sentientia LMS.
//
// theme_sentientia/chart_loader — Chart.js loader for dashboard analytics
// surfaces (P1 #17 in docs/audits/PLATFORM-VISUAL-AUDIT-2026-05-24.md,
// finding F-14).
//
// Why this module exists
// ----------------------
// Before this chip the dashboard pulled Chart.js straight from
// cdn.jsdelivr.net via a `<script src=…>` inside the mustache template.
// Three problems with that:
//   1. Customer-N on a restricted / offline network gets a silent
//      chart breakage — the canvas tag renders empty with no error.
//   2. No Subresource Integrity hash on the script tag → if the CDN
//      ever served a compromised build, the dashboard would execute it.
//   3. The version pin lived in the template, not in version-controlled
//      JS — bumping Chart.js meant editing a mustache file.
//
// Why we delegate to core/chartjs
// -------------------------------
// Moodle 5.x already ships Chart.js v4.4.2 as the AMD module
// `core/chartjs` (`lib/amd/src/chartjs.js` → `lib/amd/src/chartjs-lazy.js`).
// It is hand-vendored from the upstream MIT release, gets a `thirdpartylibs`
// entry, and rides along with every Moodle upgrade. Re-vendoring our own
// copy would (a) duplicate ~250 KB of JS in the theme bundle and (b)
// drift independently of core upgrades. We just consume the core module.
//
// Server contract
// ---------------
// Pages that need Chart.js should:
//   - Wire this module from PHP:
//       `$PAGE->requires->js_call_amd('theme_sentientia/chart_loader', 'init');`
//     This triggers the dependency chain (core/chartjs → core/chartjs-lazy)
//     so Chart.js is in the AMD cache before the chart-init scripts run.
//   - Wrap any inline chart-init script in a `require()` callback that
//     pulls this module, e.g.:
//       require(['theme_sentientia/chart_loader'], function() {
//           new Chart(document.getElementById('foo'), { … });
//       });
//
// This module sets `window.Chart` as a side-effect so that existing
// inline chart-init code (which uses the global `Chart` constructor
// the same way the CDN UMD bundle exposes it) keeps working with no
// rewrite of the chart configuration logic. Per the audit fix scope:
// the chart-rendering JavaScript is the *consumer* of Chart.js — its
// dataset / options shape is unchanged; only the dependency loader
// flips.
//
// @module     theme_sentientia/chart_loader
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

define(['core/chartjs'], function(Chart) {

    // Side-effect: expose Chart on the global so that legacy / inline
    // chart-init scripts can use `new Chart(canvas, config)` without
    // having to take the constructor through this module's exports.
    // Idempotent — running `init()` twice does not re-wrap or reset
    // anything; the second `window.Chart =` is a no-op assignment of
    // the same reference.
    if (typeof window !== 'undefined' && Chart) {
        window.Chart = Chart;
    }

    return {
        /**
         * Module entry point. Called once per page from PHP via
         * `$PAGE->requires->js_call_amd('theme_sentientia/chart_loader', 'init');`
         *
         * The work this method does is finished by the time the AMD
         * loader resolves the `core/chartjs` dependency above — the
         * factory body has already assigned `window.Chart`. Calling
         * `init()` is a no-op in steady state; it exists only so PHP
         * has a method name to invoke (Moodle's `js_call_amd` requires
         * a method, not a side-effect import).
         *
         * @returns {Function} The Chart constructor — handy for callers
         *                     that want it via destructure instead of
         *                     the window global.
         */
        init: function() {
            return Chart;
        },

        // Direct export of the constructor for consumers that prefer
        // `require(['theme_sentientia/chart_loader'], function(loader) {
        //      new loader.Chart(...)
        //  })` over the window global.
        Chart: Chart,
    };
});
