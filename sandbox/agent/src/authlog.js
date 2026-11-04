// Journal d'authentification de sshd : les connexions relayées arrivent toutes
// de l'agent ; le port source permet de retrouver l'IP réelle du visiteur.
//
// Une correspondance « port → IP » n'existe que pendant la connexion et quelques
// secondes après sa fermeture : un port réutilisé ne peut pas être attribué à une
// ancienne connexion. Le journal est relevé toutes les quelques secondes ; un relevé
// arrivé en retard n'est pas attribué (pas de bannissement sur une supposition).
import { relayedPorts } from './gateway.js';
import { logConnection, recordFailure } from './state.js';
import { run } from './sandbox.js';

// « Invalid user … » n'est pas compté : la ligne « Failed password for invalid user … » suit toujours.
const LINE = /(?:Failed \S+ for (?:invalid user )?(\S+)|Accepted (\S+) for (\S+)) from \S+ port (\d+)/;

/** Délai de grâce après la fermeture d'une connexion, pour lire ses dernières lignes. */
export const GRACE_MS = 10000;

/** Au-delà de cet écart depuis le dernier relevé, le lot n'est pas attribué. */
export const MAX_GAP_MS = 15000;

let lastCollectAt = null;

/** Analyse le journal ; renvoie les événements reconnus (testable sans machine). */
export function parse(text) {
    const events = [];
    for (const line of text.split('\n')) {
        const m = line.match(LINE);
        if (! m) continue;
        const port = Number(m[4]);
        if (m[2]) {
            events.push({ type: 'accepted', username: m[3], method: m[2], port });
        } else {
            events.push({ type: 'failed', username: m[1], port });
        }
    }
    return events;
}

/**
 * Attribue les événements aux connexions relayées, puis solde les connexions fermées :
 * fermée sans authentification réussie ni échec explicite → comptée comme un échec.
 * map : Map port → {ip, channel, openedAt, closedAt, authenticated, failed} (testable).
 */
export function attribute(events, map, now = Date.now(), reliable = true) {
    for (const event of events) {
        const relayed = map.get(event.port);
        if (! relayed || event.username === 'root') {
            continue; // terminal web (géré à part), administration par l'agent, ou ligne trop tardive
        }
        if (! reliable) {
            relayed.failed = relayed.failed || event.type === 'failed'; // ni journal ni bannissement
            continue;
        }
        if (event.type === 'failed') {
            relayed.failed = true;
            recordFailure({ ip: relayed.ip, channel: relayed.channel, username: event.username });
            logConnection({ ip: relayed.ip, channel: relayed.channel, username: event.username, result: 'refusée (identifiants)' });
        } else {
            relayed.authenticated = true;
            logConnection({ ip: relayed.ip, channel: relayed.channel, username: event.username, result: 'authentifiée' });
        }
    }

    for (const [port, entry] of map) {
        if (entry.closedAt !== null && now - entry.closedAt >= GRACE_MS) {
            if (reliable && ! entry.authenticated && ! entry.failed) {
                recordFailure({ ip: entry.ip, channel: entry.channel, username: null });
                logConnection({ ip: entry.ip, channel: entry.channel, result: 'refusée (fermée sans authentification)' });
            }
            map.delete(port);
        } else if (now - entry.openedAt > 86400000) {
            map.delete(port); // connexion ouverte depuis plus d'un jour : correspondance abandonnée
        }
    }
}

export async function collect() {
    const now = Date.now();
    const reliable = lastCollectAt !== null && now - lastCollectAt <= MAX_GAP_MS;
    const text = await run('auth-log');
    attribute(parse(text), relayedPorts, Date.now(), reliable);
    if (! reliable && text.trim()) {
        console.warn('Journal sshd relevé en retard : lignes non attribuées (aucun bannissement).');
    }
    lastCollectAt = now;
}
