// Commandes d'administration sur la machine : connexion SSH root par clé,
// limitée côté machine à la commande forcée « sandboxctl ».
import fs from 'node:fs';
import path from 'node:path';
import ssh2 from 'ssh2';

const { Client, utils } = ssh2;
import config from './config.js';

const privateKeyPath = path.join(config.dataDir, 'agent_key');

/** Clé de l'agent : créée au premier démarrage ; la clé publique est partagée avec la machine (volume). */
export function ensureKey() {
    if (! fs.existsSync(privateKeyPath)) {
        const keys = utils.generateKeyPairSync('ed25519', { comment: 'sandbox-agent' });
        fs.writeFileSync(privateKeyPath, keys.private, { mode: 0o600 });
        fs.writeFileSync(`${privateKeyPath}.pub`, keys.public + '\n');
    }
    const publicKey = fs.readFileSync(`${privateKeyPath}.pub`, 'utf8').trim();
    fs.mkdirSync(config.keysDir, { recursive: true });
    fs.writeFileSync(path.join(config.keysDir, 'authorized_keys'),
        `command="/usr/local/sbin/sandboxctl",restrict ${publicKey}\n`, { mode: 0o644 });
}

function connect() {
    return new Promise((resolve, reject) => {
        const client = new Client();
        client.on('ready', () => resolve(client)).on('error', reject).connect({
            host: config.sshHost,
            port: config.sshPort,
            username: 'root',
            privateKey: fs.readFileSync(privateKeyPath),
            readyTimeout: 10000,
        });
    });
}

/**
 * Exécute « sandboxctl <commande> ». input : texte ou flux envoyé sur l'entrée ;
 * output : flux recevant la sortie (sinon elle est renvoyée sous forme de texte).
 */
export async function run(command, { input = null, output = null } = {}) {
    const client = await connect();
    try {
        return await new Promise((resolve, reject) => {
            client.exec(command, (error, stream) => {
                if (error) {
                    reject(error);
                    return;
                }
                const chunks = [];
                let stderr = '';
                stream.on('data', (chunk) => (output ? output.write(chunk) : chunks.push(chunk)));
                stream.stderr.on('data', (chunk) => { stderr += chunk; });
                stream.on('close', (code) => {
                    if (code === 0) {
                        resolve(Buffer.concat(chunks).toString('utf8'));
                    } else {
                        reject(new Error(`sandboxctl ${command} : code ${code} ${stderr.trim()}`));
                    }
                });
                if (input === null) {
                    stream.end();
                } else if (typeof input === 'string' || Buffer.isBuffer(input)) {
                    stream.end(input);
                } else {
                    input.pipe(stream);
                }
            });
        });
    } finally {
        client.end();
    }
}

export async function applyAccounts(accounts) {
    await run('apply', { input: JSON.stringify({ accounts }) });
}

export async function sessions() {
    return JSON.parse((await run('sessions')).trim() || '[]');
}

export async function killSessions(username = null) {
    await run(username ? `kill ${username}` : 'kill');
}

export async function usageKb() {
    return Number.parseInt((await run('usage')).trim(), 10) || 0;
}
