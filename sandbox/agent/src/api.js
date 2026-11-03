// API de contrôle pour le panel du portfolio (réseau interne uniquement,
// jeton « Bearer » obligatoire). Toutes les réponses sont en JSON, sauf le
// téléchargement d'une sauvegarde.
import crypto from 'node:crypto';
import dns from 'node:dns/promises';
import http from 'node:http';
import net from 'node:net';
import config from './config.js';
import * as backups from './backups.js';
import * as docker from './docker.js';
import * as sandbox from './sandbox.js';
import * as s3 from './s3.js';
import { closeAll } from './gateway.js';
import { closeAllTerminals } from './terminal.js';
import { totalSessions } from './limits.js';
import { DEFAULT_LIMITS, pendingFailures, runtime, saveState, state } from './state.js';
import { apply } from './reconcile.js';

const USERNAME = /^[a-z][a-z0-9_-]{2,31}$/;
const MANAGER = /^adm-[a-z0-9_-]{2,27}$/;
const PUBLIC_KEY = /^(ssh-ed25519|ssh-rsa|ecdsa-sha2-nistp(256|384|521)|sk-ssh-ed25519@openssh\.com|sk-ecdsa-sha2-nistp256@openssh\.com) [A-Za-z0-9+/=]{40,16384}( [^\n]{0,200})?$/;

/** Adresse publique de la machine (affichée par le portfolio). */
const publicSsh = () => ({ port: config.publicSshPort, host: config.publicSshHost, manager_port: config.publicManagerPort });

const authorized = (request) => {
    const given = Buffer.from(request.headers.authorization?.replace(/^Bearer /, '') ?? '');
    const expected = Buffer.from(config.apiToken);
    return given.length === expected.length && crypto.timingSafeEqual(given, expected);
};

const readJson = (request) => new Promise((resolve, reject) => {
    let body = '';
    request.on('data', (chunk) => {
        body += chunk;
        if (body.length > 1024 * 1024) {
            reject(Object.assign(new Error('Requête trop volumineuse'), { status: 413 }));
            request.destroy();
        }
    });
    request.on('end', () => {
        try {
            resolve(body ? JSON.parse(body) : {});
        } catch {
            reject(Object.assign(new Error('JSON invalide'), { status: 400 }));
        }
    });
});

const safe = async (fn, fallback = null) => {
    try {
        return await fn();
    } catch {
        return fallback;
    }
};

function validateState(body) {
    const accounts = Array.isArray(body.accounts) ? body.accounts : [];
    if (accounts.length > 200 || accounts.some((a) => ! USERNAME.test(a?.username ?? '') || a.username.startsWith('adm-') || ! String(a?.hash ?? '').startsWith('$6$'))) {
        throw Object.assign(new Error('Comptes invalides'), { status: 422 });
    }
    const managers = Array.isArray(body.managers) ? body.managers : [];
    if (managers.length > 50 || managers.some((m) => ! MANAGER.test(m?.username ?? '') || ! Array.isArray(m.keys) || m.keys.length > 20
        || m.keys.some((k) => ! PUBLIC_KEY.test(String(k))))) {
        throw Object.assign(new Error('Comptes de gestion invalides'), { status: 422 });
    }
    const limits = body.limits ?? {};
    const bounded = (value, min, max, fallback) => (Number.isInteger(value) && value >= min && value <= max ? value : fallback);
    return {
        limits: {
            nproc: bounded(limits.nproc, 4, 256, DEFAULT_LIMITS.nproc),
            memory_kb: bounded(limits.memory_kb, 32768, 1048576, DEFAULT_LIMITS.memory_kb),
            file_kb: bounded(limits.file_kb, 1024, 1048576, DEFAULT_LIMITS.file_kb),
            user_quota_mb: bounded(limits.user_quota_mb, 1, 4096, DEFAULT_LIMITS.user_quota_mb),
        },
        resources: body.resources ? {
            cpus: Math.min(4, Math.max(0.05, Number(body.resources.cpus) || 0.25)),
            memory_mb: bounded(body.resources.memory_mb, 32, 4096, 128),
            pids: bounded(body.resources.pids, 16, 4096, 64),
        } : null,
        connections: body.connections ? {
            max: bounded(body.connections.max, 1, 200, config.maxSessions),
            per_ip: bounded(body.connections.per_ip, 1, 20, config.maxSessionsPerIp),
            per_minute: bounded(body.connections.per_minute, 1, 120, config.maxConnectionsPerMinute),
        } : null,
        motd: typeof body.motd === 'string' ? body.motd.slice(0, 4000) : null,
        public: body.public === true,
        internet: body.internet === true,
        accounts: accounts.map((a) => ({ username: a.username, hash: a.hash, enabled: a.enabled !== false })),
        managers: managers.map((m) => ({ username: m.username, keys: m.keys.map(String) })),
        banned_ips: (Array.isArray(body.banned_ips) ? body.banned_ips : [])
            .filter((ip) => typeof ip === 'string' && net.isIP(ip)).slice(0, 10000),
    };
}

