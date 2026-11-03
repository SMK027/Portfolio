// Sauvegardes des dossiers personnels (/home) : archives tar.gz dans le volume
// de l'agent, avec un index (libellé, date, taille, origine manuelle ou automatique).
import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { pipeline } from 'node:stream/promises';
import config from './config.js';
import { run } from './sandbox.js';

const dir = path.join(config.dataDir, 'backups');
const indexFile = path.join(dir, 'index.json');
fs.mkdirSync(dir, { recursive: true });

const readIndex = () => (fs.existsSync(indexFile) ? JSON.parse(fs.readFileSync(indexFile, 'utf8')) : []);
const writeIndex = (items) => fs.writeFileSync(indexFile, JSON.stringify(items, null, 2));
const fileFor = (id) => path.join(dir, `${id}.tar.gz`);

export function list() {
    return readIndex().sort((a, b) => b.created_at.localeCompare(a.created_at));
}

export function find(id) {
    return /^[a-f0-9]{16}$/.test(String(id)) ? readIndex().find((b) => b.id === id) ?? null : null;
}

/** Nouvelle sauvegarde ; keep : nombre de sauvegardes automatiques conservées. */
export async function create(label, automatic = false, keep = null) {
    const id = crypto.randomBytes(8).toString('hex');
    const file = fileFor(id);
    const output = fs.createWriteStream(file, { mode: 0o600 });
    try {
        await run('backup', { output });
        await new Promise((resolve) => output.end(resolve));
    } catch (error) {
        output.destroy();
        fs.rmSync(file, { force: true });
        throw error;
    }

    const backup = { id, label: String(label || 'Sauvegarde').slice(0, 120), automatic, size: fs.statSync(file).size, created_at: new Date().toISOString() };
    writeIndex([...readIndex(), backup]);

    if (automatic && keep) {
        list().filter((b) => b.automatic).slice(keep).forEach((b) => remove(b.id));
    }
    return backup;
}

export async function restore(id) {
    const backup = find(id);
    if (! backup) {
        throw Object.assign(new Error('Sauvegarde introuvable'), { status: 404 });
    }
    await run('restore', { input: fs.createReadStream(fileFor(id)) });
    return backup;
}

/** Réinitialisation : dossiers personnels vidés (archive vide). */
export async function wipe() {
    const empty = path.join(dir, '.empty.tar.gz');
    if (! fs.existsSync(empty)) {
        const { execFileSync } = await import('node:child_process');
        const emptyDir = fs.mkdtempSync(path.join(dir, '.empty-'));
        execFileSync('tar', ['-czf', empty, '-C', emptyDir, '.']);
        fs.rmSync(emptyDir, { recursive: true });
    }
    await run('restore', { input: fs.createReadStream(empty) });
}

export function remove(id) {
    const backup = find(id);
    if (! backup) {
        return false;
    }
    fs.rmSync(fileFor(id), { force: true });
    writeIndex(readIndex().filter((b) => b.id !== id));
    return true;
}

export function stream(id) {
    return find(id) ? fs.createReadStream(fileFor(id)) : null;
}

export { pipeline };
