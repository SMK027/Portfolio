// Écoute des combinaisons : les touches tapées sont comparées à des empreintes SHA-256
// (le code de la page ne contient jamais les combinaisons en clair).

const encoder = new TextEncoder();

async function sha256(text) {
    const digest = await crypto.subtle.digest('SHA-256', encoder.encode(text));
    return [...new Uint8Array(digest)].map((b) => b.toString(16).padStart(2, '0')).join('');
}

/**
 * @param {Array<{length: number, hash: string}>} targets
 * @param {(keys: string[], target: object) => void} onMatch
 */
export function listenForSequences(targets, onMatch) {
    if (! targets.length || ! window.crypto?.subtle) return;
    const max = Math.max(...targets.map((t) => t.length));
    let buffer = [];

    document.addEventListener('keydown', async (event) => {
        // Pas de capture pendant la saisie dans un champ.
        if (event.target.closest?.('input, textarea, select, [contenteditable]') || event.ctrlKey || event.metaKey || event.altKey) return;
        if (['shift', 'capslock', 'dead'].includes(event.key.toLowerCase())) return;

        buffer = [...buffer, event.key.toLowerCase()].slice(-max);
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
    });
}
