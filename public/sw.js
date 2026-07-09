// Service worker minimo: serve solo a rendere l'app installabile (PWA).
// Non mette in cache pagine o dati dinamici — questa è un'app gestionale,
// mostrare dati vecchi (pagamenti, stato promo, ecc.) sarebbe peggio che
// non funzionare offline. Aggiorna solo gli asset statici di base.
const CACHE_NAME = 'hub-core-shell-v1';
const STATIC_ASSETS = [
    '/images/icon-192.png',
    '/images/icon-512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(STATIC_ASSETS))
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(
            keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
        ))
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') {
        return;
    }

    const url = new URL(event.request.url);
    const isStaticAsset = STATIC_ASSETS.some((asset) => url.pathname === asset);

    if (!isStaticAsset) {
        return; // tutto il resto passa dritto alla rete, niente cache
    }

    event.respondWith(
        caches.match(event.request).then((cached) => cached || fetch(event.request))
    );
});
