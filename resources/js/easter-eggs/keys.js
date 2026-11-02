// Écoute des combinaisons : les touches tapées sont comparées à des empreintes SHA-256
// (le code de la page ne contient jamais les combinaisons en clair). Sur écran tactile,
// les balayages comptent comme des flèches (↑ ↓ ← →).

const encoder = new TextEncoder();

/** Appareil tactile sans souris (téléphone, tablette) : balayages et champ de saisie. */
export const isTouchDevice = () => window.matchMedia('(pointer: coarse) and (hover: none)').matches;

async function sha256(text) {
    const digest = await crypto.subtle.digest('SHA-256', encoder.encode(text));
    return [...new Uint8Array(digest)].map((b) => b.toString(16).padStart(2, '0')).join('');
}

/**
 * Combinaison saisie dans un champ : touches séparées par des espaces
 * (« ArrowUp ArrowUp b a »), sinon un mot tapé lettre par lettre.
 */
export function keysFromText(text) {
    const value = text.trim().toLowerCase();
    if (! value) return [];
    return /\s/.test(value) ? value.split(/\s+/) : [...value];
}

/** Première cible dont l'empreinte correspond exactement aux touches données. */
export async function findMatch(keys, targets) {
    const hash = await sha256(keys.join(' '));
    return targets.find((t) => t.length === keys.length && t.hash === hash) ?? null;
}

/** Balayage net et rapide → flèche ; null pour un défilement ou un geste hésitant. */
function swipeDirection(start, end) {
    const dx = end.x - start.x;
    const dy = end.y - start.y;
    const distance = Math.max(Math.abs(dx), Math.abs(dy));
    if (distance < 50 || end.t - start.t > 600) return null;
    if (Math.abs(dx) > Math.abs(dy) * 1.5) return dx > 0 ? 'arrowright' : 'arrowleft';
    if (Math.abs(dy) > Math.abs(dx) * 1.5) return dy > 0 ? 'arrowdown' : 'arrowup';
    return null;
}

/**
 * @param {Array<{length: number, hash: string}>} targets
 * @param {(keys: string[], target: object) => void} onMatch
 */
export function listenForSequences(targets, onMatch) {
    if (! targets.length || ! window.crypto?.subtle) return;
    const max = Math.max(...targets.map((t) => t.length));
    let buffer = [];

    const push = async (key) => {
        buffer = [...buffer, key].slice(-max);
        const snapshot = buffer;
        for (const target of targets) {
            if (snapshot.length < target.length) continue;
            const keys = snapshot.slice(-target.length);
            if (await sha256(keys.join(' ')) === target.hash) {
                buffer = [];
                onMatch(keys, target);
                return;
            }
        }
    };

    document.addEventListener('keydown', (event) => {
        // Pas de capture pendant la saisie dans un champ.
        if (event.target.closest?.('input, textarea, select, [contenteditable]') || event.ctrlKey || event.metaKey || event.altKey) return;
        if (['shift', 'capslock', 'dead', 'unidentified', 'process'].includes(event.key.toLowerCase())) return;
        push(event.key.toLowerCase());
    });

    if (! isTouchDevice()) return;

    // Balayages : un doigt, hors champs de saisie, carrousels et visionneuse (qui ont leurs propres gestes).
    let start = null;
    const ignored = 'input, textarea, select, [contenteditable], [aria-roledescription="carrousel"], [role="dialog"]';
    document.addEventListener('touchstart', (event) => {
        start = event.touches.length === 1 && ! event.target.closest?.(ignored)
            ? { x: event.touches[0].clientX, y: event.touches[0].clientY, t: performance.now() }
            : null;
    }, { passive: true });
    document.addEventListener('touchend', (event) => {
        if (! start) return;
        const touch = event.changedTouches[0];
        const direction = swipeDirection(start, { x: touch.clientX, y: touch.clientY, t: performance.now() });
        start = null;
        if (direction) push(direction);
    }, { passive: true });
}
