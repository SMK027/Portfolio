// sandbox-agent : seul point de contact avec la machine SSH bac à sable.
//   - API de contrôle (réseau interne, jeton) pour le panel du portfolio ;
//   - relais du port SSH public et terminal web, fermés quand l'accès public est coupé ;
//   - accès Docker restreint via docker-socket-proxy.
import fs from 'node:fs';
import config from './config.js';
import { startApi } from './api.js';
import { startGateway } from './gateway.js';
import { watch } from './reconcile.js';
import { ensureKey } from './sandbox.js';
import { startTerminal } from './terminal.js';

fs.mkdirSync(config.dataDir, { recursive: true });
ensureKey();
startApi();
startGateway();
startTerminal();

watch();
setInterval(watch, 20000);

process.on('SIGTERM', () => process.exit(0));
