#!/usr/bin/env bash
# Test d'intégration de la machine SSH : démarre une pile isolée (projet Compose
# dédié, ports locaux), vérifie le comportement réel, puis supprime tout.
#
#   sandbox/tests/integration.sh            (prérequis : docker, sshpass, node ≥ 22, openssl, curl)
#
# Variables : SANDBOX_TEST_PORT_BASE (défaut 23000), SANDBOX_TEST_KEEP=1 pour garder la pile.
# Le terminal web est testé avec le module « ws » de l'agent : cd sandbox/agent && npm ci
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
BASE="${SANDBOX_TEST_PORT_BASE:-23000}"
SSH_PORT=$((BASE + 22)) MANAGER_PORT=$((BASE + 24)) API_PORT=$((BASE + 80)) WS_PORT=$((BASE + 81))
PROJECT="sandbox-test-$$"
TOKEN="$(openssl rand -hex 32)"
WORK="$(mktemp -d)"
FAILURES=0

cat > "$WORK/override.yml" <<YAML
name: $PROJECT
services:
  sandbox-ssh:
    container_name: $PROJECT-ssh
  sandbox-egress:
    container_name: $PROJECT-egress
  sandbox-agent:
    environment:
      - SANDBOX_CONTAINER=$PROJECT-ssh
      - SANDBOX_EGRESS_CONTAINER=$PROJECT-egress
      - SANDBOX_EGRESS_NETWORK=$PROJECT-egress
    ports: !override
      - "127.0.0.1:$SSH_PORT:2222"
      - "127.0.0.1:$MANAGER_PORT:2224"
      - "127.0.0.1:$API_PORT:8080"
      - "127.0.0.1:$WS_PORT:8081"
networks:
  proxy:
    external: false
  control:
    name: $PROJECT-control
YAML

compose() { SANDBOX_API_TOKEN="$TOKEN" SANDBOX_CONTAINER="$PROJECT-ssh" docker compose -f "$ROOT/docker-compose.yml" -f "$WORK/override.yml" "$@"; }
api() { curl -s -X "$1" -H "Authorization: Bearer $TOKEN" ${3:+-d "$3"} "127.0.0.1:$API_PORT/$2"; }
ssh_as() { local user="$1" pass="$2"; shift 2; timeout 40 sshpass -p "$pass" ssh -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null \
    -o LogLevel=ERROR -o PubkeyAuthentication=no -o IdentitiesOnly=yes -o NumberOfPasswordPrompts=1 -p "$SSH_PORT" "$user@127.0.0.1" "$@"; }
check() { local label="$1"; shift; if "$@" >/dev/null 2>&1; then echo "  ✔ $label"; else echo "  ✘ $label"; FAILURES=$((FAILURES + 1)); fi; }
wait_for() { for _ in $(seq 1 40); do "$@" >/dev/null 2>&1 && return 0; sleep 2; done; return 1; }
# Fonctions et variables utilisées dans les sous-shells des contrôles
export TOKEN API_PORT SSH_PORT MANAGER_PORT PROJECT WORK
export -f api ssh_as wait_for

cleanup() {
    if [ "${SANDBOX_TEST_KEEP:-0}" != 1 ]; then
        docker network disconnect -f "$PROJECT-egress" "$PROJECT-egress" >/dev/null 2>&1 || true
        compose down -v --rmi local >/dev/null 2>&1 || true
        docker network rm "$PROJECT-egress" >/dev/null 2>&1 || true
    fi
    rm -rf "$WORK"
}
trap cleanup EXIT

echo "▶ Construction et démarrage ($PROJECT)"
compose up -d --build >/dev/null
wait_for curl -sf "127.0.0.1:$API_PORT/healthz"

HASH="$(openssl passwd -6 'Test-pass-1')"
state() { api PUT state "{\"public\":$1,\"internet\":$2,\"limits\":{\"nproc\":12,\"user_quota_mb\":2},\"connections\":{\"max\":10,\"per_ip\":5,\"per_minute\":60},\"accounts\":[{\"username\":\"testeur\",\"hash\":\"$HASH\",\"enabled\":true}]}" >/dev/null; }
state true false
wait_for ssh_as testeur Test-pass-1 true

