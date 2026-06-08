<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Service worker delivery endpoint — local_sentientia_pwa.
 *
 * Serves the service worker JS with the right HTTP headers so the
 * browser can register it AT THE ROOT SCOPE of the Moodle install.
 *
 * Why a PHP endpoint instead of a static .js file: the SW scope is
 * determined by the URL it was fetched from UNLESS the response also
 * carries a `Service-Worker-Allowed` header. Since we want the SW to
 * control the WHOLE Moodle (including /my/, /course/*, etc.) but the
 * SW lives in /local/sentientia_pwa/, we need this header. A static
 * .js file can't add HTTP response headers — only a PHP-served file
 * can. Hence sw.php.
 *
 * Cache behaviour: we set Cache-Control: no-cache so browsers always
 * check for updates. The browser still uses ETag/304 to skip body
 * transfer when unchanged — fast in practice but always fresh.
 *
 * @package local_sentientia_pwa
 */

// Set up Moodle context EARLY so we can read $CFG for the wwwroot path
// the SW needs to know about. Skip require_login — service workers are
// requested without cookies sometimes, and a 302 to login would break
// registration.
define('NO_DEBUG_DISPLAY', true);
define('NO_MOODLE_COOKIES', true);
require_once(__DIR__ . '/../../config.php');

// Honour the PWA feature flag — when OFF, serve a no-op SW that just
// unregisters itself, so any previously-installed SW is cleaned up.
$pwa_enabled = true;
if (class_exists('\\local_sentientia_platform\\feature_flags')) {
    try {
        $pwa_enabled = \local_sentientia_platform\feature_flags::is_enabled('sentientia.pwa.enabled');
    } catch (\Throwable $e) {
        // Resolver hiccup — default ON. Don't break PWA over a flag bug.
    }
}

// Required headers for SW registration to succeed at root scope.
header('Content-Type: application/javascript; charset=utf-8');
header('Service-Worker-Allowed: /');
header('Cache-Control: no-cache, must-revalidate');

// Wwwroot path that the SW will use as cache prefix.
$wwwpath = parse_url($CFG->wwwroot, PHP_URL_PATH) ?: '';
$wwwpath = rtrim($wwwpath, '/');

// Cache version — bump this whenever the SW body changes. The browser
// triggers a fresh install + activate cycle on version change.
// Phase D.1.d bumped to v2 to refresh caches with the new offline.html.
$sw_version = 'sentientia-pwa-v2';

if (!$pwa_enabled) {
    // Kill-switch SW — unregisters itself + clears all caches the
    // previous version created. Browsers will replace any installed SW
    // with this one on next page load.
    echo <<<JS
// Sentientia LMS PWA — kill-switch (feature flag OFF)
self.addEventListener('install', function(e){ self.skipWaiting(); });
self.addEventListener('activate', function(e){
    e.waitUntil(
        caches.keys().then(function(keys){
            return Promise.all(keys.map(function(k){ return caches.delete(k); }));
        }).then(function(){ return self.registration.unregister(); })
        .then(function(){ return self.clients.matchAll(); })
        .then(function(clients){ clients.forEach(function(c){ c.navigate(c.url); }); })
    );
});
JS;
    exit;
}

// Active SW — caches the offline shell, intercepts navigation requests
// to fall back to cached shell when network is unreachable, listens for
// future push events (Phase B.2+).
echo <<<JS
// Sentientia LMS Progressive Web App — service worker
// Stream B / Phase B.1
//
// @package local_sentientia_pwa
// @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

const CACHE_NAME = '{$sw_version}';
const OFFLINE_URL = '{$wwwpath}/local/sentientia_pwa/offline.html';
const WWW_PATH = '{$wwwpath}';
const PRECACHE_URLS = [
    OFFLINE_URL,
    '{$wwwpath}/local/sentientia_pwa/manifest.php',
    '{$wwwpath}/my/',
    '{$wwwpath}/theme/airpayux/pix/brand/academy-logo-350.png',
    '{$wwwpath}/theme/airpayux/pix/brand/favicon_io/android-chrome-192x192.png',
    '{$wwwpath}/theme/airpayux/pix/brand/favicon_io/android-chrome-512x512.png',
];
// Phase D.1.d — extensions we'll cache-first (static-ish assets). Anything
// not in this list passes through to network on its own.
const CACHE_FIRST_EXT = ['.css', '.js', '.woff', '.woff2', '.svg', '.png',
                          '.jpg', '.jpeg', '.gif', '.ico'];

// ── INSTALL ───────────────────────────────────────────────────
// Try to cache the offline shell. Failures here don't block install —
// we'd rather have a less-functional SW than no SW at all.
self.addEventListener('install', function(event) {
    event.waitUntil(
        caches.open(CACHE_NAME).then(function(cache) {
            return Promise.allSettled(
                PRECACHE_URLS.map(function(url) {
                    return cache.add(new Request(url, { credentials: 'same-origin' }))
                        .catch(function(){ /* swallow per-url failures */ });
                })
            );
        }).then(function() {
            return self.skipWaiting();
        })
    );
});

// ── ACTIVATE ──────────────────────────────────────────────────
// Clean up old caches from previous SW versions.
self.addEventListener('activate', function(event) {
    event.waitUntil(
        caches.keys().then(function(keys) {
            return Promise.all(keys.map(function(key) {
                if (key !== CACHE_NAME) { return caches.delete(key); }
            }));
        }).then(function() { return self.clients.claim(); })
    );
});

// ── FETCH ─────────────────────────────────────────────────────
// Phase D.1.d — two strategies:
//   1) navigation requests        → network-first, offline.html fallback
//   2) static-asset GETs           → cache-first with stale-revalidate
//   3) everything else (XHR, SSE)  → pass through to network
//
// SSE streams (Server-Sent Events) deliberately bypass: caching long-
// lived event streams would break the Sentientia Live realtime channel.
// Same for REST API calls (Authorization header would not be replayed
// from cache, returning stale auth state to the page).
self.addEventListener('fetch', function(event) {
    const request = event.request;
    if (request.method !== 'GET') { return; }
    const url = new URL(request.url);
    if (url.origin !== self.location.origin) { return; }

    // Bypass SSE + REST API + Moodle's internal AJAX endpoints — these
    // must always go to the network with current credentials.
    if (url.pathname.indexOf('/lib/ajax/') === 0
        || url.pathname.indexOf('/webservice/') === 0
        || url.pathname.indexOf('/local/sentientia_live/stream.php') !== -1
        || url.pathname.indexOf('/admin/cli/') === 0) {
        return;
    }

    // 1) Navigation requests (HTML page loads) — network-first.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(function() {
                return caches.match(OFFLINE_URL, { ignoreSearch: true })
                    .then(function(cached) {
                        return cached || new Response(
                            '<!DOCTYPE html><meta charset="utf-8"><title>Offline</title>'
                            + '<p>You are offline. Connect and reload.</p>',
                            { status: 200, headers: { 'Content-Type': 'text/html; charset=utf-8' } }
                        );
                    });
            })
        );
        return;
    }

    // 2) Static-asset GETs — cache-first with background revalidation.
    const lower = url.pathname.toLowerCase();
    let isStatic = false;
    for (let i = 0; i < CACHE_FIRST_EXT.length; i++) {
        if (lower.endsWith(CACHE_FIRST_EXT[i])) { isStatic = true; break; }
    }
    if (isStatic) {
        event.respondWith(
            caches.match(request).then(function(cached) {
                const fetchPromise = fetch(request).then(function(networkResp) {
                    if (networkResp && networkResp.status === 200
                        && networkResp.type === 'basic') {
                        const clone = networkResp.clone();
                        caches.open(CACHE_NAME).then(function(cache) {
                            cache.put(request, clone);
                        });
                    }
                    return networkResp;
                }).catch(function() {
                    return cached;  // network down — fall back to cached
                });
                return cached || fetchPromise;
            })
        );
        return;
    }

    // 3) Everything else — pass through.
});

