// Terminal web : websocket ⇄ session SSH sur la machine. Le navigateur présente
// un ticket signé par le portfolio, puis l'identifiant et le mot de passe du compte.
//
// Messages du navigateur : {type:"auth", username, password, cols, rows} ou
// {type:"auth", ephemeral:true, cols, rows} (compte jetable, si le ticket l'autorise),
// {type:"input", data}, {type:"resize", cols, rows}.
// Messages de l'agent : {type:"output", data}, {type:"status", message}, {type:"closed", message},
// {type:"limits", idle_minutes, max_minutes, username}.
import { WebSocketServer } from 'ws';
import ssh2 from 'ssh2';
import config from './config.js';
import { acquire, release } from './limits.js';
import { isBanned, isOpen, logConnection, recordFailure } from './state.js';
import { verifyTicket } from './tickets.js';
import { run } from './sandbox.js';

const { Client } = ssh2;

export const terminals = new Set();

const clampSize = (n, max) => Math.min(max, Math.max(10, Number.parseInt(n, 10) || 80));

export function startTerminal() {
    const wss = new WebSocketServer({ port: config.terminalPort, path: config.terminalPath, maxPayload: 64 * 1024 });

    wss.on('connection', (socket, request) => {
        const url = new URL(request.url, 'http://agent');
        const ticket = verifyTicket(url.searchParams.get('ticket'));
        const ip = ticket?.ip ?? request.headers['x-forwarded-for']?.split(',')[0].trim() ?? request.socket.remoteAddress;
        const send = (message) => socket.readyState === socket.OPEN && socket.send(JSON.stringify(message));
        const refuse = (message) => {
            logConnection({ ip, channel: 'web', result: `refusée (${message})` });
            send({ type: 'closed', message });
            socket.close();
        };

        if (! ticket) {
            refuse('lien expiré, rechargez la page');
            return;
        }
        if (isBanned(ip)) {
            refuse('adresse bannie');
            return;
        }
        if (! isOpen()) {
            refuse('l\'accès à la machine est actuellement fermé');
            return;
        }
        const refusal = acquire(ip);
        if (refusal) {
            refuse(refusal);
            return;
        }

        let ssh = null;
        let shell = null;
        let ephemeralUser = null;
        let authenticating = false;
        let idleTimer = null;
        let released = false;
        const close = (message) => {
            if (! released) {
                released = true;
                release(ip);
                terminals.delete(close);
            }
            clearTimeout(idleTimer);
            clearTimeout(maxTimer);
            if (message) send({ type: 'closed', message });
            ssh?.end();
            if (socket.readyState === socket.OPEN) socket.close();
            if (ephemeralUser) {
                const user = ephemeralUser;
                ephemeralUser = null;
                run(`ephemeral-delete ${user}`).catch(() => {}); // sinon supprimé par le nettoyage périodique
            }
        };
        const resetIdle = () => {
            clearTimeout(idleTimer);
            idleTimer = setTimeout(() => close('session fermée après inactivité'), config.terminalIdleMinutes * 60000);
        };
        const maxTimer = setTimeout(() => close('durée maximale de session atteinte'), config.terminalMaxMinutes * 60000);
        terminals.add(close);
        resetIdle();

        socket.on('message', async (raw) => {
            let message;
            try {
                message = JSON.parse(raw.toString());
            } catch {
                return;
            }
            resetIdle();

            if (message.type === 'auth' && ! ssh && ! authenticating) {
                authenticating = true; // une seule authentification par websocket
                let username = String(message.username ?? '');
                let password = String(message.password ?? '');
                if (message.ephemeral === true) {
                    if (! ticket.ephemeral) {
                        close('sessions jetables désactivées');
                        return;
                    }
                    try {
                        ({ username, password } = JSON.parse(await run('ephemeral-create')));
                        ephemeralUser = username;
                    } catch {
                        close('impossible de créer la session');
                        return;
                    }
                }
                if (! /^[a-z][a-z0-9_-]{2,31}$/.test(username)) {
                    close('identifiant invalide');
                    return;
                }
                send({ type: 'status', message: 'Connexion…' });
                ssh = new Client();
                ssh.on('ready', () => {
                    logConnection({ ip, channel: 'web', username, result: 'ouverte' });
                    ssh.shell({ term: 'xterm-256color', cols: clampSize(message.cols, 300), rows: clampSize(message.rows, 120) }, (error, stream) => {
                        if (error) {
                            close('impossible d\'ouvrir le terminal');
                            return;
                        }
                        shell = stream;
                        send({ type: 'status', message: 'connected' });
                        send({ type: 'limits', idle_minutes: config.terminalIdleMinutes, max_minutes: config.terminalMaxMinutes, username });
                        stream.on('data', (data) => send({ type: 'output', data: data.toString('utf8') }));
                        stream.stderr.on('data', (data) => send({ type: 'output', data: data.toString('utf8') }));
                        stream.on('close', () => close('session terminée'));
                    });
                });
                ssh.on('error', (error) => {
                    const denied = error.level === 'client-authentication';
                    logConnection({ ip, channel: 'web', username, result: denied ? 'refusée (identifiants)' : 'erreur' });
                    if (denied) recordFailure({ ip, channel: 'web', username });
                    close(denied ? 'identifiant ou mot de passe incorrect' : 'machine injoignable');
                });
                ssh.connect({
                    host: config.sshHost, port: config.sshPort, username, password,
                    readyTimeout: 15000, tryKeyboard: false,
                });
            } else if (message.type === 'input' && shell && typeof message.data === 'string') {
                shell.write(message.data);
            } else if (message.type === 'resize' && shell) {
                shell.setWindow(clampSize(message.rows, 120), clampSize(message.cols, 300), 0, 0);
            }
        });
        socket.on('close', () => close());
        socket.on('error', () => close());
    });

    console.log(`Terminal web sur le port ${config.terminalPort}${config.terminalPath}`);
}

/** Ferme tous les terminaux web (accès public fermé). */
export function closeAllTerminals(message = 'l\'accès à la machine a été fermé') {
    [...terminals].forEach((close) => close(message));
}
