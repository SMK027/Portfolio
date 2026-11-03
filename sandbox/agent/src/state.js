// État voulu par le panel (accès public, internet, comptes), conservé sur disque
// pour être réappliqué après un redémarrage de la machine ou de l'agent.
import fs from 'node:fs';
import path from 'node:path';
import config from './config.js';

const file = path.join(config.dataDir, 'state.json');
const LOG_SIZE = 200;

/** Limites par défaut (réglables depuis le panel). */
export const DEFAULT_LIMITS = { nproc: 24, memory_kb: 196608, file_kb: 102400, user_quota_mb: 100 };

export const state = {
    public: false,
    internet: false,
    accounts: [],
    limits: { ...DEFAULT_LIMITS },
    ...(fs.existsSync(file) ? JSON.parse(fs.readFileSync(file, 'utf8')) : {}),
};

/** Informations d'exécution (non persistées). */
export const runtime = {
    overQuota: false,
    usageKb: null,
    usageByUser: {},
    overUsers: [], // comptes dépassant leur quota : bloqués jusqu'à libération de l'espace
    lastApplied: null,
    lastError: null,
    containerStartedAt: null,
    connections: [], // journal des connexions publiques récentes
};

export function saveState(next) {
    Object.assign(state, next);
    fs.writeFileSync(file, JSON.stringify(state, null, 2), { mode: 0o600 });
}

/** Accès public : ouvert par le panel et quota respecté. */
export function isOpen() {
    return state.public && ! runtime.overQuota;
}

export function logConnection(entry) {
    runtime.connections.unshift({ at: new Date().toISOString(), ...entry });
    runtime.connections.length = Math.min(runtime.connections.length, LOG_SIZE);
}