// ── PUSH (Phase B.2+ scaffold) ───────────────────────────────
// Receives web-push messages from the backend sender. The PUSH SUBSCRIPTION
// flow that produces the endpoint URL (Phase B.2) hasn't shipped yet, so
// in Phase B.1 this listener is effectively a no-op. Listed for forward
// compatibility — when B.2 lands, only the backend changes; no SW redeploy
// needed.
self.addEventListener('push', function(event) {
    if (!event.data) { return; }
    try {
        const payload = event.data.json();
        const title = payload.title || 'Sentientia LMS';
        const options = {
            body: payload.body || '',
            icon: payload.icon || WWW_PATH + '/theme/airpayux/pix/brand/favicon_io/android-chrome-192x192.png',
            badge: WWW_PATH + '/theme/airpayux/pix/brand/favicon_io/android-chrome-192x192.png',
            data: { url: payload.url || WWW_PATH + '/my/' },
            tag: payload.tag || undefined,
            requireInteraction: !!payload.requireInteraction,
        };
        event.waitUntil(self.registration.showNotification(title, options));
    } catch (e) {
        // Malformed payload — fall back to a generic notification.
        event.waitUntil(self.registration.showNotification('Sentientia LMS', {
            body: 'You have a new notification.',
            data: { url: WWW_PATH + '/my/' }
        }));
    }
});

// ── NOTIFICATIONCLICK ────────────────────────────────────────
// Focus an existing tab if one is open at the notification's URL,
// else open a new one.
self.addEventListener('notificationclick', function(event) {
    event.notification.close();
    const targetUrl = event.notification.data && event.notification.data.url
        ? event.notification.data.url
        : WWW_PATH + '/my/';
    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then(function(clientList) {
                for (let i = 0; i < clientList.length; i++) {
                    const c = clientList[i];
                    if (c.url.indexOf(targetUrl) !== -1 && 'focus' in c) {
                        return c.focus();
                    }
                }
                if (self.clients.openWindow) {
                    return self.clients.openWindow(targetUrl);
                }
            })
    );
});

// ── MESSAGE ──────────────────────────────────────────────────
// Page-script → SW message channel. Used by the registration script
// to ask the SW to update or unregister itself.
self.addEventListener('message', function(event) {
    if (!event.data || !event.data.type) { return; }
    if (event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    } else if (event.data.type === 'UNREGISTER') {
        self.registration.unregister().then(function() {
            return self.clients.matchAll();
        }).then(function(clients) {
            clients.forEach(function(c) { c.navigate(c.url); });
        });
    }
});
JS;
