/**
 * Airpay Academy Service Worker
 * Caches static assets (CSS, JS, fonts, images) for faster page loads.
 * Does NOT cache HTML pages or AJAX/API calls — those must stay fresh.
 * Does NOT interfere with SCORM iframe content.
 */

const CACHE_NAME = 'airpay-academy-v1';
const STATIC_ASSETS = [
    // Fonts will be cached on first request (runtime caching below)
];

// Install — pre-cache critical assets
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(STATIC_ASSETS);
        })
    );
    self.skipWaiting();
});

// Activate — clean up old caches
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys.filter((key) => key !== CACHE_NAME)
                    .map((key) => caches.delete(key))
            );
        })
    );
    self.clients.claim();
});

// Fetch — stale-while-revalidate for static assets, network-first for everything else
self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    // Skip non-GET requests
    if (event.request.method !== 'GET') return;

    // Skip SCORM content (runs in iframes, must not be cached)
    if (url.pathname.includes('/mod/scorm/') ||
        url.pathname.includes('/pluginfile.php') && url.pathname.includes('mod_scorm')) {
        return;
    }

    // Skip Moodle AJAX/REST API calls
    if (url.pathname.includes('/lib/ajax/') ||
        url.pathname.includes('/webservice/') ||
        url.pathname.includes('service.php') ||
        url.search.includes('wstoken')) {
        return;
    }

    // Skip admin pages
    if (url.pathname.includes('/admin/')) return;

    // Cache static assets: CSS, JS, fonts, images
    const isStaticAsset = /\.(css|js|woff2?|ttf|eot|svg|png|jpg|jpeg|gif|ico|webp)(\?|$)/.test(url.pathname) ||
                          url.pathname.includes('/theme/image.php') ||
                          url.pathname.includes('/theme/font.php') ||
                          url.pathname.includes('/theme/javascript.php');

    if (isStaticAsset) {
        event.respondWith(
            caches.open(CACHE_NAME).then((cache) => {
                return cache.match(event.request).then((cachedResponse) => {
                    const fetchPromise = fetch(event.request).then((networkResponse) => {
                        if (networkResponse.ok) {
                            cache.put(event.request, networkResponse.clone());
                        }
                        return networkResponse;
                    }).catch(() => cachedResponse);

                    return cachedResponse || fetchPromise;
                });
            })
        );
        return;
    }

    // All other requests — network only (HTML pages, API calls)
    // Do not cache to ensure fresh content
});
