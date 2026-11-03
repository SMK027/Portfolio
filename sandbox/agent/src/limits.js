// Limites par adresse IP communes au relais SSH et au terminal web.
import config from './config.js';

const active = new Map(); // ip => nombre de sessions ouvertes
const recent = new Map(); // ip => horodatages des connexions de la dernière minute

export function totalSessions() {
    return [...active.values()].reduce((sum, n) => sum + n, 0);
}

/** Renvoie la raison du refus, ou null si la connexion est acceptée (et comptée). */
export function acquire(ip) {
    const now = Date.now();
    const times = (recent.get(ip) ?? []).filter((t) => now - t < 60000);
    times.push(now);
    recent.set(ip, times);

    if (times.length > config.maxConnectionsPerMinute) {
        return 'trop de connexions en une minute';
    }
    if ((active.get(ip) ?? 0) >= config.maxSessionsPerIp) {
        return 'trop de sessions ouvertes depuis cette adresse';
    }
    if (totalSessions() >= config.maxSessions) {
        return 'machine complète, réessayez plus tard';
    }
    active.set(ip, (active.get(ip) ?? 0) + 1);
    return null;
}

export function release(ip) {
    const n = (active.get(ip) ?? 1) - 1;
    n > 0 ? active.set(ip, n) : active.delete(ip);
}

// Nettoyage périodique des compteurs de la dernière minute.
setInterval(() => {
    const now = Date.now();
    for (const [ip, times] of recent) {
        const kept = times.filter((t) => now - t < 60000);
        kept.length ? recent.set(ip, kept) : recent.delete(ip);
    }
}, 60000).unref();