const routes = {
    'GET /status': async () => {
        const info = await safe(() => docker.inspect());
        const running = Boolean(info?.State?.Running);
        return {
            ssh: publicSsh(),
            container: {
                running,
                status: info?.State?.Status ?? 'introuvable',
                started_at: info?.State?.StartedAt ?? null,
                ...(running ? await safe(() => docker.stats(), {}) : {}),
            },
            public: state.public,
            internet: running ? await safe(() => docker.hasInternet(), state.internet) : state.internet,
            accounts: state.accounts.map((a) => a.username),
            quota: { used_kb: runtime.usageKb, limit_kb: config.quotaKb, exceeded: runtime.overQuota },
            usage_by_user: runtime.usageByUser,
            over_quota_users: runtime.overUsers,
            limits: state.limits,
            resources: state.resources,
            connections_limits: state.connections,
            sessions: running ? await safe(() => sandbox.sessions(), []) : [],
            relayed_sessions: totalSessions(),
            connections: runtime.connections.slice(0, 50),
            offsite_backups: s3.isEnabled(),
            last_applied: runtime.lastApplied,
            last_error: runtime.lastError,
        };
    },
    'PUT /state': async (request) => {
        const next = validateState(await readJson(request));
        const closing = state.public && ! next.public;
        saveState(next);
        if (closing) {
            closeAll();
            closeAllTerminals();
            await safe(() => sandbox.killSessions());
        }
        await apply();
        return { ok: true, last_error: runtime.lastError, ssh: publicSsh() };
    },
    'POST /sessions/kill': async (request) => {
        const { username = null } = await readJson(request);
        if (username !== null && ! USERNAME.test(username) && ! MANAGER.test(username)) {
            throw Object.assign(new Error('Identifiant invalide'), { status: 422 });
        }
        if (username === null) {
            closeAll();
            closeAllTerminals('session fermée par l\'administrateur');
        }
        await sandbox.killSessions(username);
        return { ok: true };
    },
    // Paquets de la machine mis à jour (nécessite temporairement la sortie internet)
    'POST /upgrade': async () => {
        await docker.setInternet(true);
        try {
            return { ok: true, output: await sandbox.upgrade() };
        } finally {
            await docker.setInternet(state.internet);
        }
    },
    'POST /restart': async () => {
        closeAll();
        closeAllTerminals('redémarrage de la machine');
        await docker.restart();
        return { ok: true };
    },
    'POST /reset': async () => {
        closeAll();
        closeAllTerminals('réinitialisation de la machine');
        await backups.wipe();
        await apply();
        return { ok: true };
    },
    // Échecs d'authentification depuis la dernière lecture (le portfolio applique ses règles de bannissement)
    'POST /auth-failures/drain': async () => ({ failures: pendingFailures.splice(0) }),
    'GET /backups': async () => ({ backups: backups.list() }),
    'POST /backups': async (request) => {
        const body = await readJson(request);
        const keep = Number.isInteger(body.keep) && body.keep > 0 ? body.keep : null;
        return { backup: await backups.create(body.label, body.automatic === true, keep) };
    },
};

const paramRoutes = [
    [/^POST \/backups\/([a-f0-9]{16})\/restore$/, async (request, id) => {
        closeAll();
        closeAllTerminals('restauration d\'une sauvegarde');
        const backup = await backups.restore(id);
        await apply();
        return { ok: true, backup };
    }],
    [/^DELETE \/backups\/([a-f0-9]{16})$/, async (request, id) => {
        if (! backups.remove(id)) throw Object.assign(new Error('Sauvegarde introuvable'), { status: 404 });
        return { ok: true };
    }],
];

export function startApi() {
    http.createServer(async (request, response) => {
        const send = (status, body) => {
            response.writeHead(status, { 'Content-Type': 'application/json; charset=utf-8', 'Cache-Control': 'no-store' });
            response.end(JSON.stringify(body));
        };
        // Sonde de santé (Docker HEALTHCHECK), sans jeton : aucune information sensible.
        if (request.method === 'GET' && request.url === '/healthz') {
            send(200, { ok: true });
            return;
        }

        // La machine partage un réseau avec l'agent : elle n'a jamais accès à l'API.
        const sandboxIp = await dns.lookup(config.sshHost).then((r) => r.address).catch(() => null);
        if (sandboxIp && request.socket.remoteAddress?.replace(/^::ffff:/, '') === sandboxIp) {
            send(403, { error: 'Interdit' });
            return;
        }
        if (! authorized(request)) {
            send(401, { error: 'Jeton invalide' });
            return;
        }

        const { pathname } = new URL(request.url, 'http://agent');
        const key = `${request.method} ${pathname.replace(/^\/api/, '').replace(/\/$/, '') || '/'}`;

        // Téléchargement d'une sauvegarde : flux binaire.
        const download = key.match(/^GET \/backups\/([a-f0-9]{16})\/download$/);
        if (download) {
            const stream = backups.stream(download[1]);
            if (! stream) {
                send(404, { error: 'Sauvegarde introuvable' });
                return;
            }
            response.writeHead(200, { 'Content-Type': 'application/gzip' });
            stream.pipe(response);
            return;
        }

        try {
            const route = routes[key];
            if (route) {
                send(200, await route(request));
                return;
            }
            for (const [pattern, handler] of paramRoutes) {
                const match = key.match(pattern);
                if (match) {
                    send(200, await handler(request, match[1]));
                    return;
                }
            }
            send(404, { error: 'Route inconnue' });
        } catch (error) {
            console.error(error);
            send(error.status ?? 500, { error: error.message });
        }
    }).listen(config.apiPort, () => console.log(`API de contrôle sur le port ${config.apiPort}`));
}
