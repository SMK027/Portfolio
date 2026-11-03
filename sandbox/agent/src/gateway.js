// Relais TCP des ports SSH publics vers la machine (seuls chemins d'accès depuis l'extérieur) :
//   - port visiteurs : fermé quand l'accès public est coupé, limité par adresse IP ;
//   - port de gestion : toujours ouvert, réservé par sshd aux comptes de gestion (clé),
//     soumis aux bannissements et à une limite de connexions.
import net from 'node:net';
import config from './config.js';
import { acquire, release } from './limits.js';
import { isBanned, isOpen, logConnection } from './state.js';

export const openSockets = new Set();

/** Port source de chaque connexion relayée vers sshd → IP réelle et canal (pour le journal sshd). */
export const relayedPorts = new Map();

// Limites du port de gestion, indépendantes de celles des visiteurs
const MANAGER_MAX_SESSIONS = 5;
const MANAGER_PER_MINUTE = 10;
const managerSockets = new Set();
const managerAttempts = new Map(); // ip => horodatages de la dernière minute

function acquireManager(ip) {
    const now = Date.now();
    const times = (managerAttempts.get(ip) ?? []).filter((t) => now - t < 60000);
    times.push(now);
    managerAttempts.set(ip, times);
    if (times.length > MANAGER_PER_MINUTE) {
        return 'trop de connexions en une minute';
    }
    return managerSockets.size >= MANAGER_MAX_SESSIONS ? 'trop de sessions de gestion ouvertes' : null;
}

function relay({ listenPort, upstreamPort, channel, label, admit, onOpen, onClose }) {
    const server = net.createServer((client) => {
        const ip = client.remoteAddress?.replace(/^::ffff:/, '') ?? 'inconnue';
        const refusal = isBanned(ip) ? 'adresse bannie' : admit(ip);
        if (refusal) {
            logConnection({ ip, channel, result: `refusée (${refusal})` });
            client.destroy();
            return;
        }

        logConnection({ ip, channel, result: 'ouverte' });
        const upstream = net.connect(upstreamPort, config.sshHost, () => {
            relayedPorts.set(upstream.localPort, { ip, channel, at: Date.now() });
        });
        onOpen(client);
        let closed = false;
        const close = () => {
            if (! closed) {
                closed = true;
                onClose(client, ip);
            }
            client.destroy();
            upstream.destroy();
        };
        client.on('error', close).on('close', close);
        upstream.on('error', close).on('close', close);
        client.pipe(upstream).pipe(client);
    });
    server.listen(listenPort, () => console.log(`${label} sur le port ${listenPort}`));
}

export function startGateway() {
    relay({
        listenPort: config.gatewayPort,
        upstreamPort: config.sshPort,
        channel: 'ssh',
        label: 'Relais SSH public',
        admit: (ip) => (isOpen() ? acquire(ip) : 'accès fermé'),
        onOpen: (client) => openSockets.add(client),
        onClose: (client, ip) => openSockets.delete(client) && release(ip),
    });
    relay({
        listenPort: config.managerGatewayPort,
        upstreamPort: config.sshManagerPort,
        channel: 'gestion',
        label: 'Relais SSH de gestion',
        admit: acquireManager,
        onOpen: (client) => managerSockets.add(client),
        onClose: (client) => managerSockets.delete(client),
    });
}

/** Coupe toutes les connexions relayées des visiteurs (accès public fermé) ; la gestion n'est pas concernée. */
export function closeAll() {
    openSockets.forEach((socket) => socket.destroy());
}
