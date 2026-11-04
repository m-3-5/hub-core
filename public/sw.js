// Service worker di inm35.it: rende il sito installabile come app e mostra «Sei offline» senza rete.
// Non mette in cache le pagine né i dati (pagamenti, ordini, promo): sarebbe peggio mostrare dati vecchi.
// In cache solo la pagina offline e le icone dell'app.
const CACHE_NAME = 'hub-core-shell-v3';
const OFFLINE_URL = '/offline.html';
const STATIC_ASSETS = [
    OFFLINE_URL,
    '/pwa/icon-192.png',
    '/pwa/icon-512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.addAll(STATIC_ASSETS)));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') {
        return;
    }

    // Pagine: sempre dalla rete; se la rete non c'è, la pagina «Sei offline»
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_URL)));
        return;
    }

    const url = new URL(request.url);
    if (url.origin === self.location.origin && STATIC_ASSETS.includes(url.pathname)) {
        event.respondWith(caches.match(request).then((cached) => cached || fetch(request)));
    }
    // tutto il resto passa dritto alla rete
});
