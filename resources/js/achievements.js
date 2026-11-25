// Succès : notifications de déblocage et événements signalés au serveur.
// - succès débloqués au chargement : <script id="achievement-toasts"> (middleware TrackAchievements) ;
// - succès débloqués par une requête fetch : en-tête X-Achievements (JSON en base64) ;
// - événements du navigateur : « achievement-event » (palette, thème) et une minute de présence par minute visible.
const HEADER = 'X-Achievements';
const MINUTE_MS = 60000;

let stack = null;

function container() {
    if (! stack) {
        stack = document.createElement('div');
        stack.className = 'pointer-events-none fixed bottom-4 right-4 z-[60] flex w-80 max-w-[calc(100vw-2rem)] flex-col gap-3';
        stack.setAttribute('aria-live', 'polite');
        document.body.appendChild(stack);
    }

    return stack;
}

export function showAchievement({ title, description }) {
    const toast = document.createElement('div');
    toast.className = 'pointer-events-auto flex translate-y-4 items-start gap-3 rounded-2xl border border-amber-200 bg-white p-4 opacity-0 shadow-xl transition duration-300 dark:border-amber-500/30';
    toast.setAttribute('role', 'status');

    const badge = document.createElement('span');
    badge.className = 'flex h-10 w-10 flex-none items-center justify-center rounded-xl bg-gradient-to-br from-amber-300 to-amber-500 text-xl';
    badge.textContent = '🏆';

    const text = document.createElement('div');
    text.className = 'min-w-0';
    const label = document.createElement('p');
    label.className = 'text-xs font-semibold uppercase tracking-wider text-amber-600';
    label.textContent = 'Succès débloqué';
    const heading = document.createElement('p');
    heading.className = 'font-semibold text-slate-900';
    heading.textContent = title;
    text.append(label, heading);
    if (description) {
        const body = document.createElement('p');
        body.className = 'text-sm text-slate-600';
        body.textContent = description;
        text.append(body);
    }

    toast.append(badge, text);
    toast.addEventListener('click', () => toast.remove());
    container().appendChild(toast);
    requestAnimationFrame(() => toast.classList.remove('translate-y-4', 'opacity-0'));

    setTimeout(() => {
        toast.classList.add('opacity-0');
        setTimeout(() => toast.remove(), 300);
    }, 6000);
}

function decode(header) {
    try {
        const bytes = Uint8Array.from(atob(header), (c) => c.charCodeAt(0));
        return JSON.parse(new TextDecoder().decode(bytes));
    } catch (e) {
        return [];
    }
}

function showAll(list, delay = 400) {
    list.forEach((item, i) => setTimeout(() => showAchievement(item), delay + i * 700));
}

export default function startAchievements() {
    const initial = document.getElementById('achievement-toasts');
    if (initial) {
        try { showAll(JSON.parse(initial.textContent)); } catch (e) { /* données illisibles : ignorées */ }
    }

    // Toute réponse fetch du site peut annoncer des succès (réactions, déverrouillages…).
    const nativeFetch = window.fetch.bind(window);
    window.fetch = async (...args) => {
        const response = await nativeFetch(...args);
        const header = response.headers.get(HEADER);
        if (header) showAll(decode(header), 0);

        return response;
    };

    // Même chose pour axios (ticket du terminal SSH, défis…), succès comme erreurs.
    const fromAxios = (response) => {
        const header = response?.headers?.[HEADER.toLowerCase()];
        if (header) showAll(decode(header), 0);
    };
    window.axios?.interceptors.response.use(
        (response) => { fromAxios(response); return response; },
        (error) => { fromAxios(error.response); return Promise.reject(error); },
    );

    const url = document.body.dataset.achievementsUrl;
    if (! url) return;

    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    const send = (event) => fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token },
        body: JSON.stringify({ event }),
    }).catch(() => {});

    window.addEventListener('achievement-event', (e) => {
        if (typeof e.detail === 'string') send(e.detail);
    });

    setInterval(() => {
        if (document.visibilityState === 'visible') send('minutes_spent');
    }, MINUTE_MS);
}
