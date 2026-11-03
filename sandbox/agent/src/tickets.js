// Tickets du terminal web : signés par le portfolio (HMAC, clé dérivée du jeton
// d'API), valables une minute et utilisables une seule fois.
import crypto from 'node:crypto';
import config from './config.js';

const used = new Map(); // nonce => expiration

export const ticketKey = () => crypto.createHash('sha256').update(`terminal|${config.apiToken}`).digest();

/** Contenu du ticket s'il est valide, sinon null. */
export function verifyTicket(ticket) {
    const [payload, signature] = String(ticket ?? '').split('.');
    if (! payload || ! signature) {
        return null;
    }
    const expected = crypto.createHmac('sha256', ticketKey()).update(payload).digest('base64url');
    if (signature.length !== expected.length || ! crypto.timingSafeEqual(Buffer.from(signature), Buffer.from(expected))) {
        return null;
    }
    let data;
    try {
        data = JSON.parse(Buffer.from(payload, 'base64url').toString('utf8'));
    } catch {
        return null;
    }
    const now = Math.floor(Date.now() / 1000);
    if (typeof data.exp !== 'number' || data.exp < now || typeof data.nonce !== 'string' || used.has(data.nonce)) {
        return null;
    }
    used.set(data.nonce, data.exp);
    for (const [nonce, exp] of used) {
        if (exp < now) used.delete(nonce);
    }
    return data;
}
