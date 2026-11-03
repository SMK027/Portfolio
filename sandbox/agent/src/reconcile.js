// Application de l'état voulu (comptes, internet), surveillance du quota et
// réapplication automatique quand la machine redémarre.
import config from './config.js';
import * as docker from './docker.js';
import * as sandbox from './sandbox.js';
import { closeAll } from './gateway.js';
import { closeAllTerminals } from './terminal.js';
import { DEFAULT_LIMITS, runtime, state } from './state.js';
import { collect as collectAuthLog } from './authlog.js';

export async function apply() {
    try {
        await docker.isolateSandbox();
        await docker.setInternet(state.internet);
        // Compte au-delà de son quota : désactivé jusqu'à ce que l'espace soit libéré.
        const accounts = state.accounts.map((a) => ({ ...a, enabled: a.enabled && ! runtime.overUsers.includes(a.username) }));
        const { user_quota_mb: _, ...limits } = { ...DEFAULT_LIMITS, ...state.limits };
        await sandbox.applyAccounts(accounts, limits);
        runtime.lastApplied = new Date().toISOString();
        runtime.lastError = null;
    } catch (error) {
        runtime.lastError = error.message;
        console.error('Application de l\'état impossible :', error.message);
    }
}

/** Vérification périodique : redémarrage de la machine détecté, quota disque. */
export async function watch() {
    try {
        const info = await docker.inspect();
        const startedAt = info?.State?.StartedAt ?? null;
        if (info?.State?.Running && startedAt !== runtime.containerStartedAt) {
            runtime.containerStartedAt = startedAt;
            // Laisse sshd démarrer avant de réappliquer les comptes.
            await new Promise((resolve) => setTimeout(resolve, 3000));
            await apply();
        }
        if (info?.State?.Running) {
            runtime.usageKb = await sandbox.usageKb();
            const over = runtime.usageKb > config.quotaKb;
            if (over && ! runtime.overQuota) {
                console.warn(`Quota dépassé (${runtime.usageKb} Ko) : accès public suspendu.`);
                closeAll();
                closeAllTerminals('espace disque de la machine saturé');
                await sandbox.killSessions().catch(() => {});
            }
            runtime.overQuota = over;

            // Quota par compte
            runtime.usageByUser = await sandbox.usageByUser();
            const quotaKb = (state.limits?.user_quota_mb ?? DEFAULT_LIMITS.user_quota_mb) * 1024;
            const overUsers = Object.entries(runtime.usageByUser).filter(([, kb]) => kb > quotaKb).map(([user]) => user).sort();
            if (overUsers.join() !== runtime.overUsers.join()) {
                runtime.overUsers = overUsers;
                await apply();
            }

            await sandbox.reap();
            await collectAuthLog();
        }
    } catch (error) {
        runtime.lastError = error.message;
    }
}
