// Service worker registration — local_sentientia_pwa
// Stream B / Phase B.1
//
// This is a plain script (not an AMD module) loaded directly via a
// <script> tag in head.mustache. We deliberately avoid the AMD module
// system here because (1) SW registration should happen as early as
// possible — before AMD even finishes booting — and (2) AMD modules
// need RequireJS, which adds 60-100ms of overhead we don't need for
// a one-shot registration call.
//
// @package local_sentientia_pwa
// @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

(function() {
    'use strict';

    // Modern-browser bail-out check. Service workers require:
    //   - serviceWorker in navigator
    //   - secure context (HTTPS or localhost)
    //   - browser support for the Cache API
    if (!('serviceWorker' in navigator)) { return; }
    if (!window.isSecureContext) { return; }

    // Compute the wwwroot path from the current page URL. We can't
    // hardcode "/moodle" because some installs serve from root and
    // others from a subdirectory. M.cfg.wwwroot is the canonical source
    // when M is available; otherwise infer from location.pathname.
    var wwwroot;
    if (typeof window.M !== 'undefined' && M.cfg && M.cfg.wwwroot) {
        wwwroot = M.cfg.wwwroot;
    } else {
        // Fallback for very early loads (head script runs before M.cfg
        // is set on some pages). Use the start_url segment from manifest
        // by inferring from window.location.
        var path = window.location.pathname;
        // Strip the page filename + any trailing path segments to get
        // the install root. For /moodle/my/index.php this gives /moodle.
        // Conservative — when in doubt, fall back to "/moodle".
        wwwroot = path.indexOf('/moodle/') === 0 ? '/moodle'
            : path.indexOf('/moodle') === 0 ? '/moodle'
            : '';
        // Convert to absolute URL.
        wwwroot = window.location.origin + wwwroot;
    }

    var swUrl   = wwwroot + '/local/sentientia_pwa/sw.php';
    var swScope = wwwroot + '/';

    // Register with the explicit scope. The PHP endpoint sends
    // Service-Worker-Allowed: / to permit this.
    navigator.serviceWorker.register(swUrl, { scope: swScope })
        .then(function(reg) {
            // Listen for updates — when a new SW is waiting, prompt it
            // to take over immediately. In production this is what makes
            // a deploy roll out without users having to close their tabs.
            reg.addEventListener('updatefound', function() {
                var installing = reg.installing;
                if (!installing) { return; }
                installing.addEventListener('statechange', function() {
                    if (installing.state === 'installed' && navigator.serviceWorker.controller) {
                        // A new SW is waiting. Tell it to take over.
                        installing.postMessage({ type: 'SKIP_WAITING' });
                    }
                });
            });
        })
        .catch(function(err) {
            // Registration failed. Common causes:
            //   - HTTP (not HTTPS, not localhost)
            //   - SW file 404 / wrong MIME type
            //   - Service-Worker-Allowed header missing
            // Log but don't surface to user — the site still works without PWA.
            if (window.console && console.warn) {
                console.warn('[Sentientia PWA] SW registration failed:', err);
            }
        });

    // When the controller changes (a new SW activated), reload the page
    // ONCE so the user sees the updated shell. Guard against reload-loop
    // via a session-storage flag.
    var reloadKey = 'sentientia-pwa-reloading';
    navigator.serviceWorker.addEventListener('controllerchange', function() {
        if (sessionStorage.getItem(reloadKey)) { return; }
        sessionStorage.setItem(reloadKey, '1');
        setTimeout(function() { sessionStorage.removeItem(reloadKey); }, 5000);
        window.location.reload();
    });
})();
