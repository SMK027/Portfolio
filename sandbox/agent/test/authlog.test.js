import { test } from 'node:test';
import assert from 'node:assert/strict';

process.env.SANDBOX_API_TOKEN = 'x'.repeat(40);
const { parse } = await import('../src/authlog.js');

test('échecs et réussites reconnus avec leur port source', () => {
    const events = parse([
        'Failed password for bob from 172.25.0.3 port 41262 ssh2',
        'Failed password for invalid user admin from 172.25.0.3 port 41263 ssh2',
        'Invalid user test from 172.25.0.3 port 41264',
        'Accepted password for alice from 172.25.0.3 port 41265 ssh2',
        'Server listening on 0.0.0.0 port 22.',
    ].join('\n'));
    assert.deepEqual(events, [
        { type: 'failed', username: 'bob', port: 41262 },
        { type: 'failed', username: 'admin', port: 41263 },
        { type: 'accepted', username: 'alice', method: 'password', port: 41265 },
    ]);
});

const { attribute, GRACE_MS } = await import('../src/authlog.js');
const { pendingFailures, runtime } = await import('../src/state.js');

const entry = (ip, extra = {}) => ({ ip, channel: 'ssh', openedAt: 0, closedAt: null, authenticated: false, failed: false, ...extra });
const reset = () => { pendingFailures.splice(0); runtime.connections.length = 0; };

test('échec attribué à la connexion ouverte sur ce port', () => {
    reset();
    const map = new Map([[41000, entry('203.0.113.1')]]);
    attribute([{ type: 'failed', username: 'leo', port: 41000 }], map, 1000, true);
    assert.deepEqual(pendingFailures.map((f) => f.ip), ['203.0.113.1']);
});

test('lot relevé en retard : ni journal ni bannissement', () => {
    reset();
    const map = new Map([[41000, entry('203.0.113.1')]]);
    attribute(Array(486).fill({ type: 'failed', username: 'leo', port: 41000 }), map, 1000, false);
    assert.equal(pendingFailures.length, 0);
    assert.equal(runtime.connections.length, 0);
});

test('port d\'une connexion fermée depuis longtemps : correspondance oubliée', () => {
    reset();
    const map = new Map([[41000, entry('203.0.113.1', { closedAt: 0, failed: true })]]);
    attribute([], map, GRACE_MS + 1, true); // fin du délai de grâce : retirée
    assert.equal(map.size, 0);
    attribute([{ type: 'failed', username: 'leo', port: 41000 }], map, GRACE_MS + 2, true); // ligne tardive : personne
    assert.equal(pendingFailures.length, 0);
});

test('connexion fermée sans authentification : comptée comme un échec', () => {
    reset();
    const map = new Map([
        [41001, entry('198.51.100.5', { channel: 'gestion', closedAt: 0 })], // robot : ouvre puis referme
        [41002, entry('203.0.113.2', { closedAt: 0, authenticated: true })], // visiteur légitime
    ]);
    attribute([], map, GRACE_MS, true);
    assert.deepEqual(pendingFailures.map((f) => [f.ip, f.channel]), [['198.51.100.5', 'gestion']]);
    assert.equal(runtime.connections[0].result, 'refusée (fermée sans authentification)');
    assert.equal(map.size, 0);
});

test('connexion encore dans le délai de grâce : conservée', () => {
    reset();
    const map = new Map([[41003, entry('203.0.113.3', { closedAt: 0 })]]);
    attribute([], map, GRACE_MS - 1, true);
    assert.equal(map.size, 1);
    assert.equal(pendingFailures.length, 0);
});
