// Copie des sauvegardes hors du serveur, vers un stockage compatible S3 (AWS, Backblaze B2,
// Scaleway, OVH, MinIO…) : signature AWS SigV4 sans dépendance, adresses « path-style ».
import crypto from 'node:crypto';
import fs from 'node:fs';
import config from './config.js';

const hmac = (key, data) => crypto.createHmac('sha256', key).update(data).digest();
const sha256 = (data) => crypto.createHash('sha256').update(data).digest('hex');
const encode = (segment) => encodeURIComponent(segment).replace(/[!'()*]/g, (c) => `%${c.charCodeAt(0).toString(16).toUpperCase()}`);

export const isEnabled = () => Boolean(config.s3.endpoint && config.s3.bucket && config.s3.accessKey && config.s3.secretKey);

/**
 * En-tête Authorization AWS SigV4 (testé avec l'exemple de la documentation AWS).
 * headers : en-têtes signés (host et x-amz-* compris), en minuscules.
 */
export function sign({ method, url, headers, payloadHash, date, accessKey, secretKey, region, service = 's3' }) {
    const { pathname, searchParams } = new URL(url);
    const amzDate = date.toISOString().replace(/[:-]|\.\d{3}/g, '');
    const day = amzDate.slice(0, 8);
    const names = Object.keys(headers).map((h) => h.toLowerCase()).sort();
    const canonicalHeaders = names.map((h) => `${h}:${String(headers[h]).trim()}\n`).join('');
    const query = [...searchParams].sort(([a], [b]) => a.localeCompare(b)).map(([k, v]) => `${encode(k)}=${encode(v)}`).join('&');
    const canonical = [method, pathname, query, canonicalHeaders, names.join(';'), payloadHash].join('\n');
    const scope = `${day}/${region}/${service}/aws4_request`;
    const toSign = ['AWS4-HMAC-SHA256', amzDate, scope, sha256(canonical)].join('\n');
    const key = hmac(hmac(hmac(hmac(`AWS4${secretKey}`, day), region), service), 'aws4_request');
    const signature = crypto.createHmac('sha256', key).update(toSign).digest('hex');

    return `AWS4-HMAC-SHA256 Credential=${accessKey}/${scope}, SignedHeaders=${names.join(';')}, Signature=${signature}`;
}

async function request(method, objectKey, { body = null, length = 0 } = {}) {
    const { endpoint, bucket, region, accessKey, secretKey, prefix } = config.s3;
    const key = `${prefix}${objectKey}`.split('/').map(encode).join('/');
    const url = `${endpoint.replace(/\/$/, '')}/${encode(bucket)}/${key}`;
    const date = new Date();
    const headers = {
        host: new URL(url).host,
        'x-amz-content-sha256': 'UNSIGNED-PAYLOAD',
        'x-amz-date': date.toISOString().replace(/[:-]|\.\d{3}/g, ''),
    };
    const authorization = sign({ method, url, headers, payloadHash: 'UNSIGNED-PAYLOAD', date, accessKey, secretKey, region });
    const response = await fetch(url, {
        method,
        headers: { ...headers, authorization, ...(body ? { 'content-length': String(length), 'content-type': 'application/gzip' } : {}) },
        body,
        duplex: body ? 'half' : undefined,
        signal: AbortSignal.timeout(300000),
    });
    if (! response.ok && ! (method === 'DELETE' && response.status === 404)) {
        throw new Error(`S3 ${method} ${response.status} ${(await response.text()).slice(0, 200)}`);
    }
}

export async function upload(file, name) {
    await request('PUT', name, { body: fs.createReadStream(file), length: fs.statSync(file).size });
}

export async function remove(name) {
    await request('DELETE', name);
}
