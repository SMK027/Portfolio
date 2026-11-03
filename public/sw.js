// Service worker du portfolio (application installable).
// - Pages : réseau d'abord ; une page déjà lue reste consultable hors connexion
//   (articles de veille en particulier), sinon la page « hors ligne » est affichée.
// - Fichiers compilés (/build/, noms versionnés) : cache d'abord.
// - Images publiques (/storage/) : cache, rafraîchi en arrière-plan.
// Jamais mis en cache : administration, connexion, formulaires, CV, liens personnels.
// Les visiteurs connectés n'utilisent pas le service worker (voir resources/js/pwa.js).

const VERSION = 'v1';
const STATIC_CACHE = `static-${VERSION}`;
const PAGES_CACHE = `pages-${VERSION}`;
const IMAGES_CACHE = `images-${VERSION}`;
const OFFLINE_URL = '/hors-ligne';
const MAX_PAGES = 40;
const MAX_IMAGES = 80;

const EXCLUDED = [
    /^\/admin/, /^\/login/, /^\/logout/, /^\/dashboard/, /^\/profile/, /^\/supervision/, /^\/bot/,
    /^\/cv/, /^\/stats\//, /^\/surprise/, /^\/recommandation\//, /^\/rendez-vous\/annuler/, /^\/veille\/apercu\//,
    /^\/sitemap\.xml/, /^\/robots\.txt/, /^\/api\//,
];

self.addEventListener('install', (event) => {
    event.waitUntil((async () => {
        const cache = await caches.open(STATIC_CACHE);
        const response = await fetch(OFFLINE_URL, { cache: 'no-store' });
        if (response.ok) {
            const html = await response.clone().text();
            await cache.put(OFFLINE_URL, response);
            // Styles et scripts de la page hors ligne
            const assets = [...html.matchAll(/(?:href|src)="([^"]*\/build\/[^"]+)"/g)].map((m) => m[1]);
            await Promise.all(assets.map((url) => cache.add(url).catch(() => null)));
        }
        await self.skipWaiting();
    })());
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        const keep = [STATIC_CACHE, PAGES_CACHE, IMAGES_CACHE];
        await Promise.all((await caches.keys()).filter((key) => ! keep.includes(key)).map((key) => caches.delete(key)));
        await self.clients.claim();
    })());
});

// Messages de la page : oubli complet (connexion d'un administrateur).
self.addEventListener('message', (event) => {
    if (event.data === 'clear') {
        event.waitUntil(caches.keys().then((keys) => Promise.all(keys.map((key) => caches.delete(key)))));
    }
});

const trim = async (cacheName, max) => {
    const cache = await caches.open(cacheName);
    const keys = await cache.keys();
    await Promise.all(keys.slice(0, Math.max(0, keys.length - max)).map((key) => cache.delete(key)));
};

const cacheable = (response) => response && response.ok && response.type === 'basic' && ! response.redirected;

self.addEventListener('fetch', (event) => {
    const { request } = event;
    if (request.method !== 'GET') {
        return;
    }
    const url = new URL(request.url);
    if (url.origin !== self.location.origin || EXCLUDED.some((pattern) => pattern.test(url.pathname))) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith((async () => {
            try {
                const response = await fetch(request);
                if (cacheable(response)) {
                    const cache = await caches.open(PAGES_CACHE);
                    await cache.put(request, response.clone());
                    event.waitUntil(trim(PAGES_CACHE, MAX_PAGES));
                }
                return response;
            } catch {
                return (await caches.match(request, { ignoreSearch: true }))
                    ?? (await caches.match(OFFLINE_URL))
                    ?? new Response('Hors connexion', { status: 503, headers: { 'Content-Type': 'text/plain; charset=utf-8' } });
            }
        })());
        return;
    }

    if (url.pathname.startsWith('/build/')) {
        event.respondWith((async () => {
            const cached = await caches.match(request);
            if (cached) {
                return cached;
            }
            const response = await fetch(request);
            if (cacheable(response)) {
                (await caches.open(STATIC_CACHE)).put(request, response.clone());
            }
            return response;
        })());
        return;
    }

    if (url.pathname.startsWith('/storage/') || url.pathname.startsWith('/pwa/')) {
        event.respondWith((async () => {
            const cache = await caches.open(IMAGES_CACHE);
            const cached = await cache.match(request);
            const network = fetch(request).then((response) => {
                if (cacheable(response)) {
                    cache.put(request, response.clone());
                    trim(IMAGES_CACHE, MAX_IMAGES);
                }
                return response;
            }).catch(() => cached);
            return cached ?? network;
        })());
    }
});
