// Application installable : service worker pour les visiteurs ; les personnes
// connectées n'en utilisent pas (aucune page d'administration ou privée en cache).
export default function startPwa() {
    if (! ('serviceWorker' in navigator)) {
        return;
    }

    if (document.body.dataset.serviceWorker) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register(document.body.dataset.serviceWorker, { scope: '/' }).catch(() => {});
        });
        return;
    }

    // Connecté : oubli du cache et désinscription.
    navigator.serviceWorker.controller?.postMessage('clear');
    navigator.serviceWorker.getRegistrations().then((registrations) => registrations.forEach((r) => r.unregister())).catch(() => {});
    if (window.caches) {
        caches.keys().then((keys) => keys.forEach((key) => caches.delete(key))).catch(() => {});
    }
}

// Page hors ligne : pages déjà lues, disponibles sans connexion.
export async function listOfflinePages(list) {
    if (! window.caches) {
        return;
    }
    const keys = (await caches.keys()).filter((key) => key.startsWith('pages-'));
    const items = [];
    for (const key of keys) {
        const cache = await caches.open(key);
        for (const request of await cache.keys()) {
            const response = await cache.match(request);
            const html = await response.text();
            const title = (html.match(/<title>([^<]*)<\/title>/) ?? [])[1] ?? new URL(request.url).pathname;
            items.push({ url: request.url, title, article: new URL(request.url).pathname.startsWith('/veille/') });
        }
    }
    items.sort((a, b) => Number(b.article) - Number(a.article));

    list.innerHTML = '';
    for (const item of items) {
        const li = document.createElement('li');
        const a = document.createElement('a');
        a.href = item.url;
        a.className = 'block rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-100';
        const textarea = document.createElement('textarea');
        textarea.innerHTML = item.title; // décode les entités HTML
        a.textContent = textarea.value;
        li.appendChild(a);
        list.appendChild(li);
    }
    list.closest('[data-offline-pages]')?.classList.toggle('hidden', items.length === 0);
}
