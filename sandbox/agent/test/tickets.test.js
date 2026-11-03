import { test } from 'node:test';
import assert from 'node:assert/strict';
import crypto from 'node:crypto';

process.env.SANDBOX_API_TOKEN = 'x'.repeat(40);
const { verifyTicket, ticketKey } = await import('../src/tickets.js');

const sign = (data) => {
    const payload = Buffer.from(JSON.stringify(data)).toString('base64url');
    return `${payload}.${crypto.createHmac('sha256', ticketKey()).update(payload).digest('base64url')}`;
};

test('un ticket valide est accepté une seule fois', () => {
    const ticket = sign({ exp: Math.floor(Date.now() / 1000) + 60, nonce: 'abc', ip: '203.0.113.1' });
    assert.equal(verifyTicket(ticket).ip, '203.0.113.1');
    assert.equal(verifyTicket(ticket), null);
});

test('ticket expiré, falsifié ou mal formé refusé', () => {
    assert.equal(verifyTicket(sign({ exp: 1, nonce: 'old' })), null);
    const forged = sign({ exp: Math.floor(Date.now() / 1000) + 60, nonce: 'f' }).replace(/.$/, 'A');
    assert.equal(verifyTicket(forged), null);
    assert.equal(verifyTicket('nimportequoi'), null);
    assert.equal(verifyTicket(undefined), null);
});