echo "▶ Accès et isolation"
check "connexion par mot de passe" ssh_as testeur Test-pass-1 true
check "mauvais mot de passe refusé" bash -c "! ssh_as testeur faux true"
check "root refusé" bash -c "! timeout 20 sshpass -p x ssh -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o PubkeyAuthentication=no -p $SSH_PORT root@127.0.0.1 true"
check "pas d'élévation (su)" bash -c "ssh_as testeur Test-pass-1 'su -c id root </dev/null' 2>&1 | grep -q 'must be suid'" 
check "limite de processus par compte" bash -c "ssh_as testeur Test-pass-1 'ulimit -u' | grep -qx 12"
check "dossier personnel privé (0700)" bash -c "ssh_as testeur Test-pass-1 'stat -c %a ~' | grep -qx 700"
check "API de l'agent interdite depuis la machine" bash -c "ssh_as testeur Test-pass-1 'curl -s -o /dev/null -w %{http_code} --noproxy \"*\" http://sandbox-agent:8080/status' | grep -qx 403"

echo "▶ Internet"
check "coupé par défaut" bash -c "! ssh_as testeur Test-pass-1 'timeout 10 curl -sf https://example.com'"
state true true
check "HTTPS via le proxy" wait_for ssh_as testeur Test-pass-1 'timeout 10 curl -sf https://example.com'
check "SMTP bloqué" bash -c "! ssh_as testeur Test-pass-1 'nc -w 5 smtp.gmail.com 25'"
check "hôte Docker bloqué" bash -c "! ssh_as testeur Test-pass-1 'timeout 10 curl -sf http://172.17.0.1/'"
check "métadonnées cloud bloquées" bash -c "! ssh_as testeur Test-pass-1 'timeout 10 curl -sf http://169.254.169.254/'"
state true false

echo "▶ Sauvegarde et restauration"
ssh_as testeur Test-pass-1 'echo original > fichier.txt'
BACKUP="$(api POST backups '{"label":"test"}' | grep -o '"id":"[a-f0-9]*"' | cut -d'"' -f4)"
ssh_as testeur Test-pass-1 'echo modifié > fichier.txt'
api POST "backups/$BACKUP/restore" >/dev/null
check "fichier restauré" bash -c "ssh_as testeur Test-pass-1 'cat fichier.txt' | grep -qx original"
api POST reset >/dev/null
check "après réinitialisation : dossier recréé et accessible" bash -c "ssh_as testeur Test-pass-1 'pwd; ls -d ~/defis' | grep -qx /home/testeur/defis"
check "après réinitialisation : /home franchissable (755)" bash -c "docker exec $PROJECT-ssh stat -c %a /home | grep -qx 755"

echo "▶ Quota par compte"
ssh_as testeur Test-pass-1 'head -c 3M /dev/urandom > gros.bin' || true
check "compte bloqué au-delà du quota" wait_for bash -c "api GET status | grep -q '\"over_quota_users\":\[\"testeur\"\]'"
docker exec "$PROJECT-ssh" rm -f /home/testeur/gros.bin
check "compte débloqué après nettoyage" wait_for ssh_as testeur Test-pass-1 true

echo "▶ Attribution des échecs"
api POST auth-failures/drain >/dev/null
ssh_as testeur Test-pass-1 true
sleep 14
check "connexion réussie : aucun échec compté" bash -c "api POST auth-failures/drain | grep -qx '{\"failures\":\[\]}'"
timeout 3 bash -c "exec 3<>/dev/tcp/127.0.0.1/$SSH_PORT; sleep 1" || true
sleep 14
check "ouverture TCP sans authentification : comptée comme échec" bash -c "api POST auth-failures/drain | grep -q '\"channel\":\"ssh\",\"username\":null'"

