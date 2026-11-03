// Accès minimal à l'API Docker, via docker-socket-proxy (jamais le socket brut) :
// état du conteneur, redémarrage et rattachement du proxy de sortie à internet.
import config from './config.js';

async function call(method, path, body) {
    const response = await fetch(config.dockerUrl + path, {
        method,
        headers: body ? { 'Content-Type': 'application/json' } : {},
        body: body ? JSON.stringify(body) : undefined,
        signal: AbortSignal.timeout(15000),
    });
    if (! response.ok && response.status !== 304) {
        throw new Error(`Docker ${method} ${path} : ${response.status} ${await response.text()}`);
    }
    const text = await response.text();
    return text ? JSON.parse(text) : null;
}

const container = () => `/containers/${encodeURIComponent(config.container)}`;

export async function inspect() {
    return call('GET', `${container()}/json`);
}

export async function stats() {
    const s = await call('GET', `${container()}/stats?stream=false`);
    const cpuDelta = s.cpu_stats.cpu_usage.total_usage - (s.precpu_stats.cpu_usage?.total_usage ?? 0);
    const systemDelta = (s.cpu_stats.system_cpu_usage ?? 0) - (s.precpu_stats.system_cpu_usage ?? 0);
    const cpus = s.cpu_stats.online_cpus || 1;
    return {
        cpu_percent: systemDelta > 0 ? Math.round((cpuDelta / systemDelta) * cpus * 1000) / 10 : 0,
        memory_bytes: (s.memory_stats.usage ?? 0) - (s.memory_stats.stats?.inactive_file ?? 0),
        memory_limit_bytes: s.memory_stats.limit ?? null,
        pids: s.pids_stats?.current ?? null,
    };
}

export async function restart() {
    await call('POST', `${container()}/restart?t=5`);
}

export async function hasInternet() {
    const info = await call('GET', `/containers/${encodeURIComponent(config.egressContainer)}/json`);
    return Object.keys(info.NetworkSettings?.Networks ?? {}).includes(config.egressNetwork);
}

/** La machine n'est jamais rattachée au réseau de sortie (ancien fonctionnement, avant le proxy). */
export async function isolateSandbox() {
    const info = await inspect();
    if (Object.keys(info.NetworkSettings?.Networks ?? {}).includes(config.egressNetwork)) {
        await call('POST', `/networks/${encodeURIComponent(config.egressNetwork)}/disconnect`, { Container: config.container, Force: true });
    }
}

export async function setInternet(enabled) {
    if (enabled === await hasInternet()) {
        return;
    }
    if (enabled) {
        await ensureEgressNetwork();
    }
    const path = `/networks/${encodeURIComponent(config.egressNetwork)}/${enabled ? 'connect' : 'disconnect'}`;
    // Le proxy de sortie est rattaché au réseau internet, jamais la machine elle-même.
    await call('POST', path, enabled ? { Container: config.egressContainer } : { Container: config.egressContainer, Force: true });
}

/** Réseau de sortie vers internet, créé au besoin (sans communication entre conteneurs). */
async function ensureEgressNetwork() {
    try {
        await call('GET', `/networks/${encodeURIComponent(config.egressNetwork)}`);
    } catch {
        await call('POST', '/networks/create', {
            Name: config.egressNetwork,
            Driver: 'bridge',
            Options: { 'com.docker.network.bridge.enable_icc': 'false' },
            Labels: { 'portfolio.sandbox': 'egress' },
        });
    }
}

/** Ressources de la machine (processeur, mémoire, processus), appliquées sans redémarrage. */
export async function updateResources({ cpus, memory_mb: memoryMb, pids }) {
    const host = (await inspect()).HostConfig ?? {};
    const wanted = {
        NanoCpus: Math.round(cpus * 1e9),
        Memory: memoryMb * 1048576,
        MemorySwap: memoryMb * 1048576,
        PidsLimit: pids,
    };
    if (host.NanoCpus === wanted.NanoCpus && host.Memory === wanted.Memory && host.PidsLimit === wanted.PidsLimit) {
        return;
    }
    await call('POST', `${container()}/update`, wanted);
}
