// Configuration par variables d'environnement (voir ../.env.example).
const env = (name, fallback) => process.env[name] ?? fallback;
const int = (name, fallback) => Number.parseInt(env(name, String(fallback)), 10);

const config = {
    apiToken: env('SANDBOX_API_TOKEN', ''),
    apiPort: int('SANDBOX_API_PORT', 8080),
    terminalPort: int('SANDBOX_TERMINAL_PORT', 8081),
    terminalPath: env('SANDBOX_TERMINAL_PATH', '/ssh-ws'),
    gatewayPort: int('SANDBOX_GATEWAY_PORT', 2222),
    managerGatewayPort: int('SANDBOX_MANAGER_GATEWAY_PORT', 2224),
    sshManagerPort: int('SANDBOX_SSH_MANAGER_PORT', 2200),
    publicManagerPort: int('SANDBOX_PUBLIC_MANAGER_PORT', 2224),
    dataDir: env('SANDBOX_DATA_DIR', '/data'),
    keysDir: env('SANDBOX_KEYS_DIR', '/keys'),
    sshHost: env('SANDBOX_SSH_HOST', 'sandbox-ssh'),
    sshPort: int('SANDBOX_SSH_PORT', 22),
    container: env('SANDBOX_CONTAINER', 'portfolio-sandbox-ssh'),
    egressContainer: env('SANDBOX_EGRESS_CONTAINER', 'portfolio-sandbox-egress-proxy'),
    egressNetwork: env('SANDBOX_EGRESS_NETWORK', 'portfolio-sandbox-egress'),
    dockerUrl: env('SANDBOX_DOCKER_URL', 'http://docker-proxy:2375'),
    publicSshPort: int('SANDBOX_PUBLIC_SSH_PORT', 2222),
    publicSshHost: env('SANDBOX_PUBLIC_SSH_HOST', '') || null,
    quotaKb: int('SANDBOX_QUOTA_MB', 512) * 1024,
    maxSessionsPerIp: int('SANDBOX_MAX_SESSIONS_PER_IP', 2),
    maxSessions: int('SANDBOX_MAX_SESSIONS', 10),
    maxConnectionsPerMinute: int('SANDBOX_MAX_CONNECTIONS_PER_MINUTE', 10),
    // Copie hors serveur des sauvegardes (facultative), stockage compatible S3
    s3: {
        endpoint: env('SANDBOX_S3_ENDPOINT', ''),
        region: env('SANDBOX_S3_REGION', 'us-east-1'),
        bucket: env('SANDBOX_S3_BUCKET', ''),
        accessKey: env('SANDBOX_S3_ACCESS_KEY', ''),
        secretKey: env('SANDBOX_S3_SECRET_KEY', ''),
        prefix: env('SANDBOX_S3_PREFIX', 'portfolio-sandbox/'),
    },
    terminalIdleMinutes: int('SANDBOX_TERMINAL_IDLE_MINUTES', 15),
    terminalMaxMinutes: int('SANDBOX_TERMINAL_MAX_MINUTES', 60),
};

if (config.apiToken.length < 32) {
    console.error('SANDBOX_API_TOKEN doit contenir au moins 32 caractères.');
    process.exit(1);
}

export default config;