echo "▶ Bannissement et accès public"
api PUT state "{\"public\":true,\"internet\":false,\"banned_ips\":[\"$(api GET status | grep -o '"ip":"[0-9.]*"' | head -1 | cut -d'"' -f4)\"],\"accounts\":[{\"username\":\"testeur\",\"hash\":\"$HASH\",\"enabled\":true}]}" >/dev/null
check "adresse bannie refusée" bash -c "! ssh_as testeur Test-pass-1 true"
state false false
check "accès fermé : connexion refusée" bash -c "! ssh_as testeur Test-pass-1 true"
state true false

echo "▶ Comptes de gestion (root)"
ssh-keygen -q -t ed25519 -N '' -C integration -f "$WORK/admkey"
managers() { api PUT state "{\"public\":$1,\"internet\":false,\"managers\":$2,\"accounts\":[{\"username\":\"testeur\",\"hash\":\"$HASH\",\"enabled\":true}]}" >/dev/null; }
manager_ssh() { timeout 20 ssh -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o LogLevel=ERROR -o IdentitiesOnly=yes -o BatchMode=yes \
    -i "$WORK/admkey" -p "$1" adm-test@127.0.0.1 "${@:2}"; }
export -f manager_ssh
managers false "[{\"username\":\"adm-test\",\"keys\":[\"$(cat "$WORK/admkey.pub")\"]}]"
check "root par clé, accès public fermé" bash -c "manager_ssh $MANAGER_PORT 'id -u' | grep -qx 0"
managers true "[{\"username\":\"adm-test\",\"keys\":[\"$(cat "$WORK/admkey.pub")\"]}]"
check "port public interdit aux comptes de gestion" bash -c "! manager_ssh $SSH_PORT true"
check "port de gestion interdit aux visiteurs" bash -c "! timeout 20 sshpass -p Test-pass-1 ssh -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o PubkeyAuthentication=no -p $MANAGER_PORT testeur@127.0.0.1 true"
check "pas de mot de passe sur le port de gestion" bash -c "! timeout 20 sshpass -p x ssh -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o PubkeyAuthentication=no -p $MANAGER_PORT adm-test@127.0.0.1 true"
managers true "[]"
check "compte révoqué refusé" bash -c "! manager_ssh $MANAGER_PORT true"

echo "▶ Terminal web et session jetable"
cat > "$WORK/ws.mjs" <<'JS'
import crypto from 'node:crypto';
import WebSocket from 'ws';
const [port, token] = process.argv.slice(2);
const key = crypto.createHash('sha256').update('terminal|' + token).digest();
const payload = Buffer.from(JSON.stringify({ exp: Math.floor(Date.now() / 1000) + 60, nonce: crypto.randomUUID(), ip: '198.51.100.1', ephemeral: true })).toString('base64url');
const ws = new WebSocket(`ws://127.0.0.1:${port}/ssh-ws?ticket=${payload}.${crypto.createHmac('sha256', key).update(payload).digest('base64url')}`);
let out = '';
ws.on('open', () => ws.send(JSON.stringify({ type: 'auth', ephemeral: true, cols: 80, rows: 24 })));
ws.on('message', (m) => { const msg = JSON.parse(m); if (msg.type === 'output') out += msg.data;
    if (msg.message === 'connected') setTimeout(() => ws.send(JSON.stringify({ type: 'input', data: 'echo RESULTAT-$((6*7)); exit\n' })), 500); });
ws.on('close', () => process.exit(out.includes('RESULTAT-42') ? 0 : 1));
setTimeout(() => process.exit(1), 20000);
JS
# Le module « ws » est résolu depuis le dossier du script : copie temporaire dans agent/
cp "$WORK/ws.mjs" "$ROOT/agent/.ws-integration.mjs"
check "session jetable dans le navigateur" node "$ROOT/agent/.ws-integration.mjs" "$WS_PORT" "$TOKEN"
rm -f "$ROOT/agent/.ws-integration.mjs"
check "compte jetable supprimé après la session" wait_for bash -c "! docker exec $PROJECT-ssh grep -q ephemeral /etc/passwd"

echo
if [ "$FAILURES" -eq 0 ]; then echo "✔ Tous les contrôles sont passés."; else echo "✘ $FAILURES contrôle(s) en échec."; exit 1; fi
