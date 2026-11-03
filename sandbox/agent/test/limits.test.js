import { test } from 'node:test';
import assert from 'node:assert/strict';

process.env.SANDBOX_API_TOKEN = 'x'.repeat(40);
process.env.SANDBOX_MAX_SESSIONS_PER_IP = '2';
process.env.SANDBOX_MAX_CONNECTIONS_PER_MINUTE = '3';
const { acquire, release, totalSessions } = await import('../src/limits.js');

test('limites par adresse IP', () => {
    assert.equal(acquire('a'), null);
    assert.equal(acquire('a'), null);
    assert.match(acquire('a'), /sessions ouvertes/);
    release('a');
    assert.match(acquire('a'), /une minute/); // 4e tentative dans la minute
    assert.equal(totalSessions(), 1);
});
