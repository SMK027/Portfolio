// Journal d'authentification de sshd : les connexions relayées arrivent toutes
// de l'agent ; le port source permet de retrouver l'IP réelle du visiteur.
import { relayedPorts } from './gateway.js';
import { logConnection, recordFailure } from './state.js';
import { run } from './sandbox.js';

// « Invalid user … » n'est pas compté : la ligne « Failed password for invalid user … » suit toujours.
const LINE = /(?:Failed \S+ for (?:invalid user )?(\S+)|Accepted (\S+) for (\S+)) from \S+ port (\d+)/;

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

export async function collect() {
    const events = parse(await run('auth-log'));
    for (const event of events) {
        const relayed = relayedPorts.get(event.port);
        if (! relayed || event.username === 'root') {
            continue; // terminal web (géré à part) ou administration par l'agent
        }
        if (event.type === 'failed') {
            recordFailure({ ip: relayed.ip, channel: 'ssh', username: event.username });
            logConnection({ ip: relayed.ip, channel: 'ssh', username: event.username, result: 'refusée (identifiants)' });
        } else {
            logConnection({ ip: relayed.ip, channel: 'ssh', username: event.username, result: 'authentifiée' });
        }
    }
    // Correspondances de plus de 10 minutes : oubliées.
    const limit = Date.now() - 600000;
    for (const [port, entry] of relayedPorts) {
        if (entry.at < limit) relayedPorts.delete(port);
    }
}
