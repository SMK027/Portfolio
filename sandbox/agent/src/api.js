// API de contrôle pour le panel du portfolio (réseau interne uniquement,
// jeton « Bearer » obligatoire). Toutes les réponses sont en JSON, sauf le
// téléchargement d'une sauvegarde.
import crypto from 'node:crypto';
import dns from 'node:dns/promises';
import http from 'node:http';
import config from './config.js';
import * as backups from './backups.js';
import * as docker from './docker.js';
import * as sandbox from './sandbox.js';
import { closeAll } from './gateway.js';
import { closeAllTerminals } from './terminal.js';
import { totalSessions } from './limits.js';
import { DEFAULT_LIMITS, runtime, saveState, state } from './state.js';
import { apply } from './reconcile.js';

const USERNAME = /^[a-z][a-z0-9_-]{2,31}$/;

/** Adresse publique de la machine (affichée par le portfolio). */
const publicSsh = () => ({ port: config.publicSshPort, host: config.publicSshHost });

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
    if (accounts.length > 200 || accounts.some((a) => ! USERNAME.test(a?.username ?? '') || ! String(a?.hash ?? '').startsWith('$6$'))) {
        throw Object.assign(new Error('Comptes invalides'), { status: 422 });
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
        public: body.public === true,
        internet: body.internet === true,
        accounts: accounts.map((a) => ({ username: a.username, hash: a.hash, enabled: a.enabled !== false })),
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
            sessions: running ? await safe(() => sandbox.sessions(), []) : [],
            relayed_sessions: totalSessions(),
            connections: runtime.connections.slice(0, 50),
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
        if (username !== null && ! USERNAME.test(username)) {
            throw Object.assign(new Error('Identifiant invalide'), { status: 422 });
        }
        if (username === null) {
            closeAll();
            closeAllTerminals('session fermée par l\'administrateur');
        }
        await sandbox.killSessions(username);
        return { ok: true };
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
