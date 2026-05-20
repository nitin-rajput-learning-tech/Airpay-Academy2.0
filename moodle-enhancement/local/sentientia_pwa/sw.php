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
if (class_exists('\\local_airpay_core\\feature_flags')) {
    try {
        $pwa_enabled = \local_airpay_core\feature_flags::is_enabled('sentientia.pwa.enabled');
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
$sw_version = 'sentientia-pwa-v1';

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
const OFFLINE_URL = '{$wwwpath}/my/';
const WWW_PATH = '{$wwwpath}';
const PRECACHE_URLS = [
    '{$wwwpath}/my/',
    '{$wwwpath}/theme/airpayux/pix/brand/academy-logo-350.png',
    '{$wwwpath}/theme/airpayux/pix/brand/manifest.json',
    '{$wwwpath}/theme/airpayux/pix/brand/favicon_io/android-chrome-192x192.png',
    '{$wwwpath}/theme/airpayux/pix/brand/favicon_io/android-chrome-512x512.png',
];

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

// ── FETCH (NAVIGATION) ────────────────────────────────────────
// Network-first for navigation requests with offline-shell fallback.
// Non-navigation requests pass through transparently — we don't want
// to interfere with REST API calls, image loads, file downloads, etc.
self.addEventListener('fetch', function(event) {
    const request = event.request;

    // Only handle GET requests for HTML navigation under our wwwroot.
    if (request.method !== 'GET') { return; }
    if (request.mode !== 'navigate') { return; }
    const url = new URL(request.url);
    if (url.origin !== self.location.origin) { return; }

    event.respondWith(
        fetch(request).catch(function() {
            return caches.match(OFFLINE_URL, { ignoreSearch: true })
                .then(function(cached) {
                    return cached || new Response(
                        '<!DOCTYPE html><meta charset="utf-8">' +
                        '<title>Offline · Sentientia LMS</title>' +
                        '<style>body{font-family:Montserrat,-apple-system,sans-serif;' +
                        'padding:48px 24px;text-align:center;color:#1a1a2e;background:#F2F4FB;}' +
                        'h1{color:#0066A7;}p{color:#5a6070;}</style>' +
                        '<h1>You\\u2019re offline</h1>' +
                        '<p>Connect to the internet and try again.</p>' +
                        '<p><a href="' + WWW_PATH + '/" style="color:#0066A7;">Retry</a></p>',
                        { status: 200, headers: { 'Content-Type': 'text/html; charset=utf-8' } }
                    );
                });
        })
    );
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
