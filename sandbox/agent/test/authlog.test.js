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
