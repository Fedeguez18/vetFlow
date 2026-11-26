/*
 * Service worker of the mostrador (scope /pos). Online it stays out of the way. When a POS page can't be
 * loaded because there is no internet, it answers with the offline mostrador the page saved beforehand
 * (resources/js/pos-offline.js keeps that copy and its files in the "vetflow-pos-offline-v1" cache).
 */
const CACHE = 'vetflow-pos-offline-v1';
const OFFLINE_PAGE = '/pos/sin-conexion';

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET' || new URL(request.url).origin !== self.location.origin) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(async () => (await caches.match(OFFLINE_PAGE)) ?? Response.error()),
        );
        return;
    }

    // Files of the saved offline page (css, js, fonts): from the network when there is one, else the saved copy.
    event.respondWith(
        fetch(request).catch(async () => (await caches.match(request)) ?? Response.error()),
    );
});
