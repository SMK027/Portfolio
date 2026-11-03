// Relais TCP du port SSH public vers la machine (seul chemin d'accès depuis
// l'extérieur) : fermé quand l'accès public est coupé, limité par adresse IP.
import net from 'node:net';
import config from './config.js';
import { acquire, release } from './limits.js';
import { isOpen, logConnection } from './state.js';

export const openSockets = new Set();

export function startGateway() {
    const server = net.createServer((client) => {
        const ip = client.remoteAddress?.replace(/^::ffff:/, '') ?? 'inconnue';
        if (! isOpen()) {
            logConnection({ ip, channel: 'ssh', result: 'refusée (accès fermé)' });
            client.destroy();
            return;
        }
        const refusal = acquire(ip);
        if (refusal) {
            logConnection({ ip, channel: 'ssh', result: `refusée (${refusal})` });
            client.destroy();
            return;
        }

        logConnection({ ip, channel: 'ssh', result: 'ouverte' });
        const upstream = net.connect(config.sshPort, config.sshHost);
        openSockets.add(client);
        const close = () => {
            if (openSockets.delete(client)) {
                release(ip);
            }
            client.destroy();
            upstream.destroy();
        };
        client.on('error', close).on('close', close);
        upstream.on('error', close).on('close', close);
        client.pipe(upstream).pipe(client);
    });
    server.listen(config.gatewayPort, () => console.log(`Relais SSH public sur le port ${config.gatewayPort}`));
}

/** Coupe toutes les connexions relayées (accès public fermé). */
export function closeAll() {
    openSockets.forEach((socket) => socket.destroy());
}
