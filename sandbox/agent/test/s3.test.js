import { test } from 'node:test';
import assert from 'node:assert/strict';

process.env.SANDBOX_API_TOKEN = 'x'.repeat(40);
const { sign } = await import('../src/s3.js');

// Exemple « GET Object » de la documentation AWS (Signature Version 4, en-tête Authorization)
test('signature SigV4 conforme à l\'exemple AWS', () => {
    const authorization = sign({
        method: 'GET',
        url: 'https://examplebucket.s3.amazonaws.com/test.txt',
        headers: {
            host: 'examplebucket.s3.amazonaws.com',
            range: 'bytes=0-9',
            'x-amz-content-sha256': 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
            'x-amz-date': '20130524T000000Z',
        },
        payloadHash: 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
        date: new Date('2013-05-24T00:00:00Z'),
        accessKey: 'AKIAIOSFODNN7EXAMPLE',
        secretKey: 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
        region: 'us-east-1',
    });
    assert.equal(authorization, 'AWS4-HMAC-SHA256 Credential=AKIAIOSFODNN7EXAMPLE/20130524/us-east-1/s3/aws4_request, '
        + 'SignedHeaders=host;range;x-amz-content-sha256;x-amz-date, Signature=f0e8bdb87c964420e857bd35b5d6ed310bd44f0170aba48dd91039c6036bdb41');
});
