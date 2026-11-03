// Configuration par variables d'environnement (voir ../.env.example).
const env = (name, fallback) => process.env[name] ?? fallback;
const int = (name, fallback) => Number.parseInt(env(name, String(fallback)), 10);

const config = {
    apiToken: env('SANDBOX_API_TOKEN', ''),
    apiPort: int('SANDBOX_API_PORT', 8080),
    terminalPort: int('SANDBOX_TERMINAL_PORT', 8081),
    terminalPath: env('SANDBOX_TERMINAL_PATH', '/ssh-ws'),
    gatewayPort: int('SANDBOX_GATEWAY_PORT', 2222),
    dataDir: env('SANDBOX_DATA_DIR', '/data'),
    keysDir: env('SANDBOX_KEYS_DIR', '/keys'),
    sshHost: env('SANDBOX_SSH_HOST', 'sandbox-ssh'),
    sshPort: int('SANDBOX_SSH_PORT', 22),
    container: env('SANDBOX_CONTAINER', 'portfolio-sandbox-ssh'),
    egressNetwork: env('SANDBOX_EGRESS_NETWORK', 'portfolio-sandbox-egress'),
    dockerUrl: env('SANDBOX_DOCKER_URL', 'http://docker-proxy:2375'),
    quotaKb: int('SANDBOX_QUOTA_MB', 512) * 1024,
    maxSessionsPerIp: int('SANDBOX_MAX_SESSIONS_PER_IP', 2),
    maxSessions: int('SANDBOX_MAX_SESSIONS', 10),
    maxConnectionsPerMinute: int('SANDBOX_MAX_CONNECTIONS_PER_MINUTE', 10),
    terminalIdleMinutes: int('SANDBOX_TERMINAL_IDLE_MINUTES', 15),
    terminalMaxMinutes: int('SANDBOX_TERMINAL_MAX_MINUTES', 60),
};

if (config.apiToken.length < 32) {
    console.error('SANDBOX_API_TOKEN doit contenir au moins 32 caractères.');
    process.exit(1);
}

export default config;
